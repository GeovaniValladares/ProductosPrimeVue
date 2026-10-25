<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn('Define ADMIN_PASSWORD para crear el superadmin.');

            return;
        }
        User::firstOrCreate(['email' => env('ADMIN_EMAIL', 'admin@example.com')], ['name' => 'Superadmin', 'password' => env('ADMIN_PASSWORD'), 'role' => 'superadmin']);
    }
}
