<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    private function session(User $u): array
    {
        return ['token' => $u->createToken('vue', ['*'], now()->addHours(8))->plainTextToken, 'user' => $u];
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $u = User::where('email', $d['email'])->first();
        if (! $u || ! Hash::check($d['password'], $u->password)) {
            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }
        if (! $u->totp_confirmed) {
            return $this->session($u);
        }
        $challenge = Str::random(64);
        Cache::put('login:'.$challenge, ['id' => $u->id, 'fingerprint' => hash('sha256', $u->password), 'attempts' => 0], now()->addMinutes(5));

        return ['requires_2fa' => true, 'challenge' => $challenge, 'setup_secret' => null];
    }

    public function verify(Request $r)
    {
        $d = $r->validate(['challenge' => 'required|string|size:64', 'code' => 'required|digits:6']);

        return Cache::lock('verify:'.$d['challenge'], 10)->block(2, function () use ($d) {
            $key = 'login:'.$d['challenge'];
            $c = Cache::get($key);
            if (! $c) {
                throw ValidationException::withMessages(['code' => 'El acceso expiró. Inicia sesión nuevamente.']);
            }
            $c['attempts']++;
            if ($c['attempts'] >= 5) {
                Cache::forget($key);
            } else {
                Cache::put($key, $c, now()->addMinutes(2));
            }

            return DB::transaction(function () use ($d, $c, $key) {
                $u = User::lockForUpdate()->findOrFail($c['id']);
                if (! $u->totp_confirmed || ! hash_equals($c['fingerprint'], hash('sha256', $u->password))) {
                    throw ValidationException::withMessages(['code' => 'Inicia sesion nuevamente.']);
                }
                $secret = $u->totp_secret;
                $step = (new Google2FA)->verifyKeyNewer($secret, $d['code'], $u->totp_last_step ?? 0, 1);
                if ($step === false) {
                    throw ValidationException::withMessages(['code' => 'Código incorrecto o ya utilizado.']);
                }
                $u->forceFill(['totp_secret' => $secret, 'totp_confirmed' => true, 'totp_last_step' => $step])->save();
                Cache::forget($key);

                return $this->session($u);
            });
        });
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
