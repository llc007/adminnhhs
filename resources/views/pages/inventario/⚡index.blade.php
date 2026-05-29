<?php

use Livewire\Component;
use App\Models\ArticuloInventario;
use App\Models\User;
use Illuminate\Support\Str;
use Flux\Flux;

new class extends Component
{
    // Filtros
    public string $search = '';
    public string $filtroCategoria = '';
    public string $filtroUbicacion = '';
    public string $filtroTipo = '';
    public ?int $filtroResponsableId = null;

    // Modales
    public bool $modalAltaDirecta = false;
    public bool $modalEditarItem = false;

    // Campos Alta Directa
    public string $nuevoTipo = 'activo';
    public string $nuevoCodigo = '';
    public string $nuevoNombre = '';
    public string $nuevaCategoria = 'Tecnología';
    public string $nuevaMarca = '';
    public string $nuevoModelo = '';
    public ?string $nuevoSerial = '';
    public int $nuevaCantidad = 1;
    public string $nuevoEstado = 'excelente';
    public string $nuevaUbicacion = 'Bodega Central';
    public ?int $nuevoResponsableId = null;
    public string $nuevasObservaciones = '';

    // Campos Editar
    public ?int $editItemId = null;
    public ?int $editResponsableId = null;
    public string $editUbicacion = '';
    public string $editEstado = 'excelente';
    public string $editObservaciones = '';

    public function mount()
    {
        $this->generarCodigoPropuesto();
    }

    public function updatedNuevaCategoria()
    {
        $this->generarCodigoPropuesto();
    }

    public function updatedNuevoNombre()
    {
        $this->generarCodigoPropuesto();
    }

    public function updatedNuevoTipo()
    {
        $this->generarCodigoPropuesto();
    }

    private function generarCodigoPropuesto()
    {
        // 3 letras categoría
        $catCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $this->nuevaCategoria), 0, 3));
        if (strlen($catCode) < 3) $catCode = Str::padRight($catCode, 3, 'X');

        // 3 letras nombre/item
        $itemCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $this->nuevoNombre ?: 'ITEM'), 0, 3));
        if (strlen($itemCode) < 3) $itemCode = Str::padRight($itemCode, 3, 'X');

        $prefix = "{$catCode}-{$itemCode}-";

        // Buscar último correlativo en DB
        $ultimo = ArticuloInventario::where('codigo_patrimonial', 'like', $prefix . '%')
            ->orderBy('codigo_patrimonial', 'desc')
            ->first();

        $startCorrelativo = 1;
        if ($ultimo) {
            $parts = explode('-', $ultimo->codigo_patrimonial);
            $num = (int) end($parts);
            if ($num > 0) {
                $startCorrelativo = $num + 1;
            }
        }

        $corr = str_pad($startCorrelativo, 3, '0', STR_PAD_LEFT);
        $this->nuevoCodigo = "{$prefix}{$corr}";
    }

    public function guardarAltaDirecta()
    {
        $this->validate([
            'nuevoNombre' => 'required|string|min:3|max:255',
            'nuevaCategoria' => 'required|string',
            'nuevaCantidad' => 'required|integer|min:1|max:100',
            'nuevaUbicacion' => 'required|string',
        ], [
            'nuevoNombre.required' => 'El nombre del artículo es obligatorio.',
            'nuevoNombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nuevaCantidad.required' => 'La cantidad es obligatoria.',
            'nuevaCantidad.min' => 'La cantidad debe ser al menos 1.',
            'nuevaCantidad.max' => 'La cantidad no puede superar las 100 unidades por registro masivo.',
        ]);

        $schoolId = auth()->user()->current_school_id;

        // Generar prefijo de códigos
        $catCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $this->nuevaCategoria), 0, 3));
        if (strlen($catCode) < 3) $catCode = Str::padRight($catCode, 3, 'X');

        $itemCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $this->nuevoNombre), 0, 3));
        if (strlen($itemCode) < 3) $itemCode = Str::padRight($itemCode, 3, 'X');

        $prefix = "{$catCode}-{$itemCode}-";

        // Buscar último correlativo en DB
        $ultimo = ArticuloInventario::where('codigo_patrimonial', 'like', $prefix . '%')
            ->orderBy('codigo_patrimonial', 'desc')
            ->first();

        $startCorrelativo = 1;
        if ($ultimo) {
            $parts = explode('-', $ultimo->codigo_patrimonial);
            $num = (int) end($parts);
            if ($num > 0) {
                $startCorrelativo = $num + 1;
            }
        }

        if ($this->nuevoTipo === 'activo') {
            // Generar códigos patrimoniales correlativos
            $codigosAInsertar = [];
            for ($i = 0; $i < $this->nuevaCantidad; $i++) {
                $corr = str_pad($startCorrelativo + $i, 3, '0', STR_PAD_LEFT);
                $codigosAInsertar[] = "{$prefix}{$corr}";
            }

            // Validar unicidad de todos los códigos correlativos generados
            $existentes = ArticuloInventario::whereIn('codigo_patrimonial', $codigosAInsertar)->pluck('codigo_patrimonial')->toArray();
            if (count($existentes) > 0) {
                $this->addError('nuevoCodigo', 'Uno de los códigos correlativos ya existe: ' . implode(', ', $existentes));
                return;
            }

            // Crear las N unidades individuales
            for ($i = 0; $i < $this->nuevaCantidad; $i++) {
                ArticuloInventario::create([
                    'school_id' => $schoolId,
                    'tipo' => 'activo',
                    'codigo_patrimonial' => $codigosAInsertar[$i],
                    'nombre' => $this->nuevoNombre,
                    'categoria' => $this->nuevaCategoria,
                    'marca' => $this->nuevaMarca ?: null,
                    'modelo' => $this->nuevoModelo ?: null,
                    'numero_serie' => $this->nuevaCantidad === 1 ? ($this->nuevoSerial ?: null) : null,
                    'cantidad' => 1,
                    'estado_conservacion' => $this->nuevoEstado,
                    'ubicacion' => $this->nuevaUbicacion,
                    'responsable_user_id' => $this->nuevoResponsableId ?: null,
                    'fecha_ingreso' => now(),
                    'observaciones' => $this->nuevasObservaciones ?: null,
                ]);
            }
        } else {
            // Consumible (un solo registro que lleva la cantidad/stock total)
            $this->validate([
                'nuevoCodigo' => 'required|string|unique:articulo_inventarios,codigo_patrimonial',
            ], [
                'nuevoCodigo.required' => 'El código de barras / patrimonial es obligatorio.',
                'nuevoCodigo.unique' => 'El código de barras ya se encuentra registrado.',
            ]);

            ArticuloInventario::create([
                'school_id' => $schoolId,
                'tipo' => 'consumible',
                'codigo_patrimonial' => $this->nuevoCodigo,
                'nombre' => $this->nuevoNombre,
                'categoria' => $this->nuevaCategoria,
                'marca' => $this->nuevaMarca ?: null,
                'modelo' => $this->nuevoModelo ?: null,
                'numero_serie' => null,
                'cantidad' => $this->nuevaCantidad,
                'estado_conservacion' => $this->nuevoEstado,
                'ubicacion' => $this->nuevaUbicacion,
                'responsable_user_id' => $this->nuevoResponsableId ?: null,
                'fecha_ingreso' => now(),
                'observaciones' => $this->nuevasObservaciones ?: null,
            ]);
        }

        $this->modalAltaDirecta = false;
        
        // Reset campos
        $this->reset([
            'nuevoNombre', 'nuevaMarca', 'nuevoModelo', 'nuevoSerial', 'nuevaCantidad', 
            'nuevasObservaciones', 'nuevoResponsableId'
        ]);
        
        $this->generarCodigoPropuesto();

        \Flux::toast('Artículos registrados directamente en el inventario.', variant: 'success');
    }

    public function abrirEditar(int $id)
    {
        $item = ArticuloInventario::find($id);
        if ($item) {
            $this->editItemId = $id;
            $this->editResponsableId = $item->responsable_user_id;
            $this->editUbicacion = $item->ubicacion;
            $this->editEstado = $item->estado_conservacion;
            $this->editObservaciones = $item->observaciones ?? '';
            $this->modalEditarItem = true;
        }
    }

    public function guardarEdicion()
    {
        $this->validate([
            'editUbicacion' => 'required|string',
            'editEstado' => 'required|in:excelente,bueno,usado,regular,malo',
            'editObservaciones' => 'nullable|string',
        ]);

        $item = ArticuloInventario::find($this->editItemId);
        if ($item) {
            $item->update([
                'responsable_user_id' => $this->editResponsableId ?: null,
                'ubicacion' => $this->editUbicacion,
                'estado_conservacion' => $this->editEstado,
                'observaciones' => $this->editObservaciones ?: null,
            ]);

            $this->modalEditarItem = false;
            \Flux::toast('Artículo de inventario actualizado.', variant: 'success');
        }
    }

    #[\Livewire\Attributes\Computed]
    public function articulos()
    {
        $schoolId = auth()->user()->current_school_id;
        $query = ArticuloInventario::with('responsable')
            ->where('school_id', $schoolId);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                    ->orWhere('codigo_patrimonial', 'like', '%' . $this->search . '%')
                    ->orWhere('numero_serie', 'like', '%' . $this->search . '%')
                    ->orWhere('marca', 'like', '%' . $this->search . '%')
                    ->orWhere('modelo', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filtroCategoria !== '') {
            $query->where('categoria', $this->filtroCategoria);
        }

        if ($this->filtroUbicacion !== '') {
            $query->where('ubicacion', $this->filtroUbicacion);
        }

        if ($this->filtroTipo !== '') {
            $query->where('tipo', $this->filtroTipo);
        }

        if ($this->filtroResponsableId) {
            $query->where('responsable_user_id', $this->filtroResponsableId);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    #[\Livewire\Attributes\Computed]
    public function usuarios()
    {
        return User::orderBy('nombres', 'asc')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function categoriasExistentes()
    {
        $schoolId = auth()->user()->current_school_id;
        return ArticuloInventario::where('school_id', $schoolId)
            ->distinct()
            ->pluck('categoria');
    }

    #[\Livewire\Attributes\Computed]
    public function ubicacionesExistentes()
    {
        $schoolId = auth()->user()->current_school_id;
        return ArticuloInventario::where('school_id', $schoolId)
            ->distinct()
            ->pluck('ubicacion');
    }
};
?>

<div class="flex flex-col gap-8 max-w-7xl mx-auto w-full pb-10">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-[#00376e] dark:text-blue-100 tracking-tight">Inventario General</h1>
            <p class="text-zinc-500 dark:text-zinc-400 font-medium">Consulte, filtre y edite las custodias, ubicaciones y estados de conservación de todos los artículos.</p>
        </div>

        <div class="flex gap-2">
            <flux:button wire:click="$set('modalAltaDirecta', true)" variant="primary" icon="plus" class="bg-[#00376e] dark:bg-blue-600 text-white">
                {{ __('Alta Directa') }}
            </flux:button>
        </div>
    </div>

    {{-- Filtros y Buscador --}}
    <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="md:col-span-2">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    icon="magnifying-glass" 
                    placeholder="Buscar por código, nombre, marca o serie..." 
                    label="Buscador"
                />
            </div>
            
            <div>
                <flux:select wire:model.live="filtroTipo" label="Tipo">
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    <flux:select.option value="activo">{{ __('Activo Fijo') }}</flux:select.option>
                    <flux:select.option value="consumible">{{ __('Consumible') }}</flux:select.option>
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="filtroCategoria" label="Categoría">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach($this->categoriasExistentes as $cat)
                        <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="filtroUbicacion" label="Ubicación">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach($this->ubicacionesExistentes as $ub)
                        <flux:select.option value="{{ $ub }}">{{ $ub }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </flux:card>

    {{-- Tabla de Inventario --}}
    <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md overflow-hidden">
        <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3">{{ __('Código Patrimonial') }}</th>
                        <th class="px-4 py-3">{{ __('Artículo / Nombre') }}</th>
                        <th class="px-4 py-3">{{ __('Categoría') }}</th>
                        <th class="px-4 py-3">{{ __('Detalles') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Cant.') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Conservación') }}</th>
                        <th class="px-4 py-3">{{ __('Ubicación') }}</th>
                        <th class="px-4 py-3">{{ __('Responsable') }}</th>
                        <th class="px-4 py-3 text-center w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                    @forelse($this->articulos as $art)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition">
                            <td class="px-4 py-3 font-mono font-bold text-zinc-950 dark:text-white">
                                {{ $art->codigo_patrimonial }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $art->nombre }}</div>
                                <div class="text-[10px] text-zinc-400 capitalize">Tipo: {{ $art->tipo === 'activo' ? 'Activo Fijo' : 'Consumible' }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 font-medium">
                                {{ $art->categoria }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($art->marca || $art->modelo)
                                    <div><span class="text-zinc-400">M/M:</span> {{ $art->marca ?? '-' }} {{ $art->modelo ?? '' }}</div>
                                @endif
                                @if($art->numero_serie)
                                    <div><span class="text-zinc-400">S/N:</span> {{ $art->numero_serie }}</div>
                                @endif
                                @if(!$art->marca && !$art->modelo && !$art->numero_serie)
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                {{ $art->cantidad }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $color = match($art->estado_conservacion) {
                                        'excelente' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400',
                                        'bueno' => 'bg-teal-100 text-teal-700 dark:bg-teal-950/30 dark:text-teal-400',
                                        'usado' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-400',
                                        'regular' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400',
                                        default => 'bg-rose-100 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase {{ $color }}">
                                    {{ $art->estado_conservacion }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $art->ubicacion }}
                            </td>
                            <td class="px-4 py-3">
                                @if($art->responsable)
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-[10px] font-extrabold uppercase">
                                            {{ Str::substr($art->responsable->nombres, 0, 1) }}{{ Str::substr($art->responsable->apellido_pat, 0, 1) }}
                                        </div>
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200 truncate max-w-[150px]">
                                            {{ $art->responsable->nombreCompleto() }}
                                        </span>
                                    </div>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400 uppercase">
                                        {{ __('En Bodega') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button 
                                    type="button" 
                                    wire:click="abrirEditar({{ $art->id }})" 
                                    class="text-blue-500 hover:text-blue-700 p-1 hover:bg-blue-50 dark:hover:bg-blue-950/30 rounded"
                                    title="Editar custodias y estados"
                                >
                                    <flux:icon.pencil-square class="size-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-zinc-500">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.archive-box class="size-10 text-zinc-400" />
                                    <p class="font-medium text-sm">{{ __('No se encontraron artículos.') }}</p>
                                    <p class="text-xs text-zinc-400">{{ __('Intente remover filtros o realizar otra búsqueda.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->articulos->links() }}
        </div>
    </flux:card>

    {{-- Modal Alta Directa --}}
    <flux:modal wire:model="modalAltaDirecta" class="md:w-[35rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Registrar Artículo Directamente') }}</flux:heading>
            <flux:text>{{ __('Ingrese de forma manual un artículo al inventario sin pasar por adquisiciones.') }}</flux:text>
        </div>

        <form wire:submit.prevent="guardarAltaDirecta" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model.live="nuevoTipo" :label="__('Tipo de Artículo')">
                    <flux:select.option value="activo">{{ __('Activo Fijo') }}</flux:select.option>
                    <flux:select.option value="consumible">{{ __('Consumible') }}</flux:select.option>
                </flux:select>

                <flux:input wire:model.live="nuevaCategoria" :label="__('Categoría')" placeholder="Ej: Tecnología, Mobiliario, Oficina" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.live="nuevoNombre" :label="__('Nombre del Artículo')" placeholder="Ej: Laptop Lenovo L14" />
                
                @if($nuevoTipo === 'activo')
                    {{-- Para Activo Fijo, mostramos código base sugerido (se creará correlativo por cada unidad) --}}
                    <flux:input wire:model="nuevoCodigo" :label="__('Código Base Propuesto')" read-only disabled />
                @else
                    <flux:input wire:model="nuevoCodigo" :label="__('Código de Barras / Patrimonial')" />
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="nuevaMarca" :label="__('Marca (Opcional)')" />
                <flux:input wire:model="nuevoModelo" :label="__('Modelo (Opcional)')" />
            </div>

            <div class="grid grid-cols-2 gap-4 items-end">
                <flux:input type="number" wire:model.live="nuevaCantidad" :label="__('Cantidad a Registrar')" min="1" />
                
                @if($nuevoTipo === 'activo' && $nuevaCantidad == 1)
                    <flux:input wire:model="nuevoSerial" :label="__('Número de Serie (Opcional)')" />
                @else
                    <div class="text-xs text-zinc-400 italic pb-2">
                        @if($nuevoTipo === 'activo')
                            {{ __('Se generarán códigos patrimoniales correlativos automáticos.') }}
                        @else
                            {{ __('Se agregará como stock total en un solo registro.') }}
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="nuevoEstado" :label="__('Estado de Conservación')">
                    <flux:select.option value="excelente">{{ __('Excelente') }}</flux:select.option>
                    <flux:select.option value="bueno">{{ __('Bueno') }}</flux:select.option>
                    <flux:select.option value="usado">{{ __('Usado') }}</flux:select.option>
                    <flux:select.option value="regular">{{ __('Regular') }}</flux:select.option>
                    <flux:select.option value="malo">{{ __('Malo') }}</flux:select.option>
                </flux:select>

                <flux:input wire:model="nuevaUbicacion" :label="__('Ubicación')" />
            </div>

            <flux:select wire:model="nuevoResponsableId" :label="__('Responsable de Custodia (Opcional)')">
                <flux:select.option value="">{{ __('Dejar en Bodega (Sin Responsable)') }}</flux:select.option>
                @foreach($this->usuarios as $u)
                    <flux:select.option value="{{ $u->id }}">{{ $u->nombreCompleto() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="nuevasObservaciones" :label="__('Observaciones')" placeholder="Comentarios adicionales..." />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('modalAltaDirecta', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary" class="bg-[#00376e] dark:bg-blue-600 text-white">{{ __('Registrar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Editar --}}
    <flux:modal wire:model="modalEditarItem" class="md:w-[30rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Actualizar Custodia e Inventario') }}</flux:heading>
            <flux:text>{{ __('Modifique la ubicación, asigne el funcionario responsable o actualice su estado de conservación.') }}</flux:text>
        </div>

        <form wire:submit.prevent="guardarEdicion" class="space-y-4">
            <flux:select wire:model="editResponsableId" :label="__('Responsable de Custodia')">
                <flux:select.option value="">{{ __('En Bodega (Sin Responsable)') }}</flux:select.option>
                @foreach($this->usuarios as $u)
                    <flux:select.option value="{{ $u->id }}">{{ $u->nombreCompleto() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="editUbicacion" :label="__('Ubicación')" />

            <flux:select wire:model="editEstado" :label="__('Estado de Conservación')">
                <flux:select.option value="excelente">{{ __('Excelente') }}</flux:select.option>
                <flux:select.option value="bueno">{{ __('Bueno') }}</flux:select.option>
                <flux:select.option value="usado">{{ __('Usado') }}</flux:select.option>
                <flux:select.option value="regular">{{ __('Regular') }}</flux:select.option>
                <flux:select.option value="malo">{{ __('Malo') }}</flux:select.option>
            </flux:select>

            <flux:textarea wire:model="editObservaciones" :label="__('Observaciones')" placeholder="Comentarios de la actualización..." />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('modalEditarItem', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="primary" class="bg-[#00376e] dark:bg-blue-600 text-white">{{ __('Guardar Cambios') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>