<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contenido extends Model
{
    protected $fillable = ['titulo', 'cuerpo', 'publicado'];

    protected function casts(): array
    {
        return ['publicado' => 'boolean'];
    }
}
