<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticatorSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_returns_401_without_authentication(): void
    {
        $this->getJson('/api/auth/authenticator')->assertUnauthorized();
    }

    public function test_setup_requires_password_and_does_not_enable_until_confirmed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/authenticator/setup', ['password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/authenticator/setup', ['password' => 'password'])
            ->assertOk()->assertJsonStructure(['secret', 'otpauth_uri']);

        $this->assertFalse($user->fresh()->totp_confirmed);
        $this->assertNull($user->fresh()->totp_secret);
    }

    public function test_user_can_enable_then_disable_authenticator(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $setup = $this->postJson('/api/auth/authenticator/setup', ['password' => 'password'])->assertOk();
        $this->assertStringStartsWith('otpauth://totp/', $setup['otpauth_uri']);
        parse_str(parse_url($setup['otpauth_uri'], PHP_URL_QUERY), $parameters);
        $this->assertSame($setup['secret'], $parameters['secret']);
        $this->assertSame('6', $parameters['digits']);
        $this->assertSame('30', $parameters['period']);
        $code = (new Google2FA)->getCurrentOtp($setup['secret']);

        $this->postJson('/api/auth/authenticator/confirm', ['code' => $code])
            ->assertOk()->assertJsonPath('enabled', true);
        $this->assertTrue($user->fresh()->totp_confirmed);
        $this->assertSame($setup['secret'], $user->fresh()->totp_secret);

        $this->deleteJson('/api/auth/authenticator', ['password' => 'password', 'code' => $code])
            ->assertOk()->assertJsonPath('enabled', false);
        $this->assertFalse($user->fresh()->totp_confirmed);
        $this->assertNull($user->fresh()->totp_secret);
    }

    public function test_invalid_confirmation_and_expired_setup_do_not_enable_authenticator(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $setup = $this->postJson('/api/auth/authenticator/setup', ['password' => 'password']);
        $this->postJson('/api/auth/authenticator/confirm', ['code' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->travel(6)->minutes();

        $this->postJson('/api/auth/authenticator/confirm', ['code' => (new Google2FA)->getCurrentOtp($setup['secret'])])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertFalse($user->fresh()->totp_confirmed);
    }

    public function test_superadmin_can_choose_to_enable_second_factor(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($user);
        $setup = $this->postJson('/api/auth/authenticator/setup', ['password' => 'password'])->assertOk();
        $code = (new Google2FA)->getCurrentOtp($setup['secret']);
        $this->postJson('/api/auth/authenticator/confirm', ['code' => $code])->assertOk();

        $login = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('requires_2fa', true)->assertJsonMissingPath('token');
        $this->postJson('/api/auth/verify', ['challenge' => $login['challenge'], 'code' => $code])
            ->assertOk()->assertJsonPath('user.role', 'superadmin')->assertJsonStructure(['token']);
    }

    public function test_wrong_password_cannot_disable_enabled_authenticator(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['totp_confirmed' => true, 'totp_secret' => (new Google2FA)->generateSecretKey()])->save();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/authenticator', ['password' => 'wrong', 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue($user->fresh()->totp_confirmed);
    }
}
