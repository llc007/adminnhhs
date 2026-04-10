<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Illuminate\Validation\Rule;

new class extends Component {
    public ?int $id = null;
    public ?int $userId = null;
    public string $emailInstitucional = '';

    // Estudiante
    public string $nombresCsv = '';
    public string $rutNumero = '';
    public string $rutDv = '';
    public string $fechaNacimiento = '';
    public string $genero = '';
    public ?int $cursoId = null;

    // Apoderado
    public string $apoderadoNombres = '';
    public string $apoderadoApellidoPat = '';
    public string $apoderadoApellidoMat = '';
    public string $apoderadoRutNumero = '';
    public string $apoderadoRutDv = '';
    public string $apoderadoEmail = '';
    public string $apoderadoTelefono = '';
    public string $apoderadoParentesco = '';
    public string $apoderadoDomicilio = '';

    public function mount(int $id): void
    {
        $this->id = $id;

        $estudiante = \App\Models\Estudiante::with(['user', 'curso'])->findOrFail($id);

        // Si el estudiante no es del colegio seleccionado, abortar
        if ($estudiante->school_id !== auth()->user()->current_school_id) {
            abort(403);
        }

        $this->userId = $estudiante->user_id;
        $this->emailInstitucional = $estudiante->user ? $estudiante->user->email : 'No vinculado';

        $this->nombresCsv = $estudiante->nombres_csv ?? '';
        $this->rutNumero = $estudiante->rut_numero ?? '';
        $this->rutDv = $estudiante->rut_dv ?? '';
        $this->fechaNacimiento = $estudiante->fecha_nacimiento ?? '';
        $this->genero = $estudiante->genero ?? '';
        $this->cursoId = $estudiante->curso_id;

        $this->apoderadoNombres = $estudiante->apoderado_nombres ?? '';
        $this->apoderadoApellidoPat = $estudiante->apoderado_apellido_pat ?? '';
        $this->apoderadoApellidoMat = $estudiante->apoderado_apellido_mat ?? '';
        $this->apoderadoRutNumero = $estudiante->apoderado_rut_numero ?? '';
        $this->apoderadoRutDv = $estudiante->apoderado_rut_dv ?? '';
        $this->apoderadoEmail = $estudiante->apoderado_email ?? '';
        $this->apoderadoTelefono = $estudiante->apoderado_telefono ?? '';
        $this->apoderadoParentesco = $estudiante->apoderado_parentesco ?? '';
        $this->apoderadoDomicilio = $estudiante->apoderado_domicilio ?? '';
    }

    #[\Livewire\Attributes\Computed]
    public function cursos()
    {
        return \App\Models\Curso::where('school_id', auth()->user()->current_school_id)
            ->orderBy('nivel')
            ->orderBy('letra')
            ->get();
    }

    public function guardar(): void
    {
        $this->validate([
            'nombresCsv'          => ['nullable', 'string', 'max:255'],
            'rutNumero'           => ['nullable', 'digits_between:7,9'],
            'rutDv'               => ['nullable', 'string', 'max:1', 'regex:/^[0-9Kk]$/'],
            'fechaNacimiento'     => ['nullable', 'date'],
            'genero'              => ['nullable', 'string', 'max:20'],
            'cursoId'             => ['nullable', 'exists:cursos,id'],

            'apoderadoNombres'    => ['nullable', 'string', 'max:255'],
            'apoderadoApellidoPat'=> ['nullable', 'string', 'max:255'],
            'apoderadoApellidoMat'=> ['nullable', 'string', 'max:255'],
            'apoderadoRutNumero'  => ['nullable', 'digits_between:7,9'],
            'apoderadoRutDv'      => ['nullable', 'string', 'max:1', 'regex:/^[0-9Kk]$/'],
            'apoderadoEmail'      => ['nullable', 'email', 'max:255'],
            'apoderadoTelefono'   => ['nullable', 'string', 'max:20'],
            'apoderadoParentesco' => ['nullable', 'string', 'max:50'],
            'apoderadoDomicilio'  => ['nullable', 'string', 'max:255'],
        ]);

        \App\Models\Estudiante::findOrFail($this->id)->update([
            'nombres_csv'            => $this->nombresCsv,
            'rut_numero'             => $this->rutNumero ?: null,
            'rut_dv'                 => $this->rutDv ? strtoupper($this->rutDv) : null,
            'fecha_nacimiento'       => $this->fechaNacimiento ?: null,
            'genero'                 => $this->genero ?: null,
            'curso_id'               => $this->cursoId,

            'apoderado_nombres'      => $this->apoderadoNombres,
            'apoderado_apellido_pat' => $this->apoderadoApellidoPat,
            'apoderado_apellido_mat' => $this->apoderadoApellidoMat ?: null,
            'apoderado_rut_numero'   => $this->apoderadoRutNumero ?: null,
            'apoderado_rut_dv'       => $this->apoderadoRutDv ? strtoupper($this->apoderadoRutDv) : null,
            'apoderado_email'        => $this->apoderadoEmail,
            'apoderado_telefono'     => $this->apoderadoTelefono,
            'apoderado_parentesco'   => $this->apoderadoParentesco,
            'apoderado_domicilio'    => $this->apoderadoDomicilio,
        ]);

        $this->dispatch('saved');
    }
};
?>

