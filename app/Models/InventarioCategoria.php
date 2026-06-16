<?php

namespace App\Models;

use Database\Factories\InventarioCategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioCategoria extends Model
{
    /** @use HasFactory<InventarioCategoriaFactory> */
    use HasFactory;

    protected $table = 'inventario_categorias';

    protected $fillable = [
        'school_id',
        'nombre',
    ];

    public function subcategorias(): HasMany
    {
        return $this->hasMany(InventarioSubcategoria::class, 'categoria_id');
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(ArticuloInventario::class, 'categoria_id');
    }
}
