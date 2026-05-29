<?php

namespace App\Models;

use Database\Factories\RequerimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requerimiento extends Model
{
    /** @use HasFactory<RequerimientoFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'school_id',
        'justificacion',
        'estado',
        'comentarios_rectoria',
        'comentarios_gerencia',
        'firma_rectoria_at',
        'firma_gerencia_at',
    ];

    protected $casts = [
        'firma_rectoria_at' => 'datetime',
        'firma_gerencia_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function items()
    {
        return $this->hasMany(RequerimientoItem::class);
    }

    public function actaEntrega()
    {
        return $this->hasOne(ActaEntrega::class);
    }
}
