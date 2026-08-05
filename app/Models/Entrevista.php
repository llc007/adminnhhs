<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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
        'confirmacion_token',
        'estado_asistencia',
        'confirmado_at',
        'confirmado_desde_email',
        'motivo_rechazo_asistencia',
        'correo_citacion_enviado',
        'es_confidencial',
    ];

    protected $casts = [
        'confirmado_at' => 'datetime',
        'es_confidencial' => 'boolean',
    ];

    protected $attributes = [
        'estado_asistencia' => 'pendiente',
    ];

    protected static function booted(): void
    {
        static::creating(function (Entrevista $entrevista) {
            if (empty($entrevista->confirmacion_token)) {
                $entrevista->confirmacion_token = Str::random(40);
            }
        });
    }

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
    public function bitacora(): HasOne
    {
        return $this->hasOne(Bitacora::class);
    }

    /**
     * Accesos explícitamente compartidos para esta entrevista
     */
    public function accesosCompartidos(): HasMany
    {
        return $this->hasMany(EntrevistaCompartida::class, 'entrevista_id');
    }

    /**
     * Usuarios con los que se ha compartido la entrevista
     */
    public function usuariosCompartidos(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'entrevista_compartida', 'entrevista_id', 'user_id')->withTimestamps();
    }
}
