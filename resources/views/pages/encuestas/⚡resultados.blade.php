<?php

use App\Models\EvaluacionDocente;
use App\Models\School;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Resultados y Administración — Encuesta Docente')] class extends Component
{
    #[Url]
    public string $tipoVista = 'estudiante'; // 'estudiante' | 'apoderado'

    public string $search = '';

    public bool $soloEvaluados = false;

    public bool $modalDetalle = false;

    public ?int $profesorDetalleId = null;

    // Panel de administración de encuestas
    public bool $modalAdminEncuesta = false;

    public bool $modalConfirmarReset = false;

    public string $tipoReset = 'todas'; // 'todas' | 'estudiante' | 'apoderado'

    public bool $encuestaHabilitada = true;

    public string $pinEstudiante = '1234';

    public string $pinApoderado = '5678';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['comision_ed', 'superadmin'])) {
            abort(403, 'Solo los integrantes con el rol Comisión ED o Superadmin pueden acceder a esta sección.');
        }

        $school = $user->currentSchool ?? School::first();
        if ($school) {
            $this->encuestaHabilitada = $school->encuestasHabilitadas();
            $this->pinEstudiante = $school->pinEncuestaEstudiante();
            $this->pinApoderado = $school->pinEncuestaApoderado();
        }
    }

    public function guardarConfiguracionEncuesta(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['comision_ed', 'superadmin'])) {
            abort(403);
        }

        $this->validate([
            'pinEstudiante' => ['required', 'string', 'min:3', 'max:10'],
            'pinApoderado' => ['required', 'string', 'min:3', 'max:10', 'different:pinEstudiante'],
        ], [
            'pinEstudiante.required' => 'El PIN de Estudiantes es obligatorio.',
            'pinApoderado.required' => 'El PIN de Apoderados es obligatorio.',
            'pinApoderado.different' => 'El PIN de Apoderados debe ser distinto al PIN de Estudiantes.',
        ]);

        $school = $user->currentSchool ?? School::first();
        if (! $school) {
            return;
        }

        // Solo superadmin (o comision_ed) actualiza el estado habilitado y los PINs
        $habilitada = $user->hasRole('superadmin')
            ? $this->encuestaHabilitada
            : $this->encuestaHabilitada;

        $school->actualizarConfigEncuesta(
            $habilitada,
            $this->pinEstudiante,
            $this->pinApoderado
        );

        $this->modalAdminEncuesta = false;

        Flux::toast(
            heading: 'Configuración actualizada',
            text: $this->encuestaHabilitada
                ? 'Las encuestas están HABILITADAS y los PINs fueron guardados.'
                : 'El completado de encuestas ha sido DESHABILITADO.',
            variant: $this->encuestaHabilitada ? 'success' : 'warning',
        );
    }

    public function toggleEstadoRapido(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['superadmin', 'comision_ed'])) {
            abort(403);
        }

        $this->encuestaHabilitada = ! $this->encuestaHabilitada;
        $this->guardarConfiguracionEncuesta();
    }

    public function reiniciarEncuestas(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['superadmin', 'comision_ed'])) {
            abort(403);
        }

        $schoolId = $user->current_school_id ?? School::first()?->id;
        if (! $schoolId) {
            return;
        }

        $query = EvaluacionDocente::query()->where('school_id', $schoolId);

        if (in_array($this->tipoReset, ['estudiante', 'apoderado'], true)) {
            $query->where('tipo_respondente', $this->tipoReset);
        }

        $eliminadas = $query->delete();

        $this->modalConfirmarReset = false;
        $this->modalAdminEncuesta = false;
        $this->modalDetalle = false;
        $this->profesorDetalleId = null;

        unset($this->kpis, $this->tablaResultados, $this->detalleProfesor);

        Flux::toast(
            heading: 'Encuestas reiniciadas',
            text: "Se han eliminado {$eliminadas} respuestas correctamente.",
            variant: 'success',
        );
    }

    public function cambiarVista(string $tipo): void
    {
        if (in_array($tipo, ['estudiante', 'apoderado'], true)) {
            $this->tipoVista = $tipo;
        }
    }

    public function verDetalleProfesor(int $profesorId): void
    {
        $this->profesorDetalleId = $profesorId;
        $this->modalDetalle = true;
    }

    #[Computed]
    public function school(): ?School
    {
        return auth()->user()?->currentSchool ?? School::first();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function preguntasVista(): array
    {
        return $this->tipoVista === 'apoderado'
            ? EvaluacionDocente::PREGUNTAS_APODERADO
            : EvaluacionDocente::PREGUNTAS_ESTUDIANTE;
    }

    #[Computed]
    public function metaRespuestas(): int
    {
        return $this->tipoVista === 'apoderado'
            ? EvaluacionDocente::META_APODERADOS
            : EvaluacionDocente::META_ESTUDIANTES;
    }

    /**
     * @return array{total_estudiantes: int, total_apoderados: int, docentes_evaluados: int, promedio_general: float}
     */
    #[Computed]
    public function kpis(): array
    {
        $schoolId = auth()->user()->current_school_id;

        $evaluaciones = EvaluacionDocente::query()
            ->where('school_id', $schoolId)
            ->get(['profesor_id', 'tipo_respondente', 'promedio']);

        $totalEstudiantes = $evaluaciones->where('tipo_respondente', 'estudiante')->count();
        $totalApoderados = $evaluaciones->where('tipo_respondente', 'apoderado')->count();
        $docentesEvaluados = $evaluaciones->pluck('profesor_id')->unique()->count();
        $promedioGeneral = $evaluaciones->count() > 0 ? round((float) $evaluaciones->avg('promedio'), 2) : 0.0;

        return [
            'total_estudiantes' => $totalEstudiantes,
            'total_apoderados' => $totalApoderados,
            'docentes_evaluados' => $docentesEvaluados,
            'promedio_general' => $promedioGeneral,
        ];
    }

    #[Computed]
    public function tablaResultados()
    {
        $schoolId = auth()->user()->current_school_id;
        $preguntas = $this->preguntasVista;

        $profesores = User::query()
            ->whereHas('schools', fn ($q) => $q->where('school_id', $schoolId))
            ->where(function ($query) use ($schoolId) {
                $query->whereHas('roles', fn ($q) => $q->where('roles.team_id', $schoolId)->where('roles.name', 'docente'))
                    ->orWhereExists(function ($sub) use ($schoolId) {
                        $sub->select(DB::raw(1))
                            ->from('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->whereColumn('model_has_roles.model_id', 'users.id')
                            ->where('model_has_roles.model_type', User::class)
                            ->where('model_has_roles.team_id', $schoolId)
                            ->where('roles.name', 'docente');
                    });
            })
            ->with([
                'jefeDeCursos' => fn ($q) => $q->where('school_id', $schoolId),
                'evaluacionesDocente' => fn ($q) => $q
                    ->where('school_id', $schoolId)
                    ->where('tipo_respondente', $this->tipoVista),
            ])
            ->when(strlen(trim($this->search)) >= 1, function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('nombres', 'like', $term)
                        ->orWhere('apellido_pat', 'like', $term)
                        ->orWhere('apellido_mat', 'like', $term);
                });
            })
            ->get();

        $filas = $profesores->map(function (User $profesor) use ($preguntas) {
            $evals = $profesor->evaluacionesDocente;
            $cantidad = $evals->count();
            $esJefe = $profesor->jefeDeCursos->isNotEmpty();

            // Curso principal para ordenamiento
            $cursoPrincipal = $profesor->jefeDeCursos->sortBy(fn ($c) => [
                $c->modalidad->value === 'basica' ? 1 : 2,
                $c->nivel,
                $c->letra,
            ])->first();

            $promediosPorPregunta = [];
            foreach (array_keys($preguntas) as $num) {
                $key = (string) $num;
                $valores = $evals
                    ->map(fn (EvaluacionDocente $e) => $e->respuestas[$key] ?? null)
                    ->filter(fn ($v) => $v !== null);

                $promediosPorPregunta[$num] = $valores->isNotEmpty()
                    ? round((float) $valores->avg(), 2)
                    : null;
            }

            $puntajeTotalPromedio = $cantidad > 0
                ? round((float) $evals->avg('puntaje_obtenido'), 1)
                : null;

            // Puntaje máximo: 30 para estudiantes; 21 con jefatura y 18 sin jefatura para apoderados
            $puntajeMaximoRef = $cantidad > 0
                ? (int) round((float) $evals->avg('puntaje_maximo'))
                : ($this->tipoVista === 'estudiante' ? 30 : ($esJefe ? 21 : 18));

            $promedioGeneral = $cantidad > 0
                ? round((float) $evals->avg('promedio'), 2)
                : null;

            $clasificacion = EvaluacionDocente::clasificacionPorcentual($puntajeTotalPromedio, $puntajeMaximoRef);

            return [
                'profesor' => $profesor,
                'es_jefe' => $esJefe,
                'curso_principal' => $cursoPrincipal,
                'cursos_jefe' => $profesor->jefeDeCursos->map(fn ($c) => $c->nombreCompleto())->implode(', '),
                'cantidad' => $cantidad,
                'promedios_pregunta' => $promediosPorPregunta,
                'puntaje_total_promedio' => $puntajeTotalPromedio,
                'puntaje_maximo' => $puntajeMaximoRef,
                'promedio_general' => $promedioGeneral,
                'clasificacion' => $clasificacion,
            ];
        });

        // Ordenar en la tabla por Curso de forma ascendente: primero Básica (1° a 8°), luego Media (1° a 4°), y finalmente Docentes de Asignatura sin jefatura
        $filas = $filas->sort(function (array $a, array $b) {
            /** @var \App\Models\User $profA */
            $profA = $a['profesor'];
            /** @var \App\Models\User $profB */
            $profB = $b['profesor'];

            /** @var \App\Models\Curso|null $cursoA */
            $cursoA = $a['curso_principal'];
            /** @var \App\Models\Curso|null $cursoB */
            $cursoB = $b['curso_principal'];

            $hasCursoA = $cursoA ? 1 : 2;
            $hasCursoB = $cursoB ? 1 : 2;

            if ($hasCursoA !== $hasCursoB) {
                return $hasCursoA <=> $hasCursoB;
            }

            if ($cursoA && $cursoB) {
                // Primero Básica (1), luego Media (2)
                $modA = $cursoA->modalidad->value === 'basica' ? 1 : 2;
                $modB = $cursoB->modalidad->value === 'basica' ? 1 : 2;
                if ($modA !== $modB) {
                    return $modA <=> $modB;
                }

                // Nivel ascendente (1, 2, 3...)
                if ($cursoA->nivel !== $cursoB->nivel) {
                    return $cursoA->nivel <=> $cursoB->nivel;
                }

                // Letra ascendente (A, B, C...)
                if ($cursoA->letra !== $cursoB->letra) {
                    return strcmp($cursoA->letra, $cursoB->letra);
                }
            }

            // Orden secundario alfabético por nombre
            return strcmp($profA->nombreCompleto(), $profB->nombreCompleto());
        })->values();

        if ($this->soloEvaluados) {
            $filas = $filas->filter(fn (array $fila) => $fila['cantidad'] > 0)->values();
        }

        return $filas;
    }

    #[Computed]
    public function detalleProfesor(): ?array
    {
        if (! $this->profesorDetalleId) {
            return null;
        }

        $schoolId = auth()->user()->current_school_id;
        $profesor = User::query()
            ->whereHas('schools', fn ($q) => $q->where('school_id', $schoolId))
            ->whereHas('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', 'docente');
            })
            ->find($this->profesorDetalleId);

        if (! $profesor) {
            return null;
        }

        $evals = EvaluacionDocente::query()
            ->where('school_id', $schoolId)
            ->where('profesor_id', $profesor->id)
            ->where('tipo_respondente', $this->tipoVista)
            ->get();

        $preguntas = $this->preguntasVista;
        $desglose = [];

        foreach ($preguntas as $num => $texto) {
            $key = (string) $num;
            $valores = $evals
                ->map(fn (EvaluacionDocente $e) => $e->respuestas[$key] ?? null)
                ->filter(fn ($v) => $v !== null);

            $totalRespPregunta = $valores->count();
            $conteoAlternativas = [];

            foreach (EvaluacionDocente::ALTERNATIVAS as $pts => $label) {
                $count = $valores->filter(fn ($v) => (int) $v === $pts)->count();
                $conteoAlternativas[$pts] = [
                    'label' => $label,
                    'count' => $count,
                    'porcentaje' => $totalRespPregunta > 0 ? round(($count / $totalRespPregunta) * 100) : 0,
                ];
            }

            $desglose[$num] = [
                'numero' => $num,
                'texto' => $texto,
                'total_respuestas' => $totalRespPregunta,
                'promedio' => $totalRespPregunta > 0 ? round((float) $valores->avg(), 2) : null,
                'alternativas' => $conteoAlternativas,
            ];
        }

        $puntajePromedio = $evals->isNotEmpty() ? round((float) $evals->avg('puntaje_obtenido'), 1) : null;
        $esJefe = $profesor->jefeDeCursos()->where('school_id', $schoolId)->exists();
        $puntajeMaximo = $evals->isNotEmpty() ? (int) round((float) $evals->avg('puntaje_maximo')) : ($this->tipoVista === 'estudiante' ? 30 : ($esJefe ? 21 : 18));
        $promedioGeneral = $evals->isNotEmpty() ? round((float) $evals->avg('promedio'), 2) : null;
        $clasificacion = EvaluacionDocente::clasificacionPorcentual($puntajePromedio, $puntajeMaximo);

        return [
            'profesor' => $profesor,
            'total_evaluaciones' => $evals->count(),
            'puntaje_promedio' => $puntajePromedio ?? 0,
            'puntaje_maximo' => $puntajeMaximo,
            'promedio_general' => $promedioGeneral ?? 0,
            'clasificacion' => $clasificacion,
            'desglose' => $desglose,
        ];
    }
};
?>

