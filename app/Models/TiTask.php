<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TiTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ti_tasks';

    protected $fillable = [
        'titulo',
        'descripcion',
        'frecuencia',
        'prioridad',
        'categoria',
        'estado',
        'fecha_programada',
        'fecha_vencimiento',
        'fecha_completada',
        'asignado_a',
        'creado_por',
        'parent_id',
        'notas_cierre',
        'es_recurrente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'fecha_vencimiento' => 'date',
            'fecha_completada' => 'datetime',
            'es_recurrente' => 'boolean',
        ];
    }

    /**
     * User assigned to the task.
     */
    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    /**
     * User who created the task.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Parent recurring task.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Next occurrences generated from this task.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Mark task as completed and generate next recurrence if applicable.
     */
    public function completar(?string $notas = null): ?self
    {
        $this->update([
            'estado' => 'completada',
            'fecha_completada' => now(),
            'notas_cierre' => $notas ?? $this->notas_cierre,
        ]);

        if ($this->es_recurrente && $this->frecuencia !== 'unica') {
            return $this->generarSiguienteRecurrencia();
        }

        return null;
    }

    /**
     * Generate next recurring task instance.
     */
    public function generarSiguienteRecurrencia(): self
    {
        $baseDate = $this->fecha_programada ? Carbon::parse($this->fecha_programada) : Carbon::today();

        $siguienteFecha = match ($this->frecuencia) {
            'diaria' => $baseDate->copy()->addDay(),
            'semanal' => $baseDate->copy()->addWeek(),
            'semestral' => $baseDate->copy()->addMonths(6),
            'anual' => $baseDate->copy()->addYear(),
            default => $baseDate->copy()->addDay(),
        };

        // Si la siguiente fecha cayó en el pasado respecto a hoy, la proyectamos a la fecha futura más próxima
        while ($siguienteFecha->isPast() && ! $siguienteFecha->isToday()) {
            $siguienteFecha = match ($this->frecuencia) {
                'diaria' => $siguienteFecha->addDay(),
                'semanal' => $siguienteFecha->addWeek(),
                'semestral' => $siguienteFecha->addMonths(6),
                'anual' => $siguienteFecha->addYear(),
                default => $siguienteFecha->addDay(),
            };
        }

        $duracionDias = ($this->fecha_programada && $this->fecha_vencimiento)
            ? Carbon::parse($this->fecha_programada)->diffInDays(Carbon::parse($this->fecha_vencimiento))
            : null;

        $siguienteVencimiento = $duracionDias !== null
            ? $siguienteFecha->copy()->addDays($duracionDias)
            : null;

        return self::create([
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'frecuencia' => $this->frecuencia,
            'prioridad' => $this->prioridad,
            'categoria' => $this->categoria,
            'estado' => 'pendiente',
            'fecha_programada' => $siguienteFecha->format('Y-m-d'),
            'fecha_vencimiento' => $siguienteVencimiento?->format('Y-m-d'),
            'asignado_a' => $this->asignado_a,
            'creado_por' => $this->creado_por,
            'parent_id' => $this->id,
            'es_recurrente' => true,
        ]);
    }

    /**
     * Check if task is overdue.
     */
    public function getEsVencidaAttribute(): bool
    {
        if ($this->estado === 'completada' || $this->estado === 'omitida') {
            return false;
        }

        if ($this->fecha_vencimiento) {
            return $this->fecha_vencimiento->isPast() && ! $this->fecha_vencimiento->isToday();
        }

        if ($this->fecha_programada) {
            return $this->fecha_programada->isPast() && ! $this->fecha_programada->isToday();
        }

        return false;
    }
}
