<?php

namespace App\Models;

use Database\Factories\RequerimientoItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequerimientoItem extends Model
{
    /** @use HasFactory<RequerimientoItemFactory> */
    use HasFactory;

    protected $fillable = [
        'requerimiento_id',
        'descripcion',
        'cantidad',
        'precio_estimado',
        'tienda_sugerida',
        'estado',
        'comentario_item',
        'observacion',
    ];

    public function requerimiento()
    {
        return $this->belongsTo(Requerimiento::class);
    }

    public function articulosInventario()
    {
        return $this->hasMany(ArticuloInventario::class);
    }
}
