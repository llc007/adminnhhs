<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Entrevista;
use App\Models\LugarAtencion;
use App\Notifications\IngresoApoderado;
use App\Notifications\SalidaApoderado;
use Carbon\Carbon;
use Flux\Flux;

new class extends Component {
    use WithPagination;
    
    // Configuración para el modal de ingreso
    public bool $modalIngreso = false;
    public ?int $entrevistaSeleccionadaId = null;
    public string $lugarIngreso = '';
    public string $mensajeRecepcion = '';

    // Configuración para el modal de nuevo lugar
    public bool $modalNuevoLugar = false;
    public string $nuevoLugarNombre = '';

    // Filtros de tabla
    public string $searchTexto = '';
    public string $filtroDocente = 'todos';
    public string $filtroCurso = 'todos';
    public string $filtroTemporalidad = 'dia'; 
    public string $filtroEstado = 'todos';

    public function updated($property)
    {
        if (in_array($property, ['searchTexto', 'filtroDocente', 'filtroCurso', 'filtroTemporalidad', 'filtroEstado'])) {
            $this->resetPage();
        }
    }

    #[\Livewire\Attributes\Computed]
    public function metricas()
    {
        $hoy = now('America/Santiago')->format('Y-m-d');
        $baseQuery = Entrevista::where('school_id', auth()->user()->current_school_id)
                       ->whereDate('fecha', $hoy);

        return [
            'total_hoy' => (clone $baseQuery)->count(),
            'pendientes' => (clone $baseQuery)->where('estado', 'pendiente')->count(),
            'registrados' => (clone $baseQuery)->where('estado', 'ingresada')
                               ->where(function($q) {
                                   $q->whereNull('mensaje_recepcion')
                                     ->orWhere('mensaje_recepcion', 'not like', '%[SALIDA]%');
                               })->count(),
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function docentes()
    {
        return \App\Models\User::whereHas('schools', function ($q) {
            $q->where('school_id', auth()->user()->current_school_id)
              ->whereJsonContains('school_user.roles', 'docente');
        })->orderBy('nombres')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function cursos()
    {
        return \App\Models\Curso::where('school_id', auth()->user()->current_school_id)
            ->orderBy('modalidad')
            ->orderBy('nivel')
            ->orderBy('letra')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function proximasEntrevistas()
    {
        $query = Entrevista::with(['estudiante.curso', 'user'])
                   ->where('school_id', auth()->user()->current_school_id);

        $hoy = now('America/Santiago')->format('Y-m-d');
        
        if ($this->filtroTemporalidad === 'dia') {
            $query->whereDate('fecha', $hoy);
        } elseif ($this->filtroTemporalidad === 'semana') {
            $inicioSemana = now('America/Santiago')->startOfWeek()->format('Y-m-d');
            $finSemana = now('America/Santiago')->endOfWeek()->format('Y-m-d');
            $query->whereBetween('fecha', [$inicioSemana, $finSemana]);
        } elseif ($this->filtroTemporalidad === 'mes') {
            $inicioMes = now('America/Santiago')->startOfMonth()->format('Y-m-d');
            $finMes = now('America/Santiago')->endOfMonth()->format('Y-m-d');
            $query->whereBetween('fecha', [$inicioMes, $finMes]);
        }

        if ($this->filtroEstado === 'pendientes') {
            $query->where('estado', 'pendiente');
        } elseif ($this->filtroEstado === 'ingresados') {
            $query->whereIn('estado', ['ingresada', 'realizada']);
        } elseif ($this->filtroEstado !== 'todos') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroDocente !== 'todos') {
            $query->where('user_id', $this->filtroDocente);
        }

        if ($this->filtroCurso !== 'todos') {
            $query->whereHas('estudiante', function($q) {
                $q->where('curso_id', $this->filtroCurso);
            });
        }

        if (trim($this->searchTexto) !== '') {
            $term = trim($this->searchTexto);
            $query->whereHas('estudiante', function($q) use ($term) {
                $q->where('nombres_csv', 'like', "%{$term}%")
                  ->orWhere('rut_numero', 'like', "%{$term}%")
                  ->orWhere('apoderado_nombres', 'like', "%{$term}%")
                  ->orWhere('apoderado_apellido_pat', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('fecha', 'asc')->orderBy('hora', 'asc')->paginate(50);
    }

    #[\Livewire\Attributes\Computed]
    public function lugares()
    {
        return LugarAtencion::where('school_id', auth()->user()->current_school_id)
                ->where('activo', true)
                ->orderBy('nombre', 'asc')
                ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function boxesStatus()
    {
        $hoy = now('America/Santiago')->format('Y-m-d');
        $lugares = $this->lugares;
        
        // Entrevistas que están físicamente en el colegio ahora mismo
        $activas = Entrevista::with(['user', 'estudiante'])
            ->where('school_id', auth()->user()->current_school_id)
            ->whereDate('fecha', $hoy)
            ->whereIn('estado', ['ingresada', 'realizada'])
            ->where(function($q) {
                $q->whereNull('mensaje_recepcion')
                  ->orWhere('mensaje_recepcion', 'not like', '%[SALIDA]%');
            })
            ->get();

        $status = [];
        foreach ($lugares as $lugar) {
            $ocupante = $activas->firstWhere('lugar', $lugar->nombre);
            
            $status[] = (object) [
                'id' => $lugar->id,
                'nombre' => $lugar->nombre,
                'ocupado' => $ocupante ? true : false,
                'entrevista' => $ocupante
            ];
        }

        return collect($status);
    }

    public function prepararIngreso($id)
    {
        $this->entrevistaSeleccionadaId = $id;
        $this->lugarIngreso = '';
        $this->mensajeRecepcion = '';
        $this->modalIngreso = true;
    }

    public function registrarIngreso()
    {
        $this->validate([
            'lugarIngreso' => 'required|string|max:100',
        ], [
            'lugarIngreso.required' => 'Debe indicar a qué anexo/box se dirige el apoderado.'
        ]);

        $entrevista = Entrevista::find($this->entrevistaSeleccionadaId);
        
        if ($entrevista) {
            $entrevista->update([
                'estado' => 'ingresada',
                'lugar' => $this->lugarIngreso,
                'hora_llegada' => now('America/Santiago')->format('H:i:s'),
                'mensaje_recepcion' => trim($this->mensajeRecepcion) !== '' ? $this->mensajeRecepcion : null,
            ]);

            if ($entrevista->user) {
                $entrevista->user->notify(new IngresoApoderado($entrevista));
            }

            \Flux::toast('Ingreso registrado exitosamente.', variant: 'success');
        }

        $this->modalIngreso = false;
        $this->entrevistaSeleccionadaId = null;
    }

    public function registrarSalida($id)
    {
        $entrevista = Entrevista::find($id);
        
        if ($entrevista && !str_contains($entrevista->mensaje_recepcion ?? '', '[SALIDA]')) {
            $hora = now('America/Santiago')->format('H:i');
            $notaSalida = "[SALIDA] El apoderado se retiró del recinto a las {$hora}.";
            
            $nuevoMensaje = $entrevista->mensaje_recepcion 
                ? $entrevista->mensaje_recepcion . "\n\n" . $notaSalida 
                : $notaSalida;

            $entrevista->update([
                'mensaje_recepcion' => $nuevoMensaje
            ]);

            if ($entrevista->user) {
                $entrevista->user->notify(new SalidaApoderado($entrevista));
            }

            \Flux::toast('Salida registrada exitosamente.', variant: 'success');
        }
    }

    public function abrirNuevoLugar()
    {
        $this->nuevoLugarNombre = '';
        $this->modalNuevoLugar = true;
    }

    public function guardarNuevoLugar()
    {
        $this->validate([
            'nuevoLugarNombre' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('lugares_atencion', 'nombre')
                    ->where('school_id', auth()->user()->current_school_id)
            ]
        ], [
            'nuevoLugarNombre.required' => 'El nombre del lugar es obligatorio.',
            'nuevoLugarNombre.unique' => 'Este lugar ya está registrado.',
        ]);

        LugarAtencion::create([
            'school_id' => auth()->user()->current_school_id,
            'nombre' => mb_strtoupper(trim($this->nuevoLugarNombre), 'UTF-8'),
            'activo' => true,
        ]);

        $this->modalNuevoLugar = false;
        $this->nuevoLugarNombre = '';
        
        Flux::toast('Lugar de atención agregado con éxito.', variant: 'success');
    }
};
?>
<div class="max-w-7xl mx-auto w-full pb-10">

    <x-header 
        titulo="Panel de Recepción" 
        subtitulo="Administra los ingresos y accesos programados para el recinto durante el día de hoy." 
        icono="building-office-2"
    >
        <div class="text-right flex items-center justify-end gap-3 text-sm text-zinc-500 font-medium mr-4">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </span>
            Actualización en vivo
        </div>
    </x-header>

 

    {{-- Cronograma Interactivo --}}
    <flux:card class="p-0 overflow-hidden mb-6 w-full border border-zinc-200 dark:border-zinc-800/80 shadow-lg dark:shadow-none bg-white dark:bg-zinc-900/80 dark:backdrop-blur-md" wire:poll.5m>
        <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-800/80 bg-gradient-to-r from-zinc-50 to-zinc-100 dark:from-zinc-900 dark:to-zinc-900/40">
            <h4 class="text-xl font-bold text-[#00376e] dark:text-white flex items-center gap-2">
                <flux:icon.list-bullet class="size-6 text-[#00376e] dark:text-zinc-200" />
                Cronograma de Entrevistas
            </h4>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="bg-zinc-100/50 dark:bg-zinc-950/60 text-zinc-600 dark:text-zinc-400 uppercase text-[11px] font-bold tracking-wider border-b border-zinc-200 dark:border-zinc-800/80">
                        <th class="px-6 py-4 w-32">Hora</th>
                        <th class="px-6 py-4">Alumno / Curso</th>
                        <th class="px-6 py-4">Apoderado</th>
                        <th class="px-6 py-4">Profesor Cita</th>
                        <th class="px-6 py-4 text-center">Estado</th>
                        <th class="px-6 py-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800/60">
                    @forelse($this->proximasEntrevistas as $cita)
                        @php
                            $isEven = $loop->even;
                            $rowClass = 'hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition-colors group';
                            $firstCellClass = 'px-6 py-5';
                            
                            if ($cita->estado === 'ingresada' && !str_contains($cita->mensaje_recepcion ?? '', '[SALIDA]')) {
                                // Visita Activa (dentro del recinto)
                                $rowClass .= ' bg-emerald-50/70 dark:bg-emerald-500/[0.08]';
                                $hourClass = 'text-emerald-700 dark:text-emerald-300';
                            } elseif ($cita->estado === 'ingresada' && str_contains($cita->mensaje_recepcion ?? '', '[SALIDA]')) {
                                // Se retiró
                                $rowClass .= ' bg-amber-50/30 dark:bg-amber-500/[0.04] text-zinc-500 dark:text-zinc-400';
                                $hourClass = 'text-zinc-500 dark:text-zinc-450';
                            } elseif ($cita->estado === 'pendiente') {
                                // Pendiente
                                $rowClass .= $isEven ? ' bg-zinc-50/60 dark:bg-zinc-900/80' : ' bg-white dark:bg-zinc-900/30';
                                $hourClass = 'text-[#00376e] dark:text-zinc-200';
                            } elseif ($cita->estado === 'realizada') {
                                // Realizada
                                $rowClass .= ' bg-zinc-100/40 dark:bg-zinc-950/40 text-zinc-500 dark:text-zinc-500 opacity-80 dark:opacity-60';
                                $hourClass = 'text-zinc-500 dark:text-zinc-500';
                            } else {
                                // Cancelada / Ausente
                                $rowClass .= ' bg-red-50/10 dark:bg-zinc-950/10 text-zinc-400 dark:text-zinc-650 opacity-65 dark:opacity-40';
                                $hourClass = 'text-zinc-400 dark:text-zinc-600';
                            }
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="{{ $firstCellClass }}">
                                @if($filtroTemporalidad !== 'dia')
                                    <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">{{ \Carbon\Carbon::parse($cita->fecha)->translatedFormat('d M') }}</p>
                                @endif
                                <span class="text-2xl font-black {{ $hourClass }}">
                                    {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
                                </span>
                            </td>
                            <td class="px-6 py-5">
                                @if($cita->estudiante)
                                    <p class="font-bold text-lg {{ in_array($cita->estado, ['cancelada', 'ausente']) ? 'text-zinc-400 dark:text-zinc-500 line-through decoration-zinc-300 dark:decoration-zinc-700' : (in_array($cita->estado, ['realizada']) || ($cita->estado === 'ingresada' && str_contains($cita->mensaje_recepcion ?? '', '[SALIDA]')) ? 'text-zinc-500 dark:text-zinc-400' : 'text-zinc-900 dark:text-white') }}">
                                        {{ $cita->estudiante->nombreCompleto() }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase mt-0.5">
                                        {{ $cita->estudiante->curso ? $cita->estudiante->curso->nombreCompleto() : 'Sin Curso' }}
                                    </p>
                                @else
                                    <p class="text-zinc-500">Estudiante Desvinculado</p>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                @if($cita->estudiante)
                                    <p class="font-bold text-base {{ in_array($cita->estado, ['cancelada', 'ausente']) ? 'text-zinc-400 dark:text-zinc-500 line-through decoration-zinc-300 dark:decoration-zinc-700' : (in_array($cita->estado, ['realizada']) || ($cita->estado === 'ingresada' && str_contains($cita->mensaje_recepcion ?? '', '[SALIDA]')) ? 'text-zinc-500 dark:text-zinc-400' : 'text-zinc-800 dark:text-zinc-200') }}">
                                        {{ $cita->estudiante->apoderado_nombres ? $cita->estudiante->apoderado_nombres . ' ' . $cita->estudiante->apoderado_apellido_pat : 'Sin nombre registrado' }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-mono mt-0.5">
                                        {{ $cita->estudiante->rut_numero ? $cita->estudiante->rutCompleto() : '-' }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                <p class="font-bold text-base {{ in_array($cita->estado, ['cancelada', 'ausente']) ? 'text-zinc-400 dark:text-zinc-500 line-through decoration-zinc-300 dark:decoration-zinc-700' : (in_array($cita->estado, ['realizada']) || ($cita->estado === 'ingresada' && str_contains($cita->mensaje_recepcion ?? '', '[SALIDA]')) ? 'text-zinc-500 dark:text-zinc-400' : 'text-zinc-800 dark:text-zinc-200') }}">
                                    {{ $cita->user ? trim($cita->user->nombres . ' ' . $cita->user->apellido_pat) : 'Usuario' }}
                                </p>
                            </td>
                            <td class="px-6 py-5 text-center">
                                @if($cita->estado === 'pendiente')
                                    <flux:badge size="sm" color="zinc" class="w-24 justify-center">Pendiente</flux:badge>
                                @elseif($cita->estado === 'ingresada')
                                    @if(str_contains($cita->mensaje_recepcion ?? '', '[SALIDA]'))
                                        <flux:badge size="sm" color="amber" class="w-24 justify-center">Se Retiró</flux:badge>
                                        <p class="text-[10px] text-zinc-500 mt-1">(Bitácora Abierta)</p>
                                    @else
                                        <flux:badge size="sm" color="emerald" class="w-24 justify-center">Ingresó</flux:badge>
                                        <p class="text-[10px] text-zinc-500 mt-1">({{ \Carbon\Carbon::parse($cita->hora_llegada)->format('H:i') }})</p>
                                    @endif
                                @elseif($cita->estado === 'realizada')
                                    <flux:badge size="sm" color="blue" class="w-24 justify-center">Realizada</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red" class="w-24 justify-center">{{ ucfirst($cita->estado) }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-5 text-right">
                                @if($cita->estado === 'pendiente')
                                    <flux:button variant="primary" wire:click="prepararIngreso({{ $cita->id }})" class="font-bold shadow-sm">
                                        {{ __('Registrar Ingreso') }}
                                    </flux:button>
                                @else
                                    <div class="flex flex-col items-end gap-2">
                                        <div class="inline-flex items-center justify-end gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold text-xs uppercase tracking-wide bg-emerald-50 dark:bg-emerald-900/20 px-3 py-1.5 rounded-md">
                                            <flux:icon.map-pin class="size-4" />
                                            {{ $cita->lugar ?? 'Ingresado' }}
                                        </div>
                                        @if(!str_contains($cita->mensaje_recepcion ?? '', '[SALIDA]') && $cita->estado !== 'realizada' && $cita->estado !== 'cancelada' && $cita->estado !== 'ausente')
                                            <flux:modal.trigger name="confirmar-salida-{{ $cita->id }}">
                                                <flux:button size="sm" variant="danger" class="font-bold shadow-sm">
                                                    Marcar Salida
                                                </flux:button>
                                            </flux:modal.trigger>

                                            <flux:modal name="confirmar-salida-{{ $cita->id }}" class="min-w-[22rem]">
                                                <div class="space-y-6 text-left">
                                                    <div>
                                                        <flux:heading size="lg">¿Confirmar salida del recinto?</flux:heading>
                                                        <flux:text class="mt-2 text-sm">
                                                            El sistema registrará la hora exacta en que el apoderado abandonó la institución.
                                                        </flux:text>
                                                    </div>
                                                    <div class="flex gap-2 justify-end">
                                                        <flux:modal.close>
                                                            <flux:button variant="ghost">Cancelar</flux:button>
                                                        </flux:modal.close>
                                                        <flux:modal.close>
                                                            <flux:button variant="primary" wire:click="registrarSalida({{ $cita->id }})">Sí, registrar salida</flux:button>
                                                        </flux:modal.close>
                                                    </div>
                                                </div>
                                            </flux:modal>
                                        @elseif(str_contains($cita->mensaje_recepcion ?? '', '[SALIDA]'))
                                            <span class="text-[10px] text-zinc-400 font-bold uppercase">Se retiró</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-zinc-500">
                                <flux:icon.calendar class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                                <p class="text-lg font-medium">No hay entrevistas agendadas</p>
                                <p class="text-sm mt-1 text-zinc-400">Intente cambiar el filtro de visualización superior.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->proximasEntrevistas->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $this->proximasEntrevistas->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Panel de Filtros --}}
    <flux:card class="p-0 overflow-hidden mb-10 border border-zinc-200 dark:border-zinc-800/80 shadow-md dark:shadow-none bg-white dark:bg-zinc-900/80 dark:backdrop-blur-md">
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800/80 bg-gradient-to-r from-zinc-50 to-zinc-100 dark:from-zinc-900 dark:to-zinc-900/40 flex items-center gap-2">
            <flux:icon.funnel class="size-5 text-[#00376e] dark:text-zinc-200" />
            <h4 class="text-sm font-black uppercase tracking-wider text-zinc-700 dark:text-white">
                {{ __('Filtros de Búsqueda') }}
            </h4>
        </div>
        
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <flux:field>
                <flux:label class="text-xs">{{ __('Buscar Texto') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="searchTexto" placeholder="Buscar Estudiante o Apoderado..." />
            </flux:field>

            <flux:field>
                <flux:label class="text-xs">{{ __('Filtrar por Profesor') }}</flux:label>
                <flux:select wire:model.live="filtroDocente">
                    <flux:select.option value="todos">{{ __('Todos los docentes') }}</flux:select.option>
                    @foreach($this->docentes as $docente)
                        <flux:select.option value="{{ $docente->id }}">{{ $docente->nombres }} {{ $docente->apellido_pat }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label class="text-xs">{{ __('Filtrar por Curso') }}</flux:label>
                <flux:select wire:model.live="filtroCurso">
                    <flux:select.option value="todos">{{ __('Todos los cursos') }}</flux:select.option>
                    @foreach($this->cursos as $curso)
                        <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label class="text-xs">{{ __('Temporalidad') }}</flux:label>
                <flux:radio.group wire:model.live="filtroTemporalidad" variant="segmented" class="w-full">
                    <flux:radio value="dia" label="Día" />
                    <flux:radio value="semana" label="Semana" />
                    <flux:radio value="mes" label="Mes" />
                </flux:radio.group>
            </flux:field>

            <flux:field>
                <flux:label class="text-xs">{{ __('Estado') }}</flux:label>
                <flux:select wire:model.live="filtroEstado">
                    <flux:select.option value="todos">{{ __('Todos los estados') }}</flux:select.option>
                    <flux:select.option value="pendientes">{{ __('Pendientes') }}</flux:select.option>
                    <flux:select.option value="ingresados">{{ __('Ingresados (En Recinto)') }}</flux:select.option>
                    <flux:select.option value="realizada">{{ __('Realizadas') }}</flux:select.option>
                    <flux:select.option value="cancelada">{{ __('Canceladas') }}</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
    </flux:card>

    {{-- Estado de Boxes --}}
    <flux:card class="p-0 overflow-hidden mb-10 w-full border border-zinc-200 dark:border-zinc-800/80 shadow-lg dark:shadow-none bg-white dark:bg-zinc-900/80 dark:backdrop-blur-md" wire:poll.10s>
        <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-800/80 flex flex-col sm:flex-row justify-between items-center gap-4 bg-gradient-to-r from-zinc-50 to-zinc-100 dark:from-zinc-900 dark:to-zinc-900/40">
            <h4 class="text-xl font-bold text-[#00376e] dark:text-white flex items-center gap-2">
                <flux:icon.building-office-2 class="size-6 text-[#00376e] dark:text-zinc-200" />
                Estado de Lugares y Boxes
            </h4>
            <flux:button wire:click="abrirNuevoLugar" variant="primary" size="sm" icon="plus">
                Agregar Lugar
            </flux:button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[600px]">
                <thead>
                    <tr class="bg-zinc-100/50 dark:bg-zinc-950/60 text-zinc-600 dark:text-zinc-400 uppercase text-[11px] font-bold tracking-wider border-b border-zinc-200 dark:border-zinc-800/80">
                        <th class="px-6 py-4">Lugar / Box</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4">Docente a cargo</th>
                        <th class="px-6 py-4">Apoderado en interior</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800/60">
                    @forelse($this->boxesStatus as $box)
                        @php
                            $isBoxEven = $loop->even;
                            $boxRowClass = 'hover:bg-zinc-50/80 dark:hover:bg-zinc-800/80 transition-colors group';
                            $boxFirstCellClass = 'px-6 py-4';
                            
                            if ($box->ocupado) {
                                $boxRowClass .= ' bg-red-50/25 dark:bg-red-950/15 text-zinc-900 dark:text-zinc-100';
                            } else {
                                $boxRowClass .= $isBoxEven ? ' bg-zinc-50/60 dark:bg-zinc-850/40' : ' bg-white dark:bg-zinc-900';
                                $boxRowClass .= ' text-zinc-500 dark:text-zinc-400';
                            }
                        @endphp
                        <tr class="{{ $boxRowClass }}">
                            <td class="{{ $boxFirstCellClass }}">
                                <span class="font-bold text-zinc-900 dark:text-white text-base">{{ $box->nombre }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if($box->ocupado)
                                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400 border border-red-200 dark:border-red-800">
                                        <span class="size-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                        Ocupado
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        Disponible
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($box->ocupado && $box->entrevista && $box->entrevista->user)
                                    <p class="font-bold text-zinc-900 dark:text-zinc-200 text-sm">
                                        {{ trim($box->entrevista->user->nombres . ' ' . $box->entrevista->user->apellido_pat) }}
                                    </p>
                                @else
                                    <span class="text-zinc-400 text-sm">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($box->ocupado && $box->entrevista && $box->entrevista->estudiante)
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $box->entrevista->estudiante->apoderado_nombres ? $box->entrevista->estudiante->apoderado_nombres . ' ' . $box->entrevista->estudiante->apoderado_apellido_pat : 'Apoderado' }}
                                    </p>
                                    <p class="text-[10px] text-zinc-500 font-mono mt-0.5">
                                        Ingreso: {{ \Carbon\Carbon::parse($box->entrevista->hora_llegada)->format('H:i') }}
                                    </p>
                                @else
                                    <span class="text-zinc-400 text-sm">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-zinc-500">
                                No hay lugares configurados en el sistema.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Métricas Bento --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
        <flux:card class="border-l-4 border-l-blue-600 bg-white dark:bg-zinc-900/80 dark:backdrop-blur-md dark:border-zinc-800/80 shadow-md dark:shadow-none">
            <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2">Total del Día</p>
            <p class="text-4xl font-extrabold text-zinc-900 dark:text-white">{{ str_pad($this->metricas['total_hoy'], 2, '0', STR_PAD_LEFT) }}</p>
            <p class="text-[11px] text-blue-600 dark:text-blue-400 font-bold mt-2 flex items-center gap-1">
                <flux:icon.calendar class="size-4" /> Actividad Normal
            </p>
        </flux:card>

        <flux:card class="border-l-4 border-l-amber-500 bg-white dark:bg-zinc-900/80 dark:backdrop-blur-md dark:border-zinc-800/80 shadow-md dark:shadow-none">
            <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2">Por LLegar (Pendientes)</p>
            <p class="text-4xl font-extrabold text-zinc-900 dark:text-white">{{ str_pad($this->metricas['pendientes'], 2, '0', STR_PAD_LEFT) }}</p>
            <p class="text-[11px] text-amber-600 font-bold mt-2 flex items-center gap-1">
                <flux:icon.clock class="size-4" /> En espera de acceso
            </p>
        </flux:card>

        <flux:card class="border-l-4 border-l-emerald-500 bg-white dark:bg-zinc-900/80 dark:backdrop-blur-md dark:border-zinc-800/80 shadow-md dark:shadow-none">
            <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2">Visitas Activas</p>
            <p class="text-4xl font-extrabold text-zinc-900 dark:text-white">{{ str_pad($this->metricas['registrados'], 2, '0', STR_PAD_LEFT) }}</p>
            <p class="text-[11px] text-emerald-600 font-bold mt-2 flex items-center gap-1">
                <flux:icon.check-circle class="size-4" /> Apoderados dentro del recinto
            </p>
        </flux:card>
    </div>

    {{-- Banner de Acciones Rápidas --}}
    <div class="mb-10 p-6 rounded-2xl bg-gradient-to-br from-blue-700 to-indigo-800 dark:from-blue-950/40 dark:to-indigo-950/30 text-white dark:border dark:border-blue-500/20 shadow-lg shadow-blue-900/20 dark:shadow-none flex flex-col md:flex-row items-center justify-between">
        <div class="space-y-1 mb-4 md:mb-0">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] opacity-80 text-blue-100 dark:text-blue-200">{{ __('Estado de Acceso') }}</p>
            <h3 class="text-3xl font-extrabold tracking-tight">{{ __('Reporte Diario de Entrevistas') }}</h3>
            <p class="text-blue-100 dark:text-blue-300 text-sm md:text-base font-medium mt-1">
                Hoy: {{ now('America/Santiago')->translatedFormat('l d \d\e F, Y') }} • {{ $this->metricas['total_hoy'] }} Citas programadas
            </p>
        </div>
        <div class="flex gap-4 w-full md:w-auto">
            <flux:button variant="primary" class="bg-white text-blue-800 dark:bg-white dark:text-blue-950 hover:bg-zinc-50" icon="user-plus">
                {{ __('Visita Externa (Próximamente)') }}
            </flux:button>
        </div>
    </div>

    {{-- Notas Panel (Muro Institucional) --}}
    <flux:card class="border-t-4 border-t-indigo-500 p-0 overflow-hidden bg-white dark:bg-zinc-900/80 dark:border-zinc-800/80 dark:shadow-none">
        <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-800/80 flex justify-between bg-zinc-50/50 dark:bg-zinc-900/30">
            <h4 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon.chat-bubble-left-ellipsis class="size-5 text-indigo-500" />
                Muro de Recepción (Próximamente)
            </h4>
        </div>
        <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
            // Aquí irá el panel de chat rápido institucional que mencionamos. 
            // Esperando a que el usuario confirme si creamos una tabla simple de "Notas" para alimentarlo.
        </div>
    </flux:card>

    {{-- Modal Llenado Box --}}
    <flux:modal wire:model="modalIngreso" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Registrar LLegada') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Asigna de forma inmediata el box o lugar físico hacia donde el apoderado debe ir a reunirse.') }}</flux:text>
            </div>

            <form wire:submit.prevent="registrarIngreso" class="space-y-4">
                <flux:select wire:model="lugarIngreso" label="Lugar / Anexo / Box" placeholder="Seleccione un box de atención...">
                    @foreach($this->lugares as $lugar)
                        <flux:select.option value="{{ $lugar->nombre }}">{{ $lugar->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="text-xs text-zinc-500 mt-1 mb-4">El sistema notificará a Inspectoría y guardará la hora exacta de llegada automáticamente.</div>

                <flux:textarea wire:model="mensajeRecepcion" label="Mensaje para el Profesor (Opcional)" placeholder="Ej: Apoderado viene acompañado de su hijo, o 'Apoderado muy ofuscado'..." rows="2" />

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button wire:click="$set('modalIngreso', false)" variant="ghost">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">Confirmar Acceso</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Modal Agregar Lugar de Atención --}}
    <flux:modal wire:model="modalNuevoLugar" class="md:w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Nuevo Lugar de Atención') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Ingresa el nombre del nuevo box, oficina o lugar donde se realizarán las entrevistas.') }}</flux:text>
            </div>

            <flux:input 
                wire:model="nuevoLugarNombre" 
                :label="__('Nombre del Lugar / Box')" 
                placeholder="EJ: BOX 5, OFICINA UTP, etc." 
                x-on:input="$event.target.value = $event.target.value.toLocaleUpperCase(); $wire.set('nuevoLugarNombre', $event.target.value)" 
            />
            <flux:error name="nuevoLugarNombre" />

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="$set('modalNuevoLugar', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="guardarNuevoLugar" variant="primary" class="ml-2">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
