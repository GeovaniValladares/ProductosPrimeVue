<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('user');
            $t->text('totp_secret')->nullable();
            $t->boolean('totp_confirmed')->default(false);
            $t->unsignedBigInteger('totp_last_step')->nullable();
        });
        Schema::create('productos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->text('descripcion')->nullable();
            $t->decimal('precio', 12, 2);
            $t->unsignedInteger('stock')->default(0);
            $t->timestamps();
        });
        Schema::create('contenidos', function (Blueprint $t) {
            $t->id();
            $t->string('titulo');
            $t->text('cuerpo');
            $t->boolean('publicado')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contenidos');
        Schema::dropIfExists('productos');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'totp_secret', 'totp_confirmed', 'totp_last_step']));
    }
};
