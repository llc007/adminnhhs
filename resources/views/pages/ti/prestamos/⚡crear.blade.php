<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\User;
use App\Models\ArticuloInventario;
use App\Models\Prestamo;
use Carbon\Carbon;

new #[Title('Registrar Préstamo')] class extends Component
{
    // Form variables
    public ?int $user_id = null;
    public ?int $articulo_inventario_id = null;
    public string $nombre_articulo = '';
    public string $marca = '';
    public string $modelo = '';
    public string $numero_serie = '';
    public int $cantidad = 1;
    public string $fecha_prestamo = '';
    public string $fecha_devolucion_estimada = '';
    public string $observaciones = '';

    // Autocomplete list
    public string $search_articulo = '';
    public array $sugerencias = [];

    public function mount(): void
    {
        $this->fecha_prestamo = now()->toDateString();
        // Por defecto una semana
        $this->fecha_devolucion_estimada = now()->addWeek()->toDateString();
    }

    public function updatedSearchArticulo(): void
    {
        if (strlen($this->search_articulo) >= 2) {
            $this->sugerencias = ArticuloInventario::query()
                ->where('school_id', auth()->user()->current_school_id)
                ->whereNull('fecha_baja')
                ->where(function ($q) {
                    $q->where('nombre', 'like', '%' . $this->search_articulo . '%')
                      ->orWhere('codigo_patrimonial', 'like', '%' . $this->search_articulo . '%');
                })
                ->take(6)
                ->get()
                ->toArray();
        } else {
            $this->sugerencias = [];
        }
    }

    public function seleccionarArticulo(int $id): void
    {
        $articulo = ArticuloInventario::find($id);
        if ($articulo) {
            $this->articulo_inventario_id = $articulo->id;
            $this->nombre_articulo = $articulo->nombre;
            $this->marca = $articulo->marca ?? '';
            $this->modelo = $articulo->modelo ?? '';
            $this->numero_serie = $articulo->numero_serie ?? '';
            $this->search_articulo = $articulo->nombre . ($articulo->codigo_patrimonial ? ' (' . $articulo->codigo_patrimonial . ')' : '');
        }
        $this->sugerencias = [];
    }

    public function limpiarArticulo(): void
    {
        $this->reset(['articulo_inventario_id', 'nombre_articulo', 'marca', 'modelo', 'numero_serie', 'search_articulo', 'sugerencias']);
    }

    public function setPresetFecha(string $preset): void
    {
        $fechaBase = Carbon::parse($this->fecha_prestamo);
        
        if ($preset === 'day') {
            $this->fecha_devolucion_estimada = $fechaBase->addDay()->toDateString();
        } elseif ($preset === 'week') {
            $this->fecha_devolucion_estimada = $fechaBase->addWeek()->toDateString();
        } elseif ($preset === 'month') {
            $this->fecha_devolucion_estimada = $fechaBase->addMonth()->toDateString();
        }
    }

    // Fetch all users associated with the current school
    #[\Livewire\Attributes\Computed]
    public function funcionarios()
    {
        return User::query()
            ->whereHas('schools', fn($q) => $q->where('schools.id', auth()->user()->current_school_id))
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'nombre' => $user->nombreCompleto() . ' (' . $user->email . ')'
            ])
            ->sortBy('nombre');
    }

    public function guardar(): void
    {
        // Si no seleccionó artículo pero escribió en la búsqueda, tomamos eso como nombre
        if (!$this->articulo_inventario_id && trim($this->nombre_articulo) === '' && trim($this->search_articulo) !== '') {
            $this->nombre_articulo = trim($this->search_articulo);
        }

        $this->validate([
            'user_id' => 'required|exists:users,id',
            'nombre_articulo' => 'required|string|min:2|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'numero_serie' => 'nullable|string|max:255',
            'cantidad' => 'required|integer|min:1',
            'fecha_prestamo' => 'required|date',
            'fecha_devolucion_estimada' => 'required|date|after_or_equal:fecha_prestamo',
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'user_id.required' => 'Debe seleccionar un docente o funcionario.',
            'user_id.exists' => 'El funcionario seleccionado no es válido.',
            'nombre_articulo.required' => 'El nombre del artículo es obligatorio (seleccione uno del inventario o ingrese un nombre).',
            'nombre_articulo.min' => 'El nombre del artículo debe tener al menos 2 caracteres.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
            'fecha_prestamo.required' => 'La fecha de préstamo es obligatoria.',
            'fecha_devolucion_estimada.required' => 'La fecha estimada de devolución es obligatoria.',
            'fecha_devolucion_estimada.after_or_equal' => 'La fecha estimada de devolución no puede ser anterior a la fecha de préstamo.',
        ]);

        $prestamo = Prestamo::create([
            'school_id' => auth()->user()->current_school_id,
            'user_id' => $this->user_id,
            'articulo_inventario_id' => $this->articulo_inventario_id,
            'nombre_articulo' => $this->nombre_articulo,
            'marca' => $this->marca ?: null,
            'modelo' => $this->modelo ?: null,
            'numero_serie' => $this->numero_serie ?: null,
            'cantidad' => $this->cantidad,
            'fecha_prestamo' => $this->fecha_prestamo,
            'fecha_devolucion_estimada' => $this->fecha_devolucion_estimada,
            'estado' => 'prestado',
            'observaciones' => $this->observaciones ?: null,
            'creado_por_user_id' => auth()->id(),
        ]);

        // Enviar notificación de correo al docente/funcionario
        $prestamo->user->notify(new \App\Notifications\PrestamoRegistrado($prestamo));

        \Flux::toast('El préstamo ha sido registrado exitosamente.', variant: 'success');
        
        $this->redirect(route('ti.prestamos.index'), navigate: true);
    }
};
?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header
        :titulo="__('Registrar Préstamo de Insumo')"
        :subtitulo="__('Registra la asignación temporal de un recurso tecnológico u otro insumo a un funcionario o docente.')"
        icono="document-plus"
    />

    <form wire:submit="guardar" class="space-y-6">
        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('Información del Préstamo') }}</flux:heading>

            {{-- Selección de Funcionario --}}
            <flux:field>
                <flux:label>{{ __('Docente o Funcionario Receptor') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="user_id" variant="listbox" searchable :placeholder="__('Seleccione un funcionario...')">
                    @foreach($this->funcionarios as $func)
                        <flux:select.option value="{{ $func['id'] }}">{{ $func['nombre'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="user_id" />
            </flux:field>

            <hr class="border-zinc-200 dark:border-zinc-700" />

            <flux:heading size="md">{{ __('Artículo o Insumo') }}</flux:heading>

            {{-- Buscador del Inventario General --}}
            <div class="relative">
                <flux:field>
                    <flux:label>{{ __('Buscar en Inventario General') }}</flux:label>
                    <flux:input 
                        wire:model.live.debounce.300ms="search_articulo" 
                        icon="magnifying-glass" 
                        :placeholder="__('Escriba el nombre o código patrimonial para buscar en inventario...')"
                    />
                    @if($articulo_inventario_id)
                        <flux:button size="sm" variant="ghost" icon="x-mark" class="absolute right-2 top-8" wire:click="limpiarArticulo" />
                    @endif
                </flux:field>

                {{-- Resultados del Autocompletar --}}
                @if(!empty($sugerencias))
                    <div class="absolute z-10 left-0 right-0 mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                        @foreach($sugerencias as $sug)
                            <button 
                                type="button" 
                                wire:click="seleccionarArticulo({{ $sug['id'] }})"
                                class="w-full text-left px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-sm border-b border-zinc-100 dark:border-zinc-800 last:border-0"
                            >
                                <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $sug['nombre'] }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $sug['categoria'] }} - {{ $sug['marca'] ?? 'Sin Marca' }} {{ $sug['modelo'] ?? 'Sin Modelo' }} 
                                    @if($sug['codigo_patrimonial']) | Cod: {{ $sug['codigo_patrimonial'] }} @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Campos del Insumo --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre del Artículo') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="nombre_articulo" :placeholder="__('Ej: Computador Lenovo')" />
                    <flux:error name="nombre_articulo" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Cantidad Prestada') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" wire:model="cantidad" min="1" />
                    <flux:error name="cantidad" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Marca (Opcional)') }}</flux:label>
                    <flux:input wire:model="marca" :placeholder="__('Ej: HP, Dell')" />
                    <flux:error name="marca" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Modelo (Opcional)') }}</flux:label>
                    <flux:input wire:model="modelo" :placeholder="__('Ej: EliteBook 840')" />
                    <flux:error name="modelo" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('Número de Serie / Código (Opcional)') }}</flux:label>
                    <flux:input wire:model="numero_serie" :placeholder="__('Ej: S/N 12345678')" />
                    <flux:error name="numero_serie" />
                </flux:field>
            </div>

            <hr class="border-zinc-200 dark:border-zinc-700" />

            <flux:heading size="md">{{ __('Fechas y Tiempos del Préstamo') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Fecha de Préstamo --}}
                <flux:field>
                    <flux:label>{{ __('Fecha de Entrega / Préstamo') }} <span class="text-red-500">*</span></flux:label>
                    <flux:date-picker type="input" wire:model="fecha_prestamo" />
                    <flux:error name="fecha_prestamo" />
                </flux:field>

                {{-- Fecha de Devolución Estimada --}}
                <flux:field>
                    <flux:label>{{ __('Fecha Estimada de Devolución') }} <span class="text-red-500">*</span></flux:label>
                    <flux:date-picker type="input" wire:model="fecha_devolucion_estimada" />
                    
                    {{-- Accesos rápidos de fechas --}}
                    <div class="flex gap-2 mt-2">
                        <flux:button size="xs" variant="ghost" wire:click="setPresetFecha('day')">+1 Día</flux:button>
                        <flux:button size="xs" variant="ghost" wire:click="setPresetFecha('week')">+1 Semana</flux:button>
                        <flux:button size="xs" variant="ghost" wire:click="setPresetFecha('month')">+1 Mes</flux:button>
                    </div>
                    <flux:error name="fecha_devolucion_estimada" />
                </flux:field>
            </div>

            {{-- Observaciones --}}
            <flux:field>
                <flux:label>{{ __('Observaciones iniciales (Opcional)') }}</flux:label>
                <flux:textarea wire:model="observaciones" rows="3" :placeholder="__('Notas adicionales sobre el estado de entrega del insumo...')" />
                <flux:error name="observaciones" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-6">
                <flux:button :href="route('ti.prestamos.index')" variant="ghost" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button type="submit" variant="filled" color="blue">{{ __('Registrar Préstamo') }}</flux:button>
            </div>
        </flux:card>
    </form>
</div>
