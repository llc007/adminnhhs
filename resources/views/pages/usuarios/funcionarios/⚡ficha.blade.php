<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Illuminate\Validation\Rule;

new class extends Component {
    public ?int $id = null;

    // Información personal
    public string $nombres = '';
    public string $apellidoPat = '';
    public string $apellidoMat = '';
    public string $rutNumero = '';
    public string $rutDv = '';
    public string $email = '';
    public string $telefono = '';

    // Información profesional
    public string $cargo = '';
    public string $departamento = '';
    public string $fechaContratacion = '';

    // Roles de Sistema
    public array $roles = [];

    public function mount(int $id): void
    {
        $this->id = $id;

        $funcionario = \App\Models\User::findOrFail($id);

        $this->nombres = $funcionario->nombres ?? '';
        $this->apellidoPat = $funcionario->apellido_pat ?? '';
        $this->apellidoMat = $funcionario->apellido_mat ?? '';
        $this->rutNumero = $funcionario->rut_numero ?? '';
        $this->rutDv = $funcionario->rut_dv ?? '';
        $this->email = $funcionario->email;
        $this->roles = $funcionario->active_roles;
    }

    public function guardar(): void
    {
        $this->validate([
            'nombres'     => ['required', 'string', 'max:255'],
            'apellidoPat' => ['required', 'string', 'max:255'],
            'apellidoMat' => ['nullable', 'string', 'max:255'],
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($this->id)],
            'rutNumero'   => ['nullable', 'digits_between:7,9'],
            'rutDv'       => ['nullable', 'string', 'max:1', 'regex:/^[0-9Kk]$/'],
            'telefono'    => ['nullable', 'string', 'max:20'],
            'cargo'       => ['nullable', 'string', 'max:100'],
            'departamento'       => ['nullable', 'string', 'max:100'],
            'fechaContratacion'  => ['nullable', 'date'],
        ]);

        $funcionario = \App\Models\User::findOrFail($this->id);
        
        $funcionario->update([
            'nombres'      => $this->nombres,
            'apellido_pat' => $this->apellidoPat,
            'apellido_mat' => $this->apellidoMat ?: null,
            'email'        => $this->email,
            'rut_numero'   => $this->rutNumero ?: null,
            'rut_dv'       => $this->rutDv ? strtoupper($this->rutDv) : null,
        ]);

        if (auth()->user()->hasRole(['administrador', 'superadmin'])) {
            $nuevosRoles = empty($this->roles) ? ['estudiante'] : $this->roles;
            if ($funcionario->current_school_id) {
                if ($funcionario->schools()->where('school_id', $funcionario->current_school_id)->exists()) {
                    $funcionario->schools()->updateExistingPivot($funcionario->current_school_id, [
                        'roles' => json_encode($nuevosRoles)
                    ]);
                } else {
                    $funcionario->schools()->attach($funcionario->current_school_id, [
                        'roles' => json_encode($nuevosRoles)
                    ]);
                }
            }
        }

        $this->dispatch('saved');
    }
};
?>

