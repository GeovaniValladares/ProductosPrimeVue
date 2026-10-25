<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = ['nombre', 'descripcion', 'precio', 'stock'];

    protected function casts(): array
    {
        return ['precio' => 'decimal:2', 'stock' => 'integer'];
    }
}