<div class="flex flex-col gap-3 w-full max-w-7xl mx-auto -mt-3 sm:-mt-4">
    <x-header
        :titulo="__('Evaluación Docente — Resultados y Administración')"
        :subtitulo="__('Consolidado anónimo de puntajes promedios por pregunta y docente (20 Estudiantes y 2 Apoderados por profesor).')"
        icono="clipboard-document-check">
        <flux:button size="sm" variant="ghost" icon="qr-code" :href="route('encuestas.imprimir_qr')">
            {{ __('Imprimir QR y PINs') }}
        </flux:button>
        <flux:button size="sm" variant="filled" icon="cog-6-tooth" wire:click="$set('modalAdminEncuesta', true)">
            {{ __('Administrar PIN / Estado') }}
        </flux:button>
        <flux:button size="sm" variant="primary" icon="computer-desktop" :href="route('encuestas.kiosco', ['schoolId' => $this->school?->id])">
            {{ __('Abrir Encuesta (Tablet)') }}
        </flux:button>
    </x-header>

    {{-- Barra de Estado y Acceso Rápido de Administración de Encuestas --}}
    <div class="rounded-xl border p-2.5 sm:p-3 flex flex-col md:flex-row md:items-center justify-between gap-2.5 {{ $encuestaHabilitada ? 'bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/60' : 'bg-amber-50/80 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800' }}">
        <div class="flex items-center gap-2.5">
            <div class="size-8 rounded-lg flex items-center justify-center shrink-0 {{ $encuestaHabilitada ? 'bg-emerald-600 text-white' : 'bg-amber-600 text-white' }}">
                @if ($encuestaHabilitada)
                    <flux:icon.check-badge class="size-4.5" />
                @else
                    <flux:icon.lock-closed class="size-4.5" />
                @endif
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-zinc-900 dark:text-white">
                        Estado: {{ $encuestaHabilitada ? 'ENCUESTAS HABILITADAS' : 'ENCUESTAS DESHABILITADAS (CERRADAS)' }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-[11px] text-zinc-600 dark:text-zinc-300">
                    <span>PIN Estudiantes: <strong class="font-mono text-[#00376e] dark:text-blue-300">{{ $pinEstudiante }}</strong></span>
                    <span>•</span>
                    <span>PIN Apoderados: <strong class="font-mono text-indigo-700 dark:text-indigo-300">{{ $pinApoderado }}</strong></span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <flux:button size="xs" variant="ghost" icon="qr-code" :href="route('encuestas.imprimir_qr')">
                Hoja QR
            </flux:button>
            <flux:button size="xs" variant="filled" icon="key" wire:click="$set('modalAdminEncuesta', true)">
                Modificar PINs
            </flux:button>
            <flux:button size="xs" variant="ghost" icon="trash" class="!text-red-600 hover:!bg-red-50 dark:!text-red-400 dark:hover:!bg-red-950/40"
                wire:click="$set('modalConfirmarReset', true)">
                Reiniciar
            </flux:button>
            <flux:button size="xs" :variant="$encuestaHabilitada ? 'danger' : 'primary'"
                icon="{{ $encuestaHabilitada ? 'lock-closed' : 'lock-open' }}"
                wire:click="toggleEstadoRapido">
                {{ $encuestaHabilitada ? 'Deshabilitar' : 'Habilitar' }}
            </flux:button>
        </div>
    </div>

    {{-- KPIs Resumen --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-2.5 flex items-center gap-2.5 shadow-2xs">
            <div class="size-8 rounded-lg bg-blue-100 dark:bg-blue-950 text-[#00376e] dark:text-blue-400 flex items-center justify-center shrink-0">
                <flux:icon.academic-cap class="size-4" />
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 truncate">Respuestas Estudiantes</p>
                <p class="text-lg font-extrabold text-zinc-900 dark:text-white leading-tight">{{ $this->kpis['total_estudiantes'] }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-2.5 flex items-center gap-2.5 shadow-2xs">
            <div class="size-8 rounded-lg bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <flux:icon.user-group class="size-4" />
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 truncate">Respuestas Apoderados</p>
                <p class="text-lg font-extrabold text-zinc-900 dark:text-white leading-tight">{{ $this->kpis['total_apoderados'] }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-2.5 flex items-center gap-2.5 shadow-2xs">
            <div class="size-8 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <flux:icon.users class="size-4" />
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 truncate">Docentes Evaluados</p>
                <p class="text-lg font-extrabold text-zinc-900 dark:text-white leading-tight">{{ $this->kpis['docentes_evaluados'] }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-2.5 flex items-center gap-2.5 shadow-2xs">
            <div class="size-8 rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                <flux:icon.chart-bar class="size-4" />
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 truncate">Promedio Gral. (Máx 3.0)</p>
                <p class="text-lg font-extrabold text-zinc-900 dark:text-white leading-tight">{{ number_format($this->kpis['promedio_general'], 2) }}</p>
            </div>
        </div>
    </div>

    {{-- Controles y Filtros --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-2 sm:p-2.5 shadow-2xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2.5">
            {{-- Pestañas Estudiante / Apoderado --}}
            <div class="inline-flex rounded-lg bg-zinc-100 dark:bg-zinc-800 p-0.5">
                <button type="button" wire:click="cambiarVista('estudiante')"
                    class="px-2.5 py-1 rounded-md text-xs font-bold transition cursor-pointer {{ $tipoVista === 'estudiante' ? 'bg-[#00376e] text-white shadow-2xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }}">
                    Encuesta Estudiantes (Meta: 20)
                </button>
                <button type="button" wire:click="cambiarVista('apoderado')"
                    class="px-2.5 py-1 rounded-md text-xs font-bold transition cursor-pointer {{ $tipoVista === 'apoderado' ? 'bg-[#00376e] text-white shadow-2xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }}">
                    Encuesta Apoderados (Meta: 2)
                </button>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-300 cursor-pointer select-none">
                    <input type="checkbox" wire:model.live="soloEvaluados"
                        class="size-3.5 rounded border-zinc-300 text-[#00376e] focus:ring-[#00376e]" />
                    <span>Solo con respuestas</span>
                </label>

                <div class="w-full sm:w-56">
                    <flux:input size="sm" wire:model.live.debounce.250ms="search" icon="magnifying-glass"
                        placeholder="Buscar profesor(a)..." clearable />
                </div>
            </div>
        </div>
    </div>

    {{-- Escala de Clasificación --}}
    <div class="flex flex-wrap items-center gap-1.5 text-[11px] leading-none">
        <span class="font-bold text-zinc-500 dark:text-zinc-400 uppercase text-[10px] tracking-wider mr-1">Escala:</span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
            <span class="font-bold">Destacado:</span> &ge;90%
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
            <span class="font-bold">Competente:</span> 80% - 89.9%
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
            <span class="font-bold">Básico:</span> 70% - 79.9%
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300 border border-red-200 dark:border-red-800">
            <span class="font-bold">Por lograr:</span> &lt;70%
        </span>
    </div>

    {{-- Tabla de promedios por pregunta y puntaje total --}}
    @php
        $metaActual = $this->metaRespuestas;
    @endphp
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-zinc-100/90 dark:bg-zinc-800/90 border-b border-zinc-200 dark:border-zinc-700 text-[10px] uppercase tracking-wider font-bold text-zinc-600 dark:text-zinc-300">
                        <th class="py-2 px-3 sticky left-0 bg-zinc-100 dark:bg-zinc-800 z-10 min-w-44">Profesor(a)</th>
                        <th class="py-2 px-2 text-center whitespace-nowrap">Encuestas</th>
                        @foreach ($this->preguntasVista as $num => $enunciado)
                            <th class="py-2 px-1 text-center min-w-7" title="{{ $enunciado }}">
                                <span class="cursor-help border-b border-dotted border-zinc-400">P{{ $num }}</span>
                            </th>
                        @endforeach
                        <th class="py-2 px-2 text-center whitespace-nowrap bg-blue-50/70 dark:bg-blue-950/40 text-[#00376e] dark:text-blue-300">
                            Puntaje Total
                        </th>
                        <th class="py-2 px-2 text-center whitespace-nowrap min-w-32">Clasificación</th>
                        <th class="py-2 px-3 text-right whitespace-nowrap">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($this->tablaResultados as $fila)
                        @php
                            /** @var \App\Models\User $prof */
                            $prof = $fila['profesor'];
                            $cant = $fila['cantidad'];
                        @endphp
                        <tr wire:key="resultado-prof-{{ $prof->id }}"
                            class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition">
                            <td class="py-1.5 px-3 sticky left-0 bg-white dark:bg-zinc-900 z-10 font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold">{{ $prof->nombreCompleto() }}</span>
                                    @if ($fila['es_jefe'])
                                        <span class="text-[10px] font-semibold text-blue-600 dark:text-blue-400">
                                            Prof. Jefe: {{ $fila['cursos_jefe'] }}
                                        </span>
                                    @else
                                        <span class="text-[10px] font-medium text-zinc-400 dark:text-zinc-500">
                                            Docente de Asignatura
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-1.5 px-2 text-center">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold {{ $cant >= $metaActual ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($cant > 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400') }}">
                                    {{ $cant }} / {{ $metaActual }}
                                </span>
                            </td>

                            @foreach (array_keys($this->preguntasVista) as $num)
                                @php
                                    $promP = $fila['promedios_pregunta'][$num] ?? null;
                                @endphp
                                <td class="py-1.5 px-1 text-center font-mono text-[11px]">
                                    @if ($promP !== null)
                                        <span class="font-semibold {{ $promP >= 2.5 ? 'text-emerald-700 dark:text-emerald-400' : ($promP >= 1.8 ? 'text-amber-700 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                            {{ number_format($promP, 1) }}
                                        </span>
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                            @endforeach

                            <td class="py-1.5 px-2 text-center bg-blue-50/30 dark:bg-blue-950/20 font-bold text-xs text-zinc-900 dark:text-white">
                                @if ($fila['puntaje_total_promedio'] !== null)
                                    {{ number_format($fila['puntaje_total_promedio'], 1) }}
                                    <span class="text-[10px] font-normal text-zinc-500">/ {{ $fila['puntaje_maximo'] }}</span>
                                @else
                                    <span class="text-zinc-400 font-normal text-[11px]">Sin datos</span>
                                @endif
                            </td>

                            <td class="py-1.5 px-2 text-center">
                                @if ($fila['clasificacion'] !== null)
                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-extrabold shadow-2xs {{ $fila['clasificacion']['badge_class'] }}">
                                        <span>{{ $fila['clasificacion']['nombre'] }}</span>
                                        <span class="font-mono text-[10px] opacity-85">({{ number_format($fila['clasificacion']['porcentaje'], 1) }}%)</span>
                                    </span>
                                @else
                                    <span class="text-zinc-300 dark:text-zinc-600 font-mono text-[11px]">—</span>
                                @endif
                            </td>

                            <td class="py-1.5 px-3 text-right">
                                <flux:button size="xs" variant="ghost" icon="chart-bar-square"
                                    wire:click="verDetalleProfesor({{ $prof->id }})"
                                    :disabled="$cant === 0">
                                    Ver
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($this->preguntasVista) + 5 }}" class="py-8 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                No se encontraron profesores para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Glosario de Preguntas (Desplegable para optimizar pantallas de laptop) --}}
    <details class="group rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-2.5 sm:p-3 shadow-2xs">
        <summary class="flex items-center justify-between cursor-pointer select-none text-xs font-bold text-zinc-700 dark:text-zinc-300">
            <span class="flex items-center gap-2">
                <flux:icon.information-circle class="size-4 text-[#00376e] dark:text-blue-400" />
                <span>Referencia de Preguntas ({{ $tipoVista === 'estudiante' ? 'Encuesta Estudiantes' : 'Encuesta Apoderados' }})</span>
            </span>
            <span class="text-[11px] text-zinc-400 group-open:rotate-180 transition-transform">▼</span>
        </summary>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2.5 pt-2.5 border-t border-zinc-100 dark:border-zinc-800 text-[11px] text-zinc-700 dark:text-zinc-300">
            @foreach ($this->preguntasVista as $num => $texto)
                <div class="flex items-start gap-2 p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                    <span class="inline-flex items-center justify-center size-5 rounded bg-[#00376e] text-white font-bold text-[10px] shrink-0">
                        P{{ $num }}
                    </span>
                    <p class="leading-snug">{{ $texto }}</p>
                </div>
            @endforeach
        </div>
    </details>

    {{-- Modal de Administración de Encuestas (Habilitar/Deshabilitar, PINs, QR) --}}
    <flux:modal wire:model.self="modalAdminEncuesta" class="max-w-md w-full">
        <form wire:submit="guardarConfiguracionEncuesta" class="space-y-4">
            <div>
                <flux:heading size="base">Administración de Encuestas</flux:heading>
                <flux:text class="mt-0.5 text-xs">
                    Disponibilidad y códigos PIN para el acceso desde tablets.
                </flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 flex items-center justify-between gap-3 bg-zinc-50 dark:bg-zinc-800/50">
                <div>
                    <p class="text-xs font-bold text-zinc-900 dark:text-white">Habilitar encuestas</p>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                        Al deshabilitar, se bloquea el acceso en tablets.
                    </p>
                </div>
                <flux:switch wire:model.live="encuestaHabilitada" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <flux:input size="sm" wire:model="pinEstudiante" label="PIN Estudiantes" placeholder="Ej: 1234" />
                    <flux:error name="pinEstudiante" />
                </div>

                <div>
                    <flux:input size="sm" wire:model="pinApoderado" label="PIN Apoderados" placeholder="Ej: 5678" />
                    <flux:error name="pinApoderado" />
                </div>
            </div>

            <div class="rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 p-2.5 flex items-center justify-between gap-2.5">
                <div class="text-[11px] text-blue-900 dark:text-blue-200">
                    <span class="font-bold block">Hoja QR lista para imprimir</span>
                    Genera la hoja con los 2 códigos QR y PINs.
                </div>
                <flux:button size="xs" variant="ghost" icon="printer" :href="route('encuestas.imprimir_qr')">
                    Imprimir QR
                </flux:button>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button size="sm" variant="ghost" wire:click="$set('modalAdminEncuesta', false)">
                    Cancelar
                </flux:button>
                <flux:button size="sm" type="submit" variant="primary">
                    Guardar Cambios
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal de Confirmación para Reiniciar / Eliminar Todas las Encuestas --}}
    <flux:modal wire:model.self="modalConfirmarReset" class="max-w-md w-full">
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div class="size-9 rounded-xl bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                    <flux:icon.exclamation-triangle class="size-5" />
                </div>
                <div>
                    <flux:heading size="base">Reiniciar Encuestas</flux:heading>
                    <flux:text class="mt-0.5 text-xs">
                        Esta acción eliminará permanentemente las respuestas para dejar el sistema en cero.
                    </flux:text>
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    ¿Qué respuestas deseas eliminar?
                </label>
                <flux:select size="sm" wire:model="tipoReset">
                    <flux:select.option value="todas">Todas las encuestas (Estudiantes y Apoderados)</flux:select.option>
                    <flux:select.option value="estudiante">Solo encuestas de Estudiantes</flux:select.option>
                    <flux:select.option value="apoderado">Solo encuestas de Apoderados</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button size="sm" variant="ghost" wire:click="$set('modalConfirmarReset', false)">
                    Cancelar
                </flux:button>
                <flux:button size="sm" variant="danger" icon="trash" wire:click="reiniciarEncuestas">
                    Sí, eliminar
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal de Detalle Estilo Google Forms --}}
    <flux:modal wire:model.self="modalDetalle" class="max-w-2xl w-full">
        @if ($this->detalleProfesor)
            @php
                $det = $this->detalleProfesor;
            @endphp
            <div class="space-y-4">
                <div class="border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                        Desglose de Respuestas ({{ $tipoVista === 'estudiante' ? 'Estudiantes' : 'Apoderados' }})
                    </p>
                    <h2 class="text-lg font-extrabold text-zinc-900 dark:text-white mt-0.5">
                        {{ $det['profesor']->nombreCompleto() }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-2 mt-1.5 text-xs text-zinc-600 dark:text-zinc-400">
                        <span><strong>{{ $det['total_evaluaciones'] }} / {{ $metaActual }}</strong> encuestas</span>
                        <span>•</span>
                        <span>Total Prom.: <strong>{{ number_format($det['puntaje_promedio'], 1) }} / {{ $det['puntaje_maximo'] }} pts</strong></span>
                        @if ($det['clasificacion'] !== null)
                            <span>•</span>
                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-extrabold shadow-2xs {{ $det['clasificacion']['badge_class'] }}">
                                <span>{{ $det['clasificacion']['nombre'] }}</span>
                                <span class="font-mono text-[10px] opacity-85">({{ number_format($det['clasificacion']['porcentaje'], 1) }}%)</span>
                            </span>
                        @endif
                        <span>•</span>
                        <span>Prom./Preg.: <strong>{{ number_format($det['promedio_general'], 2) }} / 3.00</strong></span>
                    </div>
                </div>

                <div class="space-y-3 max-h-[55vh] overflow-y-auto pr-1">
                    @foreach ($det['desglose'] as $item)
                        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 p-3 space-y-2 bg-zinc-50/50 dark:bg-zinc-800/30">
                            <div class="flex items-start justify-between gap-2.5">
                                <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                    <span class="text-[#00376e] dark:text-blue-400">P{{ $item['numero'] }}.</span>
                                    {{ $item['texto'] }}
                                </p>
                                @if ($item['promedio'] !== null)
                                    <span class="shrink-0 inline-flex items-center rounded-md bg-blue-100 dark:bg-blue-950 px-2 py-0.5 text-xs font-extrabold text-[#00376e] dark:text-blue-300">
                                        Prom: {{ number_format($item['promedio'], 2) }}
                                    </span>
                                @endif
                            </div>

                            <div class="space-y-1.5">
                                @foreach ($item['alternativas'] as $pts => $alt)
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="w-24 text-[11px] font-semibold text-zinc-700 dark:text-zinc-300 shrink-0">
                                            {{ $alt['label'] }} ({{ $pts }}p)
                                        </span>
                                        <div class="flex-1 h-2.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full {{ $pts === 3 ? 'bg-emerald-500' : ($pts === 2 ? 'bg-blue-500' : ($pts === 1 ? 'bg-amber-500' : 'bg-red-500')) }}"
                                                style="width: {{ $alt['porcentaje'] }}%"></div>
                                        </div>
                                        <span class="w-16 text-right font-mono text-[11px] text-zinc-600 dark:text-zinc-400 shrink-0">
                                            {{ $alt['count'] }} ({{ $alt['porcentaje'] }}%)
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button size="sm" variant="ghost" wire:click="$set('modalDetalle', false)">
                        Cerrar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
