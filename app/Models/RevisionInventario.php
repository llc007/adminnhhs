<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevisionInventario extends Model
{
    use HasFactory;

    protected $table = 'revisiones_inventario';

    protected $fillable = [
        'articulo_inventario_id',
        'fecha',
        'detalle',
        'realizado_por',
        'fecha_proxima_revision',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_proxima_revision' => 'date',
    ];

    public function articulo()
    {
        return $this->belongsTo(ArticuloInventario::class, 'articulo_inventario_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
