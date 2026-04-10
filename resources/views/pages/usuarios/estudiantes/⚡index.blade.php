<?php

use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // Filtros y orden
    public string $modalidad = 'todos';
    public string $cursoId = ''; // Vacío por defecto para obligar a seleccionar
    public string $search = '';
    public string $sortBy = 'nombres_csv';
    public string $sortDirection = 'asc';

    // Modal eliminar
    public bool $modalEliminar = false;
    public ?int $eliminarId = null;

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedModalidad()
    {
        $this->cursoId = ''; // Reiniciar el curso al cambiar de modalidad
        $this->resetPage();
    }

    public function updatedCursoId()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function confirmarEliminar(int $id): void
    {
        $this->eliminarId = $id;
        $this->modalEliminar = true;
    }

    public function eliminar(): void
    {
        if ($this->eliminarId) {
            \App\Models\Estudiante::findOrFail($this->eliminarId)->delete();
        }
        $this->modalEliminar = false;
        $this->eliminarId = null;
    }

    #[\Livewire\Attributes\Computed]
    public function cursos()
    {
        $query = \App\Models\Curso::where('school_id', auth()->user()->current_school_id)
            ->orderBy('nivel')
            ->orderBy('letra');

        if ($this->modalidad !== 'todos') {
            $query->where('modalidad', $this->modalidad);
        }

        return $query->get();
    }

    #[\Livewire\Attributes\Computed]
    public function estudiantes()
    {
        // Retornar conjunto vacío si no se ha seleccionado un curso
        if ($this->cursoId === '') {
            return collect(); // Colección vacía
        }

        return \App\Models\Estudiante::query()
            ->with(['curso', 'user'])
            ->where('school_id', auth()->user()->current_school_id)
            ->when($this->cursoId !== 'todos', function ($query) {
                $query->where('curso_id', $this->cursoId);
            })
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($q) use ($search) {
                    $q->where('nombres_csv', 'like', "%{$search}%")
                      ->orWhere('rut_numero', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('nombres', 'like', "%{$search}%")
                             ->orWhere('apellido_pat', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            })
            ->when($this->sortBy === 'nombres_csv', function ($query) {
                $query->orderBy('nombres_csv', $this->sortDirection);
            })
            ->when($this->sortBy === 'rut_numero', function ($query) {
                $query->orderBy('rut_numero', $this->sortDirection);
            })
            ->paginate(50); // Mostramos 50 alumnos por página pues los cursos rondan los 30-45 alumnos
    }
};

?>

