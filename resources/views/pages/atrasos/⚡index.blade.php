<?php

use App\Models\Atraso;
use App\Models\Curso;
use App\Models\Estudiante;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registro de Atrasos')] class extends Component {
    public string $fecha = '';
    public string $search = '';
    public ?int $selectedCursoId = null;
    public string $filtroCiclo = 'todos'; // 'todos', 'basica', 'media'

    // Modal de edición rápida / justificación
    public bool $modalEdicion = false;
    public ?int $atrasoAEditarId = null;
    public string $editarEstado = 'injustificado';
    public string $editarMotivo = '';
    public string $editarObservaciones = '';

    // Modal estético de confirmación para eliminar/anular
    public bool $modalEliminar = false;
    public ?int $atrasoAEliminarId = null;
    public string $estudianteAEliminarNombre = '';

    // Manejo de jornada (auto, manana, tarde)
    public string $jornadaManual = '';
    public string $filtroFeedJornada = 'todas'; // 'todas', 'manana', 'tarde'

    public function setJornada(string $jornada): void
    {
        $this->jornadaManual = in_array($jornada, ['manana', 'tarde']) ? $jornada : '';
    }

    #[Computed]
    public function jornadaActiva(): string
    {
        if (! empty($this->jornadaManual)) {
            return $this->jornadaManual;
        }

        $horaActual = now('America/Santiago')->format('H:i');

        return $horaActual >= '13:30' ? 'tarde' : 'manana';
    }

    #[Computed]
    public function horaEntradaEsperada(): string
    {
        return $this->jornadaActiva === 'tarde' ? '13:30' : '08:00';
    }

    public function mount(): void
    {
        if (! auth()->user()->hasRole('superadmin') && ! auth()->user()->can('ingresar-atrasos')) {
            abort(403, 'No tienes permiso para ingresar atrasos.');
        }

        $this->fecha = now('America/Santiago')->format('Y-m-d');
    }

    public function getSchoolProperty()
    {
        return auth()->user()->currentSchool;
    }

    public function getAcademicYearProperty()
    {
        return $this->school?->academicYears()->where('is_active', true)->first();
    }

    #[Computed]
    public function cursos()
    {
        if (! $this->school) {
            return collect();
        }

        $allCursos = Curso::where('school_id', $this->school->id)
            ->when($this->academicYear, fn ($q) => $q->where('academic_year_id', $this->academicYear->id))
            ->orderBy('modalidad')
            ->orderBy('nivel')
            ->orderBy('letra')
            ->get();

        if ($this->filtroCiclo === 'todos') {
            return $allCursos;
        }

        return $allCursos->filter(function ($curso) {
            // Regla: Media comprende desde 8° Básico hasta 4° Medio
            $esMediaCiclo = ($curso->modalidad->value === 'media') || ($curso->modalidad->value === 'basica' && $curso->nivel >= 8);

            if ($this->filtroCiclo === 'media') {
                return $esMediaCiclo;
            }

            // Basica comprende desde 1° Básico hasta 7° Básico
            return ! $esMediaCiclo;
        })->values();
    }

    #[Computed]
    public function atrasosHoy()
    {
        if (! $this->school) {
            return collect();
        }

        $now = now('America/Santiago');

        return Atraso::with([
            'estudiante' => fn ($q) => $q->withCount([
                'atrasos as atrasos_mes_actual_count' => fn ($sq) => $sq
                    ->whereYear('fecha', $now->year)
                    ->whereMonth('fecha', $now->month),
            ])->with('curso'),
            'curso',
            'registradoPor',
        ])
            ->where('school_id', $this->school->id)
            ->whereDate('fecha', $this->fecha)
            ->when($this->filtroFeedJornada !== 'todas', fn ($q) => $q->where('jornada', $this->filtroFeedJornada))
            ->orderBy('hora', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    #[Computed]
    public function idsEstudiantesAtrasadosHoy(): array
    {
        if (! $this->school) {
            return [];
        }

        return Atraso::where('school_id', $this->school->id)
            ->whereDate('fecha', $this->fecha)
            ->pluck('estudiante_id')
            ->toArray();
    }

    #[Computed]
    public function searchResults()
    {
        $query = trim($this->search);

        if (mb_strlen($query) < 2 || ! $this->school) {
            return collect();
        }

        $cleanRut = preg_replace('/[^0-9kK]/', '', $query);

        return Estudiante::activos()
            ->with(['curso'])
            ->where('school_id', $this->school->id)
            ->where(function ($q) use ($query, $cleanRut) {
                $q->where('nombres_csv', 'like', "%{$query}%");

                if (! empty($cleanRut)) {
                    $q->orWhere('rut_numero', 'like', "%{$cleanRut}%");
                }
            })
            ->take(12)
            ->get();
    }

    #[Computed]
    public function estudiantesDelCurso()
    {
        if (! $this->selectedCursoId || ! $this->school) {
            return collect();
        }

        return Estudiante::activos()
            ->select(['id', 'school_id', 'curso_id', 'nombres_csv', 'rut_numero', 'rut_dv'])
            ->where('school_id', $this->school->id)
            ->where('curso_id', $this->selectedCursoId)
            ->orderBy('nombres_csv')
            ->get();
    }

    #[Computed]
    public function metricas(): array
    {
        if (! $this->school) {
            return [
                'total' => 0,
                'manana' => 0,
                'tarde' => 0,
                'justificados' => 0,
                'injustificados' => 0,
            ];
        }

        $todos = Atraso::where('school_id', $this->school->id)
            ->whereDate('fecha', $this->fecha)
            ->get(['estado', 'jornada']);

        return [
            'total' => $todos->count(),
            'manana' => $todos->where('jornada', 'manana')->count(),
            'tarde' => $todos->where('jornada', 'tarde')->count(),
            'justificados' => $todos->where('estado', 'justificado')->count(),
            'injustificados' => $todos->where('estado', 'injustificado')->count(),
        ];
    }

    public function selectCurso(int $cursoId): void
    {
        $this->selectedCursoId = ($this->selectedCursoId === $cursoId) ? null : $cursoId;
    }

    public function setFiltroCiclo(string $ciclo): void
    {
        $this->filtroCiclo = $ciclo;
        $this->selectedCursoId = null; // Reset curso al cambiar ciclo
    }

    /**
     * Clic sobre un estudiante:
     * - Si no está registrado hoy: se registra inmediatamente (sin modal previo).
     * - Si ya está registrado hoy: abre confirmación para "Anular atraso".
     */
    public function clickEstudiante(int $estudianteId): void
    {
        $school = $this->school;
        if (! $school) {
            Flux::toast(heading: 'Error', text: 'No tienes un colegio activo seleccionado.', variant: 'danger');
            return;
        }

        $estudiante = Estudiante::where('school_id', $school->id)->find($estudianteId);
        if (! $estudiante) {
            Flux::toast(heading: 'Error', text: 'Estudiante no encontrado.', variant: 'danger');
            return;
        }

        $yaRegistrado = Atraso::where('school_id', $school->id)
            ->where('estudiante_id', $estudiante->id)
            ->whereDate('fecha', $this->fecha)
            ->first();

        if ($yaRegistrado) {
            $this->confirmarEliminacion($yaRegistrado->id);
            return;
        }

        $this->registrarAtraso($estudiante->id);
    }

    /**
     * Registrar el primer resultado de búsqueda (al presionar ENTER).
     */
    public function registrarPrimerResultado(): void
    {
        $primerResultado = $this->searchResults->first();
        if ($primerResultado) {
            $this->clickEstudiante($primerResultado->id);
        }
    }

    /**
     * Registrar atraso de un estudiante de forma directa e inmediata.
     */
    public function registrarAtraso(?int $estudianteId = null): void
    {
        if (! $estudianteId) {
            return;
        }

        $school = $this->school;
        if (! $school) {
            Flux::toast(heading: 'Error', text: 'No tienes un colegio activo seleccionado.', variant: 'danger');
            return;
        }

        $estudiante = Estudiante::where('school_id', $school->id)->find($estudianteId);
        if (! $estudiante) {
            Flux::toast(heading: 'Error', text: 'Estudiante no encontrado.', variant: 'danger');
            return;
        }

        // Control de duplicados en el mismo día
        $yaRegistrado = Atraso::where('school_id', $school->id)
            ->where('estudiante_id', $estudiante->id)
            ->whereDate('fecha', $this->fecha)
            ->first();

        if ($yaRegistrado) {
            $this->confirmarEliminacion($yaRegistrado->id);
            return;
        }

        // Calcular hora y minutos de atraso según jornada activa (08:00 o 13:30)
        $now = now('America/Santiago');
        $horaActual = $now->format('H:i:s');
        $jornada = $this->jornadaActiva;
        $horaOficial = $jornada === 'tarde' ? '13:30:00' : '08:00:00';
        $horaLimite = Carbon::parse($this->fecha . ' ' . $horaOficial, 'America/Santiago');
        $momentoIngreso = Carbon::parse($this->fecha . ' ' . $horaActual, 'America/Santiago');

        $minutosAtraso = 0;
        if ($momentoIngreso->greaterThan($horaLimite)) {
            $minutosAtraso = (int) $horaLimite->diffInMinutes($momentoIngreso);
        }

        Atraso::create([
            'school_id' => $school->id,
            'academic_year_id' => $this->academicYear?->id,
            'estudiante_id' => $estudiante->id,
            'curso_id' => $estudiante->curso_id,
            'registrado_por_user_id' => auth()->id(),
            'fecha' => $this->fecha,
            'hora' => $horaActual,
            'jornada' => $jornada,
            'minutos_atraso' => $minutosAtraso,
            'estado' => 'injustificado',
            'motivo' => null,
            'observaciones' => null,
        ]);

        $this->search = '';

        $totalMes = $estudiante->atrasosMesActualCount();

        if ($totalMes >= 3) {
            Flux::toast(
                heading: '⚠️ Citación de Apoderado',
                text: "{$estudiante->nombreCompleto()} acumula {$totalMes} atrasos este mes.",
                variant: 'danger'
            );
        } else {
            Flux::toast(
                heading: 'Atraso Registrado',
                text: "{$estudiante->nombreCompleto()} ingresó a las " . Carbon::parse($horaActual)->format('H:i') . " hrs (#{$totalMes} del mes).",
                variant: 'success'
            );
        }
    }

    /**
     * Abrir modal para justificar o modificar estado del atraso.
     */
    public function abrirModalEdicion(int $atrasoId): void
    {
        $atraso = Atraso::where('school_id', $this->school?->id)->find($atrasoId);
        if (! $atraso) {
            return;
        }

        $this->atrasoAEditarId = $atraso->id;
        $this->editarEstado = $atraso->estado;
        $this->editarMotivo = $atraso->motivo ?? '';
        $this->editarObservaciones = $atraso->observaciones ?? '';
        $this->modalEdicion = true;
    }

    /**
     * Guardar cambios de justificación.
     */
    public function guardarEdicion(): void
    {
        if (! $this->atrasoAEditarId) {
            return;
        }

        $atraso = Atraso::where('school_id', $this->school?->id)->find($this->atrasoAEditarId);
        if ($atraso) {
            $atraso->update([
                'estado' => $this->editarEstado,
                'motivo' => $this->editarMotivo ?: null,
                'observaciones' => $this->editarObservaciones ?: null,
            ]);

            Flux::toast(heading: 'Actualizado', text: 'El estado del atraso ha sido actualizado.', variant: 'success');
        }

        $this->modalEdicion = false;
        $this->atrasoAEditarId = null;
    }

    /**
     * Cambiar rápidamente estado (Toggle Injustificado / Justificado).
     */
    public function toggleJustificado(int $atrasoId): void
    {
        $atraso = Atraso::where('school_id', $this->school?->id)->find($atrasoId);
        if (! $atraso) {
            return;
        }

        if ($atraso->estado === 'justificado') {
            $atraso->update([
                'estado' => 'injustificado',
                'motivo' => null,
            ]);
            Flux::toast(heading: 'Marcado como Injustificado', text: 'El atraso ahora está sin justificación.', variant: 'warning');
        } else {
            $atraso->update([
                'estado' => 'justificado',
                'motivo' => 'Justificado en puerta',
            ]);
            Flux::toast(heading: 'Marcado como Justificado', text: 'El atraso ha sido justificado.', variant: 'success');
        }
    }

    /**
     * Abrir modal de confirmación estética para anular atraso.
     */
    public function confirmarEliminacion(int $atrasoId): void
    {
        $atraso = Atraso::where('school_id', $this->school?->id)->find($atrasoId);
        if (! $atraso) {
            return;
        }

        $this->atrasoAEliminarId = $atraso->id;
        $this->estudianteAEliminarNombre = $atraso->estudiante?->nombreCompleto() ?? 'este estudiante';
        $this->modalEliminar = true;
    }

    /**
     * Eliminar registro de atraso tras confirmación.
     */
    public function eliminarAtraso(?int $atrasoId = null): void
    {
        $id = $atrasoId ?? $this->atrasoAEliminarId;
        if (! $id) {
            return;
        }

        $atraso = Atraso::where('school_id', $this->school?->id)->find($id);
        if ($atraso) {
            $nombre = $atraso->estudiante?->nombreCompleto() ?? 'Estudiante';
            $atraso->delete();
            Flux::toast(heading: 'Registro Anulado', text: "Se eliminó el atraso de {$nombre}.", variant: 'warning');
        }

        $this->modalEliminar = false;
        $this->atrasoAEliminarId = null;
        $this->estudianteAEliminarNombre = '';
    }
}; ?>

<div class="space-y-4">
    {{-- Header Estándar de la Plataforma (Compacto) --}}
    <x-header 
        titulo="Control de Atrasos" 
        subtitulo="Registro ágil de llegadas tarde e impresión de pases a sala." 
        icono="clock"
    >
        {{-- Reloj en tiempo real con Alpine.js --}}
        <div 
            x-data="{
                time: '',
                updateTime() {
                    const now = new Date();
                    this.time = now.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                }
            }" 
            x-init="updateTime(); setInterval(() => updateTime(), 1000)"
            class="flex items-center gap-2 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/40 dark:to-indigo-950/30 px-3 py-1 rounded-xl border border-blue-200/70 dark:border-blue-800/60 shadow-2xs"
        >
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <div class="flex items-baseline gap-1.5">
                <span class="text-[9px] uppercase font-black tracking-wider text-blue-700 dark:text-blue-300 leading-none">Hora:</span>
                <span class="font-mono text-sm font-black text-zinc-900 dark:text-zinc-100 tracking-tight leading-none" x-text="time">--:--:--</span>
            </div>
        </div>
    </x-header>

    {{-- BARRA UNIFICADA: Buscador Rápido (Izquierda) + Información y Métricas (Derecha) --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-2.5 sm:p-3 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            
            {{-- Izquierda: Buscador Rápido Más Pequeño --}}
            <div class="relative w-full lg:w-80 xl:w-96">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-zinc-400">
                        <flux:icon.magnifying-glass class="size-4" />
                    </div>
                    <input 
                        type="text" 
                        wire:model.live.debounce.150ms="search" 
                        wire:keydown.enter="registrarPrimerResultado"
                        autofocus 
                        placeholder="Buscar alumno o RUT (o pistola)..." 
                        class="w-full pl-8 pr-14 py-1.5 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs font-medium text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-2xs"
                    />
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <span class="text-[9px] font-mono font-bold bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 px-1.5 py-0.5 rounded">
                            ↵
                        </span>
                    </div>
                </div>

                {{-- Menú flotante de resultados predictivos --}}
                @if(mb_strlen(trim($search)) >= 2)
                    <div class="absolute left-0 top-full mt-1.5 w-full sm:w-[440px] z-30 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl p-2 max-h-80 overflow-y-auto space-y-1">
                        <div class="flex items-center justify-between px-1.5 py-1 text-[10px] font-bold uppercase text-zinc-400 border-b border-zinc-100 dark:border-zinc-800">
                            <span>Resultados ({{ $this->searchResults->count() }})</span>
                            <span class="text-[9px] text-zinc-400 lowercase">ENTER para el primero</span>
                        </div>

                        @forelse($this->searchResults as $res)
                            @php
                                $yaRegistrado = in_array($res->id, $this->idsEstudiantesAtrasadosHoy);
                                $atrasoRegistrado = $yaRegistrado ? $this->atrasosHoy->firstWhere('estudiante_id', $res->id) : null;
                            @endphp
                            <div 
                                wire:click="clickEstudiante({{ $res->id }})"
                                @class([
                                    'w-full text-left p-2 rounded-lg border transition-all flex items-center justify-between gap-2 cursor-pointer select-none',
                                    'bg-emerald-50/90 dark:bg-emerald-950/40 border-2 border-emerald-500 text-emerald-950 dark:text-emerald-100 shadow-2xs' => $yaRegistrado,
                                    'bg-zinc-50 hover:bg-blue-50/80 dark:bg-zinc-800/60 dark:hover:bg-blue-950/40 border-zinc-200 dark:border-zinc-700 hover:border-blue-400' => ! $yaRegistrado,
                                ])
                                title="{{ $yaRegistrado ? 'Haga clic para anular atraso' : 'Haga clic para registrar atraso' }}"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-[11px] leading-tight line-clamp-2 break-words" title="{{ $res->nombreCompleto() }}">
                                        {{ $res->nombreCompleto() }}
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[9.5px] text-zinc-500">
                                        <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $res->curso?->nombreAbreviado() ?? 'S/C' }}</span>
                                        <span>•</span>
                                        <span>{{ $res->rutCompleto() ?? 'Sin RUT' }}</span>
                                    </div>
                                </div>

                                @if($yaRegistrado && $atrasoRegistrado)
                                    <div class="flex items-center gap-1 shrink-0">
                                        <a 
                                            href="{{ route('atrasos.ticket', $atrasoRegistrado->id) }}" 
                                            target="_blank" 
                                            @click.stop 
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-600 shadow-2xs hover:bg-zinc-100"
                                            title="Imprimir Pase Térmico"
                                        >
                                            <flux:icon.printer class="size-3 text-zinc-500" />
                                            <span>Pase</span>
                                        </a>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-600 text-white" title="Clic para anular">
                                            ✓ Llegó {{ \Carbon\Carbon::parse($atrasoRegistrado->hora)->format('H:i') }}
                                        </span>
                                    </div>
                                @else
                                    <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded bg-blue-600 text-white hover:bg-blue-700">
                                        Registrar +
                                    </span>
                                @endif
                            </div>
                        @empty
                            <div class="p-3 text-center text-xs text-zinc-400">
                                No se encontraron estudiantes con "{{ $search }}".
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>

            {{-- Derecha: Fecha + Selector de Jornada + Métricas --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Selector de Fecha --}}
                <div class="flex items-center gap-1.5 bg-zinc-50 dark:bg-zinc-800/80 px-2 py-1 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <flux:icon.calendar class="size-3.5 text-zinc-400" />
                    <input type="date" wire:model.live="fecha" class="bg-transparent text-xs font-semibold text-zinc-800 dark:text-zinc-200 border-none focus:outline-none focus:ring-0 p-0 cursor-pointer" />
                </div>

                {{-- Selector de Jornada (Mañana / Tarde) --}}
                <div class="flex items-center bg-zinc-100 dark:bg-zinc-800 p-0.5 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <button 
                        type="button" 
                        wire:click="setJornada('manana')"
                        @class([
                            'px-2 py-1 text-[11px] font-bold rounded-md transition-all flex items-center gap-1 cursor-pointer',
                            'bg-[#00376e] text-white shadow-xs' => $this->jornadaActiva === 'manana',
                            'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' => $this->jornadaActiva !== 'manana',
                        ])
                        title="Jornada de la Mañana (Entrada oficial: 08:00 hrs)"
                    >
                        <span>☀️ Mañana</span>
                        <span class="text-[9px] opacity-75 font-mono">08:00</span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="setJornada('tarde')"
                        @class([
                            'px-2 py-1 text-[11px] font-bold rounded-md transition-all flex items-center gap-1 cursor-pointer',
                            'bg-[#00376e] text-white shadow-xs' => $this->jornadaActiva === 'tarde',
                            'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' => $this->jornadaActiva !== 'tarde',
                        ])
                        title="Jornada de la Tarde (Entrada oficial: 13:30 hrs)"
                    >
                        <span>🌙 Tarde</span>
                        <span class="text-[9px] opacity-75 font-mono">13:30</span>
                    </button>
                </div>

                @if($fecha !== now('America/Santiago')->format('Y-m-d'))
                    <button 
                        type="button" 
                        wire:click="$set('fecha', '{{ now('America/Santiago')->format('Y-m-d') }}')" 
                        class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline px-1"
                    >
                        Volver a Hoy
                    </button>
                @endif

                {{-- Métricas Compactas con desglose --}}
                <div class="flex items-center gap-1.5">
                    <div class="px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-center min-w-[65px]" title="Total hoy: {{ $this->metricas['total'] }} (Mañana: {{ $this->metricas['manana'] }} | Tarde: {{ $this->metricas['tarde'] }})">
                        <span class="text-[9px] uppercase font-bold text-zinc-500 block leading-tight">Total</span>
                        <span class="text-xs font-black text-zinc-900 dark:text-zinc-100 leading-tight">
                            {{ $this->metricas['total'] }}
                            <span class="text-[9px] font-medium text-zinc-400">({{ $this->metricas['manana'] }}m/{{ $this->metricas['tarde'] }}t)</span>
                        </span>
                    </div>
                    <div class="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 text-center min-w-[50px]">
                        <span class="text-[9px] uppercase font-bold text-rose-600 dark:text-rose-400 block leading-tight">Injust.</span>
                        <span class="text-xs font-black text-rose-700 dark:text-rose-300 leading-tight">{{ $this->metricas['injustificados'] }}</span>
                    </div>
                    <div class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/50 text-center min-w-[50px]">
                        <span class="text-[9px] uppercase font-bold text-emerald-600 dark:text-emerald-400 block leading-tight">Justif.</span>
                        <span class="text-xs font-black text-emerald-700 dark:text-emerald-300 leading-tight">{{ $this->metricas['justificados'] }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- SECCIÓN INTERCAMBIABLE: Botonera de Cursos O Lista de Estudiantes del Curso --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-3.5 sm:p-4 shadow-xs space-y-3">
        @if(! $selectedCursoId)
            {{-- VISTA 1: BOTONERA DE CURSOS --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 border-b border-zinc-100 dark:border-zinc-800 pb-2.5">
                <div>
                    <h2 class="text-sm font-black text-zinc-900 dark:text-zinc-100 tracking-tight">Botonera por Cursos</h2>
                    <p class="text-[11px] text-zinc-500">Selecciona el curso para registrar atrasos</p>
                </div>

                {{-- Botones Básica - Media - Todos (Reducidos) --}}
                <div class="inline-flex p-0.5 bg-zinc-100 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <button 
                        type="button" 
                        wire:click="setFiltroCiclo('basica')" 
                        @class([
                            'px-2.5 py-1 text-[11px] font-bold rounded-md transition-all',
                            'bg-white dark:bg-zinc-700 text-blue-700 dark:text-blue-300 shadow-2xs' => $filtroCiclo === 'basica',
                            'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' => $filtroCiclo !== 'basica',
                        ])
                    >
                        Básica <span class="text-[10px] font-normal opacity-70">(1°-7°)</span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="setFiltroCiclo('media')" 
                        @class([
                            'px-2.5 py-1 text-[11px] font-bold rounded-md transition-all',
                            'bg-white dark:bg-zinc-700 text-blue-700 dark:text-blue-300 shadow-2xs' => $filtroCiclo === 'media',
                            'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' => $filtroCiclo !== 'media',
                        ])
                    >
                        Media <span class="text-[10px] font-normal opacity-70">(8°-4°M)</span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="setFiltroCiclo('todos')" 
                        @class([
                            'px-2.5 py-1 text-[11px] font-bold rounded-md transition-all',
                            'bg-white dark:bg-zinc-700 text-blue-700 dark:text-blue-300 shadow-2xs' => $filtroCiclo === 'todos',
                            'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' => $filtroCiclo !== 'todos',
                        ])
                    >
                        Todos
                    </button>
                </div>
            </div>

            {{-- Grilla de Cursos Compacta --}}
            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-2">
                @forelse($this->cursos as $curso)
                    @php
                        $atrasadosCursoCount = $this->atrasosHoy->where('curso_id', $curso->id)->count();
                    @endphp
                    <button 
                        type="button" 
                        wire:click="selectCurso({{ $curso->id }})"
                        wire:loading.class="opacity-60 pointer-events-none"
                        wire:target="selectCurso({{ $curso->id }})"
                        class="relative p-2.5 rounded-lg border text-center transition-all flex items-center justify-center bg-zinc-50 hover:bg-blue-50/80 dark:bg-zinc-800/80 dark:hover:bg-blue-950/40 border-zinc-200 dark:border-zinc-700 hover:border-blue-400 group shadow-2xs hover:shadow-xs"
                    >
                        <span 
                            wire:loading.remove 
                            wire:target="selectCurso({{ $curso->id }})"
                            class="text-sm font-black tracking-tight text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors"
                        >
                            {{ $curso->nombreAbreviado() }}
                        </span>

                        <span 
                            wire:loading 
                            wire:target="selectCurso({{ $curso->id }})"
                            class="text-xs font-bold text-blue-600 dark:text-blue-400 flex items-center justify-center"
                        >
                            <flux:icon.arrow-path class="size-3.5 animate-spin" />
                        </span>

                        @if($atrasadosCursoCount > 0)
                            <span 
                                wire:loading.remove 
                                wire:target="selectCurso({{ $curso->id }})"
                                class="absolute -top-1 -right-1 size-4 rounded-full bg-rose-500 text-white text-[9px] font-black flex items-center justify-center shadow-xs"
                            >
                                {{ $atrasadosCursoCount }}
                            </span>
                        @endif
                    </button>
                @empty
                    <div class="col-span-full text-center py-6 text-xs text-zinc-400">
                        No hay cursos disponibles para el ciclo seleccionado.
                    </div>
                @endforelse
            </div>
        @else
            {{-- VISTA 2: LISTA DE ESTUDIANTES DEL CURSO SELECCIONADO --}}
            @php
                $cursoActivo = Curso::find($selectedCursoId);
            @endphp
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-2.5">
                <div class="flex items-center gap-2.5">
                    <button 
                        type="button" 
                        wire:click="$set('selectedCursoId', null)" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 text-xs font-bold transition-colors"
                    >
                        <flux:icon.arrow-left class="size-3.5" />
                        <span>Volver a Cursos</span>
                    </button>
                    <div>
                        <h2 class="text-base font-black text-blue-700 dark:text-blue-400 tracking-tight leading-tight">
                            {{ $cursoActivo?->nombreCompleto() }}
                        </h2>
                        <p class="text-[11px] text-zinc-500 leading-tight">
                            {{ $this->estudiantesDelCurso->count() }} alumnos • Clic en alumno para registrar (o anular si ya llegó)
                        </p>
                    </div>
                </div>

                <button 
                    type="button" 
                    wire:click="$set('selectedCursoId', null)" 
                    class="text-xs font-semibold text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                >
                    ✕ Cerrar
                </button>
            </div>

            {{-- Grilla de Estudiantes (Sin scroll interno: todos los alumnos visibles en pantalla) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                @forelse($this->estudiantesDelCurso as $est)
                    @php
                        $yaRegistrado = in_array($est->id, $this->idsEstudiantesAtrasadosHoy);
                        $atrasoRegistrado = $yaRegistrado ? $this->atrasosHoy->firstWhere('estudiante_id', $est->id) : null;
                    @endphp
                    <div 
                        wire:click="clickEstudiante({{ $est->id }})"
                        @class([
                            'p-2 sm:p-2.5 rounded-xl border text-left transition-all flex items-center justify-between gap-1.5 cursor-pointer select-none group',
                            'bg-emerald-50/90 dark:bg-emerald-950/40 border-2 border-emerald-500 shadow-xs text-emerald-950 dark:text-emerald-100 hover:border-emerald-600' => $yaRegistrado,
                            'bg-white dark:bg-zinc-800/60 hover:bg-blue-50 dark:hover:bg-blue-950/40 border-zinc-200 dark:border-zinc-700 hover:border-blue-400 shadow-2xs' => ! $yaRegistrado,
                        ])
                        title="{{ $yaRegistrado ? 'Haga clic para anular atraso' : 'Haga clic para registrar atraso' }}"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-[11px] leading-tight line-clamp-2 break-words group-hover:text-blue-600 dark:group-hover:text-blue-400" title="{{ $est->nombreCompleto() }}">
                                {{ $est->nombreCompleto() }}
                            </div>
                            <div class="text-[9.5px] text-zinc-500 mt-0.5 flex items-center gap-1.5">
                                @if($yaRegistrado && $atrasoRegistrado)
                                    <span class="text-emerald-700 dark:text-emerald-300 font-bold">Llegó {{ \Carbon\Carbon::parse($atrasoRegistrado->hora)->format('H:i') }} hrs</span>
                                @else
                                    <span>{{ $est->rutCompleto() ?? 'Sin RUT' }}</span>
                                @endif
                            </div>
                        </div>

                        @if($yaRegistrado && $atrasoRegistrado)
                            <div class="flex items-center gap-1 shrink-0">
                                {{-- Pase Térmico --}}
                                <a 
                                    href="{{ route('atrasos.ticket', $atrasoRegistrado->id) }}" 
                                    target="_blank" 
                                    @click.stop 
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[9.5px] font-bold bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-600 shadow-2xs hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    title="Imprimir Pase Térmico"
                                >
                                    <flux:icon.printer class="size-3 text-zinc-600 dark:text-zinc-400" />
                                    <span>Pase</span>
                                </a>
                                {{-- Checkmark verde --}}
                                <span class="size-4.5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[9px] font-black shadow-2xs" title="Clic en la tarjeta para anular">
                                    ✓
                                </span>
                            </div>
                        @else
                            <span class="shrink-0 size-4.5 rounded-full bg-zinc-100 group-hover:bg-blue-600 text-zinc-400 group-hover:text-white flex items-center justify-center text-[10px] font-black transition-colors">
                                +
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="col-span-full text-center py-8 text-xs text-zinc-400">
                        No hay estudiantes activos matriculados en este curso.
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    {{-- SECCIÓN INFERIOR: INGRESOS DE HOY (Debajo del cuadro de cursos) --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-3.5 sm:p-4 shadow-xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-zinc-100 dark:border-zinc-800 pb-2.5">
            <div>
                <h2 class="text-sm font-black text-zinc-900 dark:text-zinc-100 tracking-tight">
                    Ingresos Registrados Hoy ({{ $this->atrasosHoy->count() }})
                </h2>
                <p class="text-[11px] text-zinc-500">Ordenados cronológicamente desde la llegada más reciente</p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Filtro de Jornada para el feed --}}
                <div class="flex items-center bg-zinc-100 dark:bg-zinc-800 p-0.5 rounded-lg border border-zinc-200 dark:border-zinc-700 text-[10px]">
                    <button type="button" wire:click="$set('filtroFeedJornada', 'todas')" class="px-2 py-0.5 rounded font-bold cursor-pointer {{ $filtroFeedJornada === 'todas' ? 'bg-[#00376e] text-white shadow-xs' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400' }}">Todas</button>
                    <button type="button" wire:click="$set('filtroFeedJornada', 'manana')" class="px-2 py-0.5 rounded font-bold cursor-pointer {{ $filtroFeedJornada === 'manana' ? 'bg-[#00376e] text-white shadow-xs' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400' }}">☀️ Mañana</button>
                    <button type="button" wire:click="$set('filtroFeedJornada', 'tarde')" class="px-2 py-0.5 rounded font-bold cursor-pointer {{ $filtroFeedJornada === 'tarde' ? 'bg-[#00376e] text-white shadow-xs' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400' }}">🌙 Tarde</button>
                </div>

                <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-2 py-0.5 rounded-md">
                    {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                </span>
            </div>
        </div>

        {{-- Grilla de Ingresos de Hoy Compacta --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5 max-h-[400px] overflow-y-auto pr-1">
            @forelse($this->atrasosHoy as $atraso)
                @php
                    $estudiante = $atraso->estudiante;
                    $totalMes = $estudiante ? ($estudiante->atrasos_mes_actual_count ?? $estudiante->atrasosMesActualCount()) : 1;
                @endphp
                <div class="p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/40 hover:bg-white dark:hover:bg-zinc-800 transition-colors space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="font-black text-xs text-zinc-900 dark:text-zinc-100 leading-snug truncate">
                                {{ $estudiante?->nombreCompleto() ?? 'Estudiante' }}
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-zinc-500">
                                <span class="font-bold text-zinc-700 dark:text-zinc-300">
                                    {{ $atraso->curso?->nombreAbreviado() ?? $estudiante?->curso?->nombreAbreviado() ?? 'S/C' }}
                                </span>
                                <span>•</span>
                                <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
                                    {{ \Carbon\Carbon::parse($atraso->hora)->format('H:i') }} hrs
                                </span>
                                <span class="inline-flex items-center px-1 rounded text-[8.5px] font-bold {{ $atraso->isTarde() ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' }}">
                                    {{ $atraso->isTarde() ? '🌙 Tarde' : '☀️ Mañana' }}
                                </span>
                                @if($atraso->minutos_atraso > 0)
                                    <span class="text-rose-600 dark:text-rose-400 font-semibold">(+{{ $atraso->minutos_atraso }}m)</span>
                                @endif
                            </div>
                        </div>

                        {{-- Semáforo de Reincidencia del Mes --}}
                        <div class="shrink-0">
                            @if($totalMes >= 3)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300">
                                    ⚠️ {{ $totalMes }}° atraso (Citar)
                                </span>
                            @elseif($totalMes === 2)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-300">
                                    🟡 {{ $totalMes }}° atraso
                                </span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300">
                                    🟢 1er atraso
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Barra de Acciones Rápidas del Registro --}}
                    <div class="flex items-center justify-between pt-1 border-t border-zinc-200/60 dark:border-zinc-700/60">
                        <div class="flex items-center gap-1">
                            {{-- Botón Toggle Justificación rápida --}}
                            <button 
                                type="button" 
                                wire:click="toggleJustificado({{ $atraso->id }})"
                                @class([
                                    'px-2 py-0.5 rounded text-[9px] font-bold transition-all',
                                    'bg-emerald-600 text-white hover:bg-emerald-700' => $atraso->estado === 'justificado',
                                    'bg-zinc-200 text-zinc-700 hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-300' => $atraso->estado !== 'justificado',
                                ])
                            >
                                {{ $atraso->estado === 'justificado' ? '✓ Justificado' : 'Injustificado' }}
                            </button>

                            {{-- Botón Detalle / Observación --}}
                            <button 
                                type="button" 
                                wire:click="abrirModalEdicion({{ $atraso->id }})"
                                class="p-1 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                                title="Editar observación"
                            >
                                <flux:icon.pencil-square class="size-3.5" />
                            </button>
                        </div>

                        <div class="flex items-center gap-1">
                            {{-- Botón Imprimir Ticket Térmico --}}
                            <a 
                                href="{{ route('atrasos.ticket', $atraso->id) }}" 
                                target="_blank" 
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 transition-colors"
                                title="Imprimir Pase Térmico"
                            >
                                <flux:icon.printer class="size-3" />
                                <span>Pase</span>
                            </a>

                            {{-- Botón Anular / Eliminar --}}
                            <button 
                                type="button" 
                                wire:click="confirmarEliminacion({{ $atraso->id }})" 
                                class="p-1 rounded text-zinc-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors"
                                title="Anular atraso"
                            >
                                <flux:icon.trash class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-8 text-xs text-zinc-400">
                    No se han registrado atrasos en la fecha seleccionada.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Modal de Edición de Justificación / Motivo --}}
    <flux:modal wire:model="modalEdicion" class="md:w-96 space-y-4">
        <div>
            <flux:heading size="lg">Detalle del Atraso</flux:heading>
            <flux:subheading class="text-xs">Actualizar justificación u observación.</flux:subheading>
        </div>

        <div class="space-y-3">
            <flux:field>
                <flux:label class="text-xs">Estado de Justificación</flux:label>
                <flux:select size="sm" wire:model="editarEstado">
                    <flux:select.option value="injustificado">Injustificado</flux:select.option>
                    <flux:select.option value="justificado">Justificado</flux:select.option>
                    <flux:select.option value="pendiente">Pendiente de Justificativo</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label class="text-xs">Motivo</flux:label>
                <flux:input size="sm" wire:model="editarMotivo" placeholder="Ej: Pase médico, Locomoción..." />
            </flux:field>

            <flux:field>
                <flux:label class="text-xs">Observaciones</flux:label>
                <flux:textarea size="sm" wire:model="editarObservaciones" placeholder="Detalles de Inspectoría..." />
            </flux:field>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
            <flux:button size="sm" wire:click="$set('modalEdicion', false)">Cancelar</flux:button>
            <flux:button size="sm" variant="primary" wire:click="guardarEdicion">Guardar Cambios</flux:button>
        </div>
    </flux:modal>

    {{-- Modal Estético para Anular / Eliminar Registro --}}
    <flux:modal wire:model="modalEliminar" class="md:w-96">
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <flux:icon.trash class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 font-black">
                        Anular Atraso
                    </flux:heading>
                    <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        ¿Estás seguro de que deseas anular el atraso de <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $estudianteAEliminarNombre }}</span>? El registro será eliminado permanentemente.
                    </flux:subheading>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button size="sm" variant="ghost" wire:click="$set('modalEliminar', false)">
                    Cancelar
                </flux:button>
                <flux:button size="sm" variant="danger" wire:click="eliminarAtraso">
                    Sí, Anular Atraso
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
