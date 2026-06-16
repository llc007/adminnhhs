<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ArticuloInventario;
use App\Models\User;
use Illuminate\Support\Str;
use Flux\Flux;

new class extends Component {
    use WithPagination;

    // Filtros
    public string $search = '';
    public string $filtroCategoriaId = '';
    public string $filtroSubcategoriaId = '';
    public string $filtroUbicacionId = '';
    public string $filtroTipo = '';
    public ?int $filtroResponsableId = null;
    public string $filtroEstado = 'activos';

    // Modales
    public bool $modalAltaDirecta = false;
    public string $nuevoTipo = 'activo';
    public string $nuevoCodigo = '';
    public string $nuevoNombre = '';

    // Categorías / Subcategorías / Ubicaciones dinámicas
    public ?int $nuevaCategoriaId = null;
    public string $searchCategoria = '';

    public ?int $nuevaSubcategoriaId = null;
    public string $searchSubcategoria = '';

    public ?int $nuevaUbicacionId = null;
    public string $searchUbicacion = '';

    public string $nuevaMarca = '';
    public string $nuevoModelo = '';
    public ?string $nuevoSerial = '';
    public int $nuevaCantidad = 1;
    public string $nuevoEstado = 'excelente';
    public ?int $nuevoResponsableId = null;
    public string $nuevasObservaciones = '';

    public function mount()
    {
        $this->generarCodigoPropuesto();
    }

    public function updated($property)
    {
        if (in_array($property, ['search', 'filtroCategoriaId', 'filtroSubcategoriaId', 'filtroUbicacionId', 'filtroTipo', 'filtroEstado'])) {
            $this->resetPage();
        }
    }

    public function updatedNuevaCategoriaId()
    {
        $this->nuevaSubcategoriaId = null;
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

    public function crearCategoria()
    {
        if (trim($this->searchCategoria) === '') {
            return;
        }

        $cat = \App\Models\InventarioCategoria::create([
            'school_id' => auth()->user()->current_school_id,
            'nombre' => trim($this->searchCategoria),
        ]);

        $this->nuevaCategoriaId = $cat->id;
        $this->searchCategoria = '';
        $this->generarCodigoPropuesto();
    }

    public function crearSubcategoria()
    {
        if (!$this->nuevaCategoriaId) {
            \Flux::toast('Debe seleccionar primero una Categoría para crear la subcategoría.', variant: 'warning');
            return;
        }
        if (trim($this->searchSubcategoria) === '') {
            return;
        }

        $sub = \App\Models\InventarioSubcategoria::create([
            'school_id' => auth()->user()->current_school_id,
            'categoria_id' => $this->nuevaCategoriaId,
            'nombre' => trim($this->searchSubcategoria),
        ]);

        $this->nuevaSubcategoriaId = $sub->id;
        $this->searchSubcategoria = '';
    }

    public function crearUbicacion()
    {
        if (trim($this->searchUbicacion) === '') {
            return;
        }

        $ub = \App\Models\InventarioUbicacion::create([
            'school_id' => auth()->user()->current_school_id,
            'nombre' => trim($this->searchUbicacion),
        ]);

        $this->nuevaUbicacionId = $ub->id;
        $this->searchUbicacion = '';
    }

    private function generarCodigoPropuesto()
    {
        $catName = 'TEC';
        if ($this->nuevaCategoriaId) {
            $catModel = \App\Models\InventarioCategoria::find($this->nuevaCategoriaId);
            if ($catModel) {
                $catName = $catModel->nombre;
            }
        }

        // 3 letras categoría
        $catCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $catName), 0, 3));
        if (strlen($catCode) < 3) {
            $catCode = Str::padRight($catCode, 3, 'X');
        }

        // 3 letras nombre/item
        $itemCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $this->nuevoNombre ?: 'ITEM'), 0, 3));
        if (strlen($itemCode) < 3) {
            $itemCode = Str::padRight($itemCode, 3, 'X');
        }

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
        $this->validate(
            [
                'nuevoNombre' => 'required|string|min:3|max:255',
                'nuevaCategoriaId' => 'required|integer|exists:inventario_categorias,id',
                'nuevaSubcategoriaId' => 'nullable|integer|exists:inventario_subcategorias,id',
                'nuevaUbicacionId' => 'required|integer|exists:inventario_ubicaciones,id',
                'nuevaCantidad' => 'required|integer|min:1|max:100',
            ],
            [
                'nuevoNombre.required' => 'El nombre del artículo es obligatorio.',
                'nuevoNombre.min' => 'El nombre debe tener al menos 3 caracteres.',
                'nuevaCategoriaId.required' => 'La categoría es obligatoria.',
                'nuevaUbicacionId.required' => 'La ubicación es obligatoria.',
                'nuevaCantidad.required' => 'La cantidad es obligatoria.',
                'nuevaCantidad.min' => 'La cantidad debe ser al menos 1.',
                'nuevaCantidad.max' => 'La cantidad no puede superar las 100 unidades por registro masivo.',
            ],
        );

        $schoolId = auth()->user()->current_school_id;
        $catModel = \App\Models\InventarioCategoria::findOrFail($this->nuevaCategoriaId);
        $ubModel = \App\Models\InventarioUbicacion::findOrFail($this->nuevaUbicacionId);

        $categoriaNombre = $catModel->nombre;
        $ubicacionNombre = $ubModel->nombre;

        // Generar prefijo de códigos
        $catCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $categoriaNombre), 0, 3));
        if (strlen($catCode) < 3) {
            $catCode = Str::padRight($catCode, 3, 'X');
        }

        $itemCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $this->nuevoNombre), 0, 3));
        if (strlen($itemCode) < 3) {
            $itemCode = Str::padRight($itemCode, 3, 'X');
        }

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
                    'categoria' => $categoriaNombre,
                    'categoria_id' => $this->nuevaCategoriaId,
                    'subcategoria_id' => $this->nuevaSubcategoriaId,
                    'marca' => $this->nuevaMarca ?: null,
                    'modelo' => $this->nuevoModelo ?: null,
                    'numero_serie' => $this->nuevaCantidad === 1 ? ($this->nuevoSerial ?: null) : null,
                    'cantidad' => 1,
                    'estado_conservacion' => $this->nuevoEstado,
                    'ubicacion' => $ubicacionNombre,
                    'ubicacion_id' => $this->nuevaUbicacionId,
                    'responsable_user_id' => $this->nuevoResponsableId ?: null,
                    'fecha_ingreso' => now(),
                    'observaciones' => $this->nuevasObservaciones ?: null,
                ]);
            }
        } else {
            // Consumible (un solo registro que lleva la cantidad/stock total)
            $this->validate(
                [
                    'nuevoCodigo' => 'required|string|unique:articulo_inventarios,codigo_patrimonial',
                ],
                [
                    'nuevoCodigo.required' => 'El código de barras / patrimonial es obligatorio.',
                    'nuevoCodigo.unique' => 'El código de barras ya se encuentra registrado.',
                ],
            );

            ArticuloInventario::create([
                'school_id' => $schoolId,
                'tipo' => 'consumible',
                'codigo_patrimonial' => $this->nuevoCodigo,
                'nombre' => $this->nuevoNombre,
                'categoria' => $categoriaNombre,
                'categoria_id' => $this->nuevaCategoriaId,
                'subcategoria_id' => $this->nuevaSubcategoriaId,
                'marca' => $this->nuevaMarca ?: null,
                'modelo' => $this->nuevoModelo ?: null,
                'numero_serie' => null,
                'cantidad' => $this->nuevaCantidad,
                'estado_conservacion' => $this->nuevoEstado,
                'ubicacion' => $ubicacionNombre,
                'ubicacion_id' => $this->nuevaUbicacionId,
                'responsable_user_id' => $this->nuevoResponsableId ?: null,
                'fecha_ingreso' => now(),
                'observaciones' => $this->nuevasObservaciones ?: null,
            ]);
        }

        $this->modalAltaDirecta = false;

        // Reset campos
        $this->reset(['nuevoNombre', 'nuevaMarca', 'nuevoModelo', 'nuevoSerial', 'nuevaCantidad', 'nuevasObservaciones', 'nuevoResponsableId', 'nuevaCategoriaId', 'nuevaSubcategoriaId', 'nuevaUbicacionId']);

        $this->generarCodigoPropuesto();

        \Flux::toast('Artículos registrados directamente en el inventario.', variant: 'success');
    }

    // Para eliminación
    public string $deleteNombre = '';
    public string $deleteCategoria = '';
    public string $deleteMarca = '';
    public string $deleteModelo = '';
    public string $deleteTipo = '';
    public ?string $deleteFechaIngreso = null;

    public function mostrarModalEliminar($nombre, $categoria, $marca, $modelo, $tipo, $fechaIngreso = null)
    {
        $this->deleteNombre = $nombre;
        $this->deleteCategoria = $categoria;
        $this->deleteMarca = $marca ?? '';
        $this->deleteModelo = $modelo ?? '';
        $this->deleteTipo = $tipo;
        $this->deleteFechaIngreso = $fechaIngreso;

        $this->js("\$flux.modal('modal-confirmar-eliminar').show()");
    }

    public function confirmarEliminar()
    {
        $this->eliminarArticulo($this->deleteNombre, $this->deleteCategoria, $this->deleteMarca, $this->deleteModelo, $this->deleteTipo, $this->deleteFechaIngreso);

        $this->js("\$flux.modal('modal-confirmar-eliminar').close()");
    }

    public function eliminarArticulo($nombre, $categoria, $marca, $modelo, $tipo, $fechaIngreso)
    {
        $schoolId = auth()->user()->current_school_id;
        $query = ArticuloInventario::where('school_id', $schoolId)->where('nombre', $nombre)->where('categoria', $categoria)->where('tipo', $tipo);

        if ($fechaIngreso) {
            $query->whereDate('fecha_ingreso', $fechaIngreso);
        } else {
            $query->whereNull('fecha_ingreso');
        }

        if ($marca === '') {
            $query->whereNull('marca');
        } else {
            $query->where('marca', $marca);
        }

        if ($modelo === '') {
            $query->whereNull('modelo');
        } else {
            $query->where('modelo', $modelo);
        }

        $count = $query->count();
        $query->delete();

        \Flux::toast("Se eliminaron {$count} artículos correctamente.", variant: 'success');
    }

    #[\Livewire\Attributes\Computed]
    public function articulos()
    {
        $schoolId = auth()->user()->current_school_id;
        $query = ArticuloInventario::select(['nombre', 'categoria', 'categoria_id', 'subcategoria_id', 'marca', 'modelo', 'tipo', 'fecha_ingreso', \Illuminate\Support\Facades\DB::raw('SUM(cantidad) as cantidad'), \Illuminate\Support\Facades\DB::raw('MIN(id) as id')])->where('school_id', $schoolId);

        if ($this->filtroEstado === 'activos') {
            $query->whereNull('fecha_baja');
        } elseif ($this->filtroEstado === 'de_baja') {
            $query->whereNotNull('fecha_baja');
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                    ->orWhere('codigo_patrimonial', 'like', '%' . $this->search . '%')
                    ->orWhere('numero_serie', 'like', '%' . $this->search . '%')
                    ->orWhere('marca', 'like', '%' . $this->search . '%')
                    ->orWhere('modelo', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filtroCategoriaId !== '') {
            $query->where('categoria_id', $this->filtroCategoriaId);
        }

        if ($this->filtroSubcategoriaId !== '') {
            $query->where('subcategoria_id', $this->filtroSubcategoriaId);
        }

        if ($this->filtroUbicacionId !== '') {
            $query->where('ubicacion_id', $this->filtroUbicacionId);
        }

        if ($this->filtroTipo !== '') {
            $query->where('tipo', $this->filtroTipo);
        }

        if ($this->filtroResponsableId) {
            $query->where('responsable_user_id', $this->filtroResponsableId);
        }

        return $query
            ->groupBy('nombre', 'categoria', 'categoria_id', 'subcategoria_id', 'marca', 'modelo', 'tipo', 'fecha_ingreso')
            ->orderBy('fecha_ingreso', 'desc')
            ->with(['subcategoriaRel', 'categoriaRel'])
            ->paginate(15);
    }

    #[\Livewire\Attributes\Computed]
    public function usuarios()
    {
        return User::orderBy('nombres', 'asc')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function categorias()
    {
        return \App\Models\InventarioCategoria::where('school_id', auth()->user()->current_school_id)
            ->orderBy('nombre', 'asc')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function subcategorias()
    {
        if (!$this->nuevaCategoriaId) {
            return collect();
        }
        return \App\Models\InventarioSubcategoria::where('school_id', auth()->user()->current_school_id)
            ->where('categoria_id', $this->nuevaCategoriaId)
            ->orderBy('nombre', 'asc')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function subcategoriasFiltradas()
    {
        if (!$this->filtroCategoriaId) {
            return collect();
        }
        return \App\Models\InventarioSubcategoria::where('school_id', auth()->user()->current_school_id)
            ->where('categoria_id', $this->filtroCategoriaId)
            ->orderBy('nombre', 'asc')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function ubicaciones()
    {
        return \App\Models\InventarioUbicacion::where('school_id', auth()->user()->current_school_id)
            ->orderBy('nombre', 'asc')
            ->get();
    }
};
?>

<div class="flex flex-col gap-8 max-w-7xl mx-auto w-full pb-10">
    <x-header titulo="Inventario General"
        subtitulo="Consulte, filtre y edite las custodias, ubicaciones y estados de conservación de todos los artículos."
        icono="archive-box">
        <flux:button wire:click="$set('modalAltaDirecta', true)" variant="primary" icon="plus"
            class="bg-[#00376e] dark:bg-blue-600 text-white">
            {{ __('Alta Directa') }}
        </flux:button>
    </x-header>

    {{-- Filtros y Buscador --}}
    <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-4 items-end">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Buscar por código, nombre, marca o serie..." label="Buscador" />
            </div>

            <div>
                <flux:select wire:model.live="filtroTipo" label="Tipo">
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    <flux:select.option value="activo">{{ __('Activo Fijo') }}</flux:select.option>
                    <flux:select.option value="consumible">{{ __('Consumible') }}</flux:select.option>
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="filtroCategoriaId" label="Categoría">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach ($this->categorias as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="filtroSubcategoriaId" label="Subcategoría"
                    :disabled="!$this->filtroCategoriaId">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach ($this->subcategoriasFiltradas as $sub)
                        <flux:select.option value="{{ $sub->id }}">{{ $sub->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="filtroUbicacionId" label="Ubicación">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach ($this->ubicaciones as $ub)
                        <flux:select.option value="{{ $ub->id }}">{{ $ub->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="filtroEstado" label="Estado">
                    <flux:select.option value="activos">{{ __('Activo') }}</flux:select.option>
                    <flux:select.option value="de_baja">{{ __('Dado de Baja') }}</flux:select.option>
                </flux:select>
            </div>
        </div>
    </flux:card>

    {{-- Tabla de Inventario --}}
    <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md overflow-hidden">
        <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr
                        class="bg-zinc-100 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3">{{ __('Artículo / Nombre') }}</th>
                        <th class="px-4 py-3">{{ __('Categoría') }}</th>
                        <th class="px-4 py-3">{{ __('Detalles') }}</th>
                        <th class="px-4 py-3">{{ __('Fecha de Adquisición') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Cant.') }}</th>
                        <th class="px-4 py-3 text-center w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                    @forelse($this->articulos as $art)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $art->nombre }}</div>
                                <div class="text-[10px] text-zinc-400 capitalize">Tipo:
                                    {{ $art->tipo === 'activo' ? 'Activo Fijo' : 'Consumible' }}</div>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 font-medium">
                                {{ $art->categoria }}
                                @if ($art->subcategoriaRel)
                                    <span
                                        class="text-xs text-zinc-400 block">{{ $art->subcategoriaRel->nombre }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($art->marca || $art->modelo)
                                    <div><span class="text-zinc-400">M/M:</span> {{ $art->marca ?? '-' }}
                                        {{ $art->modelo ?? '' }}</div>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-zinc-500 font-medium">
                                {{ $art->fecha_ingreso ? $art->fecha_ingreso->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-center font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                {{ $art->cantidad }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <flux:button href="{{ route('inventario.detalles', ['id' => $art->id]) }}"
                                        variant="ghost" icon="eye" size="sm"
                                        title="Ver detalles e individualizar" />
                                    <flux:button
                                        wire:click="mostrarModalEliminar('{{ addslashes($art->nombre) }}', '{{ addslashes($art->categoria) }}', '{{ addslashes($art->marca ?? '') }}', '{{ addslashes($art->modelo ?? '') }}', '{{ $art->tipo }}', '{{ $art->fecha_ingreso ? $art->fecha_ingreso->format('Y-m-d') : '' }}')"
                                        variant="ghost" icon="trash" size="sm"
                                        class="text-red-500 hover:text-red-700" title="Eliminar lote" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-zinc-500">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.archive-box class="size-10 text-zinc-400" />
                                    <p class="font-medium text-sm">{{ __('No se encontraron artículos.') }}</p>
                                    <p class="text-xs text-zinc-400">
                                        {{ __('Intente remover filtros o realizar otra búsqueda.') }}</p>
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
    <flux:modal wire:model="modalAltaDirecta" class="w-full md:max-w-5xl 2xl:max-w-7xl space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Registrar Artículo Directamente') }}</flux:heading>
            <flux:text>{{ __('Ingrese de forma manual un artículo al inventario sin pasar por adquisiciones.') }}
            </flux:text>
        </div>

        <form wire:submit.prevent="guardarAltaDirecta" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model.live="nuevoTipo" :label="__('Tipo de Artículo')">
                    <flux:select.option value="activo">{{ __('Activo Fijo') }}</flux:select.option>
                    <flux:select.option value="consumible">{{ __('Consumible') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="nuevaCategoriaId" variant="combobox" :label="__('Categoría')">
                    <x-slot name="input">
                        <flux:select.input wire:model="searchCategoria" placeholder="Buscar o crear..." />
                    </x-slot>
                    @foreach ($this->categorias as $cat)
                        <flux:select.option :value="$cat->id" :wire:key="'alta-cat-'.$cat->id">{{ $cat->nombre }}
                        </flux:select.option>
                    @endforeach
                    <flux:select.option.create wire:click="crearCategoria" min-length="2">
                        Crear "<span wire:text="searchCategoria"></span>"
                    </flux:select.option.create>
                </flux:select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.live="nuevoNombre" :label="__('Nombre del Artículo')"
                    placeholder="Ej: Laptop Lenovo L14" />

                @if ($nuevoTipo === 'activo')
                    {{-- Para Activo Fijo, mostramos código base sugerido (se creará correlativo por cada unidad) --}}
                    <flux:input wire:model="nuevoCodigo" :label="__('Código Base Propuesto')" read-only disabled />
                @else
                    <flux:input wire:model="nuevoCodigo" :label="__('Código de Barras / Patrimonial')" />
                @endif
            </div>

            <div class="grid grid-cols-3 gap-4">
                <flux:select wire:model.live="nuevaSubcategoriaId" variant="combobox" :label="__('Subcategoría')"
                    :disabled="!$nuevaCategoriaId">
                    <x-slot name="input">
                        <flux:select.input wire:model="searchSubcategoria" placeholder="Buscar o crear..." />
                    </x-slot>
                    @foreach ($this->subcategorias as $sub)
                        <flux:select.option :value="$sub->id" :wire:key="'alta-sub-'.$sub->id">{{ $sub->nombre }}
                        </flux:select.option>
                    @endforeach
                    <flux:select.option.create wire:click="crearSubcategoria" min-length="2">
                        Crear "<span wire:text="searchSubcategoria"></span>"
                    </flux:select.option.create>
                </flux:select>

                <flux:input wire:model="nuevaMarca" :label="__('Marca (Opcional)')" />
                <flux:input wire:model="nuevoModelo" :label="__('Modelo (Opcional)')" />
            </div>

            <div class="grid grid-cols-2 gap-4 items-end">
                <flux:input type="number" wire:model.live="nuevaCantidad" :label="__('Cantidad a Registrar')"
                    min="1" />

                @if ($nuevoTipo === 'activo' && $nuevaCantidad == 1)
                    <flux:input wire:model="nuevoSerial" :label="__('Número de Serie (Opcional)')" />
                @else
                    <div class="text-xs text-zinc-400 italic pb-2">
                        @if ($nuevoTipo === 'activo')
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

                <flux:select wire:model="nuevaUbicacionId" variant="combobox" :label="__('Ubicación')">
                    <x-slot name="input">
                        <flux:select.input wire:model="searchUbicacion" placeholder="Buscar o crear..." />
                    </x-slot>
                    @foreach ($this->ubicaciones as $ub)
                        <flux:select.option :value="$ub->id" :wire:key="'alta-ub-'.$ub->id">{{ $ub->nombre }}
                        </flux:select.option>
                    @endforeach
                    <flux:select.option.create wire:click="crearUbicacion" min-length="2">
                        Crear "<span wire:text="searchUbicacion"></span>"
                    </flux:select.option.create>
                </flux:select>
            </div>

            <flux:select wire:model="nuevoResponsableId" :label="__('Responsable de Custodia (Opcional)')">
                <flux:select.option value="">{{ __('Dejar en Bodega (Sin Responsable)') }}</flux:select.option>
                @foreach ($this->usuarios as $u)
                    <flux:select.option value="{{ $u->id }}">{{ $u->nombreCompleto() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="nuevasObservaciones" :label="__('Observaciones')"
                placeholder="Comentarios adicionales..." />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button wire:click="$set('modalAltaDirecta', false)" variant="ghost">{{ __('Cancelar') }}
                </flux:button>
                <flux:button type="submit" variant="primary" class="bg-[#00376e] dark:bg-blue-600 text-white">
                    {{ __('Registrar') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Confirmación de Eliminación --}}
    <flux:modal name="modal-confirmar-eliminar" class="md:w-[25rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('¿Confirmar eliminación?') }}</flux:heading>
            <flux:text>
                {{ __('Esta acción eliminará de forma permanente todos los artículos de este lote del inventario.') }}
            </flux:text>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button x-on:click="$flux.modal('modal-confirmar-eliminar').close()" variant="ghost">
                {{ __('Cancelar') }}</flux:button>
            <flux:button wire:click="confirmarEliminar" variant="danger">{{ __('Eliminar') }}</flux:button>
        </div>
    </flux:modal>
</div>
