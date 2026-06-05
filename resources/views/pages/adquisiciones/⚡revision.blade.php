<?php

use Livewire\Component;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use Flux\Flux;

new class extends Component
{
    // Rol actual del revisor en la vista: 'rectoria' o 'gerencia'
    public string $rolRevisor = 'rectoria';

    // ID del requerimiento seleccionado para ver detalles
    public ?int $selectedId = null;

    // Comentario global de la revisión (cabecera)
    public string $comentarioCabecera = '';

    // Arreglo para almacenar los estados de los ítems en memoria antes de guardar
    // Estructura: [item_id => ['estado' => 'aprobado'|'rechazado'|'objetado', 'comentario' => '...']]
    public array $itemStates = [];

    public function mount()
    {
        // Detectar rol y preseleccionar
        $user = auth()->user();
        if ($user->hasRole(['directivo', 'administrador', 'superadmin'])) {
            $this->rolRevisor = 'rectoria';
        }
        
        $this->selectFirstRequerimiento();
    }

    public function selectFirstRequerimiento()
    {
        $req = $this->requerimientosQuery()->first();
        if ($req) {
            $this->selectRequerimiento($req->id);
        } else {
            $this->selectedId = null;
            $this->comentarioCabecera = '';
            $this->itemStates = [];
        }
    }

    public function selectRequerimiento(int $id)
    {
        $this->selectedId = $id;
        $req = Requerimiento::with('items')->find($id);
        
        $this->comentarioCabecera = $this->rolRevisor === 'rectoria' 
            ? ($req->comentarios_rectoria ?? '') 
            : ($req->comentarios_gerencia ?? '');

        $this->itemStates = [];
        foreach ($req->items as $item) {
            // Pre-rellenar con el estado actual
            $this->itemStates[$item->id] = [
                'estado' => $this->rolRevisor === 'rectoria' ? 'aprobado' : 'aprobado',
                'comentario' => $item->comentario_item ?? '',
            ];
        }
    }

    public function setItemEstado(int $itemId, string $estado)
    {
        if (isset($this->itemStates[$itemId])) {
            $this->itemStates[$itemId]['estado'] = $estado;
        }
    }

    public function changeRol(string $rol)
    {
        $this->rolRevisor = $rol;
        $this->selectFirstRequerimiento();
    }

    #[\Livewire\Attributes\Computed]
    public function requerimientos()
    {
        return $this->requerimientosQuery()->get();
    }

    private function requerimientosQuery()
    {
        $schoolId = auth()->user()->current_school_id;
        $query = Requerimiento::with(['user', 'items'])
            ->where('school_id', $schoolId);

        if ($this->rolRevisor === 'rectoria') {
            $query->where('estado', 'pendiente_rectoria');
        } else {
            $query->where('estado', 'pendiente_gerencia');
        }

        return $query->orderBy('created_at', 'asc');
    }

    #[\Livewire\Attributes\Computed]
    public function selectedRequerimiento()
    {
        if (!$this->selectedId) {
            return null;
        }
        return Requerimiento::with(['user', 'items'])->find($this->selectedId);
    }

    public function procesarRevision()
    {
        if (!$this->selectedId) {
            return;
        }

        $requerimiento = $this->selectedRequerimiento();
        
        $this->validate([
            'itemStates.*.estado' => 'required|in:aprobado,rechazado,objetado',
            'itemStates.*.comentario' => 'nullable|string|max:500',
            'comentarioCabecera' => 'nullable|string|max:1000',
        ]);

        $hasApproved = false;
        $hasRejectedOrObjected = false;

        foreach ($this->itemStates as $itemId => $data) {
            $item = RequerimientoItem::find($itemId);
            if ($item) {
                if ($this->rolRevisor === 'rectoria') {
                    if ($data['estado'] === 'aprobado') {
                        $item->estado = 'aprobado_rectoria';
                        $hasApproved = true;
                    } elseif ($data['estado'] === 'rechazado') {
                        $item->estado = 'rechazado_rectoria';
                        $hasRejectedOrObjected = true;
                    } else {
                        $item->estado = 'rechazado_rectoria'; // Objetado por rectoria se guarda como rechazo preliminar
                        $hasRejectedOrObjected = true;
                    }
                    $item->comentario_item = $data['comentario'];
                } else {
                    // Gerencia
                    if ($data['estado'] === 'aprobado') {
                        $item->estado = 'aprobado_gerencia';
                        $hasApproved = true;
                    } elseif ($data['estado'] === 'rechazado') {
                        $item->estado = 'rechazado_gerencia';
                        $hasRejectedOrObjected = true;
                    } else {
                        $item->estado = 'rechazado_gerencia';
                        $hasRejectedOrObjected = true;
                    }
                    $item->comentario_item = $data['comentario'];
                }
                $item->save();
            }
        }

        // Firma y transición de estados del requerimiento
        if ($this->rolRevisor === 'rectoria') {
            $requerimiento->firma_rectoria_at = now();
            $requerimiento->comentarios_rectoria = $this->comentarioCabecera ?: null;

            if (!$hasApproved) {
                // Si Rectoría rechazó todo
                $requerimiento->estado = 'rechazado';
            } else {
                // Si aprobó todo o parte, pasa a Gerencia
                $requerimiento->estado = 'pendiente_gerencia';
            }
        } else {
            // Gerencia
            $requerimiento->firma_gerencia_at = now();
            $requerimiento->comentarios_gerencia = $this->comentarioCabecera ?: null;

            if (!$hasApproved) {
                // Si Gerencia rechazó todo
                $requerimiento->estado = 'rechazado';
            } else {
                // Si aprobó todo
                if ($hasRejectedOrObjected) {
                    $requerimiento->estado = 'aprobado_parcialmente';
                } else {
                    $requerimiento->estado = 'en_adquisicion'; // Cambiado a en_adquisicion para compras
                }
            }
        }

        $requerimiento->save();

        \Flux::toast('Revisión firmada y registrada con éxito.', variant: 'success');

        $this->selectFirstRequerimiento();
    }
};
?>

