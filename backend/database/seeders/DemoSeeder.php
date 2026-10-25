<?php

namespace Database\Seeders;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos de prueba solo se crean en local o testing.');
        }

        User::firstOrCreate(['email' => 'admin.prueba@example.com'], [
            'name' => 'Administrador de prueba', 'password' => 'Admin2026!', 'role' => 'superadmin',
        ]);
        foreach (['ana' => 'Ana López', 'carlos' => 'Carlos Pérez', 'maria' => 'María García'] as $email => $name) {
            User::firstOrCreate(['email' => $email.'.prueba@example.com'], [
                'name' => $name, 'password' => 'Prueba2026!', 'role' => 'user',
            ]);
        }
        $products = [
            ['Teclado mecánico', 'Teclado USB con distribución en español.', 45.90, 24],
            ['Mouse inalámbrico', 'Mouse óptico con receptor USB.', 18.50, 40],
            ['Monitor 24 pulgadas', 'Pantalla Full HD para oficina.', 149.00, 12],
            ['Audífonos con micrófono', 'Conexión USB para llamadas y clases.', 32.00, 18],
            ['Memoria USB 64 GB', 'Almacenamiento portátil USB 3.0.', 9.50, 65],
            ['Base para laptop', 'Soporte ajustable de aluminio.', 27.00, 16],
            ['Cámara web HD', 'Cámara con micrófono integrado.', 39.90, 9],
            ['Cable HDMI 2 m', 'Cable de video y audio digital.', 7.25, 55],
        ];
        foreach ($products as [$name, $description, $price, $stock]) {
            Producto::firstOrCreate(['nombre' => $name], [
                'descripcion' => $description, 'precio' => $price, 'stock' => $stock,
            ]);
        }
    }
}
