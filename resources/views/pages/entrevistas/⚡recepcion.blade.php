<?php

use Livewire\Component;
use App\Models\Entrevista;
use App\Models\LugarAtencion;
use App\Notifications\IngresoApoderado;
use App\Notifications\SalidaApoderado;
use Carbon\Carbon;

new class extends Component {
    
    // Configuración para el modal de ingreso
    public bool $modalIngreso = false;
    public ?int $entrevistaSeleccionadaId = null;
    public string $lugarIngreso = '';
    public string $mensajeRecepcion = '';

    // Filtros de tabla
    public string $filtroEstado = 'todo'; // todo, pendientes, ingresados

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
    public function proximasEntrevistas()
    {
        $hoy = now('America/Santiago')->format('Y-m-d');
        $query = Entrevista::with(['estudiante.curso', 'user'])
                   ->where('school_id', auth()->user()->current_school_id)
                   ->whereDate('fecha', $hoy);

        if ($this->filtroEstado === 'pendientes') {
            $query->where('estado', 'pendiente');
        } elseif ($this->filtroEstado === 'ingresados') {
            $query->whereIn('estado', ['ingresada', 'realizada']);
        }

        return $query->orderBy('hora', 'asc')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function lugares()
    {
        return LugarAtencion::where('school_id', auth()->user()->current_school_id)
                ->where('activo', true)
                ->orderBy('nombre', 'asc')
                ->get();
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
};
?>
<div class="max-w-7xl mx-auto w-full pb-10">

    {{-- Encabezado Principal --}}
    <div class="flex items-start justify-between mb-8">
        <div>
            <flux:heading size="xl" level="1" class="text-[#00376e] dark:text-blue-400">{{ __('Panel de Recepción') }}</flux:heading>
            <flux:subheading size="lg" class="max-w-xl">
                {{ __('Administra los ingresos y accesos programados para el recinto durante el día de hoy.') }}
            </flux:subheading>
        </div>
        
        <div class="text-right flex items-center justify-end gap-3 text-sm text-zinc-500 font-medium">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </span>
            Actualización en vivo
        </div>
    </div>

    {{-- Banner de Acciones Rápidas --}}
    <div class="mb-10 p-6 rounded-2xl bg-gradient-to-br from-blue-700 to-indigo-800 text-white flex flex-col md:flex-row items-center justify-between shadow-lg shadow-blue-900/20">
        <div class="space-y-1 mb-4 md:mb-0">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] opacity-80 text-blue-100">{{ __('Estado de Acceso') }}</p>
            <h3 class="text-3xl font-extrabold tracking-tight">{{ __('Reporte Diario de Entrevistas') }}</h3>
            <p class="text-blue-100 text-sm md:text-base font-medium mt-1">
                Hoy: {{ now('America/Santiago')->translatedFormat('l d \d\e F, Y') }} • {{ $this->metricas['total_hoy'] }} Citas programadas
            </p>
        </div>
        <div class="flex gap-4 w-full md:w-auto">
            <flux:button variant="primary" class="bg-white text-blue-800 hover:bg-zinc-50" icon="user-plus">
                {{ __('Visita Externa (Próximamente)') }}
            </flux:button>
        </div>
    </div>

    {{-- Métricas Bento --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
        <flux:card class="border-l-4 border-l-blue-600 bg-white dark:bg-zinc-800">
            <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2">Total del Día</p>
            <p class="text-4xl font-extrabold text-zinc-900 dark:text-white">{{ str_pad($this->metricas['total_hoy'], 2, '0', STR_PAD_LEFT) }}</p>
            <p class="text-[11px] text-blue-600 dark:text-blue-400 font-bold mt-2 flex items-center gap-1">
                <flux:icon.calendar class="size-4" /> Actividad Normal
            </p>
        </flux:card>

        <flux:card class="border-l-4 border-l-amber-500 bg-white dark:bg-zinc-800">
            <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2">Por LLegar (Pendientes)</p>
            <p class="text-4xl font-extrabold text-zinc-900 dark:text-white">{{ str_pad($this->metricas['pendientes'], 2, '0', STR_PAD_LEFT) }}</p>
            <p class="text-[11px] text-amber-600 font-bold mt-2 flex items-center gap-1">
                <flux:icon.clock class="size-4" /> En espera de acceso
            </p>
        </flux:card>

        <flux:card class="border-l-4 border-l-emerald-500 bg-white dark:bg-zinc-800">
            <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase tracking-wider mb-2">Visitas Activas</p>
            <p class="text-4xl font-extrabold text-zinc-900 dark:text-white">{{ str_pad($this->metricas['registrados'], 2, '0', STR_PAD_LEFT) }}</p>
            <p class="text-[11px] text-emerald-600 font-bold mt-2 flex items-center gap-1">
                <flux:icon.check-circle class="size-4" /> Apoderados dentro del recinto
            </p>
        </flux:card>
    </div>

    {{-- Cronograma Interactivo --}}
    <flux:card class="p-0 overflow-hidden mb-10 w-full" wire:poll.5m>
        <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row justify-between items-center gap-4 bg-zinc-50/50 dark:bg-zinc-900/30">
            <h4 class="text-xl font-bold text-[#00376e] dark:text-blue-400 flex items-center gap-2">
                <flux:icon.list-bullet class="size-6" />
                Cronograma de Entrevistas
            </h4>
            <div class="flex gap-2">
                <flux:radio.group wire:model.live="filtroEstado" variant="segmented" size="sm">
                    <flux:radio value="todo" label="Todo" />
                    <flux:radio value="pendientes" label="Pdtes" />
                    <flux:radio value="ingresados" label="Ingresados" />
                </flux:radio.group>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="bg-zinc-100/50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400 uppercase text-[10px] font-black tracking-widest border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-6 py-3 w-28">Hora</th>
                        <th class="px-6 py-3">Alumno / Curso</th>
                        <th class="px-6 py-3">Apoderado</th>
                        <th class="px-6 py-3">Profesor Cita</th>
                        <th class="px-6 py-3 text-center">Estado</th>
                        <th class="px-6 py-3 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->proximasEntrevistas as $cita)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors group">
                            <td class="px-6 py-5">
                                <span class="text-xl font-extrabold text-[#00376e] dark:text-blue-400">
                                    {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
                                </span>
                            </td>
                            <td class="px-6 py-5">
                                @if($cita->estudiante)
                                    <p class="font-bold text-zinc-900 dark:text-white text-base">{{ $cita->estudiante->nombreCompleto() }}</p>
                                    <p class="text-xs text-zinc-500 font-medium uppercase mt-0.5">
                                        {{ $cita->estudiante->curso ? $cita->estudiante->curso->nombreCompleto() : 'Sin Curso' }}
                                    </p>
                                @else
                                    <p class="text-zinc-500">Estudiante Desvinculado</p>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                @if($cita->estudiante)
                                    <p class="font-bold text-zinc-900 dark:text-zinc-200">
                                        {{ $cita->estudiante->apoderado_nombres ? $cita->estudiante->apoderado_nombres . ' ' . $cita->estudiante->apoderado_apellido_pat : 'Sin nombre registrado' }}
                                    </p>
                                    <p class="text-xs text-zinc-500 font-mono mt-0.5">
                                        {{ $cita->estudiante->rut_numero ? $cita->estudiante->rutCompleto() : '-' }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                <p class="font-bold text-zinc-900 dark:text-zinc-200">
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
                                    <flux:button size="sm" variant="primary" wire:click="prepararIngreso({{ $cita->id }})">
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
                                                <flux:button size="xs" variant="ghost">
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
                                <p class="text-lg font-medium">No hay entrevistas agendadas para hoy</p>
                                <p class="text-sm mt-1 text-zinc-400">Intente cambiar el filtro de visualización superior.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Notas Panel (Muro Institucional) --}}
    <flux:card class="border-t-4 border-t-indigo-500 p-0 overflow-hidden">
        <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-700 flex justify-between bg-zinc-50/50 dark:bg-zinc-900/30">
            <h4 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon.chat-bubble-left-ellipsis class="size-5 text-indigo-500" />
                Muro de Recepción (Próximamente)
            </h4>
        </div>
        <div class="p-8 text-center text-zinc-500">
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
</div>
