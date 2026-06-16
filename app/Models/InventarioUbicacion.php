<?php

namespace App\Models;

use Database\Factories\InventarioUbicacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioUbicacion extends Model
{
    /** @use HasFactory<InventarioUbicacionFactory> */
    use HasFactory;

    protected $table = 'inventario_ubicaciones';

    protected $fillable = [
        'school_id',
        'nombre',
    ];

    public function articulos(): HasMany
    {
        return $this->hasMany(ArticuloInventario::class, 'ubicacion_id');
    }
}