<div>
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto">
        <!-- Quick Action Header -->
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
            <div>
                <flux:breadcrumbs class="mb-4">
                    <flux:breadcrumbs.item icon="building-library" href="#" />
                    <flux:breadcrumbs.item>{{ __('Institución') }}</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item>{{ __('Gestión de Estudiantes') }}</flux:breadcrumbs.item>
                </flux:breadcrumbs>

                <flux:heading size="xl" level="1">{{ __('Listado de Estudiantes') }}</flux:heading>
                <flux:subheading size="lg" class="max-w-xl">
                    {{ __('Administración centralizada de alumnos del establecimiento.') }}
                </flux:subheading>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <flux:button variant="ghost" icon="document-arrow-up" href="{{ route('estudiantes.carga_masiva') }}">
                    {{ __('Importar CSV') }}
                </flux:button>
                <flux:button variant="primary" icon="plus" class="shrink-0">
                    {{ __('Nuevo Estudiante') }}
                </flux:button>
            </div>
        </div>

        <!-- Filters Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            <div class="md:col-span-8">
                <flux:card class="h-full flex items-center">
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-6 w-full">
                        <flux:field class="flex-1 w-full overflow-hidden">
                            <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Filtrar por Nivel') }}
                            </flux:label>
                            <flux:radio.group wire:model.live="modalidad" variant="segmented" class="w-full overflow-x-auto">
                                <flux:radio value="todos" :label="__('Todos')" />
                                <flux:radio value="basica" :label="__('Educación Básica')" />
                                <flux:radio value="media" :label="__('Educación Media')" />
                            </flux:radio.group>
                        </flux:field>

                        <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                        <flux:field class="w-full md:w-56">
                            <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Curso') }}
                            </flux:label>
                            <flux:select wire:model.live="cursoId">
                                <flux:select.option value="" disabled>{{ __('Selecciona un Curso') }}</flux:select.option>
                                <flux:select.option value="todos">{{ __('Listar Todos') }}</flux:select.option>
                                @foreach ($this->cursos as $curso)
                                    <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    </div>
                </flux:card>
            </div>

            <div class="md:col-span-4">
                <flux:card class="h-full flex items-center justify-between bg-zinc-900 border-none !text-white dark:bg-zinc-800">
                    <div>
                        <div class="text-[10px] uppercase tracking-widest font-bold opacity-70">
                            {{ __('Total Estudiantes') }}
                        </div>
                        <div class="text-4xl font-bold mt-1">{{ \App\Models\Estudiante::where('school_id', auth()->user()->current_school_id)->count() }}</div>
                    </div>
                    <div class="p-3 bg-white/10 rounded-full">
                        <flux:icon.academic-cap class="size-8" />
                    </div>
                </flux:card>
            </div>
        </div>

        <!-- Table UI -->
        <flux:card>
            @if ($this->cursoId === '')
                <div class="py-12 flex flex-col items-center justify-center text-center">
                    <div class="p-4 bg-zinc-100 dark:bg-zinc-800 rounded-full mb-4">
                        <flux:icon.academic-cap class="size-8 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <flux:heading size="lg">{{ __('Selecciona un curso') }}</flux:heading>
                    <flux:text class="mt-2 max-w-sm">
                        {{ __('Utiliza los filtros de arriba para seleccionar un nivel y un curso para ver su nómina de estudiantes.') }}
                    </flux:text>
                </div>
            @else
                <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30 flex items-center justify-between">
                    <div class="w-full max-w-sm">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por nombre o RUT...')" size="sm" class="w-full" />
                    </div>
                </div>

                <flux:table :paginate="$this->estudiantes">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'nombres_csv'" :direction="$sortDirection" wire:click="sort('nombres_csv')">
                            {{ __('Nombre del Estudiante') }}
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'rut_numero'" :direction="$sortDirection" wire:click="sort('rut_numero')">
                            {{ __('RUT') }}
                        </flux:table.column>
                        <flux:table.column>{{ __('Curso') }}</flux:table.column>
                        <flux:table.column>{{ __('Apoderado') }}</flux:table.column>
                        <flux:table.column>{{ __('Estado') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->estudiantes as $estudiante)
                            <flux:table.row :key="$estudiante->id">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar />
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $estudiante->nombreCompleto() }}
                                            </div>
                                            @if($estudiante->user_id)
                                                <div class="text-xs text-zinc-500">{{ $estudiante->user->email }}</div>
                                            @else
                                                <div class="text-xs text-zinc-400 italic">Pendiente de vinculación</div>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $estudiante->rutCompleto() ?? '-' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($estudiante->curso)
                                        <flux:badge color="blue">{{ $estudiante->curso->nombreCompleto() }}</flux:badge>
                                    @else
                                        <span class="text-zinc-500">-</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="text-sm">{{ $estudiante->apoderado_nombres }}</div>
                                    @if($estudiante->apoderado_telefono)
                                        <div class="text-xs text-zinc-500 flex items-center gap-1 mt-0.5">
                                            <flux:icon.phone class="size-3" /> {{ $estudiante->apoderado_telefono }}
                                        </div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($estudiante->user_id)
                                        <flux:badge color="green" icon="check-circle">Activo</flux:badge>
                                    @else
                                        <flux:badge color="orange" icon="clock">Inactivo</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                    <flux:button variant="ghost" size="sm" icon="eye" :tooltip="__('Ver Ficha')" href="{{ route('estudiantes.ficha', $estudiante->id) }}" />
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" :tooltip="__('Editar')" href="#" />
                                        <flux:button variant="ghost" size="sm" icon="trash" :tooltip="__('Eliminar')" wire:click="confirmarEliminar({{ $estudiante->id }})" />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center py-6 text-zinc-500">
                                    {{ __('No hay estudiantes inscritos en este curso.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <!-- Footer Meta Info -->
        <div class="flex flex-col sm:flex-row justify-between items-center text-zinc-500 dark:text-zinc-400 gap-4">
            <div class="flex space-x-10">
                <div>
                    <div class="text-[10px] uppercase font-bold tracking-widest mb-1">{{ __('Última Actualización') }}</div>
                    <div class="text-xs font-medium">{{ __('Hoy, 09:45 AM - Control Central') }}</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs italic">"Compromiso con la excelencia y seguridad institucional"</div>
            </div>
        </div>
    </div>

    {{-- Modal Confirmar Eliminar --}}
    <flux:modal wire:model="modalEliminar" class="md:w-80">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Eliminar estudiante') }}</flux:heading>
                <flux:text class="mt-2">{{ __('El estudiante y sus datos asociados serán eliminados de los registros. Esta acción no puede deshacerse.') }}</flux:text>
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="$set('modalEliminar', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="eliminar" variant="danger" class="ml-2">{{ __('Eliminar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>