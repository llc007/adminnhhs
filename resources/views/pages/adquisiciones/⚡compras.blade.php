<?php

use Livewire\Component;
use App\Models\Requerimiento;
use App\Models\RequerimientoItem;
use App\Models\ArticuloInventario;
use App\Models\ActaEntrega;
use App\Models\ActaEntregaDetalle;
use Illuminate\Support\Str;
use Flux\Flux;

new class extends Component
{
    // Requerimiento seleccionado para registrar compras
    public ?int $selectedId = null;

    // Item del requerimiento seleccionado para registrar ingreso
    public ?int $selectedItemId = null;

    // Campos del formulario de registro físico
    public string $modoIngreso = 'bodega'; // 'bodega' o 'gasto_directo' (No inventariable)
    public string $tipo = 'activo'; // 'activo' o 'consumible'
    public string $categoria = 'Tecnología';
    public string $marca = '';
    public string $modelo = '';
    public string $ubicacion = 'Bodega Central';
    public string $observaciones = '';

    // Arreglo dinámico para activos fijos: [unit_index => ['numero_serie' => '', 'codigo_patrimonial' => '']]
    public array $activosData = [];

    // Campos para consumibles
    public int $consumibleCantidad = 1;
    public string $consumibleCodigo = '';

    public function mount()
    {
        $this->selectFirstRequerimiento();
    }

    public function selectFirstRequerimiento()
    {
        $req = $this->requerimientos->first();
        if ($req) {
            $this->selectRequerimiento($req->id);
        } else {
            $this->selectedId = null;
            $this->selectedItemId = null;
        }
    }

    public function selectRequerimiento(int $id)
    {
        $this->selectedId = $id;
        $this->selectedItemId = null;

        // Buscar el primer ítem aprobado que falte por registrar
        $req = Requerimiento::with('items')->find($id);
        if ($req) {
            foreach ($req->items as $item) {
                if ($item->estado === 'aprobado_gerencia' && !$this->isItemTotalmenteIngresado($item)) {
                    $this->selectItem($item->id);
                    break;
                }
            }
        }
    }

    private function isItemTotalmenteIngresado(RequerimientoItem $item): bool
    {
        if (in_array($item->estado, ['comprado', 'entregado'])) {
            return true;
        }
        $ingresados = ArticuloInventario::where('requerimiento_item_id', $item->id)->sum('cantidad');
        return $ingresados >= $item->cantidad;
    }

    public function selectItem(int $itemId)
    {
        $this->selectedItemId = $itemId;
        $this->modoIngreso = 'bodega';
        $item = RequerimientoItem::find($itemId);

        // Intentar adivinar la categoría basada en la descripción
        $desc = Str::lower($item->descripcion);
        if (Str::contains($desc, ['computador', 'notebook', 'hp', 'lenovo', 'dell', 'pantalla', 'mouse', 'teclado', 'ti', 'tecnologia', 'impresora'])) {
            $this->categoria = 'Tecnología';
            $this->tipo = 'activo';
        } elseif (Str::contains($desc, ['silla', 'mesa', 'mueble', 'escritorio', 'pizarra'])) {
            $this->categoria = 'Mobiliario';
            $this->tipo = 'activo';
        } else {
            $this->categoria = 'Oficina';
            $this->tipo = 'consumible';
        }

        $this->marca = '';
        $this->modelo = '';
        $this->ubicacion = 'Bodega Central';
        $this->observaciones = '';

        $this->generarCodigosPropuestos($item);
    }

    public function updatedCategoria()
    {
        if ($this->selectedItemId) {
            $item = RequerimientoItem::find($this->selectedItemId);
            $this->generarCodigosPropuestos($item);
        }
    }

    private function generarCodigosPropuestos(RequerimientoItem $item)
    {
        // 3 letras categoría
        $catCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $this->categoria), 0, 3));
        if (strlen($catCode) < 3) $catCode = Str::padRight($catCode, 3, 'X');

        // 3 letras item
        $itemCode = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $item->descripcion), 0, 3));
        if (strlen($itemCode) < 3) $itemCode = Str::padRight($itemCode, 3, 'X');

        $prefix = "{$catCode}-{$itemCode}-";

        if ($this->tipo === 'activo') {
            $this->activosData = [];
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

            for ($i = 0; $i < $item->cantidad; $i++) {
                $corr = str_pad($startCorrelativo + $i, 3, '0', STR_PAD_LEFT);
                $this->activosData[] = [
                    'numero_serie' => '',
                    'codigo_patrimonial' => "{$prefix}{$corr}",
                ];
            }
        } else {
            // Consumible
            $this->consumibleCantidad = $item->cantidad;
            $ultimo = ArticuloInventario::where('codigo_patrimonial', 'like', $prefix . '%')
                ->orderBy('codigo_patrimonial', 'desc')
                ->first();

            $corrNum = 1;
            if ($ultimo) {
                $parts = explode('-', $ultimo->codigo_patrimonial);
                $num = (int) end($parts);
                if ($num > 0) {
                    $corrNum = $num + 1;
                }
            }
            $corr = str_pad($corrNum, 3, '0', STR_PAD_LEFT);
            $this->consumibleCodigo = "{$prefix}{$corr}";
        }
    }

    public function updatedTipo()
    {
        if ($this->selectedItemId) {
            $item = RequerimientoItem::find($this->selectedItemId);
            $this->generarCodigosPropuestos($item);
        }
    }

    #[\Livewire\Attributes\Computed]
    public function requerimientos()
    {
        $schoolId = auth()->user()->current_school_id;
        return Requerimiento::with(['user', 'items'])
            ->where('school_id', $schoolId)
            ->whereIn('estado', ['en_adquisicion', 'aprobado_parcialmente'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function selectedRequerimiento()
    {
        if (!$this->selectedId) {
            return null;
        }
        return Requerimiento::with(['user', 'items'])->find($this->selectedId);
    }

    #[\Livewire\Attributes\Computed]
    public function selectedItemModel()
    {
        if (!$this->selectedItemId) {
            return null;
        }
        return RequerimientoItem::find($this->selectedItemId);
    }

    public function registrarIngreso()
    {
        if (!$this->selectedItemId || !$this->selectedId) {
            return;
        }

        $item = $this->selectedItemModel();
        $requerimiento = $this->selectedRequerimiento();
        $schoolId = auth()->user()->current_school_id;

        // Si es Gasto Directo (No inventariable)
        if ($this->modoIngreso === 'gasto_directo') {
            $item->estado = 'entregado';
            $item->save();

            // Si todos los ítems del requerimiento han sido registrados, cambiar estado a 'recibido'
            $todosRegistrados = true;
            foreach ($requerimiento->items()->get() as $it) {
                if ($it->estado === 'aprobado_gerencia' && !$this->isItemTotalmenteIngresado($it)) {
                    $todosRegistrados = false;
                    break;
                }
            }

            if ($todosRegistrados) {
                $requerimiento->estado = 'recibido';
                $requerimiento->save();
            }

            \Flux::toast('Artículo marcado como Entregado Directamente (Gasto No Inventariable).', variant: 'success');

            $this->selectFirstRequerimiento();
            return;
        }

        // Si es a Bodega/Inventario
        if ($this->tipo === 'activo') {
            $this->validate([
                'activosData.*.codigo_patrimonial' => 'required|string|distinct|unique:articulo_inventarios,codigo_patrimonial',
                'activosData.*.numero_serie' => 'nullable|string',
                'categoria' => 'required|string',
                'ubicacion' => 'required|string',
            ], [
                'activosData.*.codigo_patrimonial.required' => 'El código patrimonial es obligatorio.',
                'activosData.*.codigo_patrimonial.unique' => 'Uno de los códigos patrimoniales ya está registrado en el inventario.',
                'activosData.*.codigo_patrimonial.distinct' => 'Los códigos patrimoniales ingresados deben ser distintos entre sí.',
            ]);

            // Crear registros individuales de activos
            $articulosCreados = [];
            foreach ($this->activosData as $data) {
                $articulo = ArticuloInventario::create([
                    'school_id' => $schoolId,
                    'requerimiento_item_id' => $item->id,
                    'tipo' => 'activo',
                    'codigo_patrimonial' => $data['codigo_patrimonial'],
                    'nombre' => $item->descripcion,
                    'categoria' => $this->categoria,
                    'marca' => $this->marca ?: null,
                    'modelo' => $this->modelo ?: null,
                    'numero_serie' => $data['numero_serie'] ?: null,
                    'cantidad' => 1,
                    'estado_conservacion' => 'excelente',
                    'ubicacion' => $this->ubicacion,
                    'responsable_user_id' => null, // Queda en bodega hasta firma
                    'fecha_ingreso' => now(),
                    'observaciones' => $this->observaciones ?: null,
                ]);
                $articulosCreados[] = $articulo;
            }
        } else {
            // Consumible
            $this->validate([
                'consumibleCodigo' => 'required|string|unique:articulo_inventarios,codigo_patrimonial',
                'consumibleCantidad' => 'required|integer|min:1',
                'categoria' => 'required|string',
                'ubicacion' => 'required|string',
            ], [
                'consumibleCodigo.required' => 'El código de barras/inventario es obligatorio.',
                'consumibleCodigo.unique' => 'El código patrimonial/barras ya está registrado.',
                'consumibleCantidad.min' => 'La cantidad ingresada debe ser al menos 1.',
            ]);

            $articulo = ArticuloInventario::create([
                'school_id' => $schoolId,
                'requerimiento_item_id' => $item->id,
                'tipo' => 'consumible',
                'codigo_patrimonial' => $this->consumibleCodigo,
                'nombre' => $item->descripcion,
                'categoria' => $this->categoria,
                'marca' => $this->marca ?: null,
                'modelo' => $this->modelo ?: null,
                'numero_serie' => null,
                'cantidad' => $this->consumibleCantidad,
                'estado_conservacion' => 'excelente',
                'ubicacion' => $this->ubicacion,
                'responsable_user_id' => null, // Consumibles se quedan en bodega
                'fecha_ingreso' => now(),
                'observaciones' => $this->observaciones ?: null,
            ]);
            $articulosCreados = [$articulo];
        }

        // Generar Acta de Entrega digital pendiente de firma para el solicitante
        $acta = ActaEntrega::create([
            'requerimiento_id' => $requerimiento->id,
            'recibe_user_id' => $requerimiento->user_id,
            'entrega_user_id' => auth()->id(),
            'fecha_entrega' => now(),
            'firmado_at' => null, // Pendiente de firma por el solicitante
        ]);

        foreach ($articulosCreados as $art) {
            ActaEntregaDetalle::create([
                'acta_entrega_id' => $acta->id,
                'articulo_inventario_id' => $art->id,
                'cantidad' => $art->cantidad,
                'numero_serie' => $art->numero_serie,
            ]);
        }

        // Si todos los ítems del requerimiento han sido registrados, cambiar estado a 'recibido'
        $todosRegistrados = true;
        foreach ($requerimiento->items()->get() as $it) {
            if ($it->estado === 'aprobado_gerencia' && !$this->isItemTotalmenteIngresado($it)) {
                $todosRegistrados = false;
                break;
            }
        }

        if ($todosRegistrados) {
            $requerimiento->estado = 'recibido';
            $requerimiento->save();
        }

        \Flux::toast('Artículos ingresados físicamente y Acta de Entrega digital generada para firma.', variant: 'success');

        $this->selectFirstRequerimiento();
    }
};
?>

<div class="flex flex-col gap-8 max-w-7xl mx-auto w-full pb-10">
    <x-header 
        titulo="Recepción e Ingreso de Compras" 
        subtitulo="Registre los números de serie e ingrese físicamente los artículos adquiridos a la Bodega." 
        icono="shopping-cart"
    />

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {{-- Listado de Requerimientos en Adquisición (4 cols) --}}
        <div class="lg:col-span-4 space-y-4">
            <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md">
                <flux:heading size="lg" class="mb-4">{{ __('Compras por Ingresar') }}</flux:heading>
                
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
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                    {{ __('Adquisición') }}
                                </span>
                            </div>
                            
                            <h3 class="font-bold text-zinc-800 dark:text-zinc-100 truncate w-full">
                                {{ $req->user->nombreCompleto() }}
                            </h3>
                            
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-1">
                                {{ $req->justificacion }}
                            </p>

                            <div class="flex justify-between items-center mt-2 pt-2 border-t border-zinc-100 dark:border-zinc-700/30 w-full text-xs">
                                <span class="text-zinc-500 font-bold">
                                    {{ $req->items->where('estado', 'aprobado_gerencia')->count() }} {{ __('aprobados') }}
                                </span>
                            </div>
                        </button>
                    @empty
                        <div class="p-8 text-center text-zinc-500 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                            <flux:icon.shopping-cart class="size-8 mx-auto text-zinc-400 mb-2" />
                            <p class="text-sm font-medium">{{ __('No hay compras pendientes.') }}</p>
                            <p class="text-xs text-zinc-400">{{ __('Todos los artículos comprados han sido ingresados.') }}</p>
                        </div>
                    @endforelse
                </div>
            </flux:card>
        </div>

        {{-- Formulario de Registro Físico (8 cols) --}}
        <div class="lg:col-span-8">
            @if($this->selectedRequerimiento)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {{-- Artículos del requerimiento --}}
                    <div class="md:col-span-1">
                        <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md space-y-4">
                            <flux:heading size="lg">{{ __('Artículos Aprobados') }}</flux:heading>
                            
                            <div class="space-y-2">
                                @foreach($this->selectedRequerimiento->items as $item)
                                    @if($item->estado === 'aprobado_gerencia')
                                        @php
                                            $completado = $this->isItemTotalmenteIngresado($item);
                                        @endphp
                                        <button 
                                            type="button" 
                                            wire:click="selectItem({{ $item->id }})" 
                                            class="w-full text-left p-3 rounded-lg border transition flex flex-col gap-1 {{ $completado ? 'bg-zinc-100 border-zinc-200 dark:bg-zinc-800/40 dark:border-zinc-700/30 opacity-60' : ($selectedItemId === $item->id ? 'bg-blue-50 border-blue-200 dark:bg-blue-900/10 dark:border-blue-800/30' : 'bg-white border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700/50') }}"
                                            :disabled="$completado"
                                        >
                                            <span class="font-bold text-xs truncate w-full text-zinc-700 dark:text-zinc-300">
                                                {{ $item->descripcion }}
                                            </span>
                                            <div class="flex justify-between items-center text-[10px] w-full mt-1">
                                                <span class="text-zinc-500 font-medium">Cant: {{ $item->cantidad }}</span>
                                                @if($completado)
                                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-0.5">
                                                        <flux:icon.check class="size-3" /> {{ __('Recibido') }}
                                                    </span>
                                                @else
                                                    <span class="text-amber-600 dark:text-amber-400 font-bold">
                                                        {{ __('Pendiente') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </flux:card>
                    </div>

                    {{-- Formulario detallado del artículo --}}
                    <div class="md:col-span-2">
                        @if($this->selectedItemModel)
                            <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 backdrop-blur-md space-y-6">
                                <div class="pb-4 border-b border-zinc-200 dark:border-zinc-700/50">
                                    <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider">{{ __('Registrar Recepción de Artículo') }}</span>
                                    <h3 class="text-xl font-extrabold text-zinc-800 dark:text-white mt-1">
                                        {{ $this->selectedItemModel->descripcion }}
                                    </h3>
                                    @if($this->selectedItemModel->observacion)
                                        <div class="text-xs text-zinc-500 mt-1">
                                            <span class="font-bold text-zinc-400">Obs de Solicitante:</span> {{ $this->selectedItemModel->observacion }}
                                        </div>
                                    @endif
                                    <p class="text-xs text-zinc-500 mt-2">
                                        {{ __('Total Solicitado: ') }}<span class="font-bold">{{ $this->selectedItemModel->cantidad }} unidades</span>
                                    </p>
                                </div>

                                <form wire:submit.prevent="registrarIngreso" class="space-y-6">
                                    {{-- Modo de Ingreso --}}
                                    <flux:select wire:model.live="modoIngreso" :label="__('Modo de Ingreso')">
                                        <flux:select.option value="bodega">{{ __('Ingresar a Bodega / Inventario (Equipos o Insumos)') }}</flux:select.option>
                                        <flux:select.option value="gasto_directo">{{ __('Gasto Directo (Servicio / Comida / No Inventariable)') }}</flux:select.option>
                                    </flux:select>

                                    @if($modoIngreso === 'bodega')
                                        {{-- Tipo e Info --}}
                                        <div class="grid grid-cols-2 gap-4">
                                            <flux:select wire:model.live="tipo" :label="__('Tipo de Artículo')">
                                                <flux:select.option value="activo">{{ __('Activo Fijo (Unidades Únicas)') }}</flux:select.option>
                                                <flux:select.option value="consumible">{{ __('Consumible (Suministros/Stock)') }}</flux:select.option>
                                            </flux:select>

                                            <flux:input wire:model.live="categoria" :label="__('Categoría')" placeholder="Ej: Tecnología, Mobiliario, Oficina" />
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <flux:input wire:model="marca" :label="__('Marca')" placeholder="Ej: HP, Dell, Kingston" />
                                            <flux:input wire:model="modelo" :label="__('Modelo')" placeholder="Ej: ProBook 440 G9" />
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <flux:input wire:model="ubicacion" :label="__('Ubicación de Destino')" placeholder="Ej: Bodega Central, Laboratorio 1" />
                                        </div>

                                        {{-- Si es Activo Fijo: Cargar números de serie y códigos patrimoniales unitarios --}}
                                        @if($tipo === 'activo')
                                            <div class="space-y-4">
                                                <flux:heading size="md">{{ __('Números de Serie y Códigos Patrimoniales') }}</flux:heading>
                                                <p class="text-xs text-zinc-500">
                                                    {{ __('Indique el número de serie de fábrica y revise o edite el código patrimonial correlativo propuesto por el sistema.') }}
                                                </p>

                                                <div class="space-y-3 max-h-[30vh] overflow-y-auto pr-1">
                                                    @foreach($activosData as $index => $data)
                                                        <div class="p-4 bg-white dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/60 rounded-xl grid grid-cols-2 gap-4 items-center">
                                                            <flux:input 
                                                                wire:model="activosData.{{ $index }}.numero_serie" 
                                                                label="Unidad #{{ $index + 1 }} - N° de Serie" 
                                                                placeholder="Ej: SN123456789" 
                                                            />
                                                            <flux:input 
                                                                wire:model="activosData.{{ $index }}.codigo_patrimonial" 
                                                                label="Código Patrimonial" 
                                                                placeholder="Ej: TEC-COM-001" 
                                                            />
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            {{-- Si es Consumible: Stock total y un solo código --}}
                                            <div class="p-4 bg-white dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/60 rounded-xl grid grid-cols-2 gap-4">
                                                <flux:input 
                                                    type="number" 
                                                    wire:model="consumibleCantidad" 
                                                    :label="__('Cantidad a Ingresar')" 
                                                    min="1" 
                                                />
                                                <flux:input 
                                                    wire:model="consumibleCodigo" 
                                                    :label="__('Código de Inventario/Barras')" 
                                                    placeholder="Ej: OFI-LAP-001" 
                                                />
                                            </div>
                                        @endif
                                    @else
                                        {{-- Gasto Directo --}}
                                        <div class="p-6 bg-blue-50 border border-blue-100 dark:bg-blue-900/10 dark:border-blue-800/30 rounded-2xl text-center">
                                            <flux:icon.building-storefront class="size-8 mx-auto text-blue-500 mb-3" />
                                            <h4 class="font-bold text-blue-800 dark:text-blue-300">{{ __('Entrega de Gasto Directo') }}</h4>
                                            <p class="text-xs text-blue-600/80 dark:text-blue-400/80 mt-2 leading-relaxed">
                                                {{ __('Este artículo (ej: comida, arriendo, servicio, evento) no se ingresará como activo físico en el inventario. Al registrar, se marcará directamente como entregado.') }}
                                            </p>
                                        </div>
                                    @endif

                                    <flux:textarea wire:model="observaciones" :label="__('Observaciones / Comentarios de Recepción')" placeholder="Ej: Se reciben cajas selladas en buen estado..." rows="2" />

                                    <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700/50">
                                        <flux:button type="submit" variant="primary" icon="document-check" class="bg-[#00376e] dark:bg-blue-600 text-white">
                                            {{ $modoIngreso === 'gasto_directo' ? __('Marcar como Entregado') : __('Ingresar a Bodega') }}
                                        </flux:button>
                                    </div>
                                </form>
                            </flux:card>
                        @else
                            <div class="bg-zinc-50/30 dark:bg-zinc-900/10 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-3xl p-16 text-center h-full flex flex-col justify-center items-center">
                                <flux:icon.check-circle class="size-12 text-zinc-300 dark:text-zinc-700 mb-4" />
                                <h4 class="font-bold text-zinc-400">{{ __('Seleccione un Artículo Pendiente') }}</h4>
                                <p class="text-xs text-zinc-400 mt-1">
                                    {{ __('Haga clic en un artículo de la columna izquierda para registrar su llegada física.') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="bg-zinc-50/30 dark:bg-zinc-900/10 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-3xl p-16 text-center h-full flex flex-col justify-center items-center">
                    <flux:icon.shopping-cart class="size-16 text-zinc-300 dark:text-zinc-700 mb-4" />
                    <h3 class="text-xl font-bold text-zinc-400">{{ __('Ningún requerimiento de compras seleccionado') }}</h3>
                    <p class="text-sm text-zinc-400 mt-2 max-w-sm mx-auto">
                        {{ __('Seleccione un requerimiento en la columna izquierda para ver y registrar sus artículos aprobados.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>