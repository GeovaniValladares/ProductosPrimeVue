<?php

namespace Tests\Feature;

use App\Models\Producto;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_users_and_products_without_overwriting_edits(): void
    {
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseHas('users', ['email' => 'admin.prueba@example.com', 'role' => 'superadmin']);
        $this->assertDatabaseHas('users', ['email' => 'ana.prueba@example.com', 'role' => 'user', 'totp_confirmed' => false]);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('productos', 8);
        $product = Producto::where('nombre', 'Teclado mecánico')->firstOrFail();
        $product->update(['precio' => 99]);

        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('productos', 8);
        $this->assertDatabaseHas('productos', ['id' => $product->id, 'precio' => 99]);
    }
}
