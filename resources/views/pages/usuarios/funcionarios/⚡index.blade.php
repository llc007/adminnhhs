<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;

new class extends Component {
    use WithPagination;

    // Filtros y orden
    public string $search = '';
    public string $cargo = 'todos';
    public string $departamento = 'todos';
    public string $sortBy = 'nombres';
    public string $sortDirection = 'asc';

    // Modal crear/editar
    public bool $modalAbierto = false;
    public ?int $funcionarioId = null;
    public string $nombres = '';
    public string $apellidoPat = '';
    public string $apellidoMat = '';
    public string $email = '';
    public string $rutNumero = '';
    public string $rutDv = '';

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

    public function abrirCrear(): void
    {
        $this->reset(['funcionarioId', 'nombres', 'apellidoPat', 'apellidoMat', 'email', 'rutNumero', 'rutDv']);
        $this->modalAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        $funcionario = \App\Models\User::findOrFail($id);
        $this->funcionarioId = $funcionario->id;
        $this->nombres = $funcionario->nombres ?? '';
        $this->apellidoPat = $funcionario->apellido_pat ?? '';
        $this->apellidoMat = $funcionario->apellido_mat ?? '';
        $this->email = $funcionario->email;
        $this->rutNumero = $funcionario->rut_numero ?? '';
        $this->rutDv = $funcionario->rut_dv ?? '';
        $this->modalAbierto = true;
    }

    public function guardar(): void
    {
        $this->validate([
            'nombres'     => ['required', 'string', 'max:255'],
            'apellidoPat' => ['nullable', 'string', 'max:255'],
            'apellidoMat' => ['nullable', 'string', 'max:255'],
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($this->funcionarioId)],
            'rutNumero'   => ['nullable', 'digits_between:7,9'],
            'rutDv'       => ['nullable', 'string', 'max:1', 'regex:/^[0-9Kk]$/'],
        ]);

        if ($this->funcionarioId) {
            $funcionario = \App\Models\User::findOrFail($this->funcionarioId);
            $funcionario->update([
                'nombres'      => $this->nombres,
                'apellido_pat' => $this->apellidoPat,
                'apellido_mat' => $this->apellidoMat ?: null,
                'email'        => $this->email,
                'rut_numero'   => $this->rutNumero ?: null,
                'rut_dv'       => $this->rutDv ? strtoupper($this->rutDv) : null,
            ]);
        } else {
            $funcionario = \App\Models\User::create([
                'nombres'      => $this->nombres,
                'apellido_pat' => $this->apellidoPat,
                'apellido_mat' => $this->apellidoMat ?: null,
                'email'        => $this->email,
                'rut_numero'   => $this->rutNumero ?: null,
                'rut_dv'       => $this->rutDv ? strtoupper($this->rutDv) : null,
                'password'     => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                'current_school_id' => auth()->user()->current_school_id,
            ]);
            $funcionario->schools()->attach(auth()->user()->current_school_id, ['roles' => json_encode(['docente'])]);
        }

        $this->modalAbierto = false;
        $this->reset(['funcionarioId', 'nombres', 'apellidoPat', 'apellidoMat', 'email', 'rutNumero', 'rutDv']);
    }

    public function confirmarEliminar(int $id): void
    {
        $this->eliminarId = $id;
        $this->modalEliminar = true;
    }

    public function eliminar(): void
    {
        if ($this->eliminarId) {
            $funcionario = \App\Models\User::findOrFail($this->eliminarId);
            $funcionario->schools()->detach(auth()->user()->current_school_id);
        }
        $this->modalEliminar = false;
        $this->eliminarId = null;
    }

    #[\Livewire\Attributes\Computed]
    public function funcionarios()
    {
        return \App\Models\User::query()
            ->whereHas('schools', function ($q) {
                $q->where('school_id', auth()->user()->current_school_id)
                  ->whereJsonDoesntContain('school_user.roles', 'estudiante');
                if ($this->cargo !== 'todos') {
                    $q->whereJsonContains('school_user.roles', $this->cargo);
                }
            })
            ->when(strlen($this->search) >= 2, function ($query) {
                $query->where(function ($q) {
                    $q->where('nombres', 'like', '%' . $this->search . '%')
                      ->orWhere('apellido_pat', 'like', '%' . $this->search . '%')
                      ->orWhere('apellido_mat', 'like', '%' . $this->search . '%')
                      ->orWhere('rut_numero', 'like', '%' . $this->search . '%');
                });
            })
            ->tap(fn($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate(15);
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
                    <flux:breadcrumbs.item>{{ __('Gestión de Funcionarios') }}</flux:breadcrumbs.item>
                </flux:breadcrumbs>

                <flux:heading size="xl" level="1">{{ __('Listado de Funcionarios') }}</flux:heading>
                <flux:subheading size="lg" class="max-w-xl">
                    {{ __('Administración centralizada de personal docente, administrativo y auxiliar del establecimiento.') }}
                </flux:subheading>
            </div>

            <div class="flex flex-col gap-2 shrink-0">
                <flux:button variant="primary" icon="plus" wire:click="abrirCrear">
                    {{ __('Nuevo Funcionario') }}
                </flux:button>
                <flux:button variant="ghost" icon="document-arrow-up" href="{{ route('funcionarios.carga_masiva') }}">
                    {{ __('Carga Masiva') }}
                </flux:button>
            </div>
        </div>

        <!-- Filters Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            <div class="md:col-span-8">
                <flux:card class="h-full flex items-center">
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-6 w-full">
                        <flux:field class="flex-1 w-full md:w-64">
                            <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Búsqueda') }}
                            </flux:label>
                            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por nombre o RUT..." />
                        </flux:field>

                        <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                        <flux:field class="w-full md:w-48 overflow-hidden">
                            <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Cargo (Rol)') }}
                            </flux:label>
                            <flux:select wire:model.live="cargo">
                                <flux:select.option value="todos">{{ __('Todos') }}</flux:select.option>
                                <flux:select.option value="docente">{{ __('Docente') }}</flux:select.option>
                                <flux:select.option value="inspector">{{ __('Inspector') }}</flux:select.option>
                                <flux:select.option value="asistente">{{ __('Asistente') }}</flux:select.option>
                                <flux:select.option value="psicosocial">{{ __('Psicosocial') }}</flux:select.option>
                                <flux:select.option value="recepcion">{{ __('Recepción') }}</flux:select.option>
                                <flux:select.option value="directivo">{{ __('Directivo') }}</flux:select.option>
                                <flux:select.option value="administrador">{{ __('Administrador') }}</flux:select.option>
                            </flux:select>
                        </flux:field>

                        <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                        <flux:field class="w-full md:w-56">
                            <flux:label
                                class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Departamento') }}</flux:label>
                            <flux:select wire:model.live="departamento">
                                <flux:select.option value="todos">{{ __('Todos los Deptos.') }}</flux:select.option>
                                <flux:select.option value="ciencias">{{ __('Ciencias') }}</flux:select.option>
                                <flux:select.option value="matematicas">{{ __('Matemáticas') }}</flux:select.option>
                                <flux:select.option value="historia">{{ __('Historia') }}</flux:select.option>
                                <flux:select.option value="admin">{{ __('Administración') }}</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>
                </flux:card>
            </div>

            <div class="md:col-span-4">
                <flux:card
                    class="h-full flex items-center justify-between bg-zinc-900 border-none !text-white dark:bg-zinc-800">
                    <div>
                        <div class="text-[10px] uppercase tracking-widest font-bold opacity-70">
                            {{ __('Total Personal') }}</div>
                        <div class="text-4xl font-bold mt-1">{{ $this->funcionarios->total() }}</div>
                    </div>
                    <div class="p-3 bg-white/10 rounded-full">
                        <flux:icon.users class="size-8" />
                    </div>
                </flux:card>
            </div>
        </div>

        <!-- Table UI -->
        <flux:card>
            <flux:table :paginate="$this->funcionarios">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'nombres'" :direction="$sortDirection"
                        wire:click="sort('nombres')">{{ __('Nombre del Funcionario') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'rut_numero'" :direction="$sortDirection"
                        wire:click="sort('rut_numero')">{{ __('RUT') }}</flux:table.column>
                    <flux:table.column>{{ __('Cargo') }}</flux:table.column>
                    <flux:table.column>{{ __('Departamento') }}</flux:table.column>
                    <flux:table.column>{{ __('Estado') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Acciones') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->funcionarios as $funcionario)
                        <flux:table.row :key="$funcionario->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <flux:avatar initials="{{ $funcionario->initials() }}"
                                        :src="$funcionario->avatar" />
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $funcionario->nombreCompleto() }}</div>
                                        <div class="text-xs text-zinc-500">{{ $funcionario->email }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $funcionario->rutCompleto() ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="blue">Docente</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">-</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="green" icon="check-circle">Activo</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button variant="ghost" size="sm" icon="eye" :tooltip="__('Ver Ficha')" :href="route('funcionarios.ficha', $funcionario->id)" />
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" :tooltip="__('Editar')" wire:click="abrirEditar({{ $funcionario->id }})" />
                                    <flux:button variant="ghost" size="sm" icon="trash" :tooltip="__('Eliminar')" wire:click="confirmarEliminar({{ $funcionario->id }})" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <!-- Footer Meta Info -->
        <div class="flex flex-col sm:flex-row justify-between items-center text-zinc-500 dark:text-zinc-400 gap-4">
            <div class="flex space-x-10">
                <div>
                    <div class="text-[10px] uppercase font-bold tracking-widest mb-1">{{ __('Última Actualización') }}
                    </div>
                    <div class="text-xs font-medium">{{ __('Hoy, 09:45 AM - Control Central') }}</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs italic">"Compromiso con la excelencia y seguridad institucional"</div>
            </div>
        </div>
    </div>

    {{-- Modal Crear / Editar --}}
    <flux:modal wire:model="modalAbierto" class="md:w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $funcionarioId ? __('Editar Funcionario') : __('Nuevo Funcionario') }}</flux:heading>
                <flux:text class="mt-2">{{ $funcionarioId ? __('Modifica los datos del funcionario.') : __('Ingresa los datos del nuevo funcionario.') }}</flux:text>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <flux:input wire:model="nombres" :label="__('Nombre(s)')" placeholder="Juan" />
                <flux:input wire:model="apellidoPat" :label="__('Apellido Paterno')" placeholder="Pérez" />
                <flux:input wire:model="apellidoMat" :label="__('Apellido Materno')" placeholder="López" />
            </div>
            <flux:error name="nombres" />
            <flux:error name="apellidoPat" />

            <flux:input wire:model="email" :label="__('Correo electrónico')" type="email" placeholder="juan@colegio.cl" />
            <flux:error name="email" />

            <div class="flex gap-2">
                <flux:input wire:model="rutNumero" :label="__('RUT')" placeholder="12345678" class="flex-1" />
                <flux:input wire:model="rutDv" :label="__('DV')" placeholder="K" class="w-20" maxlength="1" />
            </div>
            <flux:error name="rutNumero" />
            <flux:error name="rutDv" />

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="$set('modalAbierto', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="guardar" variant="primary" class="ml-2">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal Confirmar Eliminar --}}
    <flux:modal wire:model="modalEliminar" class="md:w-80">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Eliminar funcionario') }}</flux:heading>
                <flux:text class="mt-2">{{ __('El funcionario será desvinculado del colegio. Esta acción no puede deshacerse.') }}</flux:text>
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="$set('modalEliminar', false)" variant="ghost">{{ __('Cancelar') }}</flux:button>
                <flux:button wire:click="eliminar" variant="danger" class="ml-2">{{ __('Eliminar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