<div class="flex flex-col gap-8 max-w-5xl mx-auto w-full">

    {{-- Breadcrumbs + Título --}}
    <div>
        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item icon="building-library" href="#" />
            <flux:breadcrumbs.item href="{{ route('estudiantes.index') }}">{{ __('Estudiantes') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Ficha del Estudiante') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex items-start justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Ficha Escolar') }}</flux:heading>
                <flux:subheading size="lg" class="max-w-2xl">
                    {{ __('Registro institucional del estudiante y su grupo familiar.') }}
                </flux:subheading>
            </div>
            <flux:button href="{{ route('estudiantes.index') }}" variant="ghost" icon="arrow-left">
                {{ __('Volver al listado') }}
            </flux:button>
        </div>
    </div>

    @if(!$userId)
    <flux:card class="bg-orange-50 dark:bg-orange-500/10 border-orange-200 dark:border-orange-500/20">
        <div class="flex gap-4">
            <div class="text-orange-500 dark:text-orange-400 mt-1">
                <flux:icon.exclamation-triangle class="size-6" />
            </div>
            <div>
                <flux:heading class="text-orange-800 dark:text-orange-300">{{ __('Pendiente de Vinculación') }}</flux:heading>
                <flux:text class="text-orange-700 dark:text-orange-400/80 mt-1">
                    {{ __('Este registro de estudiante fue importado desde el sistema de gestión preexistente, pero todavía no ha sido vinculado a una cuenta de Google institucional (Workspace).') }}
                </flux:text>
            </div>
        </div>
    </flux:card>
    @endif

    {{-- Sección 1: Información del Estudiante --}}
    <flux:card>
        <div class="flex items-center gap-3 mb-6">
            <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                <flux:icon.user class="size-5 text-zinc-600 dark:text-zinc-300" />
            </div>
            <flux:heading size="lg">{{ __('Información del Estudiante') }}</flux:heading>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <flux:input wire:model="nombresCsv" :label="__('Nombre Completo (Importado)')" placeholder="Ej: Marcela Paz Rodríguez López" />
                <flux:text class="mt-1 text-xs">{{ __('Nombre importado inicialmente. Se actualizará con el nombre real de su cuenta Google cuando inicie sesión.') }}</flux:text>
            </div>

            <div class="flex gap-2 items-end">
                <flux:input wire:model="rutNumero" :label="__('RUT Estudiante')" placeholder="12345678" class="flex-1" />
                <flux:input wire:model="rutDv" :label="__('DV')" placeholder="K" class="w-20" maxlength="1" />
            </div>

            <flux:input wire:model="emailInstitucional" :label="__('Correo Institucional (@newheavenhs.cl)')" disabled />

            <flux:input wire:model="fechaNacimiento" :label="__('Fecha de Nacimiento')" type="date" />

            <flux:select wire:model="genero" :label="__('Género')">
                <flux:select.option value="">{{ __('Seleccione género') }}</flux:select.option>
                <flux:select.option value="femenino">{{ __('Femenino') }}</flux:select.option>
                <flux:select.option value="masculino">{{ __('Masculino') }}</flux:select.option>
                <flux:select.option value="otro">{{ __('Otro / Prefiero no decir') }}</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    {{-- Sección 2: Información Académica --}}
    <flux:card>
        <div class="flex items-center gap-3 mb-6">
            <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                <flux:icon.academic-cap class="size-5 text-zinc-600 dark:text-zinc-300" />
            </div>
            <flux:heading size="lg">{{ __('Historial Académico - Año Actual') }}</flux:heading>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:select wire:model="cursoId" :label="__('Curso Asignado')">
                <flux:select.option value="">{{ __('Sin curso asignado') }}</flux:select.option>
                @foreach ($this->cursos as $curso)
                    <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input :label="__('Estado Académico')" value="Alumno Regular" disabled />
        </div>
    </flux:card>

    {{-- Sección 3: Datos del Apoderado Titular --}}
    <flux:card>
        <div class="flex items-center gap-3 mb-6">
            <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                <flux:icon.users class="size-5 text-zinc-600 dark:text-zinc-300" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('Datos del Apoderado Titular') }}</flux:heading>
                <flux:text class="text-xs mt-1">{{ __('Responsable financiero y académico principal frente a la institución.') }}</flux:text>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:input wire:model="apoderadoNombres" :label="__('Nombre(s)')" placeholder="Nombre apoderado" />
            <flux:input wire:model="apoderadoApellidoPat" :label="__('Apellido Paterno')" placeholder="" />
            <flux:input wire:model="apoderadoApellidoMat" :label="__('Apellido Materno')" placeholder="" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
            <div class="flex gap-2 items-end">
                <flux:input wire:model="apoderadoRutNumero" :label="__('RUT Apoderado')" placeholder="12345678" class="flex-1" />
                <flux:input wire:model="apoderadoRutDv" :label="__('DV')" placeholder="K" class="w-20" maxlength="1" />
            </div>

            <flux:input wire:model="apoderadoParentesco" :label="__('Parentesco con alumno')" placeholder="Ej: Madre, Padre, Tío, Abuela" />
            <flux:input wire:model="apoderadoTelefono" :label="__('Teléfono de Contacto')" type="tel" placeholder="+56 9 1234 5678" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <flux:input wire:model="apoderadoEmail" :label="__('Correo Electrónico (Personal)')" type="email" placeholder="apoderado@gmail.com" />
            <flux:input wire:model="apoderadoDomicilio" :label="__('Domicilio Registrado')" placeholder="Ej: Los Pinos 123, Villa San Rafael" />
        </div>
    </flux:card>

    {{-- Acciones --}}
    <div class="flex items-center justify-end gap-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
        <flux:button href="{{ route('estudiantes.index') }}" variant="ghost">
            {{ __('Cancelar') }}
        </flux:button>
        <flux:button wire:click="guardar" variant="primary" icon="check">
            {{ __('Guardar Ficha') }}
        </flux:button>
    </div>

    {{-- Toast de confirmación --}}
    <flux:toast />
</div>