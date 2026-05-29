<?php

namespace App\Models;

use Database\Factories\ArticuloInventarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticuloInventario extends Model
{
    /** @use HasFactory<ArticuloInventarioFactory> */
    use HasFactory;

    protected $table = 'articulo_inventarios';

    protected $fillable = [
        'school_id',
        'requerimiento_item_id',
        'tipo',
        'codigo_patrimonial',
        'nombre',
        'categoria',
        'marca',
        'modelo',
        'numero_serie',
        'cantidad',
        'estado_conservacion',
        'ubicacion',
        'responsable_user_id',
        'fecha_ingreso',
        'observaciones',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function requerimientoItem()
    {
        return $this->belongsTo(RequerimientoItem::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function actaEntregaDetalles()
    {
        return $this->hasMany(ActaEntregaDetalle::class);
    }
}
