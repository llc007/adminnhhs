<?php

use App\Models\Curso;
use App\Models\Estudiante;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    // Filtros y orden
    public string $cursoId = ''; // Vacío por defecto para obligar a seleccionar

    public string $filtroEstado = 'activos'; // 'activos' por defecto (oculta retirados)

    public string $search = '';

    public string $sortBy = 'nombres_csv';

    public string $sortDirection = 'asc';

    // Modal crear/editar
    public bool $modalAbierto = false;

    public ?int $estudianteId = null;

    public string $nombres = '';

    public string $rutNumero = '';

    public string $rutDv = '';

    public string $formCursoId = '';

    public string $email = '';

    public string $apoderadoNombres = '';

    public string $apoderadoTelefono = '';

    public string $apoderadoEmail = '';

    public string $apoderadoDomicilio = '';

    // Modal eliminar
    public bool $modalEliminar = false;

    public ?int $eliminarId = null;

    public function abrirCrear(): void
    {
        if (!auth()->user()->can('editar-estudiantes') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        $this->reset(['estudianteId', 'nombres', 'rutNumero', 'rutDv', 'email', 'formCursoId', 'apoderadoNombres', 'apoderadoTelefono', 'apoderadoEmail', 'apoderadoDomicilio']);
        $this->modalAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        if (!auth()->user()->can('editar-estudiantes') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        $estudiante = Estudiante::findOrFail($id);
        $this->estudianteId = $estudiante->id;
        $this->nombres = $estudiante->nombres_csv ?? '';
        $this->rutNumero = $estudiante->rut_numero ?? '';
        $this->rutDv = $estudiante->rut_dv ?? '';
        $this->email = $estudiante->email ?? '';
        $this->formCursoId = $estudiante->curso_id ?? '';
        $this->apoderadoNombres = $estudiante->apoderado_nombres ?? '';
        $this->apoderadoTelefono = $estudiante->apoderado_telefono ?? '';
        $this->apoderadoEmail = $estudiante->apoderado_email ?? '';
        $this->apoderadoDomicilio = $estudiante->apoderado_domicilio ?? '';
        $this->modalAbierto = true;
    }

    public function updated($propertyName, $value): void
    {
        if (in_array($propertyName, ['nombres', 'apoderadoNombres', 'apoderadoDomicilio'])) {
            $this->{$propertyName} = mb_strtoupper((string) $value, 'UTF-8');
        }
    }

    public function guardar(): void
    {
        if (!auth()->user()->can('editar-estudiantes') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        $this->validate([
            'nombres' => ['required', 'string', 'max:255'],
            'rutNumero' => [
                'nullable',
                'digits_between:7,9',
                Rule::unique('estudiantes', 'rut_numero')
                    ->where('school_id', auth()->user()->current_school_id)
                    ->ignore($this->estudianteId),
            ],
            'rutDv' => ['nullable', 'max:1', 'regex:/^[0-9Kk]$/'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('estudiantes', 'email')
                    ->ignore($this->estudianteId),
            ],
            'formCursoId' => ['required', 'exists:cursos,id'],
            'apoderadoNombres' => ['nullable', 'string', 'max:255'],
            'apoderadoTelefono' => ['nullable', 'string', 'max:40'],
            'apoderadoEmail' => ['nullable', 'email', 'max:255'],
            'apoderadoDomicilio' => ['nullable', 'string', 'max:255'],
        ]);

        $user = \App\Models\User::where('email', $this->email)->first();

        $data = [
            'school_id' => auth()->user()->current_school_id,
            'nombres_csv' => $this->nombres,
            'rut_numero' => $this->rutNumero ?: null,
            'rut_dv' => $this->rutDv !== '' ? strtoupper($this->rutDv) : null,
            'email' => $this->email ?: null,
            'curso_id' => $this->formCursoId ?: null,
            'apoderado_nombres' => $this->apoderadoNombres ?: null,
            'apoderado_telefono' => $this->apoderadoTelefono ?: null,
            'apoderado_email' => $this->apoderadoEmail ?: null,
            'apoderado_domicilio' => $this->apoderadoDomicilio ?: null,
            'user_id' => $user ? $user->id : null,
            'vinculado_en' => $user ? now() : null,
            'estado' => 'activo',
        ];

        if ($this->estudianteId) {
            Estudiante::findOrFail($this->estudianteId)->update($data);
        } else {
            Estudiante::create($data);
        }

        $this->modalAbierto = false;
        $this->reset(['estudianteId', 'nombres', 'rutNumero', 'rutDv', 'email', 'formCursoId', 'apoderadoNombres', 'apoderadoTelefono', 'apoderadoEmail', 'apoderadoDomicilio']);
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedCursoId()
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function confirmarEliminar(int $id): void
    {
        if (!auth()->user()->can('editar-estudiantes') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        $this->eliminarId = $id;
        $this->modalEliminar = true;
    }

    public function eliminar(): void
    {
        if (!auth()->user()->can('editar-estudiantes') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        if ($this->eliminarId) {
            Estudiante::findOrFail($this->eliminarId)->delete();
        }
        $this->modalEliminar = false;
        $this->eliminarId = null;
    }

    #[Computed]
    public function cursos()
    {
        return Curso::where('school_id', auth()->user()->current_school_id)
            ->orderBy('modalidad')
            ->orderBy('nivel')
            ->orderBy('letra')
            ->get();
    }

    #[Computed]
    public function totalRetiradosCount(): int
    {
        return Estudiante::where('school_id', auth()->user()->current_school_id)
            ->where('estado', 'retirado')
            ->count();
    }

    #[Computed]
    public function getEstudiantesQueryProperty()
    {
        if ($this->cursoId === '') {
            return Estudiante::query()->whereRaw('1 = 0');
        }

        return Estudiante::query()
            ->with(['curso', 'user'])
            ->where('estudiantes.school_id', auth()->user()->current_school_id)
            ->when($this->filtroEstado === 'activos', function ($query) {
                $query->where(function ($q) {
                    $q->where('estudiantes.estado', 'activo')
                        ->orWhereNull('estudiantes.estado');
                });
            })
            ->when($this->filtroEstado === 'retirados', function ($query) {
                $query->where('estudiantes.estado', 'retirado');
            })
            ->when($this->cursoId !== 'todos', function ($query) {
                $query->where('estudiantes.curso_id', $this->cursoId);
            })
            ->when(trim($this->search) !== '', function ($query) {
                $words = array_filter(explode(' ', trim($this->search)));
                $query->where(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $w = trim($word);
                        if ($w === '') {
                            continue;
                        }
                        $q->where(function ($sub) use ($w) {
                            $sub->where('estudiantes.nombres_csv', 'like', "%{$w}%")
                                ->orWhere('estudiantes.rut_numero', 'like', "%{$w}%")
                                ->orWhereHas('user', function ($uq) use ($w) {
                                    $uq->where('nombres', 'like', "%{$w}%")
                                        ->orWhere('apellido_pat', 'like', "%{$w}%")
                                        ->orWhere('apellido_mat', 'like', "%{$w}%")
                                        ->orWhere('email', 'like', "%{$w}%");
                                });
                        });
                    }
                });
            })
            ->when($this->sortBy === 'nombres_csv', function ($query) {
                $query->orderBy('estudiantes.nombres_csv', $this->sortDirection);
            })
            ->when($this->sortBy === 'rut_numero', function ($query) {
                $query->orderBy('estudiantes.rut_numero', $this->sortDirection);
            })
            ->when($this->sortBy === 'curso_id', function ($query) {
                $query->leftJoin('cursos', 'estudiantes.curso_id', '=', 'cursos.id')->select('estudiantes.*')->orderBy('cursos.modalidad', $this->sortDirection)->orderBy('cursos.nivel', $this->sortDirection)->orderBy('cursos.letra', $this->sortDirection);
            });
    }

    #[Computed]
    public function estudiantes()
    {
        if ($this->cursoId === '') {
            return collect(); // Colección vacía
        }

        return $this->getEstudiantesQueryProperty->paginate(50);
    }

    public function exportarExcel()
    {
        if ($this->cursoId === '') {
            Flux::toast('Selecciona un curso primero.', variant: 'danger');

            return;
        }

        $estudiantes = $this->getEstudiantesQueryProperty->get();

        $csvData = "Nombre del estudiante;RUT;Correo;Curso;Estado;Nombre apoderado;Telefono apoderado\n";

        foreach ($estudiantes as $estudiante) {
            $nombre = $estudiante->nombreCompleto() ?? '';
            $rut = $estudiante->rutCompleto() ?? '';
            $correo = $estudiante->email ?? ($estudiante->user_id ? $estudiante->user->email : '');
            $curso = $estudiante->curso ? $estudiante->curso->nombreCompleto() : '';
            $estado = $estudiante->estado === 'retirado' ? 'RETIRADO' : 'ACTIVO';
            $apoderado = $estudiante->apoderado_nombres ?? '';
            $telefono = $estudiante->apoderado_telefono ?? '';

            $csvData .= sprintf('"%s";"%s";"%s";"%s";"%s";"%s";"%s"' . "\n", $nombre, $rut, $correo, $curso, $estado, $apoderado, $telefono);
        }

        $csvData = "\xEF\xBB\xBF" . $csvData;

        $fileName = 'Estudiantes_Export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(
            function () use ($csvData) {
                echo $csvData;
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }
};

?>

<div>
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto">
        <!-- Quick Action Header -->
        <div>
            <x-header :titulo="__('Listado de Estudiantes')" :subtitulo="__('Administración centralizada de alumnos del establecimiento.')" icono="users">
                @if ($filtroEstado === 'activos')
                    <flux:button 
                        variant="ghost" 
                        icon="user-minus" 
                        class="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30" 
                        wire:click="$set('filtroEstado', 'retirados')"
                    >
                        {{ __('Ver Retirados') }} ({{ $this->totalRetiradosCount }})
                    </flux:button>
                @else
                    <flux:button 
                        variant="filled" 
                        icon="users" 
                        class="bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300" 
                        wire:click="$set('filtroEstado', 'activos')"
                    >
                        {{ __('Ver Alumnos Activos') }}
                    </flux:button>
                @endif

                <flux:button variant="ghost" icon="document-arrow-down" wire:click="exportarExcel">
                    {{ __('Exportar') }}
                </flux:button>
                @if (auth()->user()->can('importar-estudiantes') || auth()->user()->hasRole('superadmin'))
                    <flux:button variant="ghost" icon="document-arrow-up" href="{{ route('estudiantes.carga_masiva') }}">
                        {{ __('Importar CSV') }}
                    </flux:button>
                @endif
                @if (auth()->user()->can('editar-estudiantes') || auth()->user()->hasRole('superadmin'))
                    <flux:button variant="primary" icon="plus" class="shrink-0" wire:click="abrirCrear">
                        {{ __('Nuevo Estudiante') }}
                    </flux:button>
                @endif
            </x-header>
        </div>

        @if ($filtroEstado === 'retirados')
            <flux:card class="bg-rose-50 dark:bg-rose-500/10 border-rose-200 dark:border-rose-500/20">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400 rounded-lg">
                            <flux:icon.user-minus class="size-6" />
                        </div>
                        <div>
                            <flux:heading class="text-rose-800 dark:text-rose-300">Viendo únicamente Alumnos Retirados ({{ $this->totalRetiradosCount }})</flux:heading>
                            <flux:text class="text-rose-700 dark:text-rose-400/80 text-xs">
                                Los alumnos desvinculados no aparecen en las listas regulares. Su historial permanece intacto en el sistema.
                            </flux:text>
                        </div>
                    </div>
                    <flux:button wire:click="$set('filtroEstado', 'activos')" size="sm" variant="filled" class="bg-white text-rose-700 hover:bg-rose-100 border border-rose-200">
                        Volver a Alumnos Activos
                    </flux:button>
                </div>
            </flux:card>
        @endif

        <!-- Filters Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            <div class="md:col-span-8">
                <flux:card class="h-full flex items-center">
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-6 w-full">
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

                        <flux:field class="w-full md:w-44">
                            <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Filtro de Estado') }}
                            </flux:label>
                            <flux:select wire:model.live="filtroEstado">
                                <flux:select.option value="activos">Activos</flux:select.option>
                                <flux:select.option value="retirados">⚠️ Retirados</flux:select.option>
                            </flux:select>
                        </flux:field>

                        @if ($cursoId !== '')
                            <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                            <flux:field class="flex-1 w-full overflow-hidden">
                                <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                    {{ __('Buscar Estudiante') }}
                                </flux:label>
                                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Buscar por nombre o RUT...')" class="w-full" />
                            </flux:field>
                        @endif
                    </div>
                </flux:card>
            </div>

            <div class="md:col-span-4">
                <flux:card class="h-full flex items-center justify-between bg-zinc-900 border-none !text-white dark:bg-zinc-800">
                    <div>
                        <div class="text-[10px] uppercase tracking-widest font-bold opacity-70">
                            {{ $cursoId === '' ? __('Total Estudiantes') : __('Estudiantes Filtrados') }}
                        </div>
                        <div class="text-4xl font-bold mt-1">
                            {{ $cursoId === '' 
                                ? \App\Models\Estudiante::where('school_id', auth()->user()->current_school_id)->where(fn($q) => $q->where('estado', 'activo')->orWhereNull('estado'))->count() 
                                : $this->getEstudiantesQueryProperty->count() }}
                        </div>
                    </div>
                    <div class="p-3 bg-white/10 rounded-full">
                        <flux:icon.academic-cap class="size-8" />
                    </div>
                </flux:card>
            </div>
        </div>

        <!-- Table UI -->
        <flux:card class="relative">
            {{-- Loader overlay --}}
            <div wire:loading.flex wire:target="cursoId, filtroEstado, search, sort, gotoPage, nextPage, previousPage" class="absolute inset-0 bg-white/60 dark:bg-zinc-900/60 backdrop-blur-[1px] z-10 flex items-center justify-center rounded-xl">
                <div class="flex flex-col items-center gap-3">
                    <flux:icon.arrow-path class="size-8 animate-spin text-[#00376e] dark:text-blue-400" />
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('Cargando nómina de estudiantes...') }}</span>
                </div>
            </div>

            @if ($this->cursoId === '')
                <div class="py-12 flex flex-col items-center justify-center text-center">
                    <div class="p-4 bg-zinc-100 dark:bg-zinc-800 rounded-full mb-4">
                        <flux:icon.academic-cap class="size-8 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <flux:heading size="lg">{{ __('Selecciona un curso') }}</flux:heading>
                    <flux:text class="mt-2 max-w-sm">
                        {{ __('Utiliza los filtros de arriba para seleccionar un nivel y un curso (o "Listar Todos") para ver su nómina.') }}
                    </flux:text>
                </div>
            @else
                <flux:table :paginate="$this->estudiantes">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'nombres_csv'" :direction="$sortDirection" wire:click="sort('nombres_csv')">
                            {{ __('Nombre del Estudiante') }}
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'rut_numero'" :direction="$sortDirection" wire:click="sort('rut_numero')">
                            {{ __('RUT') }}
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'curso_id'" :direction="$sortDirection" wire:click="sort('curso_id')">
                            {{ __('Curso') }}
                        </flux:table.column>
                        <flux:table.column>{{ __('Apoderado') }}</flux:table.column>
                        <flux:table.column>{{ __('Estado') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->estudiantes as $estudiante)
                            <flux:table.row :key="$estudiante->id">
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:avatar class="size-7" />
                                        <div>
                                            <div class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $estudiante->nombreCompleto() }}
                                            </div>
                                            @if ($estudiante->email)
                                                <div class="text-[10px] text-zinc-500">{{ $estudiante->email }}</div>
                                            @elseif($estudiante->user_id)
                                                <div class="text-[10px] text-zinc-500">{{ $estudiante->user->email }}</div>
                                            @else
                                                <div class="text-[10px] text-zinc-400 italic">Sin correo vinculado</div>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-xs font-mono">{{ $estudiante->rutCompleto() ?? '-' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($estudiante->curso)
                                        <flux:badge size="sm" color="blue">{{ $estudiante->curso->nombreCompleto() }}</flux:badge>
                                    @else
                                        <span class="text-xs text-zinc-500">-</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-xs">
                                    <div class="font-medium">{{ $estudiante->apoderado_nombres ?: '-' }}</div>
                                    @if ($estudiante->apoderado_telefono)
                                        <div class="text-[10px] text-zinc-500 flex items-center gap-1 mt-0.5">
                                            <flux:icon.phone class="size-3" /> {{ $estudiante->apoderado_telefono }}
                                        </div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($estudiante->estado === 'retirado')
                                        <flux:badge size="sm" color="red" icon="user-minus">Retirado</flux:badge>
                                    @elseif ($estudiante->email || $estudiante->user_id)
                                        <flux:badge size="sm" color="green" icon="check-circle">Vinculado</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="orange" icon="clock">Inactivo</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button variant="ghost" size="sm" icon="eye" :tooltip="__('Ver Ficha')" href="{{ route('estudiantes.ficha', $estudiante->id) }}" />
                                        @if (auth()->user()->can('editar-estudiantes') || auth()->user()->hasRole('superadmin'))
                                            <flux:button variant="ghost" size="sm" icon="pencil-square" :tooltip="__('Editar')" wire:click="abrirEditar({{ $estudiante->id }})" />
                                        @endif
                                        @if (auth()->user()->can('eliminar-estudiantes') || auth()->user()->hasRole('superadmin'))
                                            <flux:button variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-700" :tooltip="__('Eliminar')" wire:click="confirmarEliminar({{ $estudiante->id }})" />
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center py-8 text-zinc-500">
                                    {{ $filtroEstado === 'retirados' ? __('No se encontraron estudiantes retirados en esta selección.') : __('No se encontraron estudiantes activos.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </div>

    <!-- Modal Crear/Editar -->
    <flux:modal wire:model="modalAbierto" class="md:w-1/2">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $estudianteId ? __('Editar Estudiante') : __('Nuevo Estudiante') }}</flux:heading>
                <flux:subheading>{{ __('Completa los datos del estudiante para guardar el registro.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="nombres" :label="__('Nombre Completo')" placeholder="EJ: MARCELA PAZ RODRÍGUEZ LÓPEZ" class="uppercase" />

                <div class="flex gap-2 items-end">
                    <flux:input wire:model="rutNumero" :label="__('RUT')" placeholder="12345678" class="flex-1" />
                    <flux:input wire:model="rutDv" :label="__('DV')" placeholder="K" class="w-20" maxlength="1" />
                </div>

                <flux:input wire:model="email" :label="__('Correo Institucional')" type="email" placeholder="estudiante@newheavenhs.cl" />

                <flux:select wire:model="formCursoId" :label="__('Curso')">
                    <flux:select.option value="" disabled>{{ __('Selecciona un Curso') }}</flux:select.option>
                    @foreach ($this->cursos as $curso)
                        <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 mt-4">
                    <flux:heading size="sm" class="mb-3">{{ __('Datos del Apoderado') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input wire:model="apoderadoNombres" :label="__('Nombre Apoderado')" placeholder="MARÍA PAZ LÓPEZ" class="uppercase" />
                        <flux:input wire:model="apoderadoTelefono" :label="__('Teléfono Apoderado')" placeholder="+56 9 1234 5678" />
                        <flux:input wire:model="apoderadoEmail" :label="__('Correo Apoderado')" type="email" placeholder="apoderado@gmail.com" />
                        <flux:input wire:model="apoderadoDomicilio" :label="__('Domicilio Apoderado')" placeholder="LOS PINOS 123" class="uppercase" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardar">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Eliminar -->
    <flux:modal wire:model="modalEliminar" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Eliminar Estudiante') }}</flux:heading>
                <flux:subheading>{{ __('¿Estás seguro de que deseas eliminar este estudiante de la base de datos? Esta acción no se puede deshacer.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="eliminar">{{ __('Eliminar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
