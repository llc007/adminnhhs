<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Illuminate\Validation\Rule;
use Flux\Flux;

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
    public string $estado = 'activo';
    public ?string $fechaRetiro = null;
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

        if ($estudiante->school_id !== auth()->user()->current_school_id) {
            abort(403);
        }

        $this->userId = $estudiante->user_id;
        $this->emailInstitucional = $estudiante->email ?? '';

        $this->nombresCsv = $estudiante->nombres_csv ?? '';
        $this->rutNumero = $estudiante->rut_numero ?? '';
        $this->rutDv = $estudiante->rut_dv ?? '';
        $this->fechaNacimiento = $estudiante->fecha_nacimiento ?? '';
        $this->genero = $estudiante->genero ?? '';
        $this->estado = $estudiante->estado ?? 'activo';
        $this->fechaRetiro = $estudiante->fecha_retiro ? $estudiante->fecha_retiro->format('Y-m-d') : null;
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

    public function updated($propertyName, $value): void
    {
        if (in_array($propertyName, ['nombresCsv', 'apoderadoNombres', 'apoderadoApellidoPat', 'apoderadoApellidoMat', 'apoderadoDomicilio'])) {
            $this->{$propertyName} = mb_strtoupper((string) $value, 'UTF-8');
        }
    }

    #[\Livewire\Attributes\Computed]
    public function cursos()
    {
        return \App\Models\Curso::where('school_id', auth()->user()->current_school_id)
            ->orderBy('nivel')
            ->orderBy('letra')
            ->get();
    }

    public function cambiarEstado(string $nuevoEstado): void
    {
        if (!auth()->user()->can('editar-estudiantes') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        $this->estado = $nuevoEstado;
        if ($nuevoEstado === 'retirado') {
            $this->fechaRetiro = date('Y-m-d');
        } else {
            $this->fechaRetiro = null;
        }

        \App\Models\Estudiante::findOrFail($this->id)->update([
            'estado' => $this->estado,
            'fecha_retiro' => $this->fechaRetiro,
        ]);

        $mensaje = $nuevoEstado === 'retirado' 
            ? 'El estudiante fue marcado como RETIRADO. Su historial se conserva intacto.'
            : 'El estudiante fue REACTIVADO como alumno regular.';
            
        Flux::toast($mensaje, variant: $nuevoEstado === 'retirado' ? 'warning' : 'success');
    }

    public function guardar(): void
    {
        if (!auth()->user()->can('editar-estudiantes') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        $this->validate([
            'nombresCsv'          => ['nullable', 'string', 'max:255'],
            'rutNumero'           => ['nullable', 'digits_between:7,9'],
            'rutDv'               => ['nullable', 'max:1', 'regex:/^[0-9Kk]$/'],
            'emailInstitucional'  => ['nullable', 'email', 'max:255', Rule::unique('estudiantes', 'email')->ignore($this->id)],
            'fechaNacimiento'     => ['nullable', 'date'],
            'genero'              => ['nullable', 'string', 'max:20'],
            'estado'              => ['required', 'string', 'in:activo,retirado'],
            'fechaRetiro'         => ['nullable', 'date'],
            'cursoId'             => ['nullable', 'exists:cursos,id'],

            'apoderadoNombres'    => ['nullable', 'string', 'max:255'],
            'apoderadoApellidoPat'=> ['nullable', 'string', 'max:255'],
            'apoderadoApellidoMat'=> ['nullable', 'string', 'max:255'],
            'apoderadoRutNumero'  => ['nullable', 'digits_between:7,9'],
            'apoderadoRutDv'      => ['nullable', 'max:1', 'regex:/^[0-9Kk]$/'],
            'apoderadoEmail'      => ['nullable', 'email', 'max:255'],
            'apoderadoTelefono'   => ['nullable', 'string', 'max:20'],
            'apoderadoParentesco' => ['nullable', 'string', 'max:50'],
            'apoderadoDomicilio'  => ['nullable', 'string', 'max:255'],
        ]);

        $user = \App\Models\User::where('email', $this->emailInstitucional)->first();
        if ($user) {
            $this->userId = $user->id;
        } else {
            $this->userId = null;
        }

        \App\Models\Estudiante::findOrFail($this->id)->update([
            'nombres_csv'            => $this->nombresCsv,
            'rut_numero'             => $this->rutNumero ?: null,
            'rut_dv'                 => $this->rutDv !== '' ? strtoupper($this->rutDv) : null,
            'email'                  => $this->emailInstitucional ?: null,
            'user_id'                => $this->userId,
            'vinculado_en'           => $user ? now() : null,
            'fecha_nacimiento'       => $this->fechaNacimiento ?: null,
            'genero'                 => $this->genero ?: null,
            'estado'                 => $this->estado,
            'fecha_retiro'           => $this->estado === 'retirado' ? ($this->fechaRetiro ?: now()) : null,
            'curso_id'               => $this->cursoId,

            'apoderado_nombres'      => $this->apoderadoNombres,
            'apoderado_apellido_pat' => $this->apoderadoApellidoPat,
            'apoderado_apellido_mat' => $this->apoderadoApellidoMat ?: null,
            'apoderado_rut_numero'   => $this->apoderadoRutNumero ?: null,
            'apoderado_rut_dv'       => $this->apoderadoRutDv !== '' ? strtoupper($this->apoderadoRutDv) : null,
            'apoderado_email'        => $this->apoderadoEmail,
            'apoderado_telefono'     => $this->apoderadoTelefono,
            'apoderado_parentesco'   => $this->apoderadoParentesco,
            'apoderado_domicilio'    => $this->apoderadoDomicilio,
        ]);

        $this->dispatch('saved');
        Flux::toast('Ficha del estudiante guardada con éxito.', variant: 'success');
    }
};
?>

<div class="flex flex-col gap-8 max-w-5xl mx-auto w-full">

    {{-- Breadcrumbs + Título + Botones de Acción --}}
    <div>
        <x-header 
            :titulo="__('Ficha Escolar')" 
            :subtitulo="__('Registro institucional del estudiante y su grupo familiar.')" 
            icono="user"
        >
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('estudiantes.index') }}" variant="ghost" icon="arrow-left">
                    {{ __('Volver al listado') }}
                </flux:button>

                @if ($estado === 'retirado')
                    <flux:badge color="rose" class="font-bold text-xs">
                        ⚠️ Retirado ({{ $fechaRetiro ? \Carbon\Carbon::parse($fechaRetiro)->format('d/m/Y') : 'Sin fecha' }})
                    </flux:badge>
                    @if (auth()->user()->can('editar-estudiantes') || auth()->user()->hasRole('superadmin'))
                        <flux:button wire:click="cambiarEstado('activo')" variant="filled" class="bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300" icon="arrow-path">
                            {{ __('Reactivar Estudiante') }}
                        </flux:button>
                    @endif
                @else
                    @if (auth()->user()->can('editar-estudiantes') || auth()->user()->hasRole('superadmin'))
                        <flux:button 
                            wire:click="cambiarEstado('retirado')" 
                            wire:confirm="¿Estás seguro de marcar a este estudiante como RETIRADO? Su historial de entrevistas y datos permanecerá intacto."
                            variant="ghost" 
                            class="text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30" 
                            icon="user-minus"
                        >
                            {{ __('Marcar como Retirado') }}
                        </flux:button>
                    @endif
                @endif
            </div>
        </x-header>
    </div>

    @if($estado === 'retirado')
    <flux:card class="bg-rose-50 dark:bg-rose-500/10 border-rose-200 dark:border-rose-500/20">
        <div class="flex gap-4 items-center justify-between">
            <div class="flex gap-4 items-center">
                <div class="text-rose-600 dark:text-rose-400">
                    <flux:icon.user-minus class="size-6" />
                </div>
                <div>
                    <flux:heading class="text-rose-800 dark:text-rose-300">{{ __('Estudiante Retirado / Desvinculado') }}</flux:heading>
                    <flux:text class="text-rose-700 dark:text-rose-400/80 mt-0.5 text-xs">
                        {{ __('Este estudiante se encuentra marcado como retirado de la institución desde el ') }} 
                        <span class="font-bold">{{ $fechaRetiro ? \Carbon\Carbon::parse($fechaRetiro)->format('d/m/Y') : 'Recientemente' }}</span>.
                        {{ __('Todo su historial de entrevistas y registros se conserva de forma permanente.') }}
                    </flux:text>
                </div>
            </div>

            @if (auth()->user()->can('editar-estudiantes') || auth()->user()->hasRole('superadmin'))
                <flux:button wire:click="cambiarEstado('activo')" variant="filled" size="sm" class="bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 whitespace-nowrap">
                    {{ __('Reactivar Alumno') }}
                </flux:button>
            @endif
        </div>
    </flux:card>
    @elseif(!$userId)
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
                <flux:input wire:model="nombresCsv" :label="__('Nombre Completo (Importado)')" placeholder="EJ: MARCELA PAZ RODRÍGUEZ LÓPEZ" class="uppercase" />
                <flux:text class="mt-1 text-xs">{{ __('Nombre importado inicialmente. Se actualizará con el nombre real de su cuenta Google cuando inicie sesión.') }}</flux:text>
            </div>

            <div class="flex gap-2 items-end">
                <flux:input wire:model="rutNumero" :label="__('RUT Estudiante')" placeholder="12345678" class="flex-1" />
                <flux:input wire:model="rutDv" :label="__('DV')" placeholder="K" class="w-20" maxlength="1" />
            </div>

            <div>
                <flux:input wire:model="emailInstitucional" :label="__('Correo Institucional (@newheavenhs.cl)')" type="email" placeholder="estudiante@newheavenhs.cl" />
                <flux:error name="emailInstitucional" />
                @if ($this->userId)
                    <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1">✓ {{ __('Cuenta Google vinculada activamente.') }}</p>
                @else
                    <p class="text-[10px] text-zinc-500 mt-1">{{ __('Sin vinculación a cuenta Google activa. Se vinculará cuando el estudiante inicie sesión con este correo.') }}</p>
                @endif
            </div>

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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:select wire:model="cursoId" :label="__('Curso Asignado')">
                <flux:select.option value="">{{ __('Sin curso asignado') }}</flux:select.option>
                @foreach ($this->cursos as $curso)
                    <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="estado" :label="__('Estado Académico')">
                <flux:select.option value="activo">{{ __('Activo / Alumno Regular') }}</flux:select.option>
                <flux:select.option value="retirado">{{ __('Retirado / Desvinculado') }}</flux:select.option>
            </flux:select>

            @if ($estado === 'retirado')
                <flux:input wire:model="fechaRetiro" :label="__('Fecha de Retiro')" type="date" />
            @endif
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
            <flux:input wire:model="apoderadoNombres" :label="__('Nombre(s)')" placeholder="EJ: MARÍA PAZ" class="uppercase" />
            <flux:input wire:model="apoderadoApellidoPat" :label="__('Apellido Paterno')" placeholder="EJ: RODRÍGUEZ" class="uppercase" />
            <flux:input wire:model="apoderadoApellidoMat" :label="__('Apellido Materno')" placeholder="EJ: LÓPEZ" class="uppercase" />
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
            <flux:input wire:model="apoderadoDomicilio" :label="__('Domicilio Registrado')" placeholder="EJ: LOS PINOS 123, VILLA SAN RAFAEL" class="uppercase" />
        </div>
    </flux:card>

    {{-- Acciones --}}
    <div class="flex items-center justify-end gap-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
        <flux:button href="{{ route('estudiantes.index') }}" variant="ghost">
            {{ __('Cancelar') }}
        </flux:button>
        @if (auth()->user()->can('editar-estudiantes') || auth()->user()->hasRole('superadmin'))
            <flux:button wire:click="guardar" variant="primary" icon="check">
                {{ __('Guardar Ficha') }}
            </flux:button>
        @endif
    </div>
</div>