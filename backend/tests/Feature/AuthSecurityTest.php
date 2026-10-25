<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_password_never_exposes_setup_secret(): void
    {
        $u = User::factory()->create();
        $this->postJson('/api/login', ['email' => $u->email, 'password' => 'wrong'])->assertUnprocessable()->assertJsonMissingPath('setup_secret')->assertJsonMissingPath('token');
    }

    public function test_expired_challenge_cannot_issue_token(): void
    {
        $u = User::factory()->create();
        $secret = (new Google2FA)->generateSecretKey();
        $u->forceFill(['totp_secret' => $secret, 'totp_confirmed' => true])->save();
        $r = $this->postJson('/api/login', ['email' => $u->email, 'password' => 'password']);
        $this->travel(6)->minutes();
        $this->postJson('/api/auth/verify', ['challenge' => $r['challenge'], 'code' => (new Google2FA)->getCurrentOtp($secret)])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_enrolled_secret_is_hidden_and_codes_cannot_be_replayed(): void
    {
        $g = new Google2FA;
        $secret = $g->generateSecretKey();
        $u = User::factory()->create();
        $u->forceFill(['totp_secret' => $secret, 'totp_confirmed' => true])->save();
        $r = $this->postJson('/api/login', ['email' => $u->email, 'password' => 'password'])->assertJsonPath('setup_secret', null);
        $code = $g->getCurrentOtp($secret);
        $this->postJson('/api/auth/verify', ['challenge' => $r['challenge'], 'code' => $code])->assertOk()->assertJsonMissingPath('user.totp_secret');
        $r = $this->postJson('/api/login', ['email' => $u->email, 'password' => 'password']);
        $this->postJson('/api/auth/verify', ['challenge' => $r['challenge'], 'code' => $code])->assertUnprocessable();
    }

    public function test_five_invalid_attempts_consume_challenge(): void
    {
        $u = User::factory()->create();
        $g = new Google2FA;
        $secret = $g->generateSecretKey();
        $u->forceFill(['totp_secret' => $secret, 'totp_confirmed' => true])->save();
        $r = $this->postJson('/api/login', ['email' => $u->email, 'password' => 'password']);
        $invalid = str_pad((string) (((int) $g->getCurrentOtp($secret) + 100) % 1000000), 6, '0', STR_PAD_LEFT);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/verify', ['challenge' => $r['challenge'], 'code' => $invalid])->assertUnprocessable();
        }
        $this->postJson('/api/auth/verify', ['challenge' => $r['challenge'], 'code' => $g->getCurrentOtp($secret)])->assertUnprocessable();
    }
}
