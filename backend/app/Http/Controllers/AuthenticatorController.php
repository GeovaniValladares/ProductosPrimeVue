<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthenticatorController extends Controller
{
    private function account(Request $request): User
    {
        $user = $request->user()->fresh();
        abort_unless(in_array($user->role, ['user', 'superadmin'], true), 403);

        return $user;
    }

    private function checkPassword(Request $request, User $user): void
    {
        $data = $request->validate(['password' => 'required|string']);
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña actual es incorrecta.']);
        }
    }

    private function setupKey(Request $request): string
    {
        return 'authenticator-setup:'.$request->user()->id.':'.$request->user()->currentAccessToken()->getKey();
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json(['enabled' => $this->account($request)->totp_confirmed]);
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $this->account($request);
        $this->checkPassword($request, $user);
        abort_if($user->totp_confirmed, 422, 'Google Authenticator ya está activado.');
        $secret = (new Google2FA)->generateSecretKey();
        Cache::put($this->setupKey($request), [
            'secret' => Crypt::encryptString($secret),
            'fingerprint' => hash('sha256', $user->password),
            'attempts' => 0,
        ], now()->addMinutes(5));

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => (new Google2FA)->getQRCodeUrl(config('app.name'), $user->email, $secret),
        ])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request): JsonResponse
    {
        $this->account($request);
        $data = $request->validate(['code' => 'required|digits:6']);

        return Cache::lock('authenticator-change:'.$request->user()->id, 10)->block(2, function () use ($request, $data): JsonResponse {
            $user = $request->user()->fresh();
            $key = $this->setupKey($request);
            $pending = Cache::get($key);
            if (! $pending || $user->totp_confirmed || ! hash_equals($pending['fingerprint'], hash('sha256', $user->password))) {
                throw ValidationException::withMessages(['code' => 'La configuración expiró. Vuelve a empezar.']);
            }
            $secret = Crypt::decryptString($pending['secret']);
            if (! (new Google2FA)->verifyKey($secret, $data['code'], 1)) {
                $pending['attempts']++;
                if ($pending['attempts'] >= 5) {
                    Cache::forget($key);
                } else {
                    Cache::put($key, $pending, now()->addMinutes(2));
                }
                throw ValidationException::withMessages(['code' => 'Código incorrecto. Revisa la hora del teléfono.']);
            }
            $user->forceFill(['totp_secret' => $secret, 'totp_confirmed' => true, 'totp_last_step' => null])->save();
            Cache::forget($key);
            $user->tokens()->whereKeyNot($request->user()->currentAccessToken()->getKey())->delete();

            return response()->json(['enabled' => true]);
        });
    }

    public function disable(Request $request): JsonResponse
    {
        $user = $this->account($request);
        $this->checkPassword($request, $user);
        $data = $request->validate(['code' => 'required|digits:6']);
        if (! $user->totp_confirmed || ! (new Google2FA)->verifyKey($user->totp_secret, $data['code'], 1)) {
            throw ValidationException::withMessages(['code' => 'Código incorrecto.']);
        }
        $user->forceFill(['totp_secret' => null, 'totp_confirmed' => false, 'totp_last_step' => null])->save();
        Cache::forget($this->setupKey($request));

        return response()->json(['enabled' => false]);
    }
}
