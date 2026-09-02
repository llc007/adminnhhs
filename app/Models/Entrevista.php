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
        'recepcionista_ingreso_id',
        'recepcionista_salida_id',
        'hora_salida',
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
     * El funcionario de recepción que registró el ingreso del apoderado
     */
    public function recepcionistaIngreso(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recepcionista_ingreso_id');
    }

    /**
     * El funcionario de recepción que registró la salida del apoderado
     */
    public function recepcionistaSalida(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recepcionista_salida_id');
    }

    /**
     * Usuarios con los que se ha compartido la entrevista
     */
    public function usuariosCompartidos(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'entrevista_compartida', 'entrevista_id', 'user_id')->withTimestamps();
    }

    /**
     * Normaliza cualquier texto o slug de motivo a su categoría oficial estandarizada
     */
    public static function normalizarCategoria(?string $motivo): string
    {
        if (empty($motivo)) {
            return 'Otro';
        }

        $m = mb_strtolower(trim($motivo));

        if (str_contains($m, 'rendimiento') || str_contains($m, 'nota') || str_contains($m, 'academico') || str_contains($m, 'académico')) {
            return 'Rendimiento Académico';
        }

        if (str_contains($m, 'conducta') || str_contains($m, 'convivencia') || str_contains($m, 'disciplina')) {
            return 'Conducta y Convivencia';
        }

        if (str_contains($m, 'asistencia') || str_contains($m, 'puntualidad') || str_contains($m, 'atraso') || str_contains($m, 'inasistencia')) {
            return 'Asistencia y Puntualidad';
        }

        if (str_contains($m, 'personal') || str_contains($m, 'familiar') || str_contains($m, 'apoderado')) {
            return 'Asunto Personal / Familiar';
        }

        if (str_contains($m, 'psico') || str_contains($m, 'evaluacion') || str_contains($m, 'evaluación') || str_contains($m, 'pie')) {
            return 'Evaluación Psicopedagógica';
        }

        if (str_contains($m, 'medica') || str_contains($m, 'médica') || str_contains($m, 'salud') || str_contains($m, 'medico') || str_contains($m, 'médico')) {
            return 'Situación Médica';
        }

        return 'Otro';
    }
}
