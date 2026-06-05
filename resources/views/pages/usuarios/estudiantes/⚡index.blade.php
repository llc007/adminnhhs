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

    public string $apoderadoNombres = '';

    public string $apoderadoTelefono = '';

    public string $apoderadoEmail = '';

    public string $apoderadoDomicilio = '';

    // Modal eliminar
    public bool $modalEliminar = false;

    public ?int $eliminarId = null;

    public function abrirCrear(): void
    {
        $this->reset(['estudianteId', 'nombres', 'rutNumero', 'rutDv', 'formCursoId', 'apoderadoNombres', 'apoderadoTelefono', 'apoderadoEmail', 'apoderadoDomicilio']);
        $this->modalAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        $estudiante = Estudiante::findOrFail($id);
        $this->estudianteId = $estudiante->id;
        $this->nombres = $estudiante->nombres_csv ?? '';
        $this->rutNumero = $estudiante->rut_numero ?? '';
        $this->rutDv = $estudiante->rut_dv ?? '';
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
            'formCursoId' => ['required', 'exists:cursos,id'],
            'apoderadoNombres' => ['nullable', 'string', 'max:255'],
            'apoderadoTelefono' => ['nullable', 'string', 'max:40'],
            'apoderadoEmail' => ['nullable', 'email', 'max:255'],
            'apoderadoDomicilio' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'school_id' => auth()->user()->current_school_id,
            'nombres_csv' => $this->nombres,
            'rut_numero' => $this->rutNumero ?: null,
            'rut_dv' => $this->rutDv !== '' ? strtoupper($this->rutDv) : null,
            'curso_id' => $this->formCursoId ?: null,
            'apoderado_nombres' => $this->apoderadoNombres ?: null,
            'apoderado_telefono' => $this->apoderadoTelefono ?: null,
            'apoderado_email' => $this->apoderadoEmail ?: null,
            'apoderado_domicilio' => $this->apoderadoDomicilio ?: null,
        ];

        if ($this->estudianteId) {
            Estudiante::findOrFail($this->estudianteId)->update($data);
        } else {
            Estudiante::create($data);
        }

        $this->modalAbierto = false;
        $this->reset(['estudianteId', 'nombres', 'rutNumero', 'rutDv', 'formCursoId', 'apoderadoNombres', 'apoderadoTelefono', 'apoderadoEmail', 'apoderadoDomicilio']);
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
    public function getEstudiantesQueryProperty()
    {
        if ($this->cursoId === '') {
            return Estudiante::query()->whereRaw('1 = 0');
        }

        return Estudiante::query()
            ->with(['curso', 'user'])
            ->where('estudiantes.school_id', auth()->user()->current_school_id)
            ->when($this->cursoId !== 'todos', function ($query) {
                $query->where('estudiantes.curso_id', $this->cursoId);
            })
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($q) use ($search) {
                    $q->where('estudiantes.nombres_csv', 'like', "%{$search}%")
                        ->orWhere('estudiantes.rut_numero', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('nombres', 'like', "%{$search}%")
                                ->orWhere('apellido_pat', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
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

        $csvData = "Nombre del estudiante;RUT;Correo;Curso;Nombre apoderado;Telefono apoderado\n";

        foreach ($estudiantes as $estudiante) {
            $nombre = $estudiante->nombreCompleto() ?? '';
            $rut = $estudiante->rutCompleto() ?? '';
            $correo = $estudiante->email ?? ($estudiante->user_id ? $estudiante->user->email : '');
            $curso = $estudiante->curso ? $estudiante->curso->nombreCompleto() : '';
            $apoderado = $estudiante->apoderado_nombres ?? '';
            $telefono = $estudiante->apoderado_telefono ?? '';

            // Encerramos en comillas por si hay punto y coma en los nombres
            $csvData .= sprintf('"%s";"%s";"%s";"%s";"%s";"%s"' . "\n", $nombre, $rut, $correo, $curso, $apoderado, $telefono);
        }

        // Fix encoding to UTF-8 with BOM for Excel
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
                <flux:button variant="ghost" icon="document-arrow-down" wire:click="exportarExcel">
                    {{ __('Exportar') }}
                </flux:button>
                <flux:button variant="ghost" icon="document-arrow-up" href="{{ route('estudiantes.carga_masiva') }}">
                    {{ __('Importar CSV') }}
                </flux:button>
                <flux:button variant="primary" icon="plus" class="shrink-0" wire:click="abrirCrear">
                    {{ __('Nuevo Estudiante') }}
                </flux:button>
            </x-header>
        </div>

        <!-- Filters Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            <div class="md:col-span-8">
                <flux:card class="h-full flex items-center">
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-6 w-full">
                        <flux:field class="w-full md:w-64">
                            <flux:label
                                class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Curso') }}
                            </flux:label>
                            <flux:select wire:model.live="cursoId">
                                <flux:select.option value="" disabled>{{ __('Selecciona un Curso') }}
                                </flux:select.option>
                                <flux:select.option value="todos">{{ __('Listar Todos') }}</flux:select.option>
                                @foreach ($this->cursos as $curso)
                                    <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        @if ($cursoId !== '')
                            <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                            <flux:field class="flex-1 w-full overflow-hidden">
                                <flux:label
                                    class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                    {{ __('Buscar Estudiante') }}
                                </flux:label>
                                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                                    :placeholder="__('Buscar por nombre o RUT...')" class="w-full" />
                            </flux:field>
                        @endif
                    </div>
                </flux:card>
            </div>

            <div class="md:col-span-4">
                <flux:card
                    class="h-full flex items-center justify-between bg-zinc-900 border-none !text-white dark:bg-zinc-800">
                    <div>
                        <div class="text-[10px] uppercase tracking-widest font-bold opacity-70">
                            {{ __('Total Estudiantes') }}
                        </div>
                        <div class="text-4xl font-bold mt-1">
                            {{ \App\Models\Estudiante::where('school_id', auth()->user()->current_school_id)->count() }}
                        </div>
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
                <flux:table :paginate="$this->estudiantes">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'nombres_csv'" :direction="$sortDirection"
                            wire:click="sort('nombres_csv')">
                            {{ __('Nombre del Estudiante') }}
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'rut_numero'" :direction="$sortDirection"
                            wire:click="sort('rut_numero')">
                            {{ __('RUT') }}
                        </flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'curso_id'" :direction="$sortDirection"
                            wire:click="sort('curso_id')">
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
                                    <div class="flex items-center gap-3">
                                        <flux:avatar />
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $estudiante->nombreCompleto() }}
                                            </div>
                                            @if ($estudiante->email)
                                                <div class="text-xs text-zinc-500">{{ $estudiante->email }}</div>
                                            @elseif($estudiante->user_id)
                                                <div class="text-xs text-zinc-500">{{ $estudiante->user->email }}</div>
                                            @else
                                                <div class="text-xs text-zinc-400 italic">Sin correo vinculado</div>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $estudiante->rutCompleto() ?? '-' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($estudiante->curso)
                                        <flux:badge color="blue">{{ $estudiante->curso->nombreCompleto() }}
                                        </flux:badge>
                                    @else
                                        <span class="text-zinc-500">-</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="text-sm">{{ $estudiante->apoderado_nombres }}</div>
                                    @if ($estudiante->apoderado_telefono)
                                        <div class="text-xs text-zinc-500 flex items-center gap-1 mt-0.5">
                                            <flux:icon.phone class="size-3" /> {{ $estudiante->apoderado_telefono }}
                                        </div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($estudiante->email || $estudiante->user_id)
                                        <flux:badge color="green" icon="check-circle">Vinculado</flux:badge>
                                    @else
                                        <flux:badge color="orange" icon="clock">Inactivo</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button variant="ghost" size="sm" icon="eye"
                                            :tooltip="__('Ver Ficha')"
                                            href="{{ route('estudiantes.ficha', $estudiante->id) }}" />
                                        <flux:button variant="ghost" size="sm" icon="pencil-square"
                                            :tooltip="__('Editar')" wire:click="abrirEditar({{ $estudiante->id }})" />
                                        <flux:button variant="ghost" size="sm" icon="trash"
                                            :tooltip="__('Eliminar')"
                                            wire:click="confirmarEliminar({{ $estudiante->id }})" />
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

    {{-- Modal Confirmar Eliminar --}}
    <flux:modal wire:model="modalEliminar" class="md:w-80">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Eliminar estudiante') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('El estudiante y sus datos asociados serán eliminados de los registros. Esta acción no puede deshacerse.') }}
                </flux:text>
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="$set('modalEliminar', false)" variant="ghost">{{ __('Cancelar') }}
                </flux:button>
                <flux:button wire:click="eliminar" variant="danger" class="ml-2">{{ __('Eliminar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal Crear / Editar --}}
    <flux:modal wire:model="modalAbierto" class="md:w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $estudianteId ? __('Editar Estudiante') : __('Nuevo Estudiante') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $estudianteId ? __('Modifica los datos del estudiante.') : __('Ingresa los datos del nuevo estudiante.') }}
                </flux:text>
            </div>

            <flux:input wire:model="nombres" :label="__('Nombre Completo')" placeholder="EJ: JUAN PÉREZ LÓPEZ"
                x-on:input="$event.target.value = $event.target.value.toLocaleUpperCase(); $wire.set('nombres', $event.target.value)" />
            <flux:error name="nombres" />

            <div class="grid grid-cols-2 gap-3">
                <div class="flex gap-2">
                    <flux:input wire:model="rutNumero" :label="__('RUT sin puntos')" placeholder="12345678"
                        class="flex-1" />
                    <flux:input wire:model="rutDv" :label="__('DV')" placeholder="K" class="w-20"
                        maxlength="1" />
                </div>
                <flux:select wire:model="formCursoId" :label="__('Curso')">
                    <flux:select.option value="" disabled>{{ __('Selecciona un Curso') }}</flux:select.option>
                    @foreach ($this->cursos as $curso)
                        <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:error name="rutNumero" />
            <flux:error name="rutDv" />
            <flux:error name="formCursoId" />

            <flux:separator text="Datos del Apoderado (Opcional)" />

            <flux:input wire:model="apoderadoNombres" :label="__('Nombre del Apoderado')"
                placeholder="EJ: MARÍA LÓPEZ"
                x-on:input="$event.target.value = $event.target.value.toLocaleUpperCase(); $wire.set('apoderadoNombres', $event.target.value)" />
            <flux:error name="apoderadoNombres" />

            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="apoderadoTelefono" :label="__('Teléfono')" placeholder="+56912345678" />
                <flux:input wire:model="apoderadoEmail" type="email" :label="__('Correo Electrónico')"
                    placeholder="maria@correo.cl" />
            </div>
            <flux:error name="apoderadoTelefono" />
            <flux:error name="apoderadoEmail" />

            <flux:input wire:model="apoderadoDomicilio" :label="__('Domicilio')"
                placeholder="EJ: AV. LOS LEONES 1234"
                x-on:input="$event.target.value = $event.target.value.toLocaleUpperCase(); $wire.set('apoderadoDomicilio', $event.target.value)" />
            <flux:error name="apoderadoDomicilio" />

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="$set('modalAbierto', false)" variant="ghost">{{ __('Cancelar') }}
                </flux:button>
                <flux:button wire:click="guardar" variant="primary" class="ml-2">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
