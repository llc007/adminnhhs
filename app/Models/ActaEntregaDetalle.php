<?php

namespace App\Models;

use Database\Factories\ActaEntregaDetalleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActaEntregaDetalle extends Model
{
    /** @use HasFactory<ActaEntregaDetalleFactory> */
    use HasFactory;

    protected $table = 'acta_entrega_detalles';

    protected $fillable = [
        'acta_entrega_id',
        'articulo_inventario_id',
        'cantidad',
        'numero_serie',
    ];

    public function actaEntrega()
    {
        return $this->belongsTo(ActaEntrega::class);
    }

    public function articuloInventario()
    {
        return $this->belongsTo(ArticuloInventario::class);
    }
}
