<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'school_id', 'curso_id',
    'nombres_csv', 'rut_numero', 'rut_dv',
    'fecha_nacimiento', 'genero',
    'apoderado_nombres', 'apoderado_apellido_pat', 'apoderado_apellido_mat',
    'apoderado_rut_numero', 'apoderado_rut_dv',
    'apoderado_email', 'apoderado_telefono', 'apoderado_parentesco', 'apoderado_domicilio',
    'vinculado_en',
])]
class Estudiante extends Model
{
    use HasFactory;

    /**
     * Get the user account associated with this student.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the school this student belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the course this student is enrolled in.
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Get the student's full name (from their user account, or fallback to CSV data).
     */
    public function nombreCompleto(): string
    {
        return $this->user ? $this->user->nombreCompleto() : ($this->nombres_csv ?? 'Sin Nombre');
    }

    /**
     * Get the student's full RUT formatted.
     */
    public function rutCompleto(): ?string
    {
        if (! $this->rut_numero) {
            return null;
        }

        $numero = number_format((int) $this->rut_numero, 0, ',', '.');

        return $numero.'-'.($this->rut_dv ?? '');
    }

    /**
     * Get the apoderado's full RUT formatted (e.g. 12.345.678-K).
     */
    public function apoderadoRutCompleto(): ?string
    {
        if (! $this->apoderado_rut_numero) {
            return null;
        }

        $numero = number_format((int) $this->apoderado_rut_numero, 0, ',', '.');

        return $numero.'-'.($this->apoderado_rut_dv ?? '');
    }
}
