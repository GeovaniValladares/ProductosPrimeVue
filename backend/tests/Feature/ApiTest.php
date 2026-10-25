<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_logs_in_directly_and_token_is_revoked(): void
    {
        $u = User::factory()->create(['role' => 'superadmin']);
        $r = $this->postJson('/api/login', ['email' => $u->email, 'password' => 'password'])->assertOk()->assertJsonPath('user.role', 'superadmin');
        $this->withToken($r['token'])->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_without_authenticator_logs_in_directly(): void
    {
        $u = User::factory()->create();
        $this->postJson('/api/login', ['email' => $u->email, 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['token', 'user'])
            ->assertJsonMissingPath('challenge')->assertJsonMissingPath('setup_secret');
        $this->assertFalse($u->fresh()->totp_confirmed);
    }

    public function test_user_with_authenticator_still_requires_code(): void
    {
        $g = new Google2FA;
        $secret = $g->generateSecretKey();
        $u = User::factory()->create();
        $u->forceFill(['totp_secret' => $secret, 'totp_confirmed' => true])->save();
        $r = $this->postJson('/api/login', ['email' => $u->email, 'password' => 'password'])
            ->assertOk()->assertJsonMissingPath('token')->assertJsonPath('requires_2fa', true);
        $code = $g->getCurrentOtp($secret);
        $this->postJson('/api/auth/verify', ['challenge' => $r['challenge'], 'code' => $code])->assertOk()->assertJsonStructure(['token', 'user']);
        $this->postJson('/api/auth/verify', ['challenge' => $r['challenge'], 'code' => $code])->assertUnprocessable();
    }

    public function test_permissions_validation_and_product_crud(): void
    {
        $this->getJson('/api/productos')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/productos', ['nombre' => 'A', 'precio' => 2, 'stock' => 1])->assertForbidden();
        $this->getJson('/api/usuarios')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'superadmin']));
        $this->postJson('/api/productos', ['nombre' => 'A', 'precio' => -1])->assertUnprocessable();
        $p = $this->postJson('/api/productos', ['nombre' => 'A', 'precio' => 2, 'stock' => 1])->assertCreated();
        $this->assertDatabaseHas('productos', ['id' => $p['id'], 'nombre' => 'A', 'stock' => 1]);
        $this->putJson('/api/productos/'.$p['id'], ['nombre' => 'B', 'precio' => 3, 'stock' => 2])->assertOk()->assertJsonPath('nombre', 'B');
        $this->assertDatabaseHas('productos', ['id' => $p['id'], 'nombre' => 'B', 'stock' => 2]);
        $this->getJson('/api/productos/'.$p['id'])->assertOk();
        $this->deleteJson('/api/productos/'.$p['id'])->assertNoContent();
        $this->assertDatabaseMissing('productos', ['id' => $p['id']]);
    }

    public function test_users_and_content_crud_and_self_delete_protection(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);
        $u = $this->postJson('/api/usuarios', ['name' => 'Test', 'email' => 'test@example.com', 'password' => 'Password123!', 'role' => 'user'])->assertCreated();
        $this->assertDatabaseHas('users', ['id' => $u['id'], 'email' => 'test@example.com', 'role' => 'user']);
        $this->putJson('/api/usuarios/'.$u['id'], ['name' => 'Edit', 'email' => 'test@example.com', 'role' => 'user'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $u['id'], 'name' => 'Edit']);
        $this->deleteJson('/api/usuarios/'.$u['id'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $u['id']]);
        $this->deleteJson('/api/usuarios/'.$admin->id)->assertUnprocessable();
        $c = $this->postJson('/api/contenidos', ['titulo' => 'Inicio', 'cuerpo' => 'Texto', 'publicado' => true])->assertCreated();
        $this->assertDatabaseHas('contenidos', ['id' => $c['id'], 'publicado' => true]);
        $this->putJson('/api/contenidos/'.$c['id'], ['titulo' => 'Nuevo', 'cuerpo' => 'Otro', 'publicado' => false])->assertOk();
        $this->assertDatabaseHas('contenidos', ['id' => $c['id'], 'titulo' => 'Nuevo', 'publicado' => false]);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/contenidos')->assertExactJson([]);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/contenidos/'.$c['id'])->assertNoContent();
        $this->assertDatabaseMissing('contenidos', ['id' => $c['id']]);
    }
}
