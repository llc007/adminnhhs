<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Flux\Flux;

new #[Title('Roles y Permisos')] class extends Component
{
    public ?int $selectedRoleId = null;
    public array $permisosSeleccionados = [];

    // Grouped standard permissions
    public array $groupedPermissions = [
        'Entrevistas' => [
            'ver-entrevistas-propias' => 'Ver entrevistas asignadas a mí',
            'ver-entrevistas-general' => 'Ver todas las entrevistas del colegio',
            'ver-bitacoras' => 'Ver todas las bitácoras (detalles) de entrevistas',
            'crear-entrevistas' => 'Agendar y coordinar nuevas citas',
            'cancelar-entrevistas' => 'Cancelar entrevistas agendadas',
            'ingresar-apoderado' => 'Registrar ingreso/salida de portería (Acceso)',
        ],
        'Estudiantes' => [
            'ver-estudiantes' => 'Ver lista e información de estudiantes',
            'editar-estudiantes' => 'Editar fichas de estudiantes',
            'importar-estudiantes' => 'Cargar estudiantes por CSV',
        ],
        'Adquisiciones' => [
            'crear-requerimientos' => 'Solicitar adquisiciones e insumos',
            'aprobar-requerimientos' => 'Aprobar o rechazar solicitudes de compras',
            'ver-requerimientos-general' => 'Ver historial general de adquisiciones',
        ],
        'Préstamos de Informática' => [
            'ver-prestamos-propios' => 'Ver mis equipos en comodato',
            'ver-prestamos-general' => 'Ver préstamos generales e inventario',
            'gestionar-prestamos' => 'Registrar préstamos, devoluciones e inventario',
        ],
        'Administración' => [
            'gestionar-modulos' => 'Habilitar/deshabilitar módulos y correos',
            'gestionar-funcionarios' => 'Administrar funcionarios (añadir, editar, asignar roles)',
            'gestionar-roles-permisos' => 'Gestionar roles y permisos del colegio',
        ]
    ];

    public function mount(): void
    {
        // 1. Ensure all standard permissions exist in the database (global scope)
        foreach ($this->groupedPermissions as $category => $permissions) {
            foreach ($permissions as $name => $description) {
                Permission::findOrCreate($name, 'web');
            }
        }

        // 2. Load the first role of the current school by default
        $schoolId = auth()->user()->current_school_id;
        if ($schoolId) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

            // Pre-create standard roles if they do not exist
            $standardRoles = ['superadmin', 'administrador', 'directivo', 'docente', 'inspector', 'asistente', 'psicosocial', 'recepcion', 'solicitante_adquisiciones', 'ti'];
            foreach ($standardRoles as $roleName) {
                Role::findOrCreate($roleName, 'web');
            }

            $firstRole = Role::where('team_id', $schoolId)->orderBy('id', 'asc')->first();
            if ($firstRole) {
                $this->selectRole($firstRole->id);
            }
        }
    }

    public function selectRole(int $roleId): void
    {
        $schoolId = auth()->user()->current_school_id;
        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

        $role = Role::where('team_id', $schoolId)->findOrFail($roleId);
        $this->selectedRoleId = $role->id;
        $this->permisosSeleccionados = $role->permissions->pluck('name')->toArray();
    }

    #[\Livewire\Attributes\Computed]
    public function roles()
    {
        $schoolId = auth()->user()->current_school_id;
        if (! $schoolId) {
            return collect();
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

        return Role::where('team_id', $schoolId)
            ->with('permissions')
            ->orderBy('id', 'asc')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function selectedRole()
    {
        if (! $this->selectedRoleId) {
            return null;
        }

        return Role::where('team_id', auth()->user()->current_school_id)
            ->find($this->selectedRoleId);
    }

    public function guardar(): void
    {
        $schoolId = auth()->user()->current_school_id;
        if (! $schoolId || ! $this->selectedRoleId) {
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
        $role = Role::where('team_id', $schoolId)->findOrFail($this->selectedRoleId);

        $role->syncPermissions($this->permisosSeleccionados);

        Flux::toast(
            heading: __('Permisos Guardados'),
            text: __('Los permisos para el rol ":role" han sido actualizados con éxito.', ['role' => strtoupper($role->name)]),
            variant: 'success'
        );
    }
}; ?>

<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">
    <x-header
        :titulo="__('Roles y Permisos')"
        :subtitulo="__('Asigna y gestiona las facultades específicas de cada perfil de funcionario en el establecimiento.')"
        icono="shield-check"
    />

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        {{-- Columna Izquierda: Listado de Roles --}}
        <div class="lg:col-span-1 space-y-4">
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Roles disponibles') }}</flux:heading>
                <flux:navlist variant="links" class="space-y-1">
                    @foreach ($this->roles as $role)
                        @php $isActive = $this->selectedRoleId === $role->id; @endphp
                        <button type="button" wire:click="selectRole({{ $role->id }})"
                            class="w-full text-left flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ $isActive ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-900' }}">
                            <span class="capitalize">{{ $role->name }}</span>
                            <flux:badge size="sm" color="{{ $isActive ? 'blue' : 'zinc' }}">
                                {{ $role->permissions->count() }}
                            </flux:badge>
                        </button>
                    @endforeach
                </flux:navlist>
            </flux:card>
        </div>

        {{-- Columna Derecha: Matriz de Permisos --}}
        <div class="lg:col-span-3">
            @if ($this->selectedRole)
                <form wire:submit="guardar" class="space-y-6">
                    <flux:card>
                        <div class="flex items-center justify-between border-b pb-4 mb-6 dark:border-zinc-700">
                            <div>
                                <flux:heading size="lg" class="capitalize">{{ __('Permisos para: ') . $this->selectedRole->name }}</flux:heading>
                                <flux:subheading size="sm">{{ __('Marca los permisos que deseas conceder a este perfil.') }}</flux:subheading>
                            </div>
                            <flux:button type="submit" variant="primary" icon="check">{{ __('Guardar Permisos') }}</flux:button>
                        </div>

                        <div class="space-y-8">
                            @foreach ($groupedPermissions as $category => $permissions)
                                <div class="space-y-3">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 dark:border-zinc-800/50 pb-1">
                                        {{ $category }}
                                    </h4>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @foreach ($permissions as $name => $description)
                                            <flux:field variant="inline" class="items-start">
                                                <flux:checkbox wire:model="permisosSeleccionados" value="{{ $name }}" />
                                                <div class="-mt-0.5">
                                                    <flux:label class="font-medium cursor-pointer">{{ $name }}</flux:label>
                                                    <flux:description class="text-xs">{{ $description }}</flux:description>
                                                </div>
                                            </flux:field>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                </form>
            @else
                <flux:card class="flex flex-col items-center justify-center py-12 text-center text-zinc-400">
                    <flux:icon.shield-check class="size-12 mb-3 text-zinc-300" />
                    <p>{{ __('Selecciona un rol en el panel de la izquierda para gestionar sus permisos.') }}</p>
                </flux:card>
            @endif
        </div>
    </div>
</div>
