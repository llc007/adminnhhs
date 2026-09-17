<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Database\Factories\AtrasoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_id',
    'academic_year_id',
    'estudiante_id',
    'curso_id',
    'registrado_por_user_id',
    'fecha',
    'hora',
    'minutos_atraso',
    'estado',
    'motivo',
    'observaciones',
])]
class Atraso extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AtrasoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'minutos_atraso' => 'integer',
        ];
    }

    /**
     * Get the school this atraso belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the academic year.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the student.
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Get the course.
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Get the user who registered this atraso.
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    /**
     * Scope to atrasos for today in Chile time.
     */
    public function scopeHoy($query, ?string $fecha = null)
    {
        $targetDate = $fecha ?? now('America/Santiago')->format('Y-m-d');

        return $query->whereDate('fecha', $targetDate);
    }
}
