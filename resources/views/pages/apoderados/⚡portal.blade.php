<?php

use App\Models\Atraso;
use App\Models\Entrevista;
use App\Models\Estudiante;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Portal de Apoderados')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'atrasos'; // 'atrasos' | 'entrevistas'

    #[Url]
    public ?int $selectedEstudianteId = null;

    #[Url]
    public string $filtroMes = ''; // '', '1', '2', etc.

    #[Url]
    public string $filtroEstadoAtraso = ''; // '', 'justificado', 'injustificado'

    public function mount(): void
    {
        $user = auth()->user();

        // Validar que el usuario tenga rol de estudiante o roles de administración
        if (! $user->hasRole('estudiante') && ! $user->hasRole(['superadmin', 'administrador'])) {
            abort(403, 'No tienes permiso para acceder al Portal de Apoderados.');
        }

        $estudiantes = $this->estudiantesAsociados;
        if ($estudiantes->isNotEmpty()) {
            if (! $this->selectedEstudianteId || ! $estudiantes->contains('id', $this->selectedEstudianteId)) {
                $this->selectedEstudianteId = $estudiantes->first()->id;
            }
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['atrasos', 'entrevistas']) ? $tab : 'atrasos';
        $this->resetPage('atrasos_page');
        $this->resetPage('entrevistas_page');
    }

    public function seleccionarEstudiante(int $estudianteId): void
    {
        if ($this->estudiantesAsociados->contains('id', $estudianteId)) {
            $this->selectedEstudianteId = $estudianteId;
            $this->resetPage('atrasos_page');
            $this->resetPage('entrevistas_page');
        }
    }

    public function clearFiltrosAtrasos(): void
    {
        $this->filtroMes = '';
        $this->filtroEstadoAtraso = '';
        $this->resetPage('atrasos_page');
    }

    #[Computed]
    public function estudiantesAsociados(): Collection
    {
        $user = auth()->user();
        $schoolId = $user->current_school_id;

        // Estudiante vinculado a este usuario
        $estudiantePrincipal = $user->estudiante;

        // Para administradores que visitan la vista con fines de prueba o soporte
        if (! $estudiantePrincipal && $user->hasRole(['superadmin', 'administrador'])) {
            if ($this->selectedEstudianteId) {
                $estudiantePrincipal = Estudiante::where('school_id', $schoolId)->find($this->selectedEstudianteId);
            }
            if (! $estudiantePrincipal) {
                $estudiantePrincipal = Estudiante::where('school_id', $schoolId)->activos()->first();
            }
        }

        if (! $estudiantePrincipal) {
            return collect();
        }

        // Si tiene RUT de apoderado registrado, buscar a todos los hermanos/pupilos en el mismo colegio
        if (! empty($estudiantePrincipal->apoderado_rut_numero)) {
            $hermanos = Estudiante::where('school_id', $schoolId)
                ->where('apoderado_rut_numero', $estudiantePrincipal->apoderado_rut_numero)
                ->activos()
                ->with(['curso.jefe', 'user'])
                ->orderBy('nombres_csv')
                ->get();

            if ($hermanos->isNotEmpty()) {
                return $hermanos;
            }
        }

        return collect([$estudiantePrincipal->loadMissing(['curso.jefe', 'user'])]);
    }

    #[Computed]
    public function selectedEstudiante(): ?Estudiante
    {
        if (! $this->selectedEstudianteId) {
            return $this->estudiantesAsociados->first();
        }

        return $this->estudiantesAsociados->firstWhere('id', $this->selectedEstudianteId)
            ?? $this->estudiantesAsociados->first();
    }

    #[Computed]
    public function metricasAtrasos(): array
    {
        $estudiante = $this->selectedEstudiante;
        if (! $estudiante) {
            return [
                'mes_actual' => 0,
                'ano_actual' => 0,
                'justificados' => 0,
                'injustificados' => 0,
            ];
        }

        $now = now('America/Santiago');

        $base = Atraso::where('estudiante_id', $estudiante->id)
            ->whereYear('fecha', $now->year);

        $mesActual = (clone $base)->whereMonth('fecha', $now->month)->count();
        $anoActual = (clone $base)->count();
        $justificados = (clone $base)->where('estado', 'justificado')->count();
        $injustificados = (clone $base)->where('estado', 'injustificado')->count();

        return [
            'mes_actual' => $mesActual,
            'ano_actual' => $anoActual,
            'justificados' => $justificados,
            'injustificados' => $injustificados,
        ];
    }

    #[Computed]
    public function metricasEntrevistas(): array
    {
        $estudiante = $this->selectedEstudiante;
        if (! $estudiante) {
            return [
                'total' => 0,
                'realizadas' => 0,
                'pendientes' => 0,
            ];
        }

        $base = Entrevista::where('estudiante_id', $estudiante->id)
            ->where('es_confidencial', false);

        $total = (clone $base)->count();
        $realizadas = (clone $base)->where('estado', 'realizada')->count();
        $pendientes = (clone $base)->whereIn('estado', ['pendiente', 'abierta', 'ingresada'])->count();

        return [
            'total' => $total,
            'realizadas' => $realizadas,
            'pendientes' => $pendientes,
        ];
    }

    public function atrasos()
    {
        $estudiante = $this->selectedEstudiante;
        if (! $estudiante) {
            return null;
        }

        $query = Atraso::where('estudiante_id', $estudiante->id);

        if (! empty($this->filtroMes)) {
            $query->whereMonth('fecha', (int) $this->filtroMes);
        }

        if (! empty($this->filtroEstadoAtraso)) {
            $query->where('estado', $this->filtroEstadoAtraso);
        }

        return $query->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->paginate(10, pageName: 'atrasos_page');
    }

    public function entrevistas()
    {
        $estudiante = $this->selectedEstudiante;
        if (! $estudiante) {
            return null;
        }

        // Regla: no exponer texto de bitácora ni notas confidenciales; solo información general
        return Entrevista::where('estudiante_id', $estudiante->id)
            ->where('es_confidencial', false)
            ->with(['user', 'bitacora'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->paginate(10, pageName: 'entrevistas_page');
    }
}; ?>

<div class="max-w-7xl mx-auto w-full pb-16 space-y-6">

    {{-- Encabezado Estándar del Sistema --}}
    <x-header 
        titulo="Portal de Apoderados" 
        subtitulo="Consulta de asistencia, atrasos y entrevistas de su pupilo." 
        icono="academic-cap"
    >
        @if($this->estudiantesAsociados->count() > 1)
            <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 text-xs font-semibold text-blue-700 dark:text-blue-300">
                <flux:icon name="users" class="size-4 text-blue-600" />
                <span>{{ $this->estudiantesAsociados->count() }} Estudiantes Vinculados</span>
            </div>
        @endif
    </x-header>

    @if(!$this->selectedEstudiante)
        {{-- Caso borde: Sin estudiante asociado --}}
        <flux:card class="p-8 text-center bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800">
            <div class="flex flex-col items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 flex items-center justify-center">
                    <flux:icon name="exclamation-triangle" class="size-6" />
                </div>
                <flux:heading size="lg" class="text-amber-800 dark:text-amber-300">
                    No se encontró un estudiante vinculado
                </flux:heading>
                <flux:text class="text-amber-700 dark:text-amber-400 max-w-md">
                    Tu cuenta institucional no se encuentra actualmente asociada a una ficha activa de estudiante. Si consideras que es un error, por favor contacta a secretaría o soporte del colegio.
                </flux:text>
            </div>
        </flux:card>
    @else

        {{-- Selector de Estudiante (si el apoderado tiene más de 1 hijo) --}}
        @if($this->estudiantesAsociados->count() > 1)
            <div class="bg-zinc-50 dark:bg-zinc-900/60 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 shadow-2xs">
                <div class="flex items-center justify-between gap-4 mb-3">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="users" class="size-4 text-[#00376e] dark:text-blue-400" />
                        <span>Selecciona el Estudiante a Consultar:</span>
                    </div>
                    <span class="text-[11px] text-zinc-400 font-medium">Familia / Pupilos asociados</span>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    @foreach($this->estudiantesAsociados as $hermano)
                        @php
                            $esActivo = $hermano->id === $this->selectedEstudiante->id;
                        @endphp
                        <button 
                            type="button" 
                            wire:click="seleccionarEstudiante({{ $hermano->id }})"
                            @class([
                                'flex items-center gap-3 px-4 py-2.5 rounded-xl border text-left cursor-pointer transition-all duration-150',
                                'bg-[#00376e] text-white border-[#00376e] shadow-sm ring-2 ring-blue-500/20' => $esActivo,
                                'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 border-zinc-200 dark:border-zinc-700 hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-zinc-700/50' => ! $esActivo,
                            ])
                        >
                            {{-- Avatar / Iniciales --}}
                            <div @class([
                                'w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0',
                                'bg-white/20 text-white' => $esActivo,
                                'bg-[#00376e]/10 text-[#00376e] dark:bg-blue-500/20 dark:text-blue-300' => ! $esActivo,
                            ])>
                                {{ $hermano->initials() }}
                            </div>

                            <div>
                                <p @class([
                                    'text-xs font-bold leading-tight',
                                    'text-white' => $esActivo,
                                    'text-zinc-900 dark:text-zinc-100' => ! $esActivo,
                                ])>
                                    {{ $hermano->nombreCompleto() }}
                                </p>
                                <p @class([
                                    'text-[11px] mt-0.5 font-medium',
                                    'text-blue-100' => $esActivo,
                                    'text-zinc-500 dark:text-zinc-400' => ! $esActivo,
                                ])>
                                    {{ $hermano->curso?->nombre_fc ?? 'Sin curso' }}
                                </p>
                            </div>

                            @if($esActivo)
                                <flux:icon name="check-circle" class="size-4 ml-1 text-emerald-300 shrink-0" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tarjeta de Resumen del Estudiante Seleccionado --}}
        <flux:card class="border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-2xs">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                
                {{-- Identificación del Estudiante --}}
                <div class="flex items-start sm:items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#00376e] to-blue-700 text-white flex items-center justify-center font-black text-lg shadow-sm shrink-0">
                        {{ $this->selectedEstudiante->initials() }}
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg" class="font-extrabold text-zinc-900 dark:text-zinc-100">
                                {{ $this->selectedEstudiante->nombreCompleto() }}
                            </flux:heading>
                            <flux:badge color="blue" size="sm">
                                {{ $this->selectedEstudiante->curso?->nombre_fc ?? 'Sin curso asignado' }}
                            </flux:badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-y-1 gap-x-4 mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                            @if($this->selectedEstudiante->rutCompleto())
                                <span class="flex items-center gap-1 font-mono">
                                    <flux:icon name="identification" class="size-3.5 text-zinc-400" />
                                    RUT: {{ $this->selectedEstudiante->rutCompleto() }}
                                </span>
                            @endif
                            <span class="flex items-center gap-1">
                                <flux:icon name="envelope" class="size-3.5 text-zinc-400" />
                                {{ $this->selectedEstudiante->email }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Datos del Profesor Jefe y Apoderado --}}
                <div class="flex flex-col sm:flex-row gap-3 pt-4 lg:pt-0 border-t lg:border-t-0 border-zinc-100 dark:border-zinc-800 text-xs">
                    {{-- Profesor Jefe --}}
                    @if($this->selectedEstudiante->curso?->jefe)
                        <div class="bg-zinc-50 dark:bg-zinc-800/60 rounded-xl px-3.5 py-2.5 border border-zinc-200/80 dark:border-zinc-700/60 min-w-[210px]">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 mb-0.5">Profesor/a Jefe</p>
                            <p class="font-bold text-zinc-800 dark:text-zinc-200">
                                {{ $this->selectedEstudiante->curso->jefe->nombreCompleto() }}
                            </p>
                            <p class="text-[11px] text-zinc-500 truncate mt-0.5">
                                {{ $this->selectedEstudiante->curso->jefe->email }}
                            </p>
                        </div>
                    @endif

                    {{-- Apoderado Titular --}}
                    <div class="bg-zinc-50 dark:bg-zinc-800/60 rounded-xl px-3.5 py-2.5 border border-zinc-200/80 dark:border-zinc-700/60 min-w-[210px]">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 mb-0.5">Apoderado Titular</p>
                        <p class="font-bold text-zinc-800 dark:text-zinc-200">
                            {{ $this->selectedEstudiante->apoderadoNombreCompleto() ?: 'No registrado' }}
                        </p>
                        <p class="text-[11px] text-zinc-500 mt-0.5">
                            {{ $this->selectedEstudiante->apoderado_parentesco ? ucfirst($this->selectedEstudiante->apoderado_parentesco) : 'Apoderado' }}
                            @if($this->selectedEstudiante->apoderadoRutCompleto())
                                · <span class="font-mono">{{ $this->selectedEstudiante->apoderadoRutCompleto() }}</span>
                            @endif
                        </p>
                    </div>
                </div>

            </div>
        </flux:card>

        {{-- Métricas / Tarjetas Resumen (KPIs) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            {{-- Atrasos Mes Actual --}}
            <flux:card class="relative overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Atrasos este mes
                    </span>
                    <div class="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-[#00376e] dark:text-blue-400">
                        <flux:icon name="clock" class="size-4" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100">
                        {{ $this->metricasAtrasos['mes_actual'] }}
                    </span>
                    <span class="text-xs text-zinc-500">en {{ now('America/Santiago')->translatedFormat('F') }}</span>
                </div>
                @if($this->metricasAtrasos['mes_actual'] >= 3)
                    <div class="mt-2 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-semibold">
                        <flux:icon name="exclamation-circle" class="size-3.5" />
                        <span>Reincidencia (3 o más atrasos)</span>
                    </div>
                @else
                    <div class="mt-2 flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                        <flux:icon name="check-circle" class="size-3.5" />
                        <span>Bajo el límite de alerta</span>
                    </div>
                @endif
            </flux:card>

            {{-- Justificados vs Injustificados Año --}}
            <flux:card class="border border-zinc-200 dark:border-zinc-800 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Total año {{ now('America/Santiago')->year }}
                    </span>
                    <div class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                        <flux:icon name="chart-bar" class="size-4" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-3">
                    <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100">
                        {{ $this->metricasAtrasos['ano_actual'] }}
                    </span>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-emerald-600 font-semibold">{{ $this->metricasAtrasos['justificados'] }} just.</span>
                        <span class="text-zinc-300 dark:text-zinc-700">|</span>
                        <span class="text-rose-600 font-semibold">{{ $this->metricasAtrasos['injustificados'] }} injust.</span>
                    </div>
                </div>
                <p class="text-[11px] text-zinc-400 mt-2">Histórico del periodo académico en curso</p>
            </flux:card>

            {{-- Entrevistas Realizadas --}}
            <flux:card class="border border-zinc-200 dark:border-zinc-800 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Entrevistas Realizadas
                    </span>
                    <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                        <flux:icon name="check-badge" class="size-4" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100">
                        {{ $this->metricasEntrevistas['realizadas'] }}
                    </span>
                    <span class="text-xs text-zinc-500">atenciones sostenidas</span>
                </div>
                <p class="text-[11px] text-zinc-400 mt-2">Docentes y profesionales del colegio</p>
            </flux:card>

            {{-- Citaciones Pendientes --}}
            <flux:card class="border border-zinc-200 dark:border-zinc-800 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Citaciones Pendientes
                    </span>
                    <div class="p-2 rounded-lg bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">
                        <flux:icon name="calendar-days" class="size-4" />
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100">
                        {{ $this->metricasEntrevistas['pendientes'] }}
                    </span>
                    <span class="text-xs text-zinc-500">por atender o confirmar</span>
                </div>
                <p class="text-[11px] text-zinc-400 mt-2">Revisa la pestaña de entrevistas</p>
            </flux:card>

        </div>

        {{-- Selector de Pestañas (Tabs) --}}
        <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-px">
            <button 
                type="button" 
                wire:click="setTab('atrasos')"
                @class([
                    'flex items-center gap-2 px-4 py-3 text-sm font-bold border-b-2 transition-all cursor-pointer',
                    'border-[#00376e] text-[#00376e] dark:border-blue-400 dark:text-blue-400' => $tab === 'atrasos',
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== 'atrasos',
                ])
            >
                <flux:icon name="clock" class="size-4" />
                <span>Historial de Atrasos</span>
                <span class="px-2 py-0.5 text-xs rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold">
                    {{ $this->metricasAtrasos['ano_actual'] }}
                </span>
            </button>

            <button 
                type="button" 
                wire:click="setTab('entrevistas')"
                @class([
                    'flex items-center gap-2 px-4 py-3 text-sm font-bold border-b-2 transition-all cursor-pointer',
                    'border-[#00376e] text-[#00376e] dark:border-blue-400 dark:text-blue-400' => $tab === 'entrevistas',
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== 'entrevistas',
                ])
            >
                <flux:icon name="calendar-days" class="size-4" />
                <span>Registro de Entrevistas</span>
                <span class="px-2 py-0.5 text-xs rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold">
                    {{ $this->metricasEntrevistas['total'] }}
                </span>
            </button>
        </div>

        {{-- CONTENIDO PESTAÑA 1: ATRASOS --}}
        @if($tab === 'atrasos')
            @php $listaAtrasos = $this->atrasos(); @endphp

            <flux:card class="border border-zinc-200 dark:border-zinc-800 p-0 overflow-hidden shadow-2xs">
                
                {{-- Barra de Filtros de Atrasos --}}
                <div class="p-4 bg-zinc-50/70 dark:bg-zinc-900/70 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <flux:icon name="funnel" class="size-4 text-zinc-400" />
                        <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">Filtrar atrasos:</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-end">
                        {{-- Filtro por Mes --}}
                        <select 
                            wire:model.live="filtroMes"
                            class="text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Todos los meses</option>
                            <option value="3">Marzo</option>
                            <option value="4">Abril</option>
                            <option value="5">Mayo</option>
                            <option value="6">Junio</option>
                            <option value="7">Julio</option>
                            <option value="8">Agosto</option>
                            <option value="9">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>
                        </select>

                        {{-- Filtro por Estado --}}
                        <select 
                            wire:model.live="filtroEstadoAtraso"
                            class="text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Todos los estados</option>
                            <option value="justificado">Justificados</option>
                            <option value="injustificado">Injustificados</option>
                        </select>

                        @if($filtroMes || $filtroEstadoAtraso)
                            <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="clearFiltrosAtrasos">
                                Limpiar
                            </flux:button>
                        @endif
                    </div>
                </div>

                {{-- Tabla de Atrasos --}}
                @if($listaAtrasos && $listaAtrasos->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-[10px] font-bold border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="px-4 py-3">Hora Ingreso</th>
                                    <th class="px-4 py-3">Jornada</th>
                                    <th class="px-4 py-3">Min. Atraso</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3">Motivo</th>
                                    <th class="px-4 py-3">Observaciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @foreach($listaAtrasos as $atraso)
                                    <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                        {{-- Fecha --}}
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ \Carbon\Carbon::parse($atraso->fecha)->format('d/m/Y') }}
                                            <span class="text-[11px] text-zinc-400 block font-normal capitalize">
                                                {{ \Carbon\Carbon::parse($atraso->fecha)->translatedFormat('l') }}
                                            </span>
                                        </td>

                                        {{-- Hora --}}
                                        <td class="px-4 py-3 whitespace-nowrap font-mono text-zinc-700 dark:text-zinc-300">
                                            {{ $atraso->hora ? \Carbon\Carbon::parse($atraso->hora)->format('H:i') : '--:--' }} hrs
                                        </td>

                                        {{-- Jornada --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 capitalize">
                                                {{ $atraso->jornada === 'tarde' ? 'Tarde' : 'Mañana' }}
                                            </span>
                                        </td>

                                        {{-- Minutos --}}
                                        <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-zinc-700 dark:text-zinc-300">
                                            {{ $atraso->minutos_atraso ?? 0 }} min
                                        </td>

                                        {{-- Estado --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($atraso->estado === 'justificado')
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                    <flux:icon name="check-circle" class="size-3" />
                                                    Justificado
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                                    <flux:icon name="x-circle" class="size-3" />
                                                    Injustificado
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Motivo --}}
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                            {{ $atraso->motivo ?: 'Sin motivo registrado' }}
                                        </td>

                                        {{-- Observaciones --}}
                                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 max-w-xs truncate" title="{{ $atraso->observaciones }}">
                                            {{ $atraso->observaciones ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginación --}}
                    @if($listaAtrasos->hasPages())
                        <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                            {{ $listaAtrasos->links() }}
                        </div>
                    @endif
                @else
                    {{-- Estado vacío Atrasos --}}
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-3">
                            <flux:icon name="check-badge" class="size-6" />
                        </div>
                        <flux:heading size="md" class="text-zinc-800 dark:text-zinc-200">
                            No se registran atrasos
                        </flux:heading>
                        <flux:text class="text-zinc-500 text-xs mt-1">
                            El estudiante no cuenta con atrasos registrados para los criterios seleccionados.
                        </flux:text>
                    </div>
                @endif
            </flux:card>
        @endif

        {{-- CONTENIDO PESTAÑA 2: ENTREVISTAS --}}
        @if($tab === 'entrevistas')
            @php $listaEntrevistas = $this->entrevistas(); @endphp

            <flux:card class="border border-zinc-200 dark:border-zinc-800 p-0 overflow-hidden shadow-2xs">
                
                <div class="p-4 bg-zinc-50/70 dark:bg-zinc-900/70 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                    <div>
                        <flux:heading size="sm" class="text-zinc-800 dark:text-zinc-200 font-bold">
                            Citaciones y Entrevistas Académicas
                        </flux:heading>
                        <flux:text class="text-[11px] text-zinc-500 mt-0.5">
                            Información general de citas sostenidas o programadas con docentes y directivos.
                        </flux:text>
                    </div>
                </div>

                {{-- Tabla de Entrevistas (Solo información general, sin exponer detalle de bitácora confidencial) --}}
                @if($listaEntrevistas && $listaEntrevistas->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider text-[10px] font-bold border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="px-4 py-3">Fecha y Hora</th>
                                    <th class="px-4 py-3">Docente / Profesional</th>
                                    <th class="px-4 py-3">Motivo de Citación</th>
                                    <th class="px-4 py-3">Lugar</th>
                                    <th class="px-4 py-3">Estado Entrevista</th>
                                    <th class="px-4 py-3">Firma de Acta</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @foreach($listaEntrevistas as $entrevista)
                                    <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                        {{-- Fecha y Hora --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="font-semibold text-zinc-900 dark:text-zinc-100 block">
                                                {{ \Carbon\Carbon::parse($entrevista->fecha)->format('d/m/Y') }}
                                            </span>
                                            <span class="text-[11px] font-mono text-zinc-500">
                                                {{ $entrevista->hora ? \Carbon\Carbon::parse($entrevista->hora)->format('H:i') : '--:--' }} hrs
                                            </span>
                                        </td>

                                        {{-- Profesional --}}
                                        <td class="px-4 py-3 whitespace-nowrap font-medium text-zinc-800 dark:text-zinc-200">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-[#00376e]/10 text-[#00376e] dark:bg-blue-500/20 dark:text-blue-300 flex items-center justify-center text-[10px] font-bold">
                                                    {{ substr($entrevista->user?->nombres ?? 'D', 0, 1) }}
                                                </div>
                                                <span>{{ $entrevista->user?->nombreCompleto() ?? 'Docente / Profesional' }}</span>
                                            </div>
                                        </td>

                                        {{-- Motivo --}}
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                            {{ $entrevista->motivo ?: 'Citación de apoderado' }}
                                        </td>

                                        {{-- Lugar --}}
                                        <td class="px-4 py-3 text-zinc-500 whitespace-nowrap">
                                            {{ $entrevista->lugar ?: 'Establecimiento' }}
                                        </td>

                                        {{-- Estado de la Entrevista --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @php
                                                $estado = strtolower($entrevista->estado ?? 'pendiente');
                                            @endphp
                                            @if($estado === 'realizada')
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                    <flux:icon name="check" class="size-3" />
                                                    Realizada
                                                </span>
                                            @elseif(in_array($estado, ['pendiente', 'abierta', 'ingresada']))
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                                    <flux:icon name="calendar" class="size-3" />
                                                    Programada
                                                </span>
                                            @elseif($estado === 'cancelada')
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                                    Cancelada
                                                </span>
                                            @elseif($estado === 'ausente')
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                                    No asistió
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-zinc-100 text-zinc-700 capitalize">
                                                    {{ $estado }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Estado de Firma --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @php
                                                $firma = $entrevista->bitacora?->estado_firma;
                                                $firmada = in_array($firma, ['firmada_presencial', 'firmada_online']) || !empty($entrevista->bitacora?->firmado_at);
                                            @endphp
                                            @if($firmada)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                    <flux:icon name="pencil-square" class="size-3" />
                                                    Firmada
                                                </span>
                                            @elseif($entrevista->bitacora)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                                    <flux:icon name="clock" class="size-3" />
                                                    Pendiente de firma
                                                </span>
                                            @else
                                                <span class="text-zinc-400 text-[11px] italic">
                                                    Sin acta
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginación Entrevistas --}}
                    @if($listaEntrevistas->hasPages())
                        <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                            {{ $listaEntrevistas->links() }}
                        </div>
                    @endif
                @else
                    {{-- Estado vacío Entrevistas --}}
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-950/50 text-[#00376e] dark:text-blue-400 flex items-center justify-center mx-auto mb-3">
                            <flux:icon name="calendar" class="size-6" />
                        </div>
                        <flux:heading size="md" class="text-zinc-800 dark:text-zinc-200">
                            Sin citaciones o entrevistas
                        </flux:heading>
                        <flux:text class="text-zinc-500 text-xs mt-1">
                            No se encuentran entrevistas o citaciones registradas para este estudiante.
                        </flux:text>
                    </div>
                @endif
            </flux:card>
        @endif

    @endif

</div>
