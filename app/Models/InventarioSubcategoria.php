<?php

namespace App\Models;

use Database\Factories\InventarioSubcategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioSubcategoria extends Model
{
    /** @use HasFactory<InventarioSubcategoriaFactory> */
    use HasFactory;

    protected $table = 'inventario_subcategorias';

    protected $fillable = [
        'school_id',
        'categoria_id',
        'nombre',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(InventarioCategoria::class, 'categoria_id');
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(ArticuloInventario::class, 'subcategoria_id');
    }
}
