<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'domain', 'modulos_publicados'])]
class School extends Model
{
    protected $casts = [
        'modulos_publicados' => 'array',
    ];

    /**
     * Get the active published modules, merging defaults.
     */
    public function getModulosPublicadosAttribute($value): array
    {
        $defaults = [
            'entrevistas' => true,
            'estudiantes' => true,
            'atrasos' => true,
            'adquisiciones' => true,
            'prestamos' => true,
            'envio_correos' => true,
            'atrasos_notificar_apoderados' => true,
            'modo_mantenimiento' => false,
            'mensaje_mantenimiento' => 'El sistema se encuentra en mantenimiento programado. Volveremos en breve.',
            'encuesta_habilitada' => true,
            'encuesta_pin_estudiante' => '1234',
            'encuesta_pin_apoderado' => '5678',
        ];

        if (is_null($value)) {
            return $defaults;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return array_merge($defaults, is_array($decoded) ? $decoded : []);
    }

    /**
     * Check if email notifications are enabled for this school.
     */
    public function emailsEnabled(): bool
    {
        return (bool) ($this->modulos_publicados['envio_correos'] ?? true);
    }

    /**
     * Check if atrasos email notifications to apoderados are enabled for this school.
     */
    public function atrasosEmailsEnabled(): bool
    {
        return $this->emailsEnabled() && (bool) ($this->modulos_publicados['atrasos_notificar_apoderados'] ?? true);
    }

    /**
     * Check if teacher evaluation surveys are currently enabled for this school.
     */
    public function encuestasHabilitadas(): bool
    {
        return filter_var($this->modulos_publicados['encuesta_habilitada'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get the access PIN for the student survey.
     */
    public function pinEncuestaEstudiante(): string
    {
        return (string) ($this->modulos_publicados['encuesta_pin_estudiante'] ?? '1234');
    }

    /**
     * Get the access PIN for the parent (apoderado) survey.
     */
    public function pinEncuestaApoderado(): string
    {
        return (string) ($this->modulos_publicados['encuesta_pin_apoderado'] ?? '5678');
    }

    /**
     * Update teacher evaluation survey configuration for this school.
     */
    public function actualizarConfigEncuesta(bool $habilitada, string $pinEstudiante, string $pinApoderado): void
    {
        $modulos = $this->modulos_publicados;
        $modulos['encuesta_habilitada'] = $habilitada;
        $modulos['encuesta_pin_estudiante'] = trim($pinEstudiante);
        $modulos['encuesta_pin_apoderado'] = trim($pinApoderado);

        $this->modulos_publicados = $modulos;
        $this->save();
    }

    /**
     * Get the users that belong to this school (many-to-many via school_user).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('roles')->withTimestamps();
    }

    /**
     * Get the academic years for this school.
     */
    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    /**
     * Get all academic terms for this school through academic years.
     */
    public function academicTerms(): HasManyThrough
    {
        return $this->hasManyThrough(AcademicTerm::class, AcademicYear::class);
    }

    /**
     * Get all cursos for this school.
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }
}
