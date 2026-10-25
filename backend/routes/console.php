<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('app:create-admin', function () {
    $name = $this->ask('Nombre');
    $email = $this->ask('Correo');
    $password = $this->secret('Contraseña (mínimo 10 caracteres)');
    $validator = validator(compact('name', 'email', 'password'), ['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:10']);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

return 1;
    }
    User::create(compact('name', 'email', 'password') + ['role' => 'superadmin']);
    $this->info('Superadmin creado.');
})->purpose('Crear un superadmin sin credenciales predeterminadas');
Artisan::command('app:reset-authenticator {email}', function () {
    $user = User::where('email', $this->argument('email'))->firstOrFail();
    if (! $this->confirm('¿Restablecer Authenticator y revocar las sesiones de '.$user->email.'?')) {
        return;
    }
    $user->forceFill(['totp_secret' => null, 'totp_confirmed' => false, 'totp_last_step' => null])->save();
    $user->tokens()->delete();
    $this->info('Authenticator restablecido.');
})->purpose('Recuperar acceso tras perder Google Authenticator');
