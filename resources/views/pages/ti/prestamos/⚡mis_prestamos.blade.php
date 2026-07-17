<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Models\Prestamo;

new #[Title('Mis Préstamos')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        if (!auth()->user()->can('ver-prestamos-propios') && !auth()->user()->hasRole(['ti', 'administrador', 'superadmin'])) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }
    }

    #[\Livewire\Attributes\Computed]
    public function misPrestamos()
    {
        return Prestamo::query()
            ->where('user_id', auth()->id())
            ->orderBy('fecha_prestamo', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10);
    }
};
?>

<div class="max-w-6xl mx-auto w-full pb-12 space-y-8">
    <x-header
        :titulo="__('Mis Préstamos de Informática')"
        :subtitulo="__('Consulta el listado histórico y activo de los insumos y equipos tecnológicos que te han sido entregados en préstamo.')"
        icono="briefcase"
    />

    {{-- Listado de Préstamos Propios --}}
    <flux:card>
        <div class="mb-4">
            <h3 class="font-headline text-lg font-bold text-primary dark:text-zinc-100">{{ __('Préstamos') }}</h3>
        </div>
        <flux:table :paginate="$this->misPrestamos">
            <flux:table.columns>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Artículo') }}</flux:table.column>
                <flux:table.column>{{ __('Cantidad') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha Entrega') }}</flux:table.column>
                <flux:table.column>{{ __('Fecha Estimada Devolución') }}</flux:table.column>
                <flux:table.column>{{ __('Observaciones') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->misPrestamos as $prestamo)
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

                        {{-- Observaciones --}}
                        <flux:table.cell class="max-w-xs text-xs text-zinc-600 dark:text-zinc-400 truncate">
                            {{ $prestamo->observaciones ?: '-' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-12 text-zinc-400">
                            {{ __('No registras ningún préstamo de insumos.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
