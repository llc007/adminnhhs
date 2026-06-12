<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Models\Prestamo;
use Carbon\Carbon;

new #[Title('Gestión de Préstamos')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filtroEstado = 'todos';

    // Modal de Devolución
    public bool $modalDevolucion = false;
    public ?int $selectedPrestamoId = null;
    public string $observacionesDevolucion = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado()
    {
        $this->resetPage();
    }

    #[\Livewire\Attributes\Computed]
    public function selectedPrestamo()
    {
        return $this->selectedPrestamoId ? Prestamo::find($this->selectedPrestamoId) : null;
    }

    #[\Livewire\Attributes\Computed]
    public function prestamos()
    {
        return Prestamo::query()
            ->with(['user', 'creador', 'receptor'])
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
            ->when($this->filtroEstado !== 'todos', function ($query) {
                if ($this->filtroEstado === 'vencido') {
                    $query->where('estado', 'prestado')
                          ->where('fecha_devolucion_estimada', '<', now()->toDateString());
                } else {
                    $query->where('estado', $this->filtroEstado);
                }
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
                $nuevaNota = "[Devolución {$fechaNota} por " . auth()->user()->nombreCompleto() . "]: " . trim($this->observacionesDevolucion);
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
};
?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header
        :titulo="__('Gestión de Préstamos TI')"
        :subtitulo="__('Administra y monitorea los insumos tecnológicos prestados temporalmente a los docentes y funcionarios.')"
        icono="briefcase"
    />
    
    <!-- Botón para móvil (arriba del card, abajo del título) -->
    <div class="block md:hidden">
        <flux:button 
            :href="route('ti.prestamos.crear')" 
            icon="plus" 
            variant="filled" 
            color="blue"
            wire:navigate
            class="w-full"
        >
            {{ __('Nuevo Préstamo') }}
        </flux:button>
    </div>

    {{-- Filtros y Buscador --}}
    <flux:card>
        <div class="flex flex-col md:flex-row gap-4 items-end justify-between w-full">
            <flux:field class="flex-1 w-full">
                <flux:label>{{ __('Buscar Préstamo') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por funcionario, artículo, marca, modelo...')" />
            </flux:field>

            <div class="h-10 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

            <flux:field class="w-full md:w-64">
                <flux:label>{{ __('Filtrar por Estado') }}</flux:label>
                <flux:select wire:model.live="filtroEstado">
                    <flux:select.option value="todos">{{ __('Todos los estados') }}</flux:select.option>
                    <flux:select.option value="prestado">{{ __('Prestado (Activo)') }}</flux:select.option>
                    <flux:select.option value="devuelto">{{ __('Devuelto') }}</flux:select.option>
                    <flux:select.option value="vencido">{{ __('Vencido (Atrasado)') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <!-- Botón para desktop (dentro del card, al lado del filtro) -->
            <div class="hidden md:block">
                <flux:button 
                    :href="route('ti.prestamos.crear')" 
                    icon="plus" 
                    variant="filled" 
                    color="blue"
                    wire:navigate
                >
                    {{ __('Nuevo Préstamo') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Listado de Préstamos --}}
    <flux:card>
        <div class="mb-4">
            <h3 class="font-headline text-lg font-bold text-primary dark:text-zinc-100">{{ __('Préstamos') }}</h3>
        </div>
        <flux:table :paginate="$this->prestamos">
            <flux:table.columns>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Funcionario') }}</flux:table.column>
                <flux:table.column>{{ __('Artículo') }}</flux:table.column>
                <flux:table.column>{{ __('Cant') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha Entrega') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha Estimada Devolución') }}</flux:table.column>
                <flux:table.column class="text-right"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->prestamos as $prestamo)
                    <flux:table.row :key="$prestamo->id">
                        {{-- Estado Badge --}}
                        <flux:table.cell>
                            @if($prestamo->estado === 'devuelto')
                                <flux:badge color="green" icon="check" size="sm">{{ __('Devuelto') }}</flux:badge>
                            @elseif($prestamo->es_vencido)
                                <flux:badge color="red" icon="exclamation-triangle" size="sm" class="animate-pulse">{{ __('Vencido') }}</flux:badge>
                            @else
                                <flux:badge color="blue" icon="clock" size="sm">{{ __('Prestado') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        {{-- Funcionario --}}
                        <flux:table.cell class="font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $prestamo->user ? $prestamo->user->nombreCompleto() : 'Sin Asignar' }}
                        </flux:table.cell>

                        {{-- Artículo --}}
                        <flux:table.cell>
                            <div>
                                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $prestamo->nombre_articulo }}</span>
                                @if($prestamo->marca || $prestamo->modelo || $prestamo->numero_serie)
                                    <div class="text-[11px] text-zinc-500">
                                        {{ $prestamo->marca }} {{ $prestamo->modelo }} 
                                        @if($prestamo->numero_serie) | S/N: {{ $prestamo->numero_serie }} @endif
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
                            @if($prestamo->estado === 'devuelto' && $prestamo->fecha_devolucion_real)
                                <div class="text-[10px] text-zinc-500 italic">
                                    {{ __('Devuelto:') }} {{ $prestamo->fecha_devolucion_real->format('d/m/Y') }}
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Acciones --}}
                        <flux:table.cell class="text-right">
                            @if($prestamo->estado === 'prestado')
                                <flux:button variant="ghost" size="sm" icon="arrow-path" wire:click="abrirDevolucion({{ $prestamo->id }})">
                                    {{ __('Recibir Devolución') }}
                                </flux:button>
                            @else
                                <span class="text-xs text-zinc-400 italic">{{ __('Completado') }}</span>
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
        @if($this->selectedPrestamo)
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
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $selected->nombre_articulo }}</span>
                    </div>
                    <div>
                        <span class="font-bold text-zinc-500">{{ __('Prestado a:') }}</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $selected->user->nombreCompleto() }}</span>
                    </div>
                    @if($selected->numero_serie)
                        <div>
                            <span class="font-bold text-zinc-500">{{ __('N° de Serie:') }}</span>
                            <span class="font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $selected->numero_serie }}</span>
                        </div>
                    @endif
                </div>

                <flux:field>
                    <flux:label>{{ __('Estado del Insumo / Comentarios de Devolución (Opcional)') }}</flux:label>
                    <flux:textarea 
                        wire:model="observacionesDevolucion" 
                        rows="3" 
                        :placeholder="__('Ej: Devuelto en perfectas condiciones, limpio y con todos sus accesorios.')" 
                    />
                    <flux:error name="observacionesDevolucion" />
                </flux:field>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('modalDevolucion', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                    <flux:button type="submit" variant="filled" color="green">{{ __('Confirmar Devolución') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