<div class="flex flex-col gap-8 max-w-7xl mx-auto w-full pb-10">
    <x-header 
        titulo="Revisión y Aprobación" 
        subtitulo="Bandeja de revisión institucional para la adquisición de requerimientos." 
        icono="shield-check"
    >
        {{-- Selector de Rol de Revisión --}}
        <div class="bg-zinc-100 dark:bg-zinc-800 p-1 rounded-xl flex gap-1 self-start">
            <button 
                type="button"
                wire:click="changeRol('rectoria')" 
                class="px-4 py-2 text-sm font-semibold rounded-lg transition-all {{ $rolRevisor === 'rectoria' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }}"
            >
                {{ __('Rectoría') }}
            </button>
            <button 
                type="button"
                wire:click="changeRol('gerencia')" 
                class="px-4 py-2 text-sm font-semibold rounded-lg transition-all {{ $rolRevisor === 'gerencia' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }}"
            >
                {{ __('Gerencia') }}
            </button>
        </div>
    </x-header>

    {{-- Grid Principal --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {{-- Listado de Solicitudes Pendientes (4 cols) --}}
        <div class="lg:col-span-4 space-y-4">
            <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md">
                <flux:heading size="lg" class="mb-4">{{ __('Solicitudes Pendientes') }}</flux:heading>
                
                <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
                    @forelse($this->requerimientos as $req)
                        <button 
                            type="button" 
                            wire:click="selectRequerimiento({{ $req->id }})" 
                            class="w-full text-left p-4 rounded-xl border transition-all flex flex-col gap-2 {{ $selectedId === $req->id ? 'bg-blue-50/80 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40 shadow-sm' : 'bg-white border-zinc-200 hover:border-zinc-300 dark:bg-zinc-800 dark:border-zinc-700/50 dark:hover:border-zinc-600' }}"
                        >
                            <div class="flex justify-between items-start w-full">
                                <span class="text-xs font-bold text-zinc-500 uppercase">
                                    {{ $req->created_at->format('d/m/Y H:i') }}
                                </span>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                                    {{ $req->estado === 'pendiente_rectoria' ? 'Rectoría' : 'Gerencia' }}
                                </span>
                            </div>
                            
                            <h3 class="font-bold text-zinc-800 dark:text-zinc-100 truncate w-full">
                                {{ $req->user->nombreCompleto() }}
                            </h3>
                            
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">
                                {{ $req->justificacion }}
                            </p>

                            <div class="flex justify-between items-center mt-2 pt-2 border-t border-zinc-100 dark:border-zinc-700/30 w-full text-xs">
                                <span class="text-zinc-500 font-bold">{{ $req->items->count() }} {{ $req->items->count() === 1 ? 'artículo' : 'artículos' }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="p-8 text-center text-zinc-500 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                            <flux:icon.document-text class="size-8 mx-auto text-zinc-400 mb-2" />
                            <p class="text-sm font-medium">{{ __('No hay solicitudes pendientes.') }}</p>
                            <p class="text-xs text-zinc-400">{{ __('Toda la bandeja se encuentra al día.') }}</p>
                        </div>
                    @endforelse
                </div>
            </flux:card>
        </div>

        {{-- Detalle e Interfaz de Revisión (8 cols) --}}
        <div class="lg:col-span-8">
            @if($this->selectedRequerimiento)
                <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md space-y-6">
                    {{-- Ficha Header --}}
                    <div class="flex justify-between items-start pb-4 border-b border-zinc-200 dark:border-zinc-700/50">
                        <div>
                            <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Solicitante') }}</span>
                            <h2 class="text-2xl font-extrabold text-zinc-800 dark:text-white mt-1">
                                {{ $this->selectedRequerimiento->user->nombreCompleto() }}
                            </h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                                {{ __('Establecimiento: ') }} <span class="font-semibold">{{ $this->selectedRequerimiento->school->nombre ?? '-' }}</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Fecha Solicitud') }}</span>
                            <p class="text-sm font-semibold text-zinc-800 dark:text-white mt-1">
                                {{ $this->selectedRequerimiento->created_at->format('d \d\e F, Y - H:i') }}
                            </p>
                        </div>
                    </div>

                    {{-- Justificación --}}
                    <div>
                        <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Justificación institucional') }}</span>
                        <div class="mt-2 p-4 bg-white dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/50 rounded-xl">
                            <p class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                                {{ $this->selectedRequerimiento->justificacion }}
                            </p>
                        </div>
                    </div>

                    {{-- Comentarios Anteriores (si hay) --}}
                    @if($rolRevisor === 'gerencia' && $this->selectedRequerimiento->comentarios_rectoria)
                        <div class="p-4 bg-blue-50 border border-blue-100 dark:bg-blue-900/10 dark:border-blue-800/30 rounded-xl">
                            <h4 class="text-xs font-bold text-blue-800 dark:text-blue-300 uppercase tracking-wider">{{ __('Firma e Indicación de Rectoría') }}</h4>
                            <p class="text-xs text-blue-700 dark:text-blue-400 mt-1 italic">
                                "{{ $this->selectedRequerimiento->comentarios_rectoria }}"
                            </p>
                        </div>
                    @endif

                    {{-- Items a Evaluar --}}
                    <div>
                        <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Evaluación de Artículos') }}</span>
                        <div class="mt-3 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                                        <th class="px-4 py-3">{{ __('Artículo / Descripción') }}</th>
                                        <th class="px-4 py-3 text-center w-20">{{ __('Cant.') }}</th>
                                        <th class="px-4 py-3 text-center w-[240px]">{{ __('Acción de Aprobación') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                                    @foreach($this->selectedRequerimiento->items as $item)
                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition">
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100 font-bold">
                                                    {{ $item->descripcion }}
                                                </div>
                                                @if($item->observacion)
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                        <span class="font-bold text-zinc-400">Obs:</span> {{ $item->observacion }}
                                                    </div>
                                                @endif
                                                @if($item->tienda_sugerida)
                                                    <div class="mt-1">
                                                        <span class="text-[10px] bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded text-zinc-500 font-medium">
                                                            {{ __('Proveedor: ') }}{{ $item->tienda_sugerida }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center font-mono font-bold">
                                                {{ $item->cantidad }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-col gap-2">
                                                    <div class="bg-zinc-100 dark:bg-zinc-800 p-0.5 rounded-lg flex gap-1">
                                                        <button 
                                                            type="button"
                                                            wire:click="setItemEstado({{ $item->id }}, 'aprobado')"
                                                            class="flex-1 py-1 px-2 text-[10px] font-bold rounded transition-all {{ ($itemStates[$item->id]['estado'] ?? '') === 'aprobado' ? 'bg-emerald-500 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
                                                        >
                                                            {{ __('Aprobar') }}
                                                        </button>
                                                        <button 
                                                            type="button"
                                                            wire:click="setItemEstado({{ $item->id }}, 'objetado')"
                                                            class="flex-1 py-1 px-2 text-[10px] font-bold rounded transition-all {{ ($itemStates[$item->id]['estado'] ?? '') === 'objetado' ? 'bg-amber-500 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
                                                        >
                                                            {{ __('Objetar') }}
                                                        </button>
                                                        <button 
                                                            type="button"
                                                            wire:click="setItemEstado({{ $item->id }}, 'rechazado')"
                                                            class="flex-1 py-1 px-2 text-[10px] font-bold rounded transition-all {{ ($itemStates[$item->id]['estado'] ?? '') === 'rechazado' ? 'bg-rose-500 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
                                                        >
                                                            {{ __('Rechazar') }}
                                                        </button>
                                                    </div>
                                                    
                                                    @if(($itemStates[$item->id]['estado'] ?? '') !== 'aprobado')
                                                        <input 
                                                            type="text" 
                                                            wire:model="itemStates.{{ $item->id }}.comentario" 
                                                            placeholder="Indique el motivo u objeción..." 
                                                            class="text-xs bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700/60 rounded px-2 py-1 w-full"
                                                        />
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Formulario de Firma / Autorización --}}
                    <div class="pt-6 border-t border-zinc-200 dark:border-zinc-700/50 space-y-4">
                        <flux:textarea 
                            wire:model="comentarioCabecera" 
                            :label="__('Indicaciones / Comentarios Generales para la Firma')" 
                            placeholder="Ej: Se autorizan computadores HP sujeto a disponibilidad de presupuesto de informática..." 
                            rows="2"
                        />

                        <div class="flex flex-col sm:flex-row justify-end items-center gap-4">
                            <flux:button 
                                wire:click="procesarRevision" 
                                variant="primary" 
                                icon="pencil-square" 
                                class="w-full sm:w-auto bg-[#00376e] dark:bg-blue-600 text-white"
                            >
                                {{ $rolRevisor === 'rectoria' ? __('Firmar y Derivar a Gerencia') : __('Firmar y Autorizar Presupuesto') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @else
                <div class="bg-zinc-50/30 dark:bg-zinc-900/10 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-3xl p-16 text-center h-full flex flex-col justify-center items-center">
                    <flux:icon.shield-check class="size-16 text-zinc-300 dark:text-zinc-700 mb-4" />
                    <h3 class="text-xl font-bold text-zinc-400">{{ __('Ningún requerimiento seleccionado') }}</h3>
                    <p class="text-sm text-zinc-400 mt-2 max-w-sm mx-auto">
                        {{ __('Seleccione una solicitud de la columna izquierda para auditarla, revisar sus artículos y firmar digitalmente.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>