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
            'diaria' => $baseDate->copy()->addWeekday(),
            'semanal' => $baseDate->copy()->addWeek(),
            'semestral' => $baseDate->copy()->addMonths(6),
            'anual' => $baseDate->copy()->addYear(),
            default => $baseDate->copy()->addWeekday(),
        };

        // Si la siguiente fecha cayó en el pasado respecto a hoy, la proyectamos a la fecha futura más próxima
        while ($siguienteFecha->isPast() && ! $siguienteFecha->isToday()) {
            $siguienteFecha = match ($this->frecuencia) {
                'diaria' => $siguienteFecha->addWeekday(),
                'semanal' => $siguienteFecha->addWeek(),
                'semestral' => $siguienteFecha->addMonths(6),
                'anual' => $siguienteFecha->addYear(),
                default => $siguienteFecha->addWeekday(),
            };
        }

        $siguienteFechaStr = $siguienteFecha->format('Y-m-d');

        // Prevenir duplicados si la tarea para esa fecha ya fue creada (por ejemplo por auto-generación de hoy)
        $existente = self::where('parent_id', $this->id)
            ->orWhere(function ($q) use ($siguienteFechaStr) {
                $q->where('titulo', $this->titulo)
                    ->where('frecuencia', $this->frecuencia)
                    ->whereDate('fecha_programada', $siguienteFechaStr);
            })->first();

        if ($existente) {
            return $existente;
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
            'fecha_programada' => $siguienteFechaStr,
            'fecha_vencimiento' => $siguienteVencimiento?->format('Y-m-d'),
            'asignado_a' => $this->asignado_a,
            'creado_por' => $this->creado_por,
            'parent_id' => $this->id,
            'es_recurrente' => true,
        ]);
    }

    /**
     * Garantiza la creación de tareas recurrentes (diarias, semanales, semestrales, anuales) para el periodo actual.
     */
    public static function generarTareasPeriodicas(Carbon|string $fechaInput): void
    {
        self::limpiarDuplicados();

        $targetDate = Carbon::parse($fechaInput)->startOfDay();
        $todayStr = $targetDate->toDateString();

        // 1. Tareas DIARIAS (solo en días hábiles)
        if (! $targetDate->isWeekend()) {
            $tasksDiarias = self::where('es_recurrente', true)
                ->where('frecuencia', 'diaria')
                ->whereDoesntHave('children')
                ->get();

            foreach ($tasksDiarias as $latestTask) {
                $lastDateStr = $latestTask->fecha_programada ? $latestTask->fecha_programada->toDateString() : null;

                if ($lastDateStr && $lastDateStr < $todayStr) {
                    $existeHoy = self::where('parent_id', $latestTask->id)
                        ->orWhere(function ($q) use ($latestTask, $todayStr) {
                            $q->where('titulo', $latestTask->titulo)
                                ->where('frecuencia', 'diaria')
                                ->whereDate('fecha_programada', $todayStr);
                        })->exists();

                    if (! $existeHoy) {
                        self::create([
                            'titulo' => $latestTask->titulo,
                            'descripcion' => $latestTask->descripcion,
                            'frecuencia' => 'diaria',
                            'prioridad' => $latestTask->prioridad,
                            'categoria' => $latestTask->categoria,
                            'estado' => 'pendiente',
                            'fecha_programada' => $todayStr,
                            'fecha_vencimiento' => $todayStr,
                            'asignado_a' => $latestTask->asignado_a,
                            'creado_por' => $latestTask->creado_por,
                            'parent_id' => $latestTask->id,
                            'es_recurrente' => true,
                        ]);
                    }
                }
            }
        }

        // 2. Tareas SEMANALES (se programan al inicio de la semana actual)
        $inicioSemanaStr = $targetDate->copy()->startOfWeek()->toDateString();
        $finSemanaStr = $targetDate->copy()->endOfWeek()->toDateString();

        $tasksSemanales = self::where('es_recurrente', true)
            ->where('frecuencia', 'semanal')
            ->whereDoesntHave('children')
            ->get();

        foreach ($tasksSemanales as $latestTask) {
            $lastDateStr = $latestTask->fecha_programada ? $latestTask->fecha_programada->toDateString() : null;

            if ($lastDateStr && $lastDateStr < $inicioSemanaStr) {
                $existeEstaSemana = self::where('parent_id', $latestTask->id)
                    ->orWhere(function ($q) use ($latestTask, $inicioSemanaStr, $finSemanaStr) {
                        $q->where('titulo', $latestTask->titulo)
                            ->where('frecuencia', 'semanal')
                            ->whereBetween('fecha_programada', [$inicioSemanaStr, $finSemanaStr]);
                    })->exists();

                if (! $existeEstaSemana) {
                    self::create([
                        'titulo' => $latestTask->titulo,
                        'descripcion' => $latestTask->descripcion,
                        'frecuencia' => 'semanal',
                        'prioridad' => $latestTask->prioridad,
                        'categoria' => $latestTask->categoria,
                        'estado' => 'pendiente',
                        'fecha_programada' => $inicioSemanaStr,
                        'fecha_vencimiento' => $finSemanaStr,
                        'asignado_a' => $latestTask->asignado_a,
                        'creado_por' => $latestTask->creado_por,
                        'parent_id' => $latestTask->id,
                        'es_recurrente' => true,
                    ]);
                }
            }
        }

        // 3. Tareas SEMESTRALES
        $mes = $targetDate->month;
        $year = $targetDate->year;
        $inicioSemestreStr = $mes <= 6 ? "$year-01-01" : "$year-07-01";
        $finSemestreStr = $mes <= 6 ? "$year-06-30" : "$year-12-31";

        $tasksSemestrales = self::where('es_recurrente', true)
            ->where('frecuencia', 'semestral')
            ->whereDoesntHave('children')
            ->get();

        foreach ($tasksSemestrales as $latestTask) {
            $lastDateStr = $latestTask->fecha_programada ? $latestTask->fecha_programada->toDateString() : null;

            if ($lastDateStr && $lastDateStr < $inicioSemestreStr) {
                $existeEsteSemestre = self::where('parent_id', $latestTask->id)
                    ->orWhere(function ($q) use ($latestTask, $inicioSemestreStr, $finSemestreStr) {
                        $q->where('titulo', $latestTask->titulo)
                            ->where('frecuencia', 'semestral')
                            ->whereBetween('fecha_programada', [$inicioSemestreStr, $finSemestreStr]);
                    })->exists();

                if (! $existeEsteSemestre) {
                    self::create([
                        'titulo' => $latestTask->titulo,
                        'descripcion' => $latestTask->descripcion,
                        'frecuencia' => 'semestral',
                        'prioridad' => $latestTask->prioridad,
                        'categoria' => $latestTask->categoria,
                        'estado' => 'pendiente',
                        'fecha_programada' => $inicioSemestreStr,
                        'fecha_vencimiento' => $finSemestreStr,
                        'asignado_a' => $latestTask->asignado_a,
                        'creado_por' => $latestTask->creado_por,
                        'parent_id' => $latestTask->id,
                        'es_recurrente' => true,
                    ]);
                }
            }
        }

        // 4. Tareas ANUALES
        $inicioAnioStr = "$year-01-01";
        $finAnioStr = "$year-12-31";

        $tasksAnuales = self::where('es_recurrente', true)
            ->where('frecuencia', 'anual')
            ->whereDoesntHave('children')
            ->get();

        foreach ($tasksAnuales as $latestTask) {
            $lastDateStr = $latestTask->fecha_programada ? $latestTask->fecha_programada->toDateString() : null;

            if ($lastDateStr && $lastDateStr < $inicioAnioStr) {
                $existeEsteAnio = self::where('parent_id', $latestTask->id)
                    ->orWhere(function ($q) use ($latestTask, $year) {
                        $q->where('titulo', $latestTask->titulo)
                            ->where('frecuencia', 'anual')
                            ->whereYear('fecha_programada', $year);
                    })->exists();

                if (! $existeEsteAnio) {
                    self::create([
                        'titulo' => $latestTask->titulo,
                        'descripcion' => $latestTask->descripcion,
                        'frecuencia' => 'anual',
                        'prioridad' => $latestTask->prioridad,
                        'categoria' => $latestTask->categoria,
                        'estado' => 'pendiente',
                        'fecha_programada' => $inicioAnioStr,
                        'fecha_vencimiento' => $finAnioStr,
                        'asignado_a' => $latestTask->asignado_a,
                        'creado_por' => $latestTask->creado_por,
                        'parent_id' => $latestTask->id,
                        'es_recurrente' => true,
                    ]);
                }
            }
        }
    }

    /**
     * Alias de retrocompatibilidad.
     */
    public static function generarTareasDelDia(Carbon|string $fechaInput): void
    {
        self::generarTareasPeriodicas($fechaInput);
    }

    /**
     * Limpia tareas duplicadas y tareas diarias no completadas creadas en fines de semana.
     */
    public static function limpiarDuplicados(): void
    {
        // Limpiar tareas diarias de fines de semana que no se hayan completado
        $weekendTasks = self::where('frecuencia', 'diaria')
            ->where('estado', '!=', 'completada')
            ->get();

        foreach ($weekendTasks as $t) {
            if ($t->fecha_programada && $t->fecha_programada->isWeekend()) {
                $t->forceDelete();
            }
        }

        $duplicados = self::select('titulo', 'frecuencia', 'fecha_programada')
            ->groupBy('titulo', 'frecuencia', 'fecha_programada')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicados as $dup) {
            $fechaStr = $dup->fecha_programada ? Carbon::parse($dup->fecha_programada)->toDateString() : null;
            $tasks = self::where('titulo', $dup->titulo)
                ->where('frecuencia', $dup->frecuencia)
                ->whereDate('fecha_programada', $fechaStr)
                ->orderBy('id', 'asc')
                ->get();

            $conservar = $tasks->firstWhere('estado', 'completada') ?? $tasks->first();

            foreach ($tasks as $t) {
                if ($t->id !== $conservar->id) {
                    $t->forceDelete();
                }
            }
        }
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
