<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Entrevista;
use App\Models\Curso;
use App\Models\User;
use App\Enums\Modalidad;
use Carbon\Carbon;

new class extends Component {
    #[Url]
    public string $periodo = 'ano_actual';

    #[Url]
    public string $ciclo = 'todos';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user->hasRole(['superadmin', 'administrador', 'directivo', 'gerencia', 'rectoria']) && ! $user->can('ver-dashboard-gerencia')) {
            abort(403, 'No tienes permiso para acceder al Dashboard de Dirección y Rectoría.');
        }
    }

    public int $cursosPage = 1;
    public int $cursosPerPage = 5;

    public function setPeriodo(string $periodo): void
    {
        $this->periodo = $periodo;
        $this->cursosPage = 1;
    }

    public function setCiclo(string $ciclo): void
    {
        $this->ciclo = $ciclo;
        $this->cursosPage = 1;
    }

    public function nextCursosPage(): void
    {
        if ($this->cursosPage < $this->totalCursosPages) {
            $this->cursosPage++;
        }
    }

    public function prevCursosPage(): void
    {
        if ($this->cursosPage > 1) {
            $this->cursosPage--;
        }
    }

    public function setCursosPage(int $page): void
    {
        $this->cursosPage = max(1, min($page, $this->totalCursosPages));
    }

    #[Computed]
    public function periodoDates(): array
    {
        $now = now('America/Santiago');
        $currentYear = $now->year;

        return match ($this->periodo) {
            'mes_actual' => [
                $now->copy()->startOfMonth()->format('Y-m-d'),
                $now->copy()->endOfMonth()->format('Y-m-d'),
                'Mes Actual (' . $now->translatedFormat('F Y') . ')',
            ],
            'primer_semestre' => [
                "{$currentYear}-01-01",
                "{$currentYear}-06-30",
                "Primer Semestre {$currentYear} (Ene - Jun)",
            ],
            'segundo_semestre' => [
                "{$currentYear}-07-01",
                "{$currentYear}-12-31",
                "Segundo Semestre {$currentYear} (Jul - Dic)",
            ],
            'todo' => [
                null,
                null,
                'Todo el Historial Registrado',
            ],
            default => [
                "{$currentYear}-01-01",
                "{$currentYear}-12-31",
                "Año Escolar {$currentYear}",
            ],
        };
    }

    #[Computed]
    public function baseQuery()
    {
        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->periodoDates;

        $query = Entrevista::with(['estudiante.curso', 'user'])
            ->where('school_id', $schoolId);

        if ($startDate && $endDate) {
            $query->whereBetween('fecha', [$startDate, $endDate]);
        }

        if ($this->ciclo !== 'todos') {
            $query->whereHas('estudiante.curso', fn ($q) => $q->where('modalidad', $this->ciclo));
        }

        return $query;
    }

    #[Computed]
    public function kpis(): array
    {
        $schoolId = auth()->user()->current_school_id;
        $now = now('America/Santiago');
        $today = $now->toDateString();

        $citas = (clone $this->baseQuery)->get();

        $totalAgendadas = $citas->count();
        $realizadas = $citas->where('estado', 'realizada')->count();
        $ausentes = $citas->where('estado', 'ausente')->count();
        $canceladas = $citas->where('estado', 'cancelada')->count();
        $noConcretadas = $ausentes + $canceladas;
        $pendientes = $citas->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '>=', $today)->count();
        $abiertas = $citas->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->where('fecha', '<', $today)->count();

        $tasaConcrecion = $totalAgendadas > 0 ? round(($realizadas / $totalAgendadas) * 100, 1) : 0;
        $tasaInasistencia = $totalAgendadas > 0 ? round(($noConcretadas / $totalAgendadas) * 100, 1) : 0;

        // Docentes del colegio
        $totalDocentesColegio = User::where('current_school_id', $schoolId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'docente')->where('roles.team_id', $schoolId))
            ->count();

        $docentesActivos = $citas->where('estado', 'realizada')->pluck('user_id')->filter()->unique()->count();
        $coberturaDocente = $totalDocentesColegio > 0 ? round(($docentesActivos / $totalDocentesColegio) * 100, 1) : 0;
        $promedioPorDocente = $docentesActivos > 0 ? round($realizadas / $docentesActivos, 1) : 0;
        $horasAcompEstimadas = round(($realizadas * 45) / 60, 0);

        return [
            'totalAgendadas' => $totalAgendadas,
            'realizadas' => $realizadas,
            'ausentes' => $ausentes,
            'canceladas' => $canceladas,
            'noConcretadas' => $noConcretadas,
            'pendientes' => $pendientes,
            'abiertas' => $abiertas,
            'tasaConcrecion' => $tasaConcrecion,
            'tasaInasistencia' => $tasaInasistencia,
            'totalDocentesColegio' => $totalDocentesColegio,
            'docentesActivos' => $docentesActivos,
            'coberturaDocente' => $coberturaDocente,
            'promedioPorDocente' => $promedioPorDocente,
            'horasAcompEstimadas' => $horasAcompEstimadas,
        ];
    }



    #[Computed]
    public function radiografiaProblematicas(): array
    {
        $citas = (clone $this->baseQuery)->get();
        $total = $citas->count();

        $conteo = [
            'Rendimiento Académico' => 0,
            'Conducta y Convivencia' => 0,
            'Asistencia y Puntualidad' => 0,
            'Asunto Personal / Familiar' => 0,
            'Evaluación Psicopedagógica' => 0,
            'Situación Médica' => 0,
            'Otro' => 0,
        ];

        $colores = [
            'Rendimiento Académico' => 'bg-blue-500 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-900',
            'Conducta y Convivencia' => 'bg-rose-500 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-900',
            'Asistencia y Puntualidad' => 'bg-amber-500 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-900',
            'Asunto Personal / Familiar' => 'bg-purple-500 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-900',
            'Evaluación Psicopedagógica' => 'bg-cyan-500 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-900',
            'Situación Médica' => 'bg-emerald-500 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-900',
            'Otro' => 'bg-zinc-400 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-800',
        ];

        foreach ($citas as $e) {
            $cat = Entrevista::normalizarCategoria($e->motivo);
            if (isset($conteo[$cat])) {
                $conteo[$cat]++;
            } else {
                $conteo['Otro']++;
            }
        }

        arsort($conteo);
        $topProblematica = key($conteo);
        $topProblematicaCant = current($conteo);
        $topProblematicaPct = $total > 0 ? round(($topProblematicaCant / $total) * 100, 1) : 0;

        $items = [];
        foreach ($conteo as $motivo => $cant) {
            $pct = $total > 0 ? round(($cant / $total) * 100, 1) : 0;
            $items[] = (object) [
                'motivo' => $motivo,
                'cantidad' => $cant,
                'porcentaje' => $pct,
                'colorClases' => $colores[$motivo] ?? 'bg-zinc-400 text-zinc-700 border-zinc-200',
            ];
        }

        return [
            'items' => $items,
            'topProblematica' => $topProblematica,
            'topProblematicaCant' => $topProblematicaCant,
            'topProblematicaPct' => $topProblematicaPct,
        ];
    }

    #[Computed]
    public function comparativaCiclos(): array
    {
        $citas = (clone $this->baseQuery)->get();

        $citasBasica = $citas->filter(fn ($e) => $e->estudiante?->curso?->modalidad === Modalidad::Basica || (is_string($e->estudiante?->curso?->modalidad) && $e->estudiante?->curso?->modalidad === 'basica'));
        $citasMedia = $citas->filter(fn ($e) => $e->estudiante?->curso?->modalidad === Modalidad::Media || (is_string($e->estudiante?->curso?->modalidad) && $e->estudiante?->curso?->modalidad === 'media'));

        $totalBasica = $citasBasica->count();
        $realizadasBasica = $citasBasica->where('estado', 'realizada')->count();
        $tasaBasica = $totalBasica > 0 ? round(($realizadasBasica / $totalBasica) * 100, 1) : 0;

        $totalMedia = $citasMedia->count();
        $realizadasMedia = $citasMedia->where('estado', 'realizada')->count();
        $tasaMedia = $totalMedia > 0 ? round(($realizadasMedia / $totalMedia) * 100, 1) : 0;

        // Causa Dominante Basica
        $motsB = [];
        foreach ($citasBasica as $c) {
            $norm = Entrevista::normalizarCategoria($c->motivo);
            $motsB[$norm] = ($motsB[$norm] ?? 0) + 1;
        }
        arsort($motsB);
        $topBasica = ! empty($motsB) ? key($motsB) : 'Sin registros';

        // Causa Dominante Media
        $motsM = [];
        foreach ($citasMedia as $c) {
            $norm = Entrevista::normalizarCategoria($c->motivo);
            $motsM[$norm] = ($motsM[$norm] ?? 0) + 1;
        }
        arsort($motsM);
        $topMedia = ! empty($motsM) ? key($motsM) : 'Sin registros';

        return [
            'basica' => [
                'total' => $totalBasica,
                'realizadas' => $realizadasBasica,
                'tasa' => $tasaBasica,
                'causaDominante' => $topBasica,
            ],
            'media' => [
                'total' => $totalMedia,
                'realizadas' => $realizadasMedia,
                'tasa' => $tasaMedia,
                'causaDominante' => $topMedia,
            ],
        ];
    }

    #[Computed]
    public function allCursosDemand(): \Illuminate\Support\Collection
    {
        $schoolId = auth()->user()->current_school_id;
        $citas = (clone $this->baseQuery)->get();
        $citasByCurso = $citas->groupBy(fn ($e) => $e->estudiante?->curso_id);

        $cursos = Curso::where('school_id', $schoolId)
            ->when($this->ciclo !== 'todos', fn ($q) => $q->where('modalidad', $this->ciclo))
            ->with('jefe')
            ->get();

        $list = [];
        foreach ($cursos as $curso) {
            $group = $citasByCurso->get($curso->id, collect());
            $mots = [];
            foreach ($group as $c) {
                $norm = Entrevista::normalizarCategoria($c->motivo);
                $mots[$norm] = ($mots[$norm] ?? 0) + 1;
            }
            arsort($mots);

            $list[] = (object) [
                'curso' => $curso,
                'total' => $group->count(),
                'realizadas' => $group->where('estado', 'realizada')->count(),
                'causaDominante' => ! empty($mots) ? key($mots) : 'Sin atenciones',
                'profesorJefe' => $curso->jefe ? $curso->jefe->nombreCompleto() : 'Sin Asignar',
            ];
        }

        return collect($list)->sortByDesc('total')->values();
    }

    #[Computed]
    public function totalCursosCount(): int
    {
        return $this->allCursosDemand->count();
    }

    #[Computed]
    public function totalCursosPages(): int
    {
        return max(1, (int) ceil($this->totalCursosCount / $this->cursosPerPage));
    }

    #[Computed]
    public function topCursos(): array
    {
        return $this->allCursosDemand
            ->slice(($this->cursosPage - 1) * $this->cursosPerPage, $this->cursosPerPage)
            ->values()
            ->all();
    }

    #[Computed]
    public function topDocentes(): array
    {
        $citas = (clone $this->baseQuery)->get();
        $docentesAgrupados = $citas->groupBy('user_id')->filter(fn ($g, $uid) => ! empty($uid));

        $top = [];
        foreach ($docentesAgrupados as $uid => $group) {
            $user = $group->first()->user;
            if (! $user || ! $user->hasRole(['docente', 'directivo', 'psicosocial'])) {
                continue;
            }

            $top[] = (object) [
                'user' => $user,
                'total' => $group->count(),
                'realizadas' => $group->where('estado', 'realizada')->count(),
                'ausentes' => $group->where('estado', 'ausente')->count(),
            ];
        }

        return collect($top)->sortByDesc('realizadas')->take(5)->values()->all();
    }
};
?>

