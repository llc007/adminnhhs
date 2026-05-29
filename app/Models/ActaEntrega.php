<?php

namespace App\Models;

use Database\Factories\ActaEntregaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActaEntrega extends Model
{
    /** @use HasFactory<ActaEntregaFactory> */
    use HasFactory;

    protected $table = 'acta_entregas';

    protected $fillable = [
        'requerimiento_id',
        'recibe_user_id',
        'entrega_user_id',
        'fecha_entrega',
        'firmado_at',
    ];

    protected $casts = [
        'fecha_entrega' => 'date',
        'firmado_at' => 'datetime',
    ];

    public function requerimiento()
    {
        return $this->belongsTo(Requerimiento::class);
    }

    public function recibeUser()
    {
        return $this->belongsTo(User::class, 'recibe_user_id');
    }

    public function entregaUser()
    {
        return $this->belongsTo(User::class, 'entrega_user_id');
    }

    public function detalles()
    {
        return $this->hasMany(ActaEntregaDetalle::class);
    }
}
