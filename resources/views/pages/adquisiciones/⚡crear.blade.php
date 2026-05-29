<?php

use Livewire\Component;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use App\Models\ArticuloInventario;
use Flux\Flux;

new class extends Component
{
    public string $justificacion = '';
    
    // Lista de ítems cargados en memoria
    public array $items = [];

    // Campos del formulario para agregar un nuevo ítem
    public string $descripcion = '';
    public int $cantidad = 1;
    public string $tienda_sugerida = '';
    public string $observacion = '';

    // Autocargar sugerencias para el autocompletador de descripción
    public array $sugerencias = [];

    public function updatedDescripcion()
    {
        if (strlen($this->descripcion) >= 3) {
            $this->sugerencias = ArticuloInventario::query()
                ->where('school_id', auth()->user()->current_school_id)
                ->where('nombre', 'like', '%' . $this->descripcion . '%')
                ->distinct()
                ->pluck('nombre')
                ->take(5)
                ->toArray();
        } else {
            $this->sugerencias = [];
        }
    }

    public function seleccionarSugerencia(string $nombre)
    {
        $this->descripcion = $nombre;
        $this->sugerencias = [];
    }

    public function agregarItem()
    {
        $this->validate([
            'descripcion' => 'required|string|min:3|max:255',
            'cantidad' => 'required|integer|min:1',
            'tienda_sugerida' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:500',
        ], [
            'descripcion.required' => 'La descripción del artículo es obligatoria.',
            'descripcion.min' => 'La descripción debe tener al menos 3 caracteres.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
        ]);

        $this->items[] = [
            'descripcion' => $this->descripcion,
            'cantidad' => $this->cantidad,
            'tienda_sugerida' => $this->tienda_sugerida ?: null,
            'observacion' => $this->observacion ?: null,
            'precio_estimado' => 0, // Desestimado de la interfaz por petición del usuario
        ];

        // Reset inputs de item
        $this->reset(['descripcion', 'cantidad', 'tienda_sugerida', 'observacion', 'sugerencias']);
        
        \Flux::toast('Artículo agregado a la lista.', variant: 'success');
    }

    public function eliminarItem(int $index)
    {
        if (isset($this->items[$index])) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
            \Flux::toast('Artículo eliminado de la lista.', variant: 'success');
        }
    }

    public function guardarRequerimiento()
    {
        $this->validate([
            'justificacion' => 'required|string|min:5|max:1000',
            'items' => 'required|array|min:1',
        ], [
            'justificacion.required' => 'La justificación o motivo del requerimiento es obligatoria.',
            'justificacion.min' => 'La justificación debe detallar al menos 5 caracteres.',
            'items.required' => 'Debe agregar al menos un artículo al requerimiento.',
            'items.min' => 'Debe agregar al menos un artículo al requerimiento.',
        ]);

        $schoolId = auth()->user()->current_school_id;

        $requerimiento = Requerimiento::create([
            'user_id' => auth()->id(),
            'school_id' => $schoolId,
            'justificacion' => $this->justificacion,
            'estado' => 'pendiente_rectoria',
        ]);

        foreach ($this->items as $item) {
            RequerimientoItem::create([
                'requerimiento_id' => $requerimiento->id,
                'descripcion' => $item['descripcion'],
                'cantidad' => $item['cantidad'],
                'precio_estimado' => 0, // Siempre 0 en DB ya que se sacó de la vista
                'tienda_sugerida' => $item['tienda_sugerida'],
                'observacion' => $item['observacion'],
                'estado' => 'pendiente',
            ]);
        }

        $this->reset(['justificacion', 'items']);
        
        \Flux::toast('Requerimiento creado exitosamente y enviado a Rectoría para revisión.', variant: 'success');
    }
};
?>

