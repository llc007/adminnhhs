<?php

use Livewire\Component;
use App\Models\ArticuloInventario;
use App\Models\RevisionInventario;
use App\Models\User;
use Flux\Flux;

new class extends Component
{
    public int $id;
    public ?ArticuloInventario $articuloBase = null;
    public array $editingItems = [];

    // Modal Detalles Físicos (Serie, Ubicación, Obs)
    public bool $modalFisicos = false;
    public ?int $selectedItemIdForFisicos = null;
    public string $editSerial = '';
    public string $editUbicacion = '';
    public string $editObservaciones = '';

    // Modal Historial de Revisiones/Mantenciones
    public bool $modalRevisiones = false;
    public ?int $selectedItemIdForRevision = null;
    public string $nuevaRevFecha = '';
    public string $nuevaRevDetalle = '';
    public string $nuevaRevRealizadoPor = '';
    public string $nuevaRevProximaFecha = '';

    // Modal Dar de Baja
    public bool $modalBaja = false;
    public ?int $selectedItemIdForBaja = null;
    public string $bajaFecha = '';
    public string $bajaMotivo = '';

    // Modal Descontar Stock (Consumibles)
    public bool $modalDescontar = false;
    public ?int $selectedItemIdForDescontar = null;
    public int $descontarCantidad = 1;
    public string $descontarMotivo = '';

    public function mount(int $id)
    {
        $this->articuloBase = ArticuloInventario::findOrFail($id);
        $this->cargarItems();
    }

    public function cargarItems()
    {
        $schoolId = auth()->user()->current_school_id;
        
        $query = ArticuloInventario::with(['responsable', 'ultimaRevision'])
            ->where('school_id', $schoolId)
            ->where('nombre', $this->articuloBase->nombre)
            ->where('categoria', $this->articuloBase->categoria)
            ->where('tipo', $this->articuloBase->tipo)
            ->whereDate('fecha_ingreso', $this->articuloBase->fecha_ingreso);

        if ($this->articuloBase->marca === null) {
            $query->whereNull('marca');
        } else {
            $query->where('marca', $this->articuloBase->marca);
        }

        if ($this->articuloBase->modelo === null) {
            $query->whereNull('modelo');
        } else {
            $query->where('modelo', $this->articuloBase->modelo);
        }

        $items = $query->orderBy('codigo_patrimonial', 'asc')->get();

        $this->editingItems = [];
        foreach ($items as $item) {
            $this->editingItems['item_' . $item->id] = [
                'id' => $item->id,
                'codigo_patrimonial' => $item->codigo_patrimonial,
                'estado_conservacion' => $item->estado_conservacion,
                'responsable_user_id' => $item->responsable_user_id,
                'fecha_baja' => $item->fecha_baja ? $item->fecha_baja->format('d/m/Y') : null,
                'motivo_baja' => $item->motivo_baja,
                'ultima_revision' => $item->ultimaRevision ? $item->ultimaRevision->fecha->format('d/m/Y') : 'Nunca',
                'cantidad' => $item->cantidad,
                'ubicacion' => $item->ubicacion,
            ];
        }
    }

    public function updatedEditingItems($value, $key)
    {
        // $key tiene el formato: "item_X.responsable_user_id" o "item_X.estado_conservacion"
        $parts = explode('.', $key);
        if (count($parts) < 2) {
            return;
        }

        $itemKey = $parts[0];
        $field = $parts[1];

        if (!isset($this->editingItems[$itemKey])) {
            return;
        }

        $data = $this->editingItems[$itemKey];

        // Si ya está de baja, omitir
        if ($data['fecha_baja']) {
            return;
        }

        if ($field === 'estado_conservacion') {
            $this->validateOnly("editingItems.{$itemKey}.estado_conservacion", [
                "editingItems.{$itemKey}.estado_conservacion" => 'required|in:excelente,bueno,usado,regular,malo',
            ]);
        } elseif ($field === 'responsable_user_id') {
            $this->validateOnly("editingItems.{$itemKey}.responsable_user_id", [
                "editingItems.{$itemKey}.responsable_user_id" => 'nullable|exists:users,id',
            ]);
        } else {
            return;
        }

        $item = ArticuloInventario::find($data['id']);
        if ($item) {
            $item->update([
                $field => $value ?: null,
            ]);

            // Registrar revisión automática
            $desc = $field === 'estado_conservacion'
                ? 'Actualización automática: Conservación cambiada a "' . ucfirst($value) . '".'
                : 'Actualización automática: Custodio asignado a "' . ($value ? User::find($value)->nombreCompleto() : 'En Bodega') . '".';

            RevisionInventario::create([
                'articulo_inventario_id' => $item->id,
                'fecha' => now(),
                'detalle' => $desc,
                'realizado_por' => auth()->user()->nombreCompleto(),
                'user_id' => auth()->id(),
            ]);

            \Flux::toast('Cambio guardado automáticamente.', variant: 'success');

            // Actualizar la fecha de última revisión localmente
            $item->load('ultimaRevision');
            $this->editingItems[$itemKey]['ultima_revision'] = $item->ultimaRevision ? $item->ultimaRevision->fecha->format('d/m/Y') : 'Nunca';
        }
    }

    // Modal Detalles Físicos
    public function abrirFisicos(int $itemId)
    {
        $item = ArticuloInventario::findOrFail($itemId);
        
        if ($item->fecha_baja) {
            \Flux::toast('No se pueden editar detalles físicos de artículos dados de baja.', variant: 'warning');
            return;
        }

        $this->selectedItemIdForFisicos = $itemId;
        $this->editSerial = $item->numero_serie ?? '';
        $this->editUbicacion = $item->ubicacion;
        $this->editObservaciones = $item->observaciones ?? '';
        $this->modalFisicos = true;
    }

    public function guardarFisicos()
    {
        $this->validate([
            'editUbicacion' => 'required|string|max:255',
            'editSerial' => 'nullable|string|max:255',
            'editObservaciones' => 'nullable|string|max:1000',
        ]);

        $item = ArticuloInventario::find($this->selectedItemIdForFisicos);
        if ($item) {
            $item->update([
                'numero_serie' => $this->editSerial ?: null,
                'ubicacion' => $this->editUbicacion,
                'observaciones' => $this->editObservaciones ?: null,
            ]);

            RevisionInventario::create([
                'articulo_inventario_id' => $item->id,
                'fecha' => now(),
                'detalle' => 'Actualización de datos físicos: Ubicación a "' . $this->editUbicacion . '"' . ($this->editSerial ? ', S/N: ' . $this->editSerial : ''),
                'realizado_por' => auth()->user()->nombreCompleto(),
                'user_id' => auth()->id(),
            ]);

            $this->modalFisicos = false;
            \Flux::toast('Detalles físicos actualizados correctamente.', variant: 'success');
            $this->cargarItems();
        }
    }

    // Modal Revisiones
    public function abrirRevisiones(int $itemId)
    {
        $this->selectedItemIdForRevision = $itemId;
        $this->nuevaRevFecha = now()->toDateString();
        $this->nuevaRevProximaFecha = '';
        $this->nuevaRevDetalle = '';
        $this->nuevaRevRealizadoPor = auth()->user()->nombreCompleto();
        $this->modalRevisiones = true;
    }

    public function guardarRevision()
    {
        $this->validate([
            'nuevaRevFecha' => 'required|date',
            'nuevaRevDetalle' => 'required|string|min:3',
            'nuevaRevRealizadoPor' => 'required|string|max:255',
            'nuevaRevProximaFecha' => 'nullable|date|after_or_equal:nuevaRevFecha',
        ]);

        RevisionInventario::create([
            'articulo_inventario_id' => $this->selectedItemIdForRevision,
            'fecha' => $this->nuevaRevFecha,
            'detalle' => $this->nuevaRevDetalle,
            'realizado_por' => $this->nuevaRevRealizadoPor,
            'fecha_proxima_revision' => $this->nuevaRevProximaFecha ?: null,
            'user_id' => auth()->id(),
        ]);

        \Flux::toast('Historial de revisión registrado con éxito.', variant: 'success');
        
        // Limpiar
        $this->nuevaRevDetalle = '';
        $this->nuevaRevProximaFecha = '';
        $this->nuevaRevRealizadoPor = auth()->user()->nombreCompleto();

        $this->cargarItems();
    }

    #[\Livewire\Attributes\Computed]
    public function revisionesItem()
    {
        if (!$this->selectedItemIdForRevision) {
            return collect();
        }

        return RevisionInventario::with('user')
            ->where('articulo_inventario_id', $this->selectedItemIdForRevision)
            ->orderBy('fecha', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // Modal Baja
    public function abrirBaja(int $itemId)
    {
        $item = ArticuloInventario::findOrFail($itemId);
        
        if ($item->fecha_baja) {
            \Flux::toast('Este artículo ya está dado de baja.', variant: 'warning');
            return;
        }

        $this->selectedItemIdForBaja = $itemId;
        $this->bajaFecha = now()->toDateString();
        $this->bajaMotivo = '';
        $this->modalBaja = true;
    }

    public function confirmarBaja()
    {
        $this->validate([
            'bajaFecha' => 'required|date',
            'bajaMotivo' => 'required|string|min:5|max:1000',
        ]);

        $item = ArticuloInventario::find($this->selectedItemIdForBaja);
        if ($item) {
            $item->update([
                'fecha_baja' => $this->bajaFecha,
                'motivo_baja' => $this->bajaMotivo,
                'responsable_user_id' => null, // Desasignar responsable
            ]);

            // Registrar en el historial de revisiones
            RevisionInventario::create([
                'articulo_inventario_id' => $item->id,
                'fecha' => $this->bajaFecha,
                'detalle' => 'DADO DE BAJA DEL INVENTARIO. Motivo: ' . $this->bajaMotivo,
                'realizado_por' => auth()->user()->nombreCompleto(),
                'user_id' => auth()->id(),
            ]);

            $this->modalBaja = false;
            \Flux::toast('El artículo fue dado de baja exitosamente.', variant: 'success');
            $this->cargarItems();
        }
    }

    // Modal Descontar Stock
    public function abrirDescontar(int $itemId)
    {
        $item = ArticuloInventario::findOrFail($itemId);
        $this->selectedItemIdForDescontar = $itemId;
        $this->descontarCantidad = 1;
        $this->descontarMotivo = '';
        $this->modalDescontar = true;
    }

    public function confirmarDescontar()
    {
        $item = ArticuloInventario::findOrFail($this->selectedItemIdForDescontar);
        
        $this->validate([
            'descontarCantidad' => 'required|integer|min:1|max:' . $item->cantidad,
            'descontarMotivo' => 'required|string|min:5|max:1000',
        ], [
            'descontarCantidad.max' => 'No puede descontar más del stock disponible (' . $item->cantidad . ').',
            'descontarMotivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $item->cantidad = $item->cantidad - $this->descontarCantidad;
        $item->save();

        // Registrar en el historial como consumo auditado
        RevisionInventario::create([
            'articulo_inventario_id' => $item->id,
            'fecha' => now(),
            'detalle' => "Consumo de stock: -{$this->descontarCantidad} unidades. Motivo: {$this->descontarMotivo}. Stock restante: {$item->cantidad}.",
            'realizado_por' => auth()->user()->nombreCompleto(),
            'user_id' => auth()->id(),
        ]);

        $this->modalDescontar = false;
        \Flux::toast('El stock fue descontado exitosamente.', variant: 'success');
        $this->cargarItems();
    }

    #[\Livewire\Attributes\Computed]
    public function usuarios()
    {
        return User::orderBy('nombres', 'asc')->get();
    }
};
?>

<div class="flex flex-col gap-8 max-w-7xl mx-auto w-full pb-10">
    <x-header 
        titulo="Detalle de Artículos" 
        subtitulo="Gestione las unidades físicas: asigne custodias, consulte y registre mantenciones o realice desincorporaciones." 
        icono="document-text"
    >
        <flux:button href="{{ route('inventario.index') }}" variant="ghost" icon="arrow-left">
            {{ __('Volver al Inventario') }}
        </flux:button>
    </x-header>

    {{-- Resumen del Lote --}}
    <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
            <div>
                <span class="text-zinc-400 text-xs uppercase font-semibold tracking-wider">{{ __('Artículo') }}</span>
                <div class="font-bold text-lg text-zinc-900 dark:text-white mt-1">{{ $this->articuloBase->nombre }}</div>
            </div>
            <div>
                <span class="text-zinc-400 text-xs uppercase font-semibold tracking-wider">{{ __('Categoría') }}</span>
                <div class="font-bold text-lg text-zinc-900 dark:text-white mt-1">{{ $this->articuloBase->categoria }}</div>
            </div>
            <div>
                <span class="text-zinc-400 text-xs uppercase font-semibold tracking-wider">{{ __('Marca / Modelo') }}</span>
                <div class="font-bold text-lg text-zinc-900 dark:text-white mt-1">
                    {{ $this->articuloBase->marca ?? '-' }} {{ $this->articuloBase->modelo ?? '' }}
                </div>
            </div>
            <div>
                <span class="text-zinc-400 text-xs uppercase font-semibold tracking-wider">{{ __('Fecha Adquisición') }}</span>
                <div class="font-bold text-lg text-zinc-900 dark:text-white mt-1">
                    {{ $this->articuloBase->fecha_ingreso ? $this->articuloBase->fecha_ingreso->format('d/m/Y') : '-' }}
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Tabla de Unidades --}}
    <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md overflow-hidden">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-zinc-900 dark:text-white text-base">
                {{ __('Unidades Registradas') }} ({{ count($this->editingItems) }} {{ __('ítems') }})
            </h3>
        </div>

        <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-zinc-100 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                            @if($this->articuloBase->tipo === 'consumible')
                                <th class="px-4 py-3 w-[30%]">{{ __('Código de Barras / Patrimonial') }}</th>
                                <th class="px-4 py-3 w-[35%]">{{ __('Ubicación Física') }}</th>
                                <th class="px-4 py-3 w-[20%] text-center">{{ __('Stock Disponible') }}</th>
                                <th class="px-4 py-3 text-center w-[15%]"></th>
                            @else
                                <th class="px-4 py-3 w-[20%]">{{ __('Código Patrimonial') }}</th>
                                <th class="px-4 py-3 w-[15%] text-center">{{ __('Última Revisión') }}</th>
                                <th class="px-4 py-3 w-[20%]">{{ __('Estado') }}</th>
                                <th class="px-4 py-3 w-[30%]">{{ __('Responsable de Custodia') }}</th>
                                <th class="px-4 py-3 text-center w-[15%]"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                        @foreach($this->editingItems as $key => $item)
                            @php 
                                $itemId = $item['id'];
                                $isBaja = $item['fecha_baja'] !== null;
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition {{ $isBaja ? 'opacity-60 bg-zinc-100/30 dark:bg-zinc-950/20' : '' }}">
                                @if($this->articuloBase->tipo === 'consumible')
                                    <td class="px-4 py-2 font-mono font-bold align-middle text-zinc-950 dark:text-white">
                                        {{ $item['codigo_patrimonial'] }}
                                    </td>
                                    <td class="px-4 py-2 text-zinc-700 dark:text-zinc-300 align-middle">
                                        {{ $item['ubicacion'] }}
                                    </td>
                                    <td class="px-4 py-2 text-center font-bold text-zinc-900 dark:text-white align-middle">
                                        {{ $item['cantidad'] }}
                                    </td>
                                    <td class="px-4 py-2 text-center align-middle space-x-1">
                                        <button 
                                            type="button" 
                                            wire:click="abrirDescontar({{ $itemId }})" 
                                            class="text-rose-500 hover:text-rose-700 p-1 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded"
                                            title="{{ __('Descontar / Consumir Stock') }}"
                                            @disabled($item['cantidad'] <= 0)
                                        >
                                            <flux:icon.minus-circle class="size-4" />
                                        </button>

                                        <button 
                                            type="button" 
                                            wire:click="abrirRevisiones({{ $itemId }})" 
                                            class="text-indigo-500 hover:text-indigo-700 p-1 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 rounded"
                                            title="{{ __('Ver historial de consumos y movimientos') }}"
                                        >
                                            <flux:icon.clock class="size-4" />
                                        </button>
                                    </td>
                                @else
                                    <td class="px-4 py-2 font-mono font-bold align-middle">
                                        <div class="flex items-center gap-2">
                                            <span class="{{ $isBaja ? 'text-zinc-400 line-through' : 'text-zinc-950 dark:text-white' }}">
                                                {{ $item['codigo_patrimonial'] }}
                                            </span>
                                            @if($isBaja)
                                                <span 
                                                    class="px-1.5 py-0.5 text-[9px] font-extrabold rounded bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 uppercase cursor-help"
                                                    title="De baja el {{ $item['fecha_baja'] }}. Motivo: {{ $item['motivo_baja'] }}"
                                                >
                                                    {{ __('Baja') }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-center text-zinc-500 font-medium align-middle">
                                        {{ $item['ultima_revision'] }}
                                    </td>
                                    <td class="px-4 py-2 align-middle">
                                        <select 
                                            wire:model.live="editingItems.{{ $key }}.estado_conservacion" 
                                            class="w-full px-2 py-1 text-xs border border-zinc-200 dark:border-zinc-700 rounded bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 focus:outline-none focus:border-blue-500 disabled:opacity-50"
                                            @disabled($isBaja)
                                        >
                                            <option value="excelente">{{ __('Excelente') }}</option>
                                            <option value="bueno">{{ __('Bueno') }}</option>
                                            <option value="usado">{{ __('Usado') }}</option>
                                            <option value="regular">{{ __('Regular') }}</option>
                                            <option value="malo">{{ __('Malo') }}</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2 align-middle">
                                        <select 
                                            wire:model.live="editingItems.{{ $key }}.responsable_user_id" 
                                            class="w-full px-2 py-1 text-xs border border-zinc-200 dark:border-zinc-700 rounded bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 focus:outline-none focus:border-blue-500 disabled:opacity-50"
                                            @disabled($isBaja)
                                        >
                                            <option value="">{{ __('En Bodega (Sin Responsable)') }}</option>
                                            @foreach($this->usuarios as $u)
                                                <option value="{{ $u->id }}">{{ $u->nombreCompleto() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-2 text-center align-middle space-x-1">
                                        <button 
                                            type="button" 
                                            wire:click="abrirFisicos({{ $itemId }})" 
                                            class="text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 p-1 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded {{ $isBaja ? 'opacity-40 cursor-not-allowed' : '' }}"
                                            title="{{ __('Editar detalles físicos (Ubicación, Serie, Obs)') }}"
                                            @disabled($isBaja)
                                        >
                                            <flux:icon.pencil-square class="size-4" />
                                        </button>

                                        <button 
                                            type="button" 
                                            wire:click="abrirRevisiones({{ $itemId }})" 
                                            class="text-indigo-500 hover:text-indigo-700 p-1 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 rounded"
                                            title="{{ __('Historial y registro de revisiones/mantenciones') }}"
                                        >
                                            <flux:icon.clock class="size-4" />
                                        </button>

                                        @if(!$isBaja)
                                            <button 
                                                type="button" 
                                                wire:click="abrirBaja({{ $itemId }})" 
                                                class="text-rose-500 hover:text-rose-700 p-1 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded"
                                                title="{{ __('Dar de baja este artículo') }}"
                                            >
                                                <flux:icon.archive-box class="size-4" />
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </flux:card>

    {{-- Modal Detalles Físicos --}}
    <flux:modal wire:model="modalFisicos" class="md:w-[30rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Detalles Físicos del Artículo') }}</flux:heading>
            <flux:text>{{ __('Edite la información física y observaciones complementarias de esta unidad.') }}</flux:text>
        </div>

        <form wire:submit.prevent="guardarFisicos" class="space-y-4">
            <flux:input wire:model="editSerial" :label="__('Número de Serie')" placeholder="S/N de fábrica..." />
            <flux:input wire:model="editUbicacion" :label="__('Ubicación Física')" placeholder="Ej: Laboratorio B, Sala de Profesores..." />
            <flux:textarea wire:model="editObservaciones" :label="__('Observaciones')" placeholder="Observaciones técnicas adicionales..." />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('modalFisicos', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary" class="bg-[#00376e] dark:bg-blue-600 text-white">{{ __('Guardar Detalles') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Historial de Revisiones/Mantenciones --}}
    <flux:modal wire:model="modalRevisiones" class="md:w-[45rem] space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $this->articuloBase->tipo === 'consumible' ? __('Historial de Movimientos y Consumos') : __('Bitácora de Mantenciones y Revisiones') }}
            </flux:heading>
            <flux:text>
                {{ $this->articuloBase->tipo === 'consumible' ? __('Consulte la bitácora de consumo de stock y egresos de este artículo.') : __('Consulte el historial de mantenciones o registre una nueva revisión técnica para este activo.') }}
            </flux:text>
        </div>

        {{-- Formulario para Nueva Revisión --}}
        @if($this->articuloBase->tipo !== 'consumible')
            <flux:card class="bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700">
                <h4 class="font-bold text-sm text-zinc-900 dark:text-white mb-4">{{ __('Registrar Nueva Mantención / Revisión') }}</h4>
                <form wire:submit.prevent="guardarRevision" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <flux:input type="date" wire:model="nuevaRevFecha" :label="__('Fecha de Revisión')" />
                        <flux:input wire:model="nuevaRevRealizadoPor" :label="__('Quién Realizó la Revisión')" placeholder="Nombre o empresa..." />
                        <flux:input type="date" wire:model="nuevaRevProximaFecha" :label="__('Fecha Próxima Revisión (Opcional)')" />
                    </div>
                    <flux:textarea wire:model="nuevaRevDetalle" :label="__('Detalle de la Revisión / Trabajo Realizado')" placeholder="Describa el trabajo o estado encontrado..." rows="2" />
                    
                    <div class="flex justify-end pt-2">
                        <flux:button type="submit" variant="primary" size="sm" class="bg-[#00376e] dark:bg-blue-600 text-white">
                            {{ __('Registrar en Bitácora') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        @endif

        {{-- Historial Timeline --}}
        <div class="space-y-4">
            <h4 class="font-bold text-sm text-zinc-900 dark:text-white">{{ __('Historial de Eventos') }}</h4>
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden max-h-[250px] overflow-y-auto">
                @if($this->revisionesItem->isNotEmpty())
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-zinc-100 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                                <th class="px-4 py-2 w-[18%]">{{ __('Fecha') }}</th>
                                <th class="px-4 py-2 w-[22%]">{{ __('Realizado Por') }}</th>
                                <th class="px-4 py-2 w-[40%]">{{ __('Detalle de la Revisión') }}</th>
                                <th class="px-4 py-2 w-[20%] text-center">{{ __('Próxima Revisión') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                            @foreach($this->revisionesItem as $rev)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition">
                                    <td class="px-4 py-2 font-medium align-middle">
                                        {{ $rev->fecha->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-2 text-zinc-700 dark:text-zinc-300 align-middle">
                                        {{ $rev->realizado_por }}
                                    </td>
                                    <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400 align-middle leading-relaxed whitespace-pre-line">
                                        {{ $rev->detalle }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-zinc-500 align-middle">
                                        {{ $rev->fecha_proxima_revision ? $rev->fecha_proxima_revision->format('d/m/Y') : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-4 py-8 text-center text-zinc-500 text-xs">
                        {{ $this->articuloBase->tipo === 'consumible' ? __('No hay registros de consumos para este insumo.') : __('No hay mantenciones o revisiones registradas para esta unidad.') }}
                    </div>
                @endif
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button wire:click="$set('modalRevisiones', false)" variant="ghost">{{ __('Cerrar') }}</flux:button>
        </div>
    </flux:modal>

    {{-- Modal Dar de Baja --}}
    <flux:modal wire:model="modalBaja" class="md:w-[30rem] space-y-6">
        <div>
            <flux:heading size="lg" class="text-rose-600 dark:text-rose-400">{{ __('Dar de Baja Artículo de Inventario') }}</flux:heading>
            <flux:text>{{ __('Esta acción desincorporará permanentemente la unidad física seleccionada de los listados y préstamos activos.') }}</flux:text>
        </div>

        <form wire:submit.prevent="confirmarBaja" class="space-y-4">
            <flux:input type="date" wire:model="bajaFecha" :label="__('Fecha de Baja')" />
            <flux:textarea wire:model="bajaMotivo" :label="__('Motivo de la Baja')" placeholder="Escriba la justificación detallada para dar de baja el artículo..." rows="3" />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('modalBaja', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="danger" class="bg-rose-600 dark:bg-rose-500 text-white">{{ __('Confirmar Baja') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Descontar Stock (Consumibles) --}}
    <flux:modal wire:model="modalDescontar" class="md:w-[30rem] space-y-6">
        <div>
            <flux:heading size="lg" class="text-rose-600 dark:text-rose-400">{{ __('Descontar Stock de Consumible') }}</flux:heading>
            <flux:text>{{ __('Registre un egreso manual de este insumo detallando la cantidad y la justificación del consumo.') }}</flux:text>
        </div>

        <form wire:submit.prevent="confirmarDescontar" class="space-y-4">
            <flux:input type="number" wire:model="descontarCantidad" :label="__('Cantidad a Descontar')" min="1" />
            <flux:textarea wire:model="descontarMotivo" :label="__('Motivo / Destinatario del Consumo')" placeholder="Escriba el motivo detallado de la entrega o uso del material..." rows="3" />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('modalDescontar', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="danger" class="bg-rose-600 dark:bg-rose-500 text-white">{{ __('Confirmar Descuento') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