<div class="flex flex-col gap-8 max-w-5xl mx-auto w-full">

    {{-- Breadcrumbs + Título --}}
    <div>
        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item icon="building-library" href="#" />
            <flux:breadcrumbs.item href="{{ route('funcionarios.index') }}">{{ __('Funcionarios') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Ficha Digital') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex items-start justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Ficha Digital del Funcionario') }}</flux:heading>
                <flux:subheading size="lg" class="max-w-2xl">
                    {{ __('Información institucional del miembro del equipo docente o administrativo.') }}
                </flux:subheading>
            </div>
            <flux:button href="{{ route('funcionarios.index') }}" variant="ghost" icon="arrow-left">
                {{ __('Volver al listado') }}
            </flux:button>
        </div>
    </div>

    {{-- Sección 1: Información Personal --}}
    <flux:card>
        <div class="flex items-center gap-3 mb-6">
            <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                <flux:icon.user class="size-5 text-zinc-600 dark:text-zinc-300" />
            </div>
            <flux:heading size="lg">{{ __('Información Personal') }}</flux:heading>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:input wire:model="nombres" :label="__('Nombre(s)')" placeholder="Ej: Marcela Paz" />
            <flux:input wire:model="apellidoPat" :label="__('Apellido Paterno')" placeholder="Ej: Rodríguez" />
            <flux:input wire:model="apellidoMat" :label="__('Apellido Materno')" placeholder="Ej: López" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <div class="flex gap-2 items-end">
                <flux:input wire:model="rutNumero" :label="__('RUT')" placeholder="12345678" class="flex-1" />
                <flux:input wire:model="rutDv" :label="__('DV')" placeholder="K" class="w-20" maxlength="1" />
            </div>

            <flux:input wire:model="email" :label="__('Correo Electrónico')" type="email" placeholder="m.paz@colegio.cl" />

            <flux:input wire:model="telefono" :label="__('Teléfono de Contacto')" type="tel" placeholder="+56 9 1234 5678" />
        </div>

        <div class="flex flex-col gap-1 mt-2">
            <flux:error name="nombres" />
            <flux:error name="apellidoPat" />
            <flux:error name="email" />
            <flux:error name="rutNumero" />
            <flux:error name="rutDv" />
        </div>
    </flux:card>

    {{-- Sección 2: Información Profesional --}}
    <flux:card>
        <div class="flex items-center gap-3 mb-6">
            <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                <flux:icon.briefcase class="size-5 text-zinc-600 dark:text-zinc-300" />
            </div>
            <flux:heading size="lg">{{ __('Información Profesional') }}</flux:heading>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:select wire:model="cargo" :label="__('Cargo')">
                <flux:select.option value="" disabled>{{ __('Seleccione cargo') }}</flux:select.option>
                <flux:select.option value="docente">{{ __('Docente de Aula') }}</flux:select.option>
                <flux:select.option value="inspector">{{ __('Inspector General') }}</flux:select.option>
                <flux:select.option value="orientador">{{ __('Orientador') }}</flux:select.option>
                <flux:select.option value="psicopedagogo">{{ __('Psicopedagogo') }}</flux:select.option>
                <flux:select.option value="administrativo">{{ __('Administrativo') }}</flux:select.option>
                <flux:select.option value="directivo">{{ __('Directivo') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model="departamento" :label="__('Departamento')" placeholder="Ej: Matemáticas" />

            <flux:input wire:model="fechaContratacion" :label="__('Fecha de Contratación')" type="date" />
        </div>
    </flux:card>

    {{-- Sección 3: Protocolo --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <flux:card class="lg:col-span-2">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                    <flux:icon.lock-closed class="size-5 text-zinc-600 dark:text-zinc-300" />
                </div>
                <flux:heading size="lg">{{ __('Credenciales del Sistema') }}</flux:heading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <flux:input :label="__('Correo institucional (usuario)')" :value="$email" disabled />
                    <flux:text class="mt-1 text-xs">{{ __('Generado a partir del correo electrónico.') }}</flux:text>
                </div>
                <div>
                    <flux:input :label="__('Contraseña Inicial')" type="password" value="••••••••" disabled />
                    <flux:text class="mt-1 text-xs">{{ __('El funcionario deberá cambiarla al primer ingreso.') }}
                    </flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card class="flex flex-col justify-center gap-3 bg-zinc-50 dark:bg-zinc-800/50">
            <flux:icon.shield-check class="size-10 text-zinc-600 dark:text-zinc-300" />
            <flux:heading>{{ __('Protocolo de Seguridad') }}</flux:heading>
            <flux:text class="text-sm">
                {{ __('Al registrar un nuevo funcionario, se le enviará automáticamente un enlace de activación a su correo institucional con las instrucciones de acceso y políticas de privacidad del establecimiento.') }}
            </flux:text>
        </flux:card>
    </div>

    @if(auth()->user()->hasRole(['administrador', 'superadmin']))
    {{-- Sección 4: Roles de Acceso (Solo Admins) --}}
    <flux:card>
        <div class="flex items-center gap-3 mb-6">
            <div class="p-2 bg-gradient-to-r from-red-500 to-[#00376e] text-white rounded-lg shadow-sm">
                <flux:icon.key class="size-5" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('Asignación de Roles en el Sistema') }}</flux:heading>
                <flux:subheading size="sm">{{ __('Niveles de acceso y módulos habilitados. Visibilidad restringida a Administradores.') }}</flux:subheading>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-y-4 gap-x-6">
            <flux:checkbox wire:model="roles" value="docente" :label="__('Docente de Aula')" />
            <flux:checkbox wire:model="roles" value="inspector" :label="__('Inspectoría')" />
            <flux:checkbox wire:model="roles" value="asistente" :label="__('Asistente de Educación')" />
            <flux:checkbox wire:model="roles" value="psicosocial" :label="__('Equipo Psicosocial')" />
            <flux:checkbox wire:model="roles" value="recepcion" :label="__('Recepción / Portería')" />
            <flux:checkbox wire:model="roles" value="directivo" :label="__('Cuerpo Directivo')" />
            <flux:checkbox border="zinc" wire:model="roles" value="administrador" label="Admin del Colegio" />
            @if(auth()->user()->hasRole('superadmin'))
                <flux:checkbox wire:model="roles" value="superadmin" label="Superusuario Global" class="text-red-500 font-bold" />
            @endif
        </div>
    </flux:card>
    @endif

    {{-- Acciones --}}
    <div class="flex items-center justify-end gap-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
        <flux:button href="{{ route('funcionarios.index') }}" variant="ghost">
            {{ __('Cancelar') }}
        </flux:button>
        <flux:button wire:click="guardar" variant="primary" icon="check">
            {{ __('Guardar Ficha') }}
        </flux:button>
    </div>

    {{-- Toast de confirmación --}}
    <flux:toast />
</div>
