<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Models\Prestamo;
use App\Models\User;
use Carbon\Carbon;

new #[Title('Gestión de Préstamos')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filtroEstado = 'todos';
    public string $activeTab = 'activos'; // 'activos' | 'devueltos'

    // Modal de Devolución
    public bool $modalDevolucion = false;
    public ?int $selectedPrestamoId = null;
    public string $observacionesDevolucion = '';

    // Modal de Edición
    public bool $modalEditar = false;
    public ?int $selectedPrestamoIdForEdit = null;
    public ?int $editDocenteId = null;
    public string $editFechaDevolucionEstimada = '';
    public int $editCantidad = 1;
    public string $editObservaciones = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado()
    {
        $this->resetPage();
    }

    public function updatedActiveTab()
    {
        $this->resetPage();
    }

    #[\Livewire\Attributes\Computed]
    public function selectedPrestamo()
    {
        return $this->selectedPrestamoId ? Prestamo::find($this->selectedPrestamoId) : null;
    }

    #[\Livewire\Attributes\Computed]
    public function countActivos(): int
    {
        return Prestamo::where('school_id', auth()->user()->current_school_id)
            ->where('estado', 'prestado')
            ->count();
    }

    #[\Livewire\Attributes\Computed]
    public function prestamos()
    {
        return Prestamo::query()
            ->with(['user', 'creador', 'receptor', 'articuloInventario'])
            ->where('school_id', auth()->user()->current_school_id)
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($q) use ($search) {
                    $q->where('nombre_articulo', 'like', "%{$search}%")
                        ->orWhere('marca', 'like', "%{$search}%")
                        ->orWhere('modelo', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('nombres', 'like', "%{$search}%")
                                ->orWhere('apellido_pat', 'like', "%{$search}%")
                                ->orWhere('apellido_mat', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->activeTab === 'activos', function ($query) {
                $query->where('estado', '!=', 'devuelto')->when($this->filtroEstado !== 'todos', function ($q) {
                    if ($this->filtroEstado === 'vencido') {
                        $q->where('estado', 'prestado')->where('fecha_devolucion_estimada', '<', now()->toDateString());
                    } else {
                        $q->where('estado', $this->filtroEstado);
                    }
                });
            })
            ->when($this->activeTab === 'devueltos', function ($query) {
                $query->where('estado', 'devuelto');
            })
            ->orderBy('fecha_prestamo', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15);
    }

    public function abrirDevolucion(int $id): void
    {
        $this->selectedPrestamoId = $id;
        $this->observacionesDevolucion = '';
        $this->modalDevolucion = true;
    }

    public function procesarDevolucion(): void
    {
        $prestamo = Prestamo::find($this->selectedPrestamoId);
        if ($prestamo && $prestamo->estado === 'prestado') {
            $notas = $prestamo->observaciones;
            if (trim($this->observacionesDevolucion) !== '') {
                $fechaNota = now()->format('d/m/Y');
                $nuevaNota = "[Devolución {$fechaNota} por " . auth()->user()->nombreCompleto() . ']: ' . trim($this->observacionesDevolucion);
                $notas = $notas ? $notas . "\n" . $nuevaNota : $nuevaNota;
            }

            $prestamo->update([
                'estado' => 'devuelto',
                'fecha_devolucion_real' => now()->toDateString(),
                'recibido_por_user_id' => auth()->id(),
                'observaciones' => $notas,
            ]);

            \Flux::toast('El insumo ha sido marcado como devuelto.', variant: 'success');
        }

        $this->modalDevolucion = false;
        $this->reset(['selectedPrestamoId', 'observacionesDevolucion']);
    }

    public function abrirEditar(int $id): void
    {
        $prestamo = Prestamo::findOrFail($id);
        $this->selectedPrestamoIdForEdit = $id;
        $this->editDocenteId = $prestamo->user_id;
        $this->editFechaDevolucionEstimada = $prestamo->fecha_devolucion_estimada->toDateString();
        $this->editCantidad = $prestamo->cantidad;
        $this->editObservaciones = $prestamo->observaciones ?? '';
        $this->modalEditar = true;
    }

    public function guardarEdicion(): void
    {
        $prestamo = Prestamo::findOrFail($this->selectedPrestamoIdForEdit);

        $this->validate(
            [
                'editDocenteId' => 'required|exists:users,id',
                'editFechaDevolucionEstimada' => 'required|date',
                'editCantidad' => 'required|integer|min:1',
                'editObservaciones' => 'nullable|string|max:2000',
            ],
            [
                'editDocenteId.required' => 'El funcionario responsable es obligatorio.',
                'editCantidad.min' => 'La cantidad debe ser al menos 1.',
            ],
        );

        $prestamo->update([
            'user_id' => $this->editDocenteId,
            'fecha_devolucion_estimada' => $this->editFechaDevolucionEstimada,
            'cantidad' => $this->editCantidad,
            'observaciones' => $this->editObservaciones ?: null,
        ]);

        $this->modalEditar = false;
        $this->reset(['selectedPrestamoIdForEdit', 'editDocenteId', 'editFechaDevolucionEstimada', 'editCantidad', 'editObservaciones']);
        \Flux::toast('El préstamo ha sido actualizado con éxito.', variant: 'success');
    }

    #[\Livewire\Attributes\Computed]
    public function usuarios()
    {
        return User::orderBy('nombres', 'asc')->get();
    }
};
?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header :titulo="__('Gestión de Préstamos TI')" :subtitulo="__(
        'Administra y monitorea los insumos tecnológicos prestados temporalmente a los docentes y funcionarios.',
    )" icono="briefcase" />

    <!-- Botón para móvil (arriba del card, abajo del título) -->
    <div class="block md:hidden">
        <flux:button :href="route('ti.prestamos.crear')" icon="plus" variant="filled" color="blue" wire:navigate
            class="w-full">
            {{ __('Nuevo Préstamo') }}
        </flux:button>
    </div>

    {{-- Filtros y Buscador --}}
    <flux:card>
        <div class="flex flex-col md:flex-row gap-4 items-end justify-between w-full">
            <flux:field class="flex-1 w-full">
                <flux:label>{{ __('Buscar Préstamo') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    :placeholder="__('Buscar por funcionario, artículo, marca, modelo...')" />
            </flux:field>

            @if ($activeTab === 'activos')
                <div class="h-10 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                <flux:field class="w-full md:w-64">
                    <flux:label>{{ __('Filtrar por Estado') }}</flux:label>
                    <flux:select wire:model.live="filtroEstado">
                        <flux:select.option value="todos">{{ __('Todos los activos') }}</flux:select.option>
                        <flux:select.option value="prestado">{{ __('Prestado (Vigente)') }}</flux:select.option>
                        <flux:select.option value="vencido">{{ __('Vencido (Atrasado)') }}</flux:select.option>
                    </flux:select>
                </flux:field>
            @endif

            <!-- Botón para desktop (dentro del card, al lado del filtro) -->
            <div class="hidden md:block">
                <flux:button :href="route('ti.prestamos.crear')" icon="plus" variant="filled" color="blue"
                    wire:navigate>
                    {{ __('Nuevo Préstamo') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Listado de Préstamos --}}
    <flux:card class="space-y-6">
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-4">
            <div class="flex gap-2">
                <button type="button" wire:click="$set('activeTab', 'activos')"
                    class="pb-2 px-4 font-semibold text-sm border-b-2 transition-colors focus:outline-none relative {{ $activeTab === 'activos' ? 'border-[#00376e] text-[#00376e] dark:border-blue-500 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                    {{ __('Préstamos Activos') }}
                    @if ($this->countActivos > 0)
                        <span
                            class="ml-1.5 px-2 py-0.5 text-xs font-bold rounded-full bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-900">
                            {{ $this->countActivos }}
                        </span>
                    @endif
                </button>
                <button type="button" wire:click="$set('activeTab', 'devueltos')"
                    class="pb-2 px-4 font-semibold text-sm border-b-2 transition-colors focus:outline-none {{ $activeTab === 'devueltos' ? 'border-[#00376e] text-[#00376e] dark:border-blue-500 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                    {{ __('Historial de Devoluciones') }}
                </button>
            </div>
            <h3 class="font-headline text-lg font-bold text-primary dark:text-zinc-100 hidden sm:block">
                {{ $activeTab === 'activos' ? __('Gestión Activa') : __('Historial') }}
            </h3>
        </div>

        <flux:table :paginate="$this->prestamos">
            <flux:table.columns>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Funcionario') }}</flux:table.column>
                <flux:table.column>{{ __('Artículo') }}</flux:table.column>
                <flux:table.column>{{ __('Cant') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha de Prestamo') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha de Devolución') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Acciones') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->prestamos as $prestamo)
                    <flux:table.row :key="$prestamo->id">
                        {{-- Estado Badge --}}
                        <flux:table.cell>
                            @if ($prestamo->estado === 'devuelto')
                                <flux:badge color="green" icon="check" size="sm">{{ __('Devuelto') }}
                                </flux:badge>
                            @elseif($prestamo->es_vencido)
                                <flux:badge color="red" icon="exclamation-triangle" size="sm"
                                    class="animate-pulse">{{ __('Vencido') }}</flux:badge>
                            @else
                                <flux:badge color="blue" icon="clock" size="sm">{{ __('Prestado') }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        {{-- Funcionario --}}
                        <flux:table.cell class="font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $prestamo->user ? $prestamo->user->nombreCompleto() : 'Sin Asignar' }}
                        </flux:table.cell>

                        {{-- Artículo --}}
                        <flux:table.cell>
                            <div>
                                <span
                                    class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $prestamo->nombre_articulo }}</span>
                                @if ($prestamo->articuloInventario)
                                    <div
                                        class="text-[10px] text-[#00376e] dark:text-blue-400 font-mono font-bold mt-0.5">
                                        Cod: {{ $prestamo->articuloInventario->codigo_patrimonial }}
                                    </div>
                                @endif
                            </div>
                        </flux:table.cell>

                        {{-- Cantidad --}}
                        <flux:table.cell>
                            {{ $prestamo->cantidad }}
                        </flux:table.cell>

                        {{-- Fecha Entrega --}}
                        <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                            {{ $prestamo->fecha_prestamo->format('d/m/Y') }}
                        </flux:table.cell>

                        {{-- Fecha Estimada Devolución --}}
                        <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                            <span class="{{ $prestamo->es_vencido ? 'text-red-600 font-bold' : '' }}">
                                {{ $prestamo->fecha_devolucion_estimada->format('d/m/Y') }}
                            </span>
                            @if ($prestamo->estado === 'devuelto' && $prestamo->fecha_devolucion_real)
                                <div class="text-[10px] text-zinc-500 italic">
                                    {{ __('Devuelto:') }} {{ $prestamo->fecha_devolucion_real->format('d/m/Y') }}
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Acciones --}}
                        <flux:table.cell align="end">
                            @if ($prestamo->estado === 'prestado')
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button variant="ghost" size="sm" icon="pencil-square"
                                        wire:click="abrirEditar({{ $prestamo->id }})"
                                        title="{{ __('Editar Préstamo') }}" />
                                    <flux:button variant="ghost" size="sm" icon="arrow-path"
                                        wire:click="abrirDevolucion({{ $prestamo->id }})">
                                        {{ __('Devolución') }}
                                    </flux:button>
                                </div>
                            @else
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Recibido por:') }} <span
                                        class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $prestamo->receptor ? $prestamo->receptor->nombreCompleto() : '-' }}</span>
                                </span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-12 text-zinc-400">
                            {{ __('No se encontraron registros de préstamos.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal de Recepción de Devolución --}}
    <flux:modal wire:model="modalDevolucion" class="md:w-lg">
        @if ($this->selectedPrestamo)
            @php $selected = $this->selectedPrestamo; @endphp
            <form wire:submit.prevent="procesarDevolucion" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Recibir Devolución') }}</flux:heading>
                    <flux:subheading size="sm" class="mt-1">
                        {{ __('Registra el ingreso del insumo de vuelta al inventario de Informática.') }}
                    </flux:subheading>
                </div>

                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/30 rounded-xl space-y-2 text-sm">
                    <div>
                        <span class="font-bold text-zinc-500">{{ __('Artículo:') }}</span>
                        <span
                            class="font-medium text-zinc-800 dark:text-zinc-200">{{ $selected->nombre_articulo }}</span>
                    </div>
                    <div>
                        <span class="font-bold text-zinc-500">{{ __('Prestado a:') }}</span>
                        <span
                            class="font-medium text-zinc-800 dark:text-zinc-200">{{ $selected->user->nombreCompleto() }}</span>
                    </div>
                    @if ($selected->numero_serie)
                        <div>
                            <span class="font-bold text-zinc-500">{{ __('N° de Serie:') }}</span>
                            <span
                                class="font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $selected->numero_serie }}</span>
                        </div>
                    @endif
                </div>

                <flux:field>
                    <flux:label>{{ __('Estado del Insumo / Comentarios de Devolución (Opcional)') }}</flux:label>
                    <flux:textarea wire:model="observacionesDevolucion" rows="3"
                        :placeholder="__('Ej: Devuelto en perfectas condiciones, limpio y con todos sus accesorios.')" />
                    <flux:error name="observacionesDevolucion" />
                </flux:field>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('modalDevolucion', false)" variant="ghost">{{ __('Cancelar') }}
                    </flux:button>
                    <flux:button type="submit" variant="filled" color="green">{{ __('Confirmar Devolución') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{-- Modal de Edición de Préstamo --}}
    <flux:modal wire:model="modalEditar" class="md:w-lg">
        <form wire:submit.prevent="guardarEdicion" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Editar Préstamo Activo') }}</flux:heading>
                <flux:subheading size="sm" class="mt-1">
                    {{ __('Modifique los datos de asignación, cantidad y fecha del préstamo activo.') }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Funcionario Responsable') }}</flux:label>
                <flux:select wire:model="editDocenteId" searchable>
                    @foreach ($this->usuarios as $u)
                        <flux:select.option value="{{ $u->id }}">{{ $u->nombreCompleto() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="editDocenteId" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Cantidad') }}</flux:label>
                    <flux:input type="number" wire:model="editCantidad" min="1" />
                    <flux:error name="editCantidad" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fecha de Devolución Estimada') }}</flux:label>
                    <flux:input type="date" wire:model="editFechaDevolucionEstimada" />
                    <flux:error name="editFechaDevolucionEstimada" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Observaciones / Notas') }}</flux:label>
                <flux:textarea wire:model="editObservaciones" rows="3" />
                <flux:error name="editObservaciones" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('modalEditar', false)" variant="ghost">{{ __('Cancelar') }}
                </flux:button>
                <flux:button type="submit" variant="filled" color="blue">{{ __('Guardar Cambios') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