<div class="max-w-7xl mx-auto w-full pb-16 space-y-8">

    {{-- Encabezado Estándar Institucional --}}
    <x-header 
        :titulo="__('Reporte Entrevistas')" 
        :subtitulo="__('Monitoreo macro de entrevistas, acompañamiento familiar y tendencias del establecimiento.')" 
        icono="presentation-chart-line">
        
        <x-slot:badge>
            <flux:badge color="purple" size="sm" class="font-bold uppercase tracking-wider">
                {{ __('Control de Gestión') }}
            </flux:badge>
        </x-slot:badge>

        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-path" wire:click="$refresh" title="Actualizar datos">
                {{ __('Actualizar') }}
            </flux:button>
            <flux:button 
                variant="primary" 
                icon="printer" 
                href="{{ route('gerencia.imprimir.resumen', ['periodo' => $periodo, 'ciclo' => $ciclo]) }}" 
                target="_blank"
                class="bg-[#00376e] hover:bg-blue-800 text-white shadow-sm font-semibold">
                {{ __('Imprimir Informe PDF') }}
            </flux:button>
        </div>
    </x-header>

    {{-- Filtros Ejecutivos (Período y Ciclo) --}}
    <flux:card class="p-4 bg-zinc-50/80 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/70 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            
            {{-- Selector de Período --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                    Período:
                </span>
                <div class="inline-flex rounded-xl p-1 bg-zinc-200/70 dark:bg-zinc-700/60 gap-1 text-xs">
                    @php
                        $periodos = [
                            'ano_actual' => 'Año Escolar 2026',
                            'primer_semestre' => '1° Semestre',
                            'segundo_semestre' => '2° Semestre',
                            'mes_actual' => 'Mes Actual',
                            'todo' => 'Historial Completo',
                        ];
                    @endphp
                    @foreach($periodos as $key => $label)
                        <button 
                            type="button" 
                            wire:click="setPeriodo('{{ $key }}')"
                            class="px-3 py-1.5 rounded-lg font-semibold transition-all select-none
                                {{ $periodo === $key ? 'bg-white dark:bg-zinc-800 text-[#00376e] dark:text-blue-400 shadow-sm font-bold' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Selector de Ciclo / Modalidad --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                    Ciclo:
                </span>
                <div class="inline-flex rounded-xl p-1 bg-zinc-200/70 dark:bg-zinc-700/60 gap-1 text-xs">
                    <button 
                        type="button" 
                        wire:click="setCiclo('todos')"
                        class="px-3 py-1.5 rounded-lg font-semibold transition-all select-none
                            {{ $ciclo === 'todos' ? 'bg-white dark:bg-zinc-800 text-[#00376e] dark:text-blue-400 shadow-sm font-bold' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">
                        Institucional
                    </button>
                    <button 
                        type="button" 
                        wire:click="setCiclo('basica')"
                        class="px-3 py-1.5 rounded-lg font-semibold transition-all select-none
                            {{ $ciclo === 'basica' ? 'bg-white dark:bg-zinc-800 text-[#00376e] dark:text-blue-400 shadow-sm font-bold' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">
                        Básica (1°-8°)
                    </button>
                    <button 
                        type="button" 
                        wire:click="setCiclo('media')"
                        class="px-3 py-1.5 rounded-lg font-semibold transition-all select-none
                            {{ $ciclo === 'media' ? 'bg-white dark:bg-zinc-800 text-[#00376e] dark:text-blue-400 shadow-sm font-bold' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">
                        Media (1°-4°)
                    </button>
                </div>
            </div>

        </div>
    </flux:card>

    {{-- Indicador de Carga --}}
    <div wire:loading.flex class="items-center justify-center gap-2 p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-xl text-blue-700 dark:text-blue-300 text-sm font-semibold">
        <flux:icon.arrow-path class="size-4 animate-spin" />
        <span>{{ __('Actualizando métricas consolidadas para Dirección & Rectoría...') }}</span>
    </div>

    {{-- Macrométricas (KPI Cards) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        {{-- KPI 1: Familias Atendidas --}}
        <flux:card class="border-t-4 border-t-blue-600 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="flex justify-between items-start mb-3">
                <div class="p-2.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-xl">
                    <flux:icon.user-group class="size-6" />
                </div>
                <span class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">
                    Familias
                </span>
            </div>
            <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                Atendidas (Realizadas)
            </p>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-4xl font-extrabold text-zinc-900 dark:text-zinc-100 font-mono tracking-tight">
                    {{ $this->kpis['realizadas'] }}
                </span>
                <span class="text-sm font-medium text-zinc-500">
                    / {{ $this->kpis['totalAgendadas'] }} citadas
                </span>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 flex items-center gap-1 font-medium">
                <span>⏱️ ~{{ $this->kpis['horasAcompEstimadas'] }} hrs de acompañamiento</span>
            </p>
        </flux:card>

        {{-- KPI 2: Tasa de Concreción & Efectividad --}}
        <flux:card class="border-t-4 border-t-emerald-500 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="flex justify-between items-start mb-3">
                <div class="p-2.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-xl">
                    <flux:icon.check-circle class="size-6" />
                </div>
                <span class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">
                    Efectividad
                </span>
            </div>
            <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                Tasa de Concreción
            </p>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-4xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono tracking-tight">
                    {{ $this->kpis['tasaConcrecion'] }}%
                </span>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 font-medium">
                {{ $this->kpis['realizadas'] }} actas formalizadas exitosamente
            </p>
        </flux:card>

        {{-- KPI 3: Cobertura Docente --}}
        <flux:card class="border-t-4 border-t-purple-500 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="flex justify-between items-start mb-3">
                <div class="p-2.5 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-xl">
                    <flux:icon.briefcase class="size-6" />
                </div>
                <span class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">
                    Cuerpo Docente
                </span>
            </div>
            <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                Cobertura Docente
            </p>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-4xl font-extrabold text-purple-600 dark:text-purple-400 font-mono tracking-tight">
                    {{ $this->kpis['coberturaDocente'] }}%
                </span>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 font-medium">
                {{ $this->kpis['docentesActivos'] }} de {{ $this->kpis['totalDocentesColegio'] }} docentes activos • ~{{ $this->kpis['promedioPorDocente'] }} citas/docente
            </p>
        </flux:card>

        {{-- KPI 4: Tasa de Desenganche / Inasistencia --}}
        <flux:card class="border-t-4 border-t-amber-500 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="flex justify-between items-start mb-3">
                <div class="p-2.5 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-xl">
                    <flux:icon.exclamation-triangle class="size-6" />
                </div>
                <span class="text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest">
                    No Concretadas
                </span>
            </div>
            <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                Inasistencias y Canceladas
            </p>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-4xl font-extrabold text-amber-600 dark:text-amber-400 font-mono tracking-tight">
                    {{ $this->kpis['tasaInasistencia'] }}%
                </span>
                <span class="text-sm font-medium text-zinc-500">
                    ({{ $this->kpis['noConcretadas'] }})
                </span>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 font-medium">
                {{ $this->kpis['ausentes'] }} ausencias apoderado • {{ $this->kpis['canceladas'] }} canceladas
            </p>
        </flux:card>

    </div>

    {{-- Radiografía de Problemáticas Escolares --}}
    <flux:card class="p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <flux:icon.funnel class="size-5 text-[#00376e] dark:text-blue-400" />
                    Radiografía de Problemáticas Institucionales
                </h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    Distribución de motivos de citación para orientar la asignación de recursos y apoyos.
                </p>
            </div>
            <div class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-xl text-xs font-bold text-blue-700 dark:text-blue-300">
                Foco Principal: {{ $this->radiografiaProblematicas['topProblematica'] }} ({{ $this->radiografiaProblematicas['topProblematicaPct'] }}%)
            </div>
        </div>

        {{-- Barra de distribución visual --}}
        <div class="h-4 w-full rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex shadow-inner mb-6">
            @foreach($this->radiografiaProblematicas['items'] as $item)
                @if($item->porcentaje > 0)
                    <div 
                        class="{{ explode(' ', $item->colorClases)[0] }} h-full transition-all duration-700" 
                        style="width: {{ $item->porcentaje }}%;"
                        title="{{ $item->motivo }}: {{ $item->cantidad }} ({{ $item->porcentaje }}%)">
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Cuadrícula de Categorías --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($this->radiografiaProblematicas['items'] as $item)
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700/80 bg-zinc-50/50 dark:bg-zinc-800/30 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full {{ explode(' ', $item->colorClases)[0] }}"></span>
                            <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">
                                {{ $item->motivo }}
                            </span>
                        </div>
                        <span class="text-[11px] text-zinc-500 mt-1 block">
                            {{ $item->cantidad }} citaciones registradas
                        </span>
                    </div>
                    <div class="text-right font-mono font-extrabold text-sm text-zinc-900 dark:text-zinc-100">
                        {{ $item->porcentaje }}%
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Enlace a reporte completo de problemáticas por curso --}}
        <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700/80 mt-6 flex justify-end">
            <flux:button 
                variant="ghost" 
                icon-trailing="arrow-right" 
                href="{{ route('reportes.index', ['tipoReporte' => 'problematicas_curso']) }}" 
                wire:navigate
                class="text-xs font-bold text-[#00376e] dark:text-blue-400 hover:underline">
                {{ __('Ver matriz completa de problemáticas por curso') }}
            </flux:button>
        </div>
    </flux:card>

    {{-- Comparativa por Ciclos (Básica vs. Media) y Top Cursos --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {{-- Comparativa Ciclos (Col 5) --}}
        <flux:card class="lg:col-span-5 p-6 flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2 mb-1">
                    <flux:icon.academic-cap class="size-5 text-[#00376e] dark:text-blue-400" />
                    Comparativa por Ciclos
                </h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-6">
                    Comportamiento de citaciones en Enseñanza Básica vs. Media.
                </p>

                <div class="space-y-4">
                    {{-- Básica --}}
                    <div class="p-4 rounded-2xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="text-xs font-extrabold text-blue-900 dark:text-blue-200 uppercase tracking-wider">
                                    Enseñanza Básica (1°-8°)
                                </span>
                                <div class="text-2xl font-black text-blue-950 dark:text-blue-100 font-mono mt-0.5">
                                    {{ $this->comparativaCiclos['basica']['realizadas'] }} <span class="text-sm font-normal text-blue-700 dark:text-blue-300">/ {{ $this->comparativaCiclos['basica']['total'] }}</span>
                                </div>
                            </div>
                            <flux:badge color="blue" size="sm" class="font-bold">
                                {{ $this->comparativaCiclos['basica']['tasa'] }}% éxito
                            </flux:badge>
                        </div>
                        <p class="text-xs text-blue-800 dark:text-blue-300">
                            Foco prioritario: <strong>{{ $this->comparativaCiclos['basica']['causaDominante'] }}</strong>
                        </p>
                    </div>

                    {{-- Media --}}
                    <div class="p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="text-xs font-extrabold text-indigo-900 dark:text-indigo-200 uppercase tracking-wider">
                                    Enseñanza Media (1°-4°)
                                </span>
                                <div class="text-2xl font-black text-indigo-950 dark:text-indigo-100 font-mono mt-0.5">
                                    {{ $this->comparativaCiclos['media']['realizadas'] }} <span class="text-sm font-normal text-indigo-700 dark:text-indigo-300">/ {{ $this->comparativaCiclos['media']['total'] }}</span>
                                </div>
                            </div>
                            <flux:badge color="indigo" size="sm" class="font-bold">
                                {{ $this->comparativaCiclos['media']['tasa'] }}% éxito
                            </flux:badge>
                        </div>
                        <p class="text-xs text-indigo-800 dark:text-indigo-300">
                            Foco prioritario: <strong>{{ $this->comparativaCiclos['media']['causaDominante'] }}</strong>
                        </p>
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- Top Cursos con Mayor Demanda (Col 7) --}}
        <flux:card class="lg:col-span-7 p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            <flux:icon.table-cells class="size-5 text-[#00376e] dark:text-blue-400" />
                            Cursos con Mayor Demanda de Atención
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Cursos que concentran el mayor volumen de entrevistas e intervenciones.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 uppercase tracking-wider font-bold">
                                <th class="py-2.5 px-3">Curso</th>
                                <th class="py-2.5 px-3">Profesor Jefe</th>
                                <th class="py-2.5 px-3 text-center">Citadas</th>
                                <th class="py-2.5 px-3 text-center">Realizadas</th>
                                <th class="py-2.5 px-3">Causa Principal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60 font-medium">
                            @forelse($this->topCursos as $item)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="py-3 px-3 font-bold text-zinc-900 dark:text-zinc-100 font-mono">
                                        {{ $item->curso->nombreAbreviado() }}
                                    </td>
                                    <td class="py-3 px-3 text-zinc-600 dark:text-zinc-300">
                                        {{ $item->profesorJefe }}
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $item->total }}
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ $item->realizadas }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <flux:badge size="xs" color="zinc" class="font-semibold">
                                            {{ $item->causaDominante }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-zinc-400">
                                        No hay datos disponibles en el período seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Paginación de Cursos --}}
            @if($this->totalCursosCount > 0)
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700/80 mt-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <span class="text-zinc-500 dark:text-zinc-400 font-medium">
                        Mostrando {{ ($cursosPage - 1) * $cursosPerPage + 1 }} - {{ min($cursosPage * $cursosPerPage, $this->totalCursosCount) }} de {{ $this->totalCursosCount }} cursos
                    </span>

                    @if($this->totalCursosPages > 1)
                        <div class="flex items-center gap-1.5">
                            <flux:button 
                                size="xs" 
                                variant="subtle" 
                                wire:click="prevCursosPage" 
                                :disabled="$cursosPage <= 1"
                                icon="chevron-left">
                                Anterior
                            </flux:button>

                            <span class="px-2 py-1 text-zinc-600 dark:text-zinc-300 font-semibold">
                                {{ $cursosPage }} / {{ $this->totalCursosPages }}
                            </span>

                            <flux:button 
                                size="xs" 
                                variant="subtle" 
                                wire:click="nextCursosPage" 
                                :disabled="$cursosPage >= $this->totalCursosPages"
                                icon-trailing="chevron-right">
                                Siguiente
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endif
        </flux:card>

    </div>

    {{-- Liderazgo Docente (Top Profesores con Mayor Acompañamiento) --}}
    <flux:card class="p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <flux:icon.user-plus class="size-5 text-[#00376e] dark:text-blue-400" />
                    Liderazgo y Vinculación Docente
                </h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    Docentes con mayor volumen de entrevistas realizadas y compromiso con apoderados.
                </p>
            </div>
            <flux:button 
                variant="subtle" 
                size="sm" 
                href="{{ route('reportes.index', ['tipoReporte' => 'entrevistas_profesor']) }}" 
                wire:navigate
                icon-trailing="arrow-right">
                {{ __('Ver reporte de Entrevistas por profesor') }}
            </flux:button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            @forelse($this->topDocentes as $i => $item)
                @php
                    $efect = $item->total > 0 ? round(($item->realizadas / $item->total) * 100) : 0;
                @endphp
                <div class="p-4 rounded-2xl border border-zinc-200 dark:border-zinc-700/80 bg-zinc-50/60 dark:bg-zinc-800/30 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-black text-zinc-400 uppercase tracking-widest">
                                #{{ $i + 1 }}
                            </span>
                            <flux:badge size="xs" :color="$efect >= 70 ? 'emerald' : 'amber'" class="font-bold">
                                {{ $efect }}% éxito
                            </flux:badge>
                        </div>
                        <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100 leading-tight">
                            {{ $item->user->nombreCompleto() }}
                        </div>
                        <div class="text-xs text-zinc-500 font-mono mt-0.5">
                            {{ $item->user->email }}
                        </div>
                    </div>

                    <div class="flex items-baseline justify-between pt-4 mt-4 border-t border-zinc-200 dark:border-zinc-700/60">
                        <span class="text-xs text-zinc-500">Realizadas:</span>
                        <span class="text-lg font-black text-emerald-600 dark:text-emerald-400 font-mono">
                            {{ $item->realizadas }} <span class="text-xs font-normal text-zinc-400">/ {{ $item->total }}</span>
                        </span>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-zinc-400">
                    No se registran datos para los filtros aplicados.
                </div>
            @endforelse
        </div>
    </flux:card>

</div>
