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
        'categoria_id',
        'subcategoria_id',
        'marca',
        'modelo',
        'numero_serie',
        'cantidad',
        'estado_conservacion',
        'ubicacion',
        'ubicacion_id',
        'responsable_user_id',
        'fecha_ingreso',
        'observaciones',
        'fecha_baja',
        'motivo_baja',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_baja' => 'date',
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

    public function revisiones()
    {
        return $this->hasMany(RevisionInventario::class, 'articulo_inventario_id');
    }

    public function ultimaRevision()
    {
        return $this->hasOne(RevisionInventario::class, 'articulo_inventario_id')->latestOfMany('fecha');
    }

    public function categoriaRel()
    {
        return $this->belongsTo(InventarioCategoria::class, 'categoria_id');
    }

    public function subcategoriaRel()
    {
        return $this->belongsTo(InventarioSubcategoria::class, 'subcategoria_id');
    }

    public function ubicacionRel()
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }
}
