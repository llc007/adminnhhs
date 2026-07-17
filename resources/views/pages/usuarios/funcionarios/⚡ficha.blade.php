<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public ?int $id = null;

    // Información personal
    public string $nombres = '';

    public string $apellidoPat = '';

    public string $apellidoMat = '';

    public string $rutNumero = '';

    public string $rutDv = '';

    public string $fechaNacimiento = '';

    public string $email = '';

    public string $telefono = '';

    // Roles de Sistema
    public array $roles = [];

    public function mount(int $id): void
    {
        $this->id = $id;

        $funcionario = User::findOrFail($id);

        $this->nombres = $funcionario->nombres ?? '';
        $this->apellidoPat = $funcionario->apellido_pat ?? '';
        $this->apellidoMat = $funcionario->apellido_mat ?? '';
        $this->rutNumero = $funcionario->rut_numero ?? '';
        $this->rutDv = $funcionario->rut_dv ?? '';
        $this->fechaNacimiento = $funcionario->fecha_nacimiento ?? '';
        $this->email = $funcionario->email;
        $this->roles = $funcionario->active_roles;
    }

    public function updated($propertyName, $value): void
    {
        if (in_array($propertyName, ['nombres', 'apellidoPat', 'apellidoMat'])) {
            $this->{$propertyName} = mb_strtoupper((string) $value, 'UTF-8');
        }
    }

    public function guardar(): void
    {
        $this->validate([
            'nombres' => ['required', 'string', 'max:255'],
            'apellidoPat' => ['required', 'string', 'max:255'],
            'apellidoMat' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->id)],
            'rutNumero' => ['nullable', 'digits_between:7,9'],
            'rutDv' => ['nullable', 'max:1', 'regex:/^[0-9Kk]$/'],
            'fechaNacimiento' => ['nullable', 'date'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        $funcionario = User::findOrFail($this->id);

        $funcionario->update([
            'nombres' => $this->nombres,
            'apellido_pat' => $this->apellidoPat,
            'apellido_mat' => $this->apellidoMat ?: null,
            'email' => $this->email,
            'rut_numero' => $this->rutNumero ?: null,
            'rut_dv' => $this->rutDv !== '' ? strtoupper($this->rutDv) : null,
            'fecha_nacimiento' => $this->fechaNacimiento ?: null,
        ]);

        if (auth()->user()->hasRole(['administrador', 'superadmin'])) {
            $nuevosRoles = $this->roles;
            // If assigning any real system roles, automatically remove the 'externo' (pending) status
            $realRoles = array_diff($nuevosRoles, ['externo']);
            if (! empty($realRoles)) {
                $nuevosRoles = array_values($realRoles);
            }
            if (empty($nuevosRoles)) {
                $nuevosRoles = ['estudiante'];
            }
            if ($funcionario->current_school_id) {
                $funcionario->syncRolesForSchool($funcionario->current_school_id, $nuevosRoles);
            }
        }

        Flux::toast(
            heading: __('Ficha actualizada'),
            text: __('Los datos del funcionario han sido guardados correctamente.'),
            variant: 'success',
        );
    }
};
?>

<div class="flex flex-col gap-8 max-w-5xl mx-auto w-full">

    {{-- Breadcrumbs + Título --}}
    <div>
        <x-header 
            :titulo="__('Ficha Digital del Funcionario')" 
            :subtitulo="__('Información institucional del miembro del equipo docente o administrativo.')" 
            icono="user"
        >
            <flux:button href="{{ route('funcionarios.index') }}" variant="ghost" icon="arrow-left">
                {{ __('Volver al listado') }}
            </flux:button>
        </x-header>
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
            <flux:input wire:model="nombres" :label="__('Nombre(s)')" placeholder="EJ: MARCELA PAZ" class="uppercase" />
            <flux:input wire:model="apellidoPat" :label="__('Apellido Paterno')" placeholder="EJ: RODRÍGUEZ" class="uppercase" />
            <flux:input wire:model="apellidoMat" :label="__('Apellido Materno')" placeholder="EJ: LÓPEZ" class="uppercase" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
            <div class="flex gap-2 items-end">
                <flux:input wire:model="rutNumero" :label="__('RUT')" placeholder="12345678" class="flex-1" />
                <flux:input wire:model="rutDv" :label="__('DV')" placeholder="K" class="w-20" maxlength="1" />
            </div>

            <flux:input wire:model="fechaNacimiento" :label="__('Fecha de Nacimiento')" type="date" />

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
            <flux:checkbox wire:model="roles" value="solicitante_adquisiciones" :label="__('Solicitante de Adquisiciones')" />
            <flux:checkbox wire:model="roles" value="ti" :label="__('Personal de TI / Informática')" />
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
</div>
