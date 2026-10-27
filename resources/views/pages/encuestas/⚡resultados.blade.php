<?php

use App\Models\EvaluacionDocente;
use App\Models\School;
use App\Models\User;
use Flux\Flux;
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
            ->whereDoesntHave('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', 'estudiante');
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
            ->orderBy('nombres')
            ->orderBy('apellido_pat')
            ->get();

        $filas = $profesores->map(function (User $profesor) use ($preguntas) {
            $evals = $profesor->evaluacionesDocente;
            $cantidad = $evals->count();
            $esJefe = $profesor->jefeDeCursos->isNotEmpty();

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

            $puntajeMaximoRef = $cantidad > 0
                ? (int) round((float) $evals->avg('puntaje_maximo'))
                : ($this->tipoVista === 'estudiante' ? 30 : ($esJefe ? 21 : 18));

            $promedioGeneral = $cantidad > 0
                ? round((float) $evals->avg('promedio'), 2)
                : null;

            return [
                'profesor' => $profesor,
                'es_jefe' => $esJefe,
                'cursos_jefe' => $profesor->jefeDeCursos->map(fn ($c) => $c->nombreAbreviado())->implode(', '),
                'cantidad' => $cantidad,
                'promedios_pregunta' => $promediosPorPregunta,
                'puntaje_total_promedio' => $puntajeTotalPromedio,
                'puntaje_maximo' => $puntajeMaximoRef,
                'promedio_general' => $promedioGeneral,
            ];
        });

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

        return [
            'profesor' => $profesor,
            'total_evaluaciones' => $evals->count(),
            'puntaje_promedio' => $evals->isNotEmpty() ? round((float) $evals->avg('puntaje_obtenido'), 1) : 0,
            'puntaje_maximo' => $evals->isNotEmpty() ? (int) round((float) $evals->avg('puntaje_maximo')) : ($this->tipoVista === 'estudiante' ? 30 : 21),
            'promedio_general' => $evals->isNotEmpty() ? round((float) $evals->avg('promedio'), 2) : 0,
            'desglose' => $desglose,
        ];
    }
};
?>

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto">
    <x-header
        :titulo="__('Evaluación Docente — Resultados y Administración')"
        :subtitulo="__('Consolidado anónimo de puntajes promedios por pregunta y docente (20 Estudiantes y 2 Apoderados por profesor).')"
        icono="clipboard-document-check">
        <flux:button variant="ghost" icon="qr-code" :href="route('encuestas.imprimir_qr')">
            {{ __('Imprimir QR y PINs') }}
        </flux:button>
        <flux:button variant="filled" icon="cog-6-tooth" wire:click="$set('modalAdminEncuesta', true)">
            {{ __('Administrar PIN / Estado') }}
        </flux:button>
        <flux:button variant="primary" icon="computer-desktop" :href="route('encuestas.kiosco', ['schoolId' => $this->school?->id])">
            {{ __('Abrir Encuesta (Tablet)') }}
        </flux:button>
    </x-header>

    {{-- Barra de Estado y Acceso Rápido de Administración de Encuestas --}}
    <div class="rounded-2xl border p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 {{ $encuestaHabilitada ? 'bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/60' : 'bg-amber-50/80 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800' }}">
        <div class="flex items-center gap-3.5">
            <div class="size-11 rounded-xl flex items-center justify-center shrink-0 {{ $encuestaHabilitada ? 'bg-emerald-600 text-white' : 'bg-amber-600 text-white' }}">
                @if ($encuestaHabilitada)
                    <flux:icon.check-badge class="size-6" />
                @else
                    <flux:icon.lock-closed class="size-6" />
                @endif
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-extrabold text-zinc-900 dark:text-white">
                        Estado de Recepción: {{ $encuestaHabilitada ? 'ENCUESTAS HABILITADAS' : 'ENCUESTAS DESHABILITADAS (CERRADAS)' }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-4 mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                    <span>PIN Estudiantes: <strong class="font-mono text-[#00376e] dark:text-blue-300">{{ $pinEstudiante }}</strong></span>
                    <span>•</span>
                    <span>PIN Apoderados: <strong class="font-mono text-indigo-700 dark:text-indigo-300">{{ $pinApoderado }}</strong></span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <flux:button size="sm" variant="ghost" icon="qr-code" :href="route('encuestas.imprimir_qr')">
                Hoja QR para Tablets
            </flux:button>
            <flux:button size="sm" variant="filled" icon="key" wire:click="$set('modalAdminEncuesta', true)">
                Modificar PINs
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600 hover:!bg-red-50 dark:!text-red-400 dark:hover:!bg-red-950/40"
                wire:click="$set('modalConfirmarReset', true)">
                Reiniciar Encuestas
            </flux:button>
            <flux:button size="sm" :variant="$encuestaHabilitada ? 'danger' : 'primary'"
                icon="{{ $encuestaHabilitada ? 'lock-closed' : 'lock-open' }}"
                wire:click="toggleEstadoRapido">
                {{ $encuestaHabilitada ? 'Deshabilitar Encuestas' : 'Habilitar Encuestas' }}
            </flux:button>
        </div>
    </div>

    {{-- KPIs Resumen --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="flex items-center gap-4">
            <div class="size-12 rounded-xl bg-blue-100 dark:bg-blue-950 text-[#00376e] dark:text-blue-400 flex items-center justify-center shrink-0">
                <flux:icon.academic-cap class="size-6" />
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Respuestas Estudiantes</p>
                <p class="text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->kpis['total_estudiantes'] }}</p>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-12 rounded-xl bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <flux:icon.user-group class="size-6" />
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Respuestas Apoderados</p>
                <p class="text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->kpis['total_apoderados'] }}</p>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-12 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <flux:icon.users class="size-6" />
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Docentes Evaluados</p>
                <p class="text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $this->kpis['docentes_evaluados'] }}</p>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="size-12 rounded-xl bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                <flux:icon.chart-bar class="size-6" />
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Promedio Gral. (Máx 3.0)</p>
                <p class="text-2xl font-extrabold text-zinc-900 dark:text-white">{{ number_format($this->kpis['promedio_general'], 2) }}</p>
            </div>
        </flux:card>
    </div>

    {{-- Controles y Filtros --}}
    <flux:card>
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            {{-- Pestañas Estudiante / Apoderado --}}
            <div class="inline-flex rounded-xl bg-zinc-100 dark:bg-zinc-800 p-1">
                <button type="button" wire:click="cambiarVista('estudiante')"
                    class="px-4 py-2 rounded-lg text-xs sm:text-sm font-bold transition cursor-pointer {{ $tipoVista === 'estudiante' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }}">
                    Encuesta Estudiantes (Meta: 20 por prof.)
                </button>
                <button type="button" wire:click="cambiarVista('apoderado')"
                    class="px-4 py-2 rounded-lg text-xs sm:text-sm font-bold transition cursor-pointer {{ $tipoVista === 'apoderado' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }}">
                    Encuesta Apoderados (Meta: 2 por prof.)
                </button>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-zinc-700 dark:text-zinc-300 cursor-pointer">
                    <input type="checkbox" wire:model.live="soloEvaluados"
                        class="rounded border-zinc-300 text-[#00376e] focus:ring-[#00376e]" />
                    Mostrar solo profesores con respuestas
                </label>

                <div class="w-full sm:w-64">
                    <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass"
                        placeholder="Buscar profesor(a)..." clearable />
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Tabla de promedios por pregunta y puntaje total --}}
    @php
        $metaActual = $this->metaRespuestas;
    @endphp
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="bg-zinc-100/80 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-[11px] uppercase tracking-wider font-bold text-zinc-600 dark:text-zinc-300">
                        <th class="py-3.5 px-4 sticky left-0 bg-zinc-100 dark:bg-zinc-800 z-10 min-w-56">Profesor(a)</th>
                        <th class="py-3.5 px-3 text-center">Encuestas</th>
                        @foreach ($this->preguntasVista as $num => $enunciado)
                            <th class="py-3.5 px-2.5 text-center" title="{{ $enunciado }}">
                                <span class="cursor-help border-b border-dotted border-zinc-400">P{{ $num }}</span>
                            </th>
                        @endforeach
                        <th class="py-3.5 px-3 text-center bg-blue-50/60 dark:bg-blue-950/30 text-[#00376e] dark:text-blue-300">
                            Puntaje Total Prom.
                        </th>
                        <th class="py-3.5 px-3 text-center">Prom. Pregunta</th>
                        <th class="py-3.5 px-4 text-right">Detalle</th>
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
                            <td class="py-3 px-4 sticky left-0 bg-white dark:bg-zinc-900 z-10 font-semibold text-zinc-900 dark:text-zinc-100">
                                <div class="flex flex-col">
                                    <span>{{ $prof->nombreCompleto() }}</span>
                                    @if ($fila['es_jefe'])
                                        <span class="text-[10px] font-semibold text-blue-600 dark:text-blue-400">
                                            Prof. Jefe ({{ $fila['cursos_jefe'] }})
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold {{ $cant >= $metaActual ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($cant > 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400') }}">
                                    {{ $cant }} / {{ $metaActual }}
                                </span>
                            </td>

                            @foreach (array_keys($this->preguntasVista) as $num)
                                @php
                                    $promP = $fila['promedios_pregunta'][$num] ?? null;
                                @endphp
                                <td class="py-3 px-2.5 text-center font-mono text-xs">
                                    @if ($promP !== null)
                                        <span class="font-semibold {{ $promP >= 2.5 ? 'text-emerald-700 dark:text-emerald-400' : ($promP >= 1.8 ? 'text-amber-700 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                            {{ number_format($promP, 1) }}
                                        </span>
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                            @endforeach

                            <td class="py-3 px-3 text-center bg-blue-50/30 dark:bg-blue-950/20 font-bold text-zinc-900 dark:text-white">
                                @if ($fila['puntaje_total_promedio'] !== null)
                                    {{ number_format($fila['puntaje_total_promedio'], 1) }}
                                    <span class="text-[11px] font-normal text-zinc-500">/ {{ $fila['puntaje_maximo'] }}</span>
                                @else
                                    <span class="text-zinc-400 font-normal">Sin datos</span>
                                @endif
                            </td>

                            <td class="py-3 px-3 text-center">
                                @if ($fila['promedio_general'] !== null)
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-extrabold {{ $fila['promedio_general'] >= 2.5 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($fila['promedio_general'] >= 1.8 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300') }}">
                                        {{ number_format($fila['promedio_general'], 2) }} / 3.00
                                    </span>
                                @else
                                    <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-right">
                                <flux:button size="xs" variant="ghost" icon="chart-bar-square"
                                    wire:click="verDetalleProfesor({{ $prof->id }})"
                                    :disabled="$cant === 0">
                                    Ver Resumen
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($this->preguntasVista) + 5 }}" class="py-12 text-center text-zinc-500 dark:text-zinc-400">
                                No se encontraron profesores para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Glosario de Preguntas --}}
    <flux:card>
        <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-300 mb-3">
            Referencia de Preguntas ({{ $tipoVista === 'estudiante' ? 'Encuesta Estudiantes' : 'Encuesta Apoderados' }})
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-zinc-700 dark:text-zinc-300">
            @foreach ($this->preguntasVista as $num => $texto)
                <div class="flex items-start gap-2.5 p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                    <span class="inline-flex items-center justify-center size-6 rounded bg-[#00376e] text-white font-bold text-[11px] shrink-0">
                        P{{ $num }}
                    </span>
                    <p class="leading-relaxed">{{ $texto }}</p>
                </div>
            @endforeach
        </div>
    </flux:card>

    {{-- Modal de Administración de Encuestas (Habilitar/Deshabilitar, PINs, QR) --}}
    <flux:modal wire:model.self="modalAdminEncuesta" class="max-w-lg w-full">
        <form wire:submit="guardarConfiguracionEncuesta" class="space-y-6">
            <div>
                <flux:heading size="lg">Panel de Administración de Encuestas</flux:heading>
                <flux:text class="mt-1 text-xs">
                    Configura la disponibilidad del periodo de encuestas y los códigos PIN únicos para el acceso desde tablets.
                </flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 flex items-center justify-between gap-4 bg-zinc-50 dark:bg-zinc-800/50">
                <div>
                    <p class="text-sm font-bold text-zinc-900 dark:text-white">Habilitar completado de encuestas</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Al deshabilitar, nadie podrá responder desde las tablets aunque tenga el QR o el PIN.
                    </p>
                </div>
                <flux:switch wire:model.live="encuestaHabilitada" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:input wire:model="pinEstudiante" label="PIN Encuesta Estudiantes" placeholder="Ej: 1234" />
                    <flux:error name="pinEstudiante" />
                </div>

                <div>
                    <flux:input wire:model="pinApoderado" label="PIN Encuesta Apoderados" placeholder="Ej: 5678" />
                    <flux:error name="pinApoderado" />
                </div>
            </div>

            <div class="rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 p-3.5 flex items-center justify-between gap-3">
                <div class="text-xs text-blue-900 dark:text-blue-200">
                    <span class="font-bold block">Códigos QR listos para imprimir</span>
                    Genera la hoja con los 2 códigos QR y sus respectivos PINs para las tablets.
                </div>
                <flux:button size="sm" variant="ghost" icon="printer" :href="route('encuestas.imprimir_qr')">
                    Imprimir QR
                </flux:button>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('modalAdminEncuesta', false)">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Guardar Cambios
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal de Confirmación para Reiniciar / Eliminar Todas las Encuestas --}}
    <flux:modal wire:model.self="modalConfirmarReset" class="max-w-md w-full">
        <div class="space-y-6">
            <div class="flex items-start gap-3.5">
                <div class="size-11 rounded-xl bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                    <flux:icon.exclamation-triangle class="size-6" />
                </div>
                <div>
                    <flux:heading size="lg">Reiniciar Encuestas</flux:heading>
                    <flux:text class="mt-1 text-xs">
                        Esta acción eliminará permanentemente las respuestas registradas para dejar el sistema en cero.
                    </flux:text>
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    ¿Qué respuestas deseas eliminar?
                </label>
                <flux:select wire:model="tipoReset">
                    <flux:select.option value="todas">Todas las encuestas (Estudiantes y Apoderados)</flux:select.option>
                    <flux:select.option value="estudiante">Solo encuestas de Estudiantes</flux:select.option>
                    <flux:select.option value="apoderado">Solo encuestas de Apoderados</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('modalConfirmarReset', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" icon="trash" wire:click="reiniciarEncuestas">
                    Sí, eliminar encuestas
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal de Detalle Estilo Google Forms --}}
    <flux:modal wire:model.self="modalDetalle" class="max-w-3xl w-full">
        @if ($this->detalleProfesor)
            @php
                $det = $this->detalleProfesor;
            @endphp
            <div class="space-y-6">
                <div class="border-b border-zinc-200 dark:border-zinc-800 pb-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                        Desglose de Respuestas ({{ $tipoVista === 'estudiante' ? 'Estudiantes' : 'Apoderados' }})
                    </p>
                    <h2 class="text-xl font-extrabold text-zinc-900 dark:text-white mt-0.5">
                        {{ $det['profesor']->nombreCompleto() }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-4 mt-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <span><strong>{{ $det['total_evaluaciones'] }} / {{ $metaActual }}</strong> encuestas recibidas</span>
                        <span>•</span>
                        <span>Puntaje Total Promedio: <strong>{{ number_format($det['puntaje_promedio'], 1) }} / {{ $det['puntaje_maximo'] }} pts</strong></span>
                        <span>•</span>
                        <span>Promedio por Pregunta: <strong>{{ number_format($det['promedio_general'], 2) }} / 3.00</strong></span>
                    </div>
                </div>

                <div class="space-y-4 max-h-[65vh] overflow-y-auto pr-1">
                    @foreach ($det['desglose'] as $item)
                        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 p-4 space-y-3 bg-zinc-50/50 dark:bg-zinc-800/30">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                    <span class="text-[#00376e] dark:text-blue-400">P{{ $item['numero'] }}.</span>
                                    {{ $item['texto'] }}
                                </p>
                                @if ($item['promedio'] !== null)
                                    <span class="shrink-0 inline-flex items-center rounded-md bg-blue-100 dark:bg-blue-950 px-2.5 py-1 text-xs font-extrabold text-[#00376e] dark:text-blue-300">
                                        Prom: {{ number_format($item['promedio'], 2) }}
                                    </span>
                                @endif
                            </div>

                            <div class="space-y-2">
                                @foreach ($item['alternativas'] as $pts => $alt)
                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="w-28 font-semibold text-zinc-700 dark:text-zinc-300 shrink-0">
                                            {{ $alt['label'] }} ({{ $pts }}p)
                                        </span>
                                        <div class="flex-1 h-3 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full {{ $pts === 3 ? 'bg-emerald-500' : ($pts === 2 ? 'bg-blue-500' : ($pts === 1 ? 'bg-amber-500' : 'bg-red-500')) }}"
                                                style="width: {{ $alt['porcentaje'] }}%"></div>
                                        </div>
                                        <span class="w-20 text-right font-mono text-zinc-600 dark:text-zinc-400 shrink-0">
                                            {{ $alt['count'] }} ({{ $alt['porcentaje'] }}%)
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-2">
                    <flux:button variant="ghost" wire:click="$set('modalDetalle', false)">
                        Cerrar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