<div class="flex flex-col gap-8 max-w-7xl mx-auto w-full pb-10">
    <div class="flex flex-col gap-2 md:flex-row md:justify-between md:items-center">
        <div>
            <h1 class="text-3xl font-extrabold text-[#00376e] dark:text-blue-100 tracking-tight">Crear Requerimiento de Adquisición</h1>
            <p class="text-zinc-500 dark:text-zinc-400 font-medium">Ingrese la justificación y los artículos que desea solicitar para su aprobación.</p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()->hasRole(['directivo', 'administrador', 'superadmin']))
                <flux:button href="{{ route('adquisiciones.revision') }}" variant="ghost" icon="shield-check" wire:navigate>
                    {{ __('Bandeja de Revisión') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Panel Izquierdo: Datos de la Solicitud & Agregar Artículo --}}
        <div class="lg:col-span-1 space-y-6">
            <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
                        <flux:icon.document-text class="size-5" />
                    </div>
                    <flux:heading size="lg">{{ __('Detalles de la Solicitud') }}</flux:heading>
                </div>

                <div class="space-y-4">
                    <flux:textarea 
                        wire:model="justificacion" 
                        :label="__('Justificación institucional / Motivo de compra')" 
                        rows="4" 
                        placeholder="Ej: Se requieren computadores para equipar el nuevo laboratorio de computación de enseñanza media..." 
                    />
                </div>
            </flux:card>

            <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <flux:icon.plus class="size-5" />
                    </div>
                    <flux:heading size="lg">{{ __('Agregar Artículo') }}</flux:heading>
                </div>

                <form wire:submit.prevent="agregarItem" class="space-y-4">
                    {{-- Buscador Completador de Descripción --}}
                    <div class="relative">
                        <flux:input 
                            wire:model.live.debounce.150ms="descripcion" 
                            :label="__('Descripción del artículo')" 
                            placeholder="Ej: Computador HP ProBook" 
                            autocomplete="off"
                        />

                        {{-- Menú de Sugerencias --}}
                        @if (count($sugerencias) > 0)
                            <div class="absolute mt-1 w-full bg-white dark:bg-zinc-800 rounded-md shadow-lg border border-zinc-200 dark:border-zinc-700 z-50 overflow-hidden">
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-700 text-sm">
                                    @foreach ($sugerencias as $sug)
                                        <li>
                                            <button 
                                                type="button" 
                                                wire:click="seleccionarSugerencia('{{ $sug }}')"
                                                class="w-full text-left px-4 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition"
                                            >
                                                {{ $sug }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-1">
                            <flux:input 
                                type="number" 
                                wire:model="cantidad" 
                                :label="__('Cantidad')" 
                                min="1" 
                            />
                        </div>
                        <div class="col-span-2">
                            <flux:input 
                                wire:model="tienda_sugerida" 
                                :label="__('Proveedor Sugerido (Opcional)')" 
                                placeholder="Ej: PC Factory" 
                            />
                        </div>
                    </div>

                    <flux:textarea 
                        wire:model="observacion" 
                        :label="__('Observaciones / Especificaciones técnicas')" 
                        rows="2" 
                        placeholder="Ej: Con pantalla de 14'', procesador Core i5..." 
                    />

                    <div class="pt-2">
                        <flux:button type="submit" variant="primary" class="w-full bg-[#00376e] dark:bg-blue-600 text-white" icon="plus">
                            {{ __('Agregar a la lista') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>

        {{-- Panel Derecho: Listado de Artículos Solicitados --}}
        <div class="lg:col-span-2 space-y-6">
            <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md h-full flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
                                <flux:icon.shopping-cart class="size-5" />
                            </div>
                            <flux:heading size="lg">{{ __('Lista de Artículos Solicitados') }}</flux:heading>
                        </div>
                        <span class="px-3 py-1 bg-zinc-200 dark:bg-zinc-700 text-xs font-bold rounded-full">
                            {{ count($items) }} {{ count($items) === 1 ? 'artículo' : 'artículos' }}
                        </span>
                    </div>

                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden flex flex-col">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                                    <th class="px-4 py-3">{{ __('Artículo / Descripción') }}</th>
                                    <th class="px-4 py-3 text-center w-20">{{ __('Cant.') }}</th>
                                    <th class="px-4 py-3 text-center">{{ __('Proveedor') }}</th>
                                    <th class="px-4 py-3 text-center w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                                @forelse($items as $idx => $item)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $item['descripcion'] }}
                                            </div>
                                            @if($item['observacion'])
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                    <span class="font-bold text-zinc-400">Obs:</span> {{ $item['observacion'] }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center font-mono font-bold">
                                            {{ $item['cantidad'] }}
                                        </td>
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500 max-w-[120px] truncate" title="{{ $item['tienda_sugerida'] ?? '-' }}">
                                            {{ $item['tienda_sugerida'] ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button 
                                                type="button" 
                                                wire:click="eliminarItem({{ $idx }})" 
                                                class="text-red-500 hover:text-red-700 transition p-1 hover:bg-red-50 dark:hover:bg-red-950/30 rounded"
                                            >
                                                <flux:icon.trash class="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-zinc-500">
                                            <div class="flex flex-col items-center gap-2">
                                                <flux:icon.shopping-cart class="size-10 text-zinc-400" />
                                                <p class="font-medium text-sm">{{ __('No hay artículos en este requerimiento.') }}</p>
                                                <p class="text-xs text-zinc-400">{{ __('Use el formulario de la izquierda para agregar artículos.') }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if(count($items) > 0)
                    <div class="mt-8 pt-6 border-t border-zinc-200 dark:border-zinc-700 flex justify-end">
                        <flux:button 
                            wire:click="guardarRequerimiento" 
                            variant="primary" 
                            icon="check" 
                            class="w-full sm:w-auto bg-[#00376e] dark:bg-blue-600 text-white"
                        >
                            {{ __('Enviar Requerimiento') }}
                        </flux:button>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>