<?php

use App\Models\AcademicYear;
use App\Models\Curso;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Gestión de Docentes y Jefaturas')] class extends Component {
    use WithPagination;

    // Filtros y búsqueda
    public string $search = '';
    public string $filtroJefatura = 'todos'; // 'todos', 'con_jefatura', 'sin_jefatura'
    public string $filtroModalidad = 'todas'; // 'todas', 'basica', 'media'
    public string $sortBy = 'nombres';
    public string $sortDirection = 'asc';
    public string $tab = 'docentes'; // 'docentes', 'cursos'

    // Modal asignación de jefatura para Docente
    public bool $modalJefatura = false;
    public ?int $selectedDocenteId = null;
    public string $selectedCursoId = 'none';

    // Modal asignación de jefe para Curso
    public bool $modalCursoJefe = false;
    public ?int $selectedCursoForModalId = null;
    public string $selectedDocenteForModalId = 'none';

    public function mount(): void
    {
        if (! auth()->user()->hasRole(['superadmin', 'administrador', 'directivo']) && ! auth()->user()->can('gestionar-funcionarios')) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroJefatura(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroModalidad(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function schoolId(): ?int
    {
        return auth()->user()->current_school_id;
    }

    #[Computed]
    public function academicYear(): ?AcademicYear
    {
        if (! $this->schoolId) {
            return null;
        }

        return AcademicYear::where('school_id', $this->schoolId)
            ->where('is_active', true)
            ->first()
            ?? AcademicYear::where('school_id', $this->schoolId)->latest('id')->first();
    }

    #[Computed]
    public function cursos()
    {
        if (! $this->schoolId) {
            return collect();
        }

        return Curso::query()
            ->where('school_id', $this->schoolId)
            ->when($this->academicYear, fn ($q) => $q->where('academic_year_id', $this->academicYear->id))
            ->with('jefe')
            ->orderBy('modalidad')
            ->orderBy('nivel')
            ->orderBy('letra')
            ->get();
    }

    #[Computed]
    public function allDocentes()
    {
        if (! $this->schoolId) {
            return collect();
        }

        return User::query()
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $this->schoolId))
            ->whereHas('roles', fn ($q) => $q->where('roles.team_id', $this->schoolId)->where('roles.name', 'docente'))
            ->with([
                'jefeDeCursos' => fn ($q) => $q->where('school_id', $this->schoolId)
                    ->when($this->academicYear, fn ($sq) => $sq->where('academic_year_id', $this->academicYear->id)),
            ])
            ->orderBy('nombres')
            ->orderBy('apellido_pat')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        if (! $this->schoolId) {
            return [
                'total_docentes' => 0,
                'con_jefatura' => 0,
                'sin_jefatura' => 0,
                'total_cursos' => 0,
                'cursos_cubiertos' => 0,
            ];
        }

        $academicYearId = $this->academicYear?->id;

        $docentesQuery = User::query()
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $this->schoolId))
            ->whereHas('roles', fn ($q) => $q->where('roles.team_id', $this->schoolId)->where('roles.name', 'docente'));

        $totalDocentes = (clone $docentesQuery)->count();

        $conJefatura = (clone $docentesQuery)->whereHas('jefeDeCursos', function ($q) use ($academicYearId) {
            $q->where('school_id', $this->schoolId)
                ->when($academicYearId, fn ($sq) => $sq->where('academic_year_id', $academicYearId));
        })->count();

        $sinJefatura = max(0, $totalDocentes - $conJefatura);

        $cursosQuery = Curso::where('school_id', $this->schoolId)
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId));

        $totalCursos = (clone $cursosQuery)->count();
        $cursosCubiertos = (clone $cursosQuery)->whereNotNull('jefe_id')->count();

        return [
            'total_docentes' => $totalDocentes,
            'con_jefatura' => $conJefatura,
            'sin_jefatura' => $sinJefatura,
            'total_cursos' => $totalCursos,
            'cursos_cubiertos' => $cursosCubiertos,
        ];
    }

    #[Computed]
    public function docentes()
    {
        if (! $this->schoolId) {
            return User::query()->whereRaw('1 = 0')->paginate(50);
        }

        $academicYearId = $this->academicYear?->id;

        return User::query()
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $this->schoolId))
            ->whereHas('roles', fn ($q) => $q->where('roles.team_id', $this->schoolId)->where('roles.name', 'docente'))
            ->with([
                'jefeDeCursos' => fn ($q) => $q->where('school_id', $this->schoolId)
                    ->when($academicYearId, fn ($sq) => $sq->where('academic_year_id', $academicYearId)),
            ])
            ->when(strlen($this->search) >= 2, function ($query) {
                $query->where(function ($q) {
                    $q->where('nombres', 'like', '%' . $this->search . '%')
                        ->orWhere('apellido_pat', 'like', '%' . $this->search . '%')
                        ->orWhere('apellido_mat', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('rut_numero', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filtroJefatura === 'con_jefatura', function ($query) use ($academicYearId) {
                $query->whereHas('jefeDeCursos', function ($q) use ($academicYearId) {
                    $q->where('school_id', $this->schoolId)
                        ->when($academicYearId, fn ($sq) => $sq->where('academic_year_id', $academicYearId));
                });
            })
            ->when($this->filtroJefatura === 'sin_jefatura', function ($query) use ($academicYearId) {
                $query->whereDoesntHave('jefeDeCursos', function ($q) use ($academicYearId) {
                    $q->where('school_id', $this->schoolId)
                        ->when($academicYearId, fn ($sq) => $sq->where('academic_year_id', $academicYearId));
                });
            })
            ->when($this->filtroModalidad !== 'todas', function ($query) use ($academicYearId) {
                $query->whereHas('jefeDeCursos', function ($q) use ($academicYearId) {
                    $q->where('school_id', $this->schoolId)
                        ->where('modalidad', $this->filtroModalidad)
                        ->when($academicYearId, fn ($sq) => $sq->where('academic_year_id', $academicYearId));
                });
            })
            ->tap(function ($query) {
                if ($this->sortBy === 'nombres') {
                    $query->orderBy('nombres', $this->sortDirection);
                } elseif ($this->sortBy === 'apellido_pat') {
                    $query->orderBy('apellido_pat', $this->sortDirection);
                } elseif ($this->sortBy === 'rut_numero') {
                    $query->orderBy('rut_numero', $this->sortDirection);
                } else {
                    $query->orderBy('nombres', 'asc');
                }
            })
            ->paginate(50);
    }

    public function abrirModalDocente(int $docenteId): void
    {
        $this->selectedDocenteId = $docenteId;
        $docente = User::whereHas('schools', fn ($q) => $q->where('schools.id', $this->schoolId))
            ->findOrFail($docenteId);

        $cursoActual = Curso::where('school_id', $this->schoolId)
            ->when($this->academicYear, fn ($q) => $q->where('academic_year_id', $this->academicYear->id))
            ->where('jefe_id', $docente->id)
            ->first();

        $this->selectedCursoId = $cursoActual ? (string) $cursoActual->id : 'none';
        $this->modalJefatura = true;
    }

    public function guardarJefatura(): void
    {
        if (! auth()->user()->hasRole(['superadmin', 'administrador', 'directivo']) && ! auth()->user()->can('gestionar-funcionarios')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        if (! $this->selectedDocenteId) {
            return;
        }

        $docente = User::whereHas('schools', fn ($q) => $q->where('schools.id', $this->schoolId))
            ->findOrFail($this->selectedDocenteId);

        DB::transaction(function () use ($docente) {
            $academicYearId = $this->academicYear?->id;

            if ($this->selectedCursoId === 'none' || empty($this->selectedCursoId)) {
                // Dejar sin jefatura
                Curso::where('school_id', $this->schoolId)
                    ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                    ->where('jefe_id', $docente->id)
                    ->update(['jefe_id' => null]);

                Flux::toast(
                    heading: 'Jefatura actualizada',
                    text: "El docente {$docente->nombreCompleto()} ahora está Sin jefatura.",
                    variant: 'success'
                );
            } else {
                $targetCurso = Curso::where('school_id', $this->schoolId)->findOrFail((int) $this->selectedCursoId);

                // Quitar a este docente de cualquier otro curso en el mismo año escolar
                Curso::where('school_id', $this->schoolId)
                    ->where('academic_year_id', $targetCurso->academic_year_id)
                    ->where('jefe_id', $docente->id)
                    ->where('id', '!=', $targetCurso->id)
                    ->update(['jefe_id' => null]);

                // Asignar docente al curso objetivo
                $targetCurso->update(['jefe_id' => $docente->id]);

                Flux::toast(
                    heading: 'Jefatura asignada',
                    text: "{$docente->nombreCompleto()} fue asignado(a) como profesor(a) jefe de {$targetCurso->nombreCompleto()}.",
                    variant: 'success'
                );
            }
        });

        $this->modalJefatura = false;
        $this->selectedDocenteId = null;
        $this->selectedCursoId = 'none';
    }

    public function quitarJefatura(int $docenteId): void
    {
        if (! auth()->user()->hasRole(['superadmin', 'administrador', 'directivo']) && ! auth()->user()->can('gestionar-funcionarios')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        $docente = User::whereHas('schools', fn ($q) => $q->where('schools.id', $this->schoolId))
            ->findOrFail($docenteId);

        $academicYearId = $this->academicYear?->id;

        Curso::where('school_id', $this->schoolId)
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->where('jefe_id', $docente->id)
            ->update(['jefe_id' => null]);

        Flux::toast(
            heading: 'Jefatura quitada',
            text: "Se retiró la jefatura de curso a {$docente->nombreCompleto()}.",
            variant: 'warning'
        );
    }

    public function abrirModalCurso(int $cursoId): void
    {
        $this->selectedCursoForModalId = $cursoId;
        $curso = Curso::where('school_id', $this->schoolId)->findOrFail($cursoId);

        $this->selectedDocenteForModalId = $curso->jefe_id ? (string) $curso->jefe_id : 'none';
        $this->modalCursoJefe = true;
    }

    public function guardarJefeCurso(): void
    {
        if (! auth()->user()->hasRole(['superadmin', 'administrador', 'directivo']) && ! auth()->user()->can('gestionar-funcionarios')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        if (! $this->selectedCursoForModalId) {
            return;
        }

        $curso = Curso::where('school_id', $this->schoolId)->findOrFail($this->selectedCursoForModalId);

        DB::transaction(function () use ($curso) {
            if ($this->selectedDocenteForModalId === 'none' || empty($this->selectedDocenteForModalId)) {
                $curso->update(['jefe_id' => null]);
                Flux::toast(heading: 'Sin profesor jefe', text: "El curso {$curso->nombreCompleto()} quedó sin jefatura asignada.", variant: 'warning');
            } else {
                $docenteId = (int) $this->selectedDocenteForModalId;
                $docente = User::whereHas('schools', fn ($q) => $q->where('schools.id', $this->schoolId))->findOrFail($docenteId);

                // Quitar a este docente de cualquier otro curso en el mismo año escolar
                Curso::where('school_id', $this->schoolId)
                    ->where('academic_year_id', $curso->academic_year_id)
                    ->where('jefe_id', $docente->id)
                    ->where('id', '!=', $curso->id)
                    ->update(['jefe_id' => null]);

                $curso->update(['jefe_id' => $docente->id]);

                Flux::toast(
                    heading: 'Profesor jefe asignado',
                    text: "{$docente->nombreCompleto()} asignado(a) a {$curso->nombreCompleto()}.",
                    variant: 'success'
                );
            }
        });

        $this->modalCursoJefe = false;
        $this->selectedCursoForModalId = null;
        $this->selectedDocenteForModalId = 'none';
    }
};
?>

<div>
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto">
        <!-- Encabezado Principal -->
        <div>
            <x-header :titulo="__('Gestión de Docentes y Jefaturas')" :subtitulo="__(
                'Listado de profesores del establecimiento y asignación de cursos con jefatura para el año escolar activo.',
            )" icono="academic-cap">
                @if ($this->academicYear)
                    <flux:badge color="blue" size="sm" icon="calendar">
                        {{ $this->academicYear->name }}
                    </flux:badge>
                @endif
            </x-header>
        </div>

        <!-- Tarjetas KPI Compactas -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
            <div class="flex items-center gap-2.5 px-3 py-2 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                <div class="p-1.5 bg-blue-50 dark:bg-blue-950/50 text-[#00376e] dark:text-blue-400 rounded-md shrink-0">
                    <flux:icon.users class="size-3.5" />
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] uppercase tracking-wider font-semibold text-zinc-500 dark:text-zinc-400 leading-none truncate">
                        {{ __('Total Docentes') }}
                    </div>
                    <div class="text-base font-black text-zinc-900 dark:text-zinc-100 leading-none mt-1">
                        {{ $this->stats['total_docentes'] }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 px-3 py-2 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                <div class="p-1.5 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 rounded-md shrink-0">
                    <flux:icon.check-circle class="size-3.5" />
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] uppercase tracking-wider font-semibold text-zinc-500 dark:text-zinc-400 leading-none truncate">
                        {{ __('Con Jefatura') }}
                    </div>
                    <div class="text-base font-black text-emerald-600 dark:text-emerald-400 leading-none mt-1">
                        {{ $this->stats['con_jefatura'] }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 px-3 py-2 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                <div class="p-1.5 bg-zinc-100 dark:bg-zinc-700/50 text-zinc-500 dark:text-zinc-400 rounded-md shrink-0">
                    <flux:icon.user class="size-3.5" />
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] uppercase tracking-wider font-semibold text-zinc-500 dark:text-zinc-400 leading-none truncate">
                        {{ __('Sin Jefatura') }}
                    </div>
                    <div class="text-base font-black text-zinc-700 dark:text-zinc-300 leading-none mt-1">
                        {{ $this->stats['sin_jefatura'] }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 px-3 py-2 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                <div class="p-1.5 bg-blue-50 dark:bg-blue-950/50 text-[#00376e] dark:text-blue-400 rounded-md shrink-0">
                    <flux:icon.academic-cap class="size-3.5" />
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] uppercase tracking-wider font-semibold text-zinc-500 dark:text-zinc-400 leading-none truncate">
                        {{ __('Cursos Cubiertos') }}
                    </div>
                    <div class="text-base font-black text-[#00376e] dark:text-blue-400 leading-none mt-1">
                        {{ $this->stats['cursos_cubiertos'] }}
                        <span class="text-xs font-normal text-zinc-400">/ {{ $this->stats['total_cursos'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra de Navegación por Pestañas -->
        <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-700">
            <button type="button" wire:click="$set('tab', 'docentes')"
                class="pb-3 px-4 text-sm font-bold border-b-2 transition cursor-pointer flex items-center gap-2 {{ $tab === 'docentes' ? 'border-[#00376e] text-[#00376e] dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                <flux:icon.users class="size-4" />
                {{ __('Vista Docentes (:count)', ['count' => $this->stats['total_docentes']]) }}
            </button>
            <button type="button" wire:click="$set('tab', 'cursos')"
                class="pb-3 px-4 text-sm font-bold border-b-2 transition cursor-pointer flex items-center gap-2 {{ $tab === 'cursos' ? 'border-[#00376e] text-[#00376e] dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                <flux:icon.academic-cap class="size-4" />
                {{ __('Vista Cursos (:count)', ['count' => $this->stats['total_cursos']]) }}
            </button>
        </div>

        @if ($tab === 'docentes')
            <!-- Filtros de Búsqueda para Docentes -->
            <flux:card>
                <div class="flex flex-col md:flex-row items-start md:items-center gap-6 w-full">
                    <flux:field class="flex-1 w-full md:w-64">
                        <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                            {{ __('Búsqueda') }}
                        </flux:label>
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Buscar por nombre o RUT..." />
                    </flux:field>

                    <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                    <flux:field class="w-full md:w-48 overflow-hidden">
                        <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                            {{ __('Estado Jefatura') }}
                        </flux:label>
                        <flux:select wire:model.live="filtroJefatura">
                            <flux:select.option value="todos">{{ __('Todos') }}</flux:select.option>
                            <flux:select.option value="con_jefatura">{{ __('Con Jefatura') }}</flux:select.option>
                            <flux:select.option value="sin_jefatura">{{ __('Sin Jefatura') }}</flux:select.option>
                        </flux:select>
                    </flux:field>

                    <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                    <flux:field class="w-full md:w-48 overflow-hidden">
                        <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                            {{ __('Modalidad') }}
                        </flux:label>
                        <flux:select wire:model.live="filtroModalidad">
                            <flux:select.option value="todas">{{ __('Todas') }}</flux:select.option>
                            <flux:select.option value="basica">{{ __('Básica') }}</flux:select.option>
                            <flux:select.option value="media">{{ __('Media') }}</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>
            </flux:card>

            <!-- Tabla de Docentes -->
            <flux:card>
                <flux:table :paginate="$this->docentes">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'nombres'" :direction="$sortDirection"
                            wire:click="sort('nombres')">{{ __('Nombre del Docente') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'rut_numero'" :direction="$sortDirection"
                            wire:click="sort('rut_numero')">{{ __('RUT') }}</flux:table.column>
                        <flux:table.column>{{ __('Jefatura Asignada') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->docentes as $docente)
                            @php
                                $cursoAsignado = $docente->jefeDeCursos->first();
                            @endphp
                            <flux:table.row :key="$docente->id">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar>
                                            @if ($docente->avatar)
                                                <img src="{{ $docente->avatar }}" 
                                                     alt="{{ $docente->nombres }}" 
                                                     class="rounded-lg size-full object-cover" 
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='';"
                                                     referrerpolicy="no-referrer">
                                                <span style="display: none;">{{ $docente->initials() }}</span>
                                            @else
                                                <span>{{ $docente->initials() }}</span>
                                            @endif
                                        </flux:avatar>
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $docente->nombreCompleto() }}
                                            </div>
                                            <div class="text-xs text-zinc-500">
                                                {{ $docente->email }}
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>{{ $docente->rutCompleto() ?? '-' }}</flux:table.cell>

                                <flux:table.cell>
                                    @if ($cursoAsignado)
                                        <flux:badge color="blue" icon="academic-cap">
                                            {{ $cursoAsignado->nombreCompleto() }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc">
                                            {{ __('Sin jefatura') }}
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button variant="ghost" size="sm" icon="pencil-square"
                                            :tooltip="$cursoAsignado ? __('Cambiar Jefatura') : __('Asignar Jefatura')"
                                            wire:click="abrirModalDocente({{ $docente->id }})" />

                                        @if ($cursoAsignado)
                                            <flux:button variant="ghost" size="sm" icon="trash"
                                                :tooltip="__('Quitar Jefatura')"
                                                wire:click="quitarJefatura({{ $docente->id }})"
                                                wire:confirm="¿Seguro que deseas quitar la jefatura de {{ $cursoAsignado->nombreCompleto() }} a {{ $docente->nombreCompleto() }}?" />
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <flux:icon.users class="size-8 text-zinc-400" />
                                        <p class="font-medium">{{ __('No se encontraron docentes con los filtros aplicados.') }}</p>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @else
            <!-- Vista de Cursos -->
            <flux:card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Curso') }}</flux:table.column>
                        <flux:table.column>{{ __('Modalidad') }}</flux:table.column>
                        <flux:table.column>{{ __('Nivel y Letra') }}</flux:table.column>
                        <flux:table.column>{{ __('Profesor(a) Jefe') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Acciones') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->cursos as $curso)
                            <flux:table.row :key="$curso->id">
                                <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $curso->nombreCompleto() }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge :color="$curso->modalidad->value === 'basica' ? 'amber' : 'purple'">
                                        {{ $curso->modalidad->label() }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell class="text-zinc-700 dark:text-zinc-300">
                                    {{ $curso->nivel }}° {{ $curso->letra }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if ($curso->jefe)
                                        <div class="flex items-center gap-3">
                                            <flux:avatar>
                                                @if ($curso->jefe->avatar)
                                                    <img src="{{ $curso->jefe->avatar }}" 
                                                         alt="{{ $curso->jefe->nombres }}" 
                                                         class="rounded-lg size-full object-cover" 
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='';"
                                                         referrerpolicy="no-referrer">
                                                    <span style="display: none;">{{ $curso->jefe->initials() }}</span>
                                                @else
                                                    <span>{{ $curso->jefe->initials() }}</span>
                                                @endif
                                            </flux:avatar>
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ $curso->jefe->nombreCompleto() }}
                                                </div>
                                                <div class="text-xs text-zinc-500">
                                                    {{ $curso->jefe->email }}
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <flux:badge color="zinc">
                                            {{ __('Sin profesor jefe') }}
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button variant="ghost" size="sm" icon="pencil-square"
                                            :tooltip="$curso->jefe ? __('Cambiar Profesor Jefe') : __('Asignar Profesor Jefe')"
                                            wire:click="abrirModalCurso({{ $curso->id }})" />
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center py-12 text-zinc-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <flux:icon.academic-cap class="size-8 text-zinc-400" />
                                        <p class="font-medium">{{ __('No hay cursos registrados para el año académico activo.') }}</p>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    </div>

    <!-- Modal Asignación de Jefatura para un Docente -->
    <flux:modal wire:model="modalJefatura" class="md:w-[480px]">
        @php
            $docenteModal = $selectedDocenteId ? User::find($selectedDocenteId) : null;
            $cursoActualDocente = $docenteModal ? $docenteModal->cursoJefatura($this->schoolId) : null;
            $cursoSeleccionado = $selectedCursoId !== 'none' ? $this->cursos->firstWhere('id', (int) $selectedCursoId) : null;
        @endphp

        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Asignar Jefatura de Curso') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Selecciona el curso homeroom que liderará este docente o déjalo Sin jefatura.') }}
                </flux:text>
            </div>

            @if ($docenteModal)
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700 flex items-center gap-3">
                    <flux:avatar size="md">
                        <span>{{ $docenteModal->initials() }}</span>
                    </flux:avatar>
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate">
                            {{ $docenteModal->nombreCompleto() }}
                        </div>
                        <div class="text-xs text-zinc-500 truncate">
                            {{ $docenteModal->email }} {{ $docenteModal->rutCompleto() ? '· ' . $docenteModal->rutCompleto() : '' }}
                        </div>
                        <div class="mt-1">
                            @if ($cursoActualDocente)
                                <span class="text-[11px] text-zinc-500">
                                    {{ __('Jefatura actual:') }} <strong class="text-[#00376e] dark:text-blue-400">{{ $cursoActualDocente->nombreCompleto() }}</strong>
                                </span>
                            @else
                                <span class="text-[11px] text-zinc-500 italic">
                                    {{ __('Actualmente sin jefatura asignada') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <flux:field>
                <flux:label class="uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                    {{ __('Seleccionar Curso de Jefatura') }}
                </flux:label>
                <flux:select wire:model.live="selectedCursoId">
                    <flux:select.option value="none">{{ __('— Sin jefatura —') }}</flux:select.option>
                    @foreach ($this->cursos as $c)
                        <flux:select.option value="{{ (string) $c->id }}">
                            {{ $c->nombreCompleto() }}
                            @if ($c->jefe && $c->jefe_id !== $selectedDocenteId)
                                ({{ __('Actualmente: ') . $c->jefe->nombreCompleto() }})
                            @elseif ($c->jefe_id === $selectedDocenteId)
                                ({{ __('Actual') }})
                            @endif
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <!-- Alerta informativa de Reasignación si el curso ya tiene otro jefe -->
            @if ($cursoSeleccionado && $cursoSeleccionado->jefe && $cursoSeleccionado->jefe_id !== $selectedDocenteId)
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-amber-800 dark:text-amber-300 text-xs flex items-start gap-2">
                    <flux:icon.exclamation-triangle class="size-4 shrink-0 mt-0.5" />
                    <div>
                        <strong>{{ __('Reasignación:') }}</strong> {{ __('El curso :curso está actualmente a cargo de :jefe. Al guardar, se reasignará automáticamente a :docente.', [
                            'curso' => $cursoSeleccionado->nombreCompleto(),
                            'jefe' => $cursoSeleccionado->jefe->nombreCompleto(),
                            'docente' => $docenteModal?->nombreCompleto() ?? 'este docente',
                        ]) }}
                    </div>
                </div>
            @endif

            @if ($selectedCursoId === 'none' && $cursoActualDocente)
                <div class="p-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-zinc-700 dark:text-zinc-300 text-xs flex items-start gap-2">
                    <flux:icon.information-circle class="size-4 shrink-0 mt-0.5 text-blue-500" />
                    <div>
                        {{ __('Al guardar, :docente dejará de ser profesor(a) jefe de :curso y quedará Sin jefatura.', [
                            'docente' => $docenteModal?->nombreCompleto() ?? 'el docente',
                            'curso' => $cursoActualDocente->nombreCompleto(),
                        ]) }}
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 pt-2">
                <flux:button wire:click="$set('modalJefatura', false)" variant="ghost">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button wire:click="guardarJefatura" variant="primary">
                    {{ __('Guardar Asignación') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Asignación de Jefe para un Curso -->
    <flux:modal wire:model="modalCursoJefe" class="md:w-[480px]">
        @php
            $cursoModal = $selectedCursoForModalId ? Curso::with('jefe')->find($selectedCursoForModalId) : null;
            $docenteSeleccionadoEnCurso = $selectedDocenteForModalId !== 'none' ? $this->allDocentes->firstWhere('id', (int) $selectedDocenteForModalId) : null;
            $jefaturaPreviaDocente = $docenteSeleccionadoEnCurso ? $docenteSeleccionadoEnCurso->cursoJefatura($this->schoolId) : null;
        @endphp

        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Asignar Profesor(a) Jefe') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Selecciona el profesor o profesora responsable de este curso.') }}
                </flux:text>
            </div>

            @if ($cursoModal)
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700 flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-blue-100 dark:bg-blue-950/60 text-[#00376e] dark:text-blue-400 font-extrabold flex items-center justify-center text-sm">
                        {{ $cursoModal->nombreAbreviado() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate">
                            {{ $cursoModal->nombreCompleto() }}
                        </div>
                        <div class="text-xs text-zinc-500">
                            {{ $cursoModal->modalidad->label() }} · {{ $cursoModal->nivel }}° {{ $cursoModal->letra }}
                        </div>
                        <div class="mt-1 text-[11px] text-zinc-500">
                            {{ __('Jefe actual:') }}
                            @if ($cursoModal->jefe)
                                <strong class="text-emerald-600 dark:text-emerald-400">{{ $cursoModal->jefe->nombreCompleto() }}</strong>
                            @else
                                <span class="italic text-zinc-400">{{ __('Sin profesor jefe asignado') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <flux:field>
                <flux:label class="uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                    {{ __('Docente Responsable') }}
                </flux:label>
                <flux:select wire:model.live="selectedDocenteForModalId">
                    <flux:select.option value="none">{{ __('— Sin profesor jefe —') }}</flux:select.option>
                    @foreach ($this->allDocentes as $doc)
                        @php
                            $jefaturaActualDoc = $doc->jefeDeCursos->first();
                        @endphp
                        <flux:select.option value="{{ (string) $doc->id }}">
                            {{ $doc->nombreCompleto() }}
                            @if ($jefaturaActualDoc && $jefaturaActualDoc->id !== $selectedCursoForModalId)
                                ({{ __('Jefe en: ') . $jefaturaActualDoc->nombreCompleto() }})
                            @elseif ($jefaturaActualDoc && $jefaturaActualDoc->id === $selectedCursoForModalId)
                                ({{ __('Actual') }})
                            @endif
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            @if ($docenteSeleccionadoEnCurso && $jefaturaPreviaDocente && $jefaturaPreviaDocente->id !== $selectedCursoForModalId)
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-amber-800 dark:text-amber-300 text-xs flex items-start gap-2">
                    <flux:icon.exclamation-triangle class="size-4 shrink-0 mt-0.5" />
                    <div>
                        <strong>{{ __('Cambio de jefatura:') }}</strong> {{ __(':docente ya es profesor jefe de :curso. Al asignarlo a este curso, dejará de ser jefe de :curso.', [
                            'docente' => $docenteSeleccionadoEnCurso->nombreCompleto(),
                            'curso' => $jefaturaPreviaDocente->nombreCompleto(),
                        ]) }}
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 pt-2">
                <flux:button wire:click="$set('modalCursoJefe', false)" variant="ghost">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button wire:click="guardarJefeCurso" variant="primary">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
