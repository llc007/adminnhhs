<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entrevista extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'estudiante_id',
        'user_id',
        'fecha',
        'hora',
        'hora_llegada',
        'urgencia',
        'motivo',
        'notas_previas',
        'estado',
        'lugar',
        'mensaje_recepcion',
    ];

    /**
     * El colegio al que pertenece la entrevista
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * El estudiante citado a la entrevista (y su apoderado)
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * El funcionario que realiza la entrevista
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La bitácora (acta) generada para esta entrevista
     */
    public function bitacora(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Bitacora::class);
    }
}
