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

        return Atraso::with(['estudiante.curso', 'curso', 'registradoPor'])
            ->where('school_id', $this->school->id)
            ->whereDate('fecha', $this->fecha)
            ->orderBy('hora', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    #[Computed]
    public function idsEstudiantesAtrasadosHoy(): array
    {
        return $this->atrasosHoy->pluck('estudiante_id')->toArray();
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
            ->where('school_id', $this->school->id)
            ->where('curso_id', $this->selectedCursoId)
            ->orderBy('nombres_csv')
            ->get();
    }

    #[Computed]
    public function metricas()
    {
        $total = $this->atrasosHoy->count();
        $justificados = $this->atrasosHoy->where('estado', 'justificado')->count();
        $injustificados = $this->atrasosHoy->where('estado', 'injustificado')->count();

        return [
            'total' => $total,
            'justificados' => $justificados,
            'injustificados' => $injustificados,
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
     * Registrar atraso inmediato de un estudiante.
     */
    public function registrarAtraso(int $estudianteId): void
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

        // 1. Control de duplicados en el mismo día
        $yaRegistrado = Atraso::where('school_id', $school->id)
            ->where('estudiante_id', $estudiante->id)
            ->whereDate('fecha', $this->fecha)
            ->first();

        if ($yaRegistrado) {
            $horaRegistrada = Carbon::parse($yaRegistrado->hora)->format('H:i');
            Flux::toast(
                heading: 'Estudiante ya registrado',
                text: "{$estudiante->nombreCompleto()} ya fue ingresado hoy a las {$horaRegistrada} hrs.",
                variant: 'warning'
            );
            $this->search = '';
            return;
        }

        // 2. Calcular hora y minutos de atraso (referencia 08:00 hrs)
        $now = now('America/Santiago');
        $horaActual = $now->format('H:i:s');
        $horaLimite = Carbon::parse($this->fecha . ' 08:00:00', 'America/Santiago');
        $momentoIngreso = Carbon::parse($this->fecha . ' ' . $horaActual, 'America/Santiago');

        $minutosAtraso = 0;
        if ($momentoIngreso->greaterThan($horaLimite)) {
            $minutosAtraso = (int) $horaLimite->diffInMinutes($momentoIngreso);
        }

        // 3. Crear el registro
        Atraso::create([
            'school_id' => $school->id,
            'academic_year_id' => $this->academicYear?->id,
            'estudiante_id' => $estudiante->id,
            'curso_id' => $estudiante->curso_id,
            'registrado_por_user_id' => auth()->id(),
            'fecha' => $this->fecha,
            'hora' => $horaActual,
            'minutos_atraso' => $minutosAtraso,
            'estado' => 'injustificado',
            'motivo' => null,
            'observaciones' => null,
        ]);

        $this->search = '';

        // Contar atrasos en el mes
        $totalMes = $estudiante->atrasosMesActualCount();

        if ($totalMes >= 3) {
            Flux::toast(
                heading: '⚠️ ALERTA: Citación de Apoderado',
                text: "{$estudiante->nombreCompleto()} acumula {$totalMes} atrasos este mes.",
                variant: 'danger'
            );
        } else {
            Flux::toast(
                heading: 'Atraso Registrado',
                text: "{$estudiante->nombreCompleto()} ingresó a las " . Carbon::parse($horaActual)->format('H:i') . " hrs (Atraso #{$totalMes} del mes).",
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

<div class="space-y-6">
    {{-- Header Estándar de la Plataforma --}}
    <x-header 
        titulo="Control de Atrasos" 
        subtitulo="Registro ágil de llegadas tarde, control de reincidencias e impresión de pases a sala." 
        icono="clock"
    >
        {{-- Reloj en tiempo real con Alpine.js (cero peticiones al servidor, rendimiento puro) --}}
        <div 
            x-data="{
                time: '',
                updateTime() {
                    const now = new Date();
                    this.time = now.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                }
            }" 
            x-init="updateTime(); setInterval(() => updateTime(), 1000)"
            class="flex items-center gap-2.5 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/40 dark:to-indigo-950/30 px-3.5 py-1.5 rounded-xl border border-blue-200/70 dark:border-blue-800/60 shadow-sm"
        >
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span>
            <div class="flex flex-col">
                <span class="text-[9px] uppercase font-black tracking-wider text-blue-700 dark:text-blue-300 leading-none">Hora Oficial</span>
                <span class="font-mono text-base font-black text-zinc-900 dark:text-zinc-100 tracking-tight leading-tight" x-text="time">--:--:--</span>
            </div>
        </div>
    </x-header>

    {{-- Barra de Control Rápido: Selector de Fecha y Tarjetas de Métricas del Día --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 bg-zinc-50 dark:bg-zinc-800/80 px-3 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <flux:icon.calendar class="size-4 text-zinc-500" />
                    <label class="text-[11px] font-bold uppercase text-zinc-500">Fecha de Registro:</label>
                    <input type="date" wire:model.live="fecha" class="bg-transparent text-xs font-bold text-zinc-800 dark:text-zinc-200 border-none focus:outline-none focus:ring-0 p-0 cursor-pointer" />
                </div>
                @if($fecha === now('America/Santiago')->format('Y-m-d'))
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                        Jornada de Hoy
                    </span>
                @else
                    <button 
                        type="button" 
                        wire:click="$set('fecha', '{{ now('America/Santiago')->format('Y-m-d') }}')" 
                        class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline"
                    >
                        Volver a Hoy
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-2.5">
                <div class="px-3.5 py-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-center min-w-[75px]">
                    <span class="block text-[10px] uppercase font-bold text-zinc-500">Total Hoy</span>
                    <span class="text-base font-black text-zinc-900 dark:text-zinc-100">{{ $this->metricas['total'] }}</span>
                </div>
                <div class="px-3.5 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 text-center min-w-[85px]">
                    <span class="block text-[10px] uppercase font-bold text-rose-600 dark:text-rose-400">Injustificados</span>
                    <span class="text-base font-black text-rose-700 dark:text-rose-300">{{ $this->metricas['injustificados'] }}</span>
                </div>
                <div class="px-3.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/50 text-center min-w-[80px]">
                    <span class="block text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400">Justificados</span>
                    <span class="text-base font-black text-emerald-700 dark:text-emerald-300">{{ $this->metricas['justificados'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Buscador Predictivo Rápido (Fast Lane) --}}
    <div class="bg-white dark:bg-zinc-900 border-2 border-blue-500/60 dark:border-blue-500/40 rounded-2xl p-4 shadow-md">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                <flux:icon.magnifying-glass class="size-5" />
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.150ms="search" 
                autofocus 
                placeholder="Escribe apellido, nombre o RUT (o escanea con la pistola lectora)..." 
                class="w-full pl-11 pr-24 py-3 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm font-medium text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
            />
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <span class="text-[10px] font-mono font-bold bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 px-2 py-0.5 rounded">
                    ENTER ↵
                </span>
            </div>
        </div>

        {{-- Resultados de Búsqueda Predictiva --}}
        @if(mb_strlen(trim($search)) >= 2)
            <div class="mt-3 border-t border-zinc-100 dark:border-zinc-800 pt-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-2">
                    Resultados de Búsqueda ({{ $this->searchResults->count() }})
                </div>

                @if($this->searchResults->isEmpty())
                    <div class="p-4 text-center text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl">
                        No se encontraron estudiantes activos con "<span class="font-semibold">{{ $search }}</span>".
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-80 overflow-y-auto pr-1">
                        @foreach($this->searchResults as $res)
                            @php
                                $yaRegistrado = in_array($res->id, $this->idsEstudiantesAtrasadosHoy);
                            @endphp
                            <button 
                                type="button" 
                                wire:click="registrarAtraso({{ $res->id }})"
                                @class([
                                    'w-full text-left p-2.5 rounded-xl border transition-all flex items-center justify-between gap-2 group',
                                    'bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800/60' => $yaRegistrado,
                                    'bg-zinc-50 hover:bg-blue-50/80 dark:bg-zinc-800/60 dark:hover:bg-blue-950/40 border-zinc-200 dark:border-zinc-700 hover:border-blue-300' => ! $yaRegistrado,
                                ])
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                        {{ $res->nombreCompleto() }}
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5 text-[11px] text-zinc-500">
                                        <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $res->curso?->nombreAbreviado() ?? 'S/C' }}</span>
                                        <span>•</span>
                                        <span>{{ $res->rutCompleto() ?? 'Sin RUT' }}</span>
                                    </div>
                                </div>

                                @if($yaRegistrado)
                                    <span class="shrink-0 size-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs">
                                        ✓
                                    </span>
                                @else
                                    <span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-lg bg-blue-600 text-white opacity-90 group-hover:opacity-100 transition-opacity">
                                        Registrar +
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- SECCIÓN INTERCAMBIABLE: Botonera de Cursos O Lista de Estudiantes del Curso --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm space-y-4">
        @if(! $selectedCursoId)
            {{-- VISTA 1: BOTONERA DE CURSOS --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div>
                    <h2 class="text-base font-black text-zinc-900 dark:text-zinc-100 tracking-tight">Botonera por Cursos</h2>
                    <p class="text-xs text-zinc-500">Selecciona el curso para ver sus estudiantes</p>
                </div>

                {{-- Botones para escoger Básica - Media - Todos --}}
                <div class="inline-flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <button 
                        type="button" 
                        wire:click="setFiltroCiclo('basica')" 
                        @class([
                            'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all',
                            'bg-white dark:bg-zinc-700 text-blue-700 dark:text-blue-300 shadow-sm' => $filtroCiclo === 'basica',
                            'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' => $filtroCiclo !== 'basica',
                        ])
                    >
                        Básica <span class="text-[10px] font-normal opacity-70">(1°-7°)</span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="setFiltroCiclo('media')" 
                        @class([
                            'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all',
                            'bg-white dark:bg-zinc-700 text-blue-700 dark:text-blue-300 shadow-sm' => $filtroCiclo === 'media',
                            'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' => $filtroCiclo !== 'media',
                        ])
                    >
                        Media <span class="text-[10px] font-normal opacity-70">(8°-4°M)</span>
                    </button>
                    <button 
                        type="button" 
                        wire:click="setFiltroCiclo('todos')" 
                        @class([
                            'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all',
                            'bg-white dark:bg-zinc-700 text-blue-700 dark:text-blue-300 shadow-sm' => $filtroCiclo === 'todos',
                            'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' => $filtroCiclo !== 'todos',
                        ])
                    >
                        Todos
                    </button>
                </div>
            </div>

            {{-- Grilla de Cursos con fuente más grande y sin segunda línea redundante --}}
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2.5">
                @forelse($this->cursos as $curso)
                    @php
                        $atrasadosCursoCount = $this->atrasosHoy->where('curso_id', $curso->id)->count();
                    @endphp
                    <button 
                        type="button" 
                        wire:click="selectCurso({{ $curso->id }})"
                        class="relative p-3.5 rounded-xl border text-center transition-all flex items-center justify-center bg-zinc-50 hover:bg-blue-50/80 dark:bg-zinc-800/80 dark:hover:bg-blue-950/40 border-zinc-200 dark:border-zinc-700 hover:border-blue-400 group shadow-sm hover:shadow"
                    >
                        <span class="text-base font-black tracking-tight text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                            {{ $curso->nombreAbreviado() }}
                        </span>

                        @if($atrasadosCursoCount > 0)
                            <span class="absolute -top-1.5 -right-1.5 size-5 rounded-full bg-rose-500 text-white text-[10px] font-black flex items-center justify-center shadow">
                                {{ $atrasadosCursoCount }}
                            </span>
                        @endif
                    </button>
                @empty
                    <div class="col-span-full text-center py-8 text-xs text-zinc-500">
                        No hay cursos disponibles para el ciclo seleccionado.
                    </div>
                @endforelse
            </div>
        @else
            {{-- VISTA 2: LISTA DE ESTUDIANTES DEL CURSO SELECCIONADO (Reemplaza la botonera) --}}
            @php
                $cursoActivo = Curso::find($selectedCursoId);
            @endphp
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-3">
                    <button 
                        type="button" 
                        wire:click="$set('selectedCursoId', null)" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 text-xs font-bold transition-colors"
                    >
                        <flux:icon.arrow-left class="size-4" />
                        <span>Volver a Cursos</span>
                    </button>
                    <div>
                        <h2 class="text-lg font-black text-blue-700 dark:text-blue-400 tracking-tight">
                            {{ $cursoActivo?->nombreCompleto() }}
                        </h2>
                        <p class="text-xs text-zinc-500">
                            {{ $this->estudiantesDelCurso->count() }} alumnos matriculados • Clic para registrar atraso
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

            {{-- Grilla de Estudiantes --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5 max-h-[460px] overflow-y-auto pr-1">
                @forelse($this->estudiantesDelCurso as $est)
                    @php
                        $yaRegistrado = in_array($est->id, $this->idsEstudiantesAtrasadosHoy);
                        $atrasoRegistrado = $yaRegistrado ? $this->atrasosHoy->firstWhere('estudiante_id', $est->id) : null;
                    @endphp
                    <button 
                        type="button" 
                        wire:click="registrarAtraso({{ $est->id }})"
                        @class([
                            'p-3 rounded-xl border text-left transition-all flex items-center justify-between gap-2 group',
                            'bg-emerald-50/80 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 shadow-inner' => $yaRegistrado,
                            'bg-white dark:bg-zinc-800/60 hover:bg-blue-50 dark:hover:bg-blue-950/40 border-zinc-200 dark:border-zinc-700 hover:border-blue-400 shadow-sm' => ! $yaRegistrado,
                        ])
                    >
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate group-hover:text-blue-600">
                                {{ $est->nombreCompleto() }}
                            </div>
                            <div class="text-[11px] text-zinc-500 mt-0.5">
                                @if($yaRegistrado && $atrasoRegistrado)
                                    <span class="text-emerald-700 dark:text-emerald-300 font-bold">Llegó {{ \Carbon\Carbon::parse($atrasoRegistrado->hora)->format('H:i') }} hrs</span>
                                @else
                                    <span>{{ $est->rutCompleto() ?? 'Sin RUT' }}</span>
                                @endif
                            </div>
                        </div>

                        @if($yaRegistrado)
                            <span class="shrink-0 size-6 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-black">
                                ✓
                            </span>
                        @else
                            <span class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity text-blue-600 font-black text-sm px-1">
                                +
                            </span>
                        @endif
                    </button>
                @empty
                    <div class="col-span-full text-center py-10 text-xs text-zinc-500">
                        No hay estudiantes activos matriculados en este curso.
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    {{-- SECCIÓN INFERIOR: INGRESOS DE HOY (Debajo del cuadro de cursos) --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
            <div>
                <h2 class="text-base font-black text-zinc-900 dark:text-zinc-100 tracking-tight">
                    Ingresos Registrados Hoy ({{ $this->atrasosHoy->count() }})
                </h2>
                <p class="text-xs text-zinc-500">Ordenados cronológicamente desde la llegada más reciente</p>
            </div>
            <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-2.5 py-1 rounded-lg">
                {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
            </span>
        </div>

        {{-- Grilla de Ingresos de Hoy --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[500px] overflow-y-auto pr-1">
            @forelse($this->atrasosHoy as $atraso)
                @php
                    $estudiante = $atraso->estudiante;
                    $totalMes = $estudiante ? $estudiante->atrasosMesActualCount() : 1;
                @endphp
                <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-800/40 hover:bg-white dark:hover:bg-zinc-800 transition-colors space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="font-black text-xs text-zinc-900 dark:text-zinc-100 leading-snug truncate">
                                {{ $estudiante?->nombreCompleto() ?? 'Estudiante' }}
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5 text-[11px] text-zinc-500">
                                <span class="font-bold text-zinc-700 dark:text-zinc-300">
                                    {{ $atraso->curso?->nombreAbreviado() ?? $estudiante?->curso?->nombreAbreviado() ?? 'S/C' }}
                                </span>
                                <span>•</span>
                                <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
                                    {{ \Carbon\Carbon::parse($atraso->hora)->format('H:i') }} hrs
                                </span>
                                @if($atraso->minutos_atraso > 0)
                                    <span class="text-rose-600 dark:text-rose-400 font-semibold">(+{{ $atraso->minutos_atraso }}m)</span>
                                @endif
                            </div>
                        </div>

                        {{-- Semáforo de Reincidencia del Mes --}}
                        <div class="shrink-0">
                            @if($totalMes >= 3)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300">
                                    ⚠️ {{ $totalMes }}° atraso (Citar)
                                </span>
                            @elseif($totalMes === 2)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-300">
                                    🟡 {{ $totalMes }}° atraso
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300">
                                    🟢 1er atraso
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Barra de Acciones Rápidas del Registro --}}
                    <div class="flex items-center justify-between pt-1 border-t border-zinc-200/60 dark:border-zinc-700/60">
                        <div class="flex items-center gap-1.5">
                            {{-- Botón Toggle Justificación rápida --}}
                            <button 
                                type="button" 
                                wire:click="toggleJustificado({{ $atraso->id }})"
                                @class([
                                    'px-2.5 py-1 rounded-lg text-[10px] font-bold transition-all',
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
                                title="Agregar motivo u observación"
                            >
                                <flux:icon.pencil-square class="size-4" />
                            </button>
                        </div>

                        <div class="flex items-center gap-1">
                            {{-- Botón Imprimir Ticket Térmico --}}
                            <a 
                                href="{{ route('atrasos.ticket', $atraso->id) }}" 
                                target="_blank" 
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 transition-colors"
                                title="Imprimir Pase Térmico"
                            >
                                <flux:icon.printer class="size-3.5" />
                                <span>Pase</span>
                            </a>

                            {{-- Botón Anular / Eliminar (Modal estético) --}}
                            <button 
                                type="button" 
                                wire:click="confirmarEliminacion({{ $atraso->id }})" 
                                class="p-1 rounded text-zinc-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors"
                                title="Anular o eliminar registro"
                            >
                                <flux:icon.trash class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12 text-xs text-zinc-400">
                    No se han registrado atrasos en la fecha seleccionada.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Modal de Edición de Justificación / Motivo --}}
    <flux:modal wire:model="modalEdicion" class="md:w-96 space-y-5">
        <div>
            <flux:heading size="lg">Detalle del Atraso</flux:heading>
            <flux:subheading>Actualizar justificación u observación de Inspectoría.</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:field>
                <flux:label>Estado de Justificación</flux:label>
                <flux:select wire:model="editarEstado">
                    <flux:select.option value="injustificado">Injustificado</flux:select.option>
                    <flux:select.option value="justificado">Justificado</flux:select.option>
                    <flux:select.option value="pendiente">Pendiente de Justificativo</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Motivo</flux:label>
                <flux:input wire:model="editarMotivo" placeholder="Ej: Pase médico, Locomoción, Apoderado presente..." />
            </flux:field>

            <flux:field>
                <flux:label>Observaciones Adicionales</flux:label>
                <flux:textarea wire:model="editarObservaciones" placeholder="Detalles relevantes para Inspectoría General..." />
            </flux:field>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <flux:button wire:click="$set('modalEdicion', false)">Cancelar</flux:button>
            <flux:button variant="primary" wire:click="guardarEdicion">Guardar Cambios</flux:button>
        </div>
    </flux:modal>

    {{-- Modal Estético para Anular / Eliminar Registro --}}
    <flux:modal wire:model="modalEliminar" class="md:w-96">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="size-11 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <flux:icon.trash class="size-6" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 font-black">
                        Anular Registro de Atraso
                    </flux:heading>
                    <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        ¿Estás seguro de que deseas anular el atraso de <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $estudianteAEliminarNombre }}</span>? El registro será eliminado permanentemente.
                    </flux:subheading>
                </div>
            </div>

            <div class="flex justify-end gap-2.5 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('modalEliminar', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="eliminarAtraso">
                    Sí, Anular Atraso
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
