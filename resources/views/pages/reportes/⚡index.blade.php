<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new #[Title('Módulo de Reportes')] class extends Component {
    use WithPagination;

    // Tipo de reporte seleccionado
    public string $tipoReporte = 'entrevistas_profesor';

    // Filtros interactivos
    public string $search = '';
    public string $periodo = 'ano_actual';
    public string $cargo = 'todos';
    public string $sortBy = 'nombres';
    public string $sortDirection = 'asc';

    // Modal para ver el desglose de entrevistas de un profesor
    public bool $modalDetalle = false;
    public ?int $selectedDocenteId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user->hasRole(['superadmin', 'administrador', 'directivo']) && ! $user->can('ver-reportes-entrevistas')) {
            abort(403, 'No tienes permiso para acceder a la sección de reportes.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodo(): void
    {
        $this->resetPage();
    }

    public function updatedCargo(): void
    {
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

    /**
     * Obtiene el rango de fechas [inicio, fin] según el filtro de período
     */
    private function getPeriodoDates(): array
    {
        $now = now('America/Santiago');
        $currentYear = $now->year;

        return match ($this->periodo) {
            'mes_actual' => [
                $now->copy()->startOfMonth()->format('Y-m-d'),
                $now->copy()->endOfMonth()->format('Y-m-d'),
            ],
            'primer_semestre' => [
                "{$currentYear}-01-01",
                "{$currentYear}-06-30",
            ],
            'segundo_semestre' => [
                "{$currentYear}-07-01",
                "{$currentYear}-12-31",
            ],
            'ano_actual' => [
                "{$currentYear}-01-01",
                "{$currentYear}-12-31",
            ],
            default => [null, null], // 'todo'
        };
    }

    /**
     * Query base de funcionarios con conteos agregados
     */
    public function getBaseQuery()
    {
        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();

        $dateClosure = function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('fecha', [$startDate, $endDate]);
            }
        };

        $query = \App\Models\User::query()
            ->whereHas('schools', fn($q) => $q->where('schools.id', $schoolId))
            ->whereDoesntHave('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', 'estudiante');
            })
            ->whereRaw("SUBSTR(email, 1, 1) != '_'")
            ->where('email', 'not like', 'docente1@%')
            ->where('email', 'not like', 'test%')
            ->withCount([
                'entrevistas as total_agendadas' => fn($q) => $q->where('school_id', $schoolId)->where($dateClosure),
                'entrevistas as total_realizadas' => fn($q) => $q->where('school_id', $schoolId)->where('estado', 'realizada')->where($dateClosure),
                'entrevistas as total_canceladas' => fn($q) => $q->where('school_id', $schoolId)->where('estado', 'cancelada')->where($dateClosure),
                'entrevistas as total_abiertas' => fn($q) => $q->where('school_id', $schoolId)->whereIn('estado', ['abierta', 'ingresada', 'pendiente'])->where($dateClosure),
                'entrevistas as total_ausentes' => fn($q) => $q->where('school_id', $schoolId)->where('estado', 'ausente')->where($dateClosure),
            ]);

        if ($this->cargo !== 'todos') {
            $query->whereHas('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', $this->cargo);
            });
        }

        if (trim($this->search) !== '') {
            $words = array_filter(explode(' ', trim($this->search)));
            $query->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $w = trim($word);
                    $q->where(function ($sq) use ($w) {
                        $cleanRut = str_replace(['.', '-'], '', $w);
                        $sq->where('nombres', 'like', "%{$w}%")
                            ->orWhere('apellido_pat', 'like', "%{$w}%")
                            ->orWhere('apellido_mat', 'like', "%{$w}%")
                            ->orWhere('email', 'like', "%{$w}%")
                            ->orWhere('rut_numero', 'like', "%{$cleanRut}%");
                    });
                }
            });
        }

        if (in_array($this->sortBy, ['total_agendadas', 'total_realizadas', 'total_canceladas', 'total_abiertas'])) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        } elseif ($this->sortBy === 'rut_numero') {
            $query->orderBy('rut_numero', $this->sortDirection);
        } else {
            $query->orderBy('nombres', $this->sortDirection)
                ->orderBy('apellido_pat', $this->sortDirection);
        }

        return $query;
    }

    #[Computed]
    public function funcionarios()
    {
        return $this->getBaseQuery()->paginate(50);
    }

    #[Computed]
    public function totalStats(): array
    {
        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();

        $citasQuery = \App\Models\Entrevista::where('school_id', $schoolId);
        if ($startDate && $endDate) {
            $citasQuery->whereBetween('fecha', [$startDate, $endDate]);
        }

        $agendadas = (clone $citasQuery)->count();
        $realizadas = (clone $citasQuery)->where('estado', 'realizada')->count();
        $canceladas = (clone $citasQuery)->where('estado', 'cancelada')->count();
        $abiertas = (clone $citasQuery)->whereIn('estado', ['abierta', 'ingresada', 'pendiente'])->count();

        return [
            'agendadas' => $agendadas,
            'realizadas' => $realizadas,
            'canceladas' => $canceladas,
            'abiertas' => $abiertas,
            'tasa_realizacion' => $agendadas > 0 ? round(($realizadas / $agendadas) * 100) : 0,
        ];
    }

    public function verDetalle(int $docenteId): void
    {
        $this->selectedDocenteId = $docenteId;
        $this->modalDetalle = true;
    }

    #[Computed]
    public function selectedDocente()
    {
        if (! $this->selectedDocenteId) {
            return null;
        }

        return \App\Models\User::find($this->selectedDocenteId);
    }

    #[Computed]
    public function detalleEntrevistas()
    {
        if (! $this->selectedDocenteId) {
            return collect();
        }

        $schoolId = auth()->user()->current_school_id;
        [$startDate, $endDate] = $this->getPeriodoDates();

        $query = \App\Models\Entrevista::with(['estudiante.curso', 'bitacora'])
            ->where('school_id', $schoolId)
            ->where('user_id', $this->selectedDocenteId);

        if ($startDate && $endDate) {
            $query->whereBetween('fecha', [$startDate, $endDate]);
        }

        return $query->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->get();
    }

    /**
     * Exporta el reporte actual a un archivo CSV compatible con Excel y Google Sheets
     */
    public function exportarExcel()
    {
        $school = auth()->user()->currentSchool;
        $schoolId = $school?->id;
        $periodoTexto = match($this->periodo) {
            'mes_actual' => 'Mes_Actual',
            'primer_semestre' => '1er_Semestre',
            'segundo_semestre' => '2do_Semestre',
            'todo' => 'Historico_Completo',
            default => 'Ano_' . now('America/Santiago')->year,
        };

        $filename = 'reporte_entrevistas_por_profesor_' . $periodoTexto . '_' . now('America/Santiago')->format('Y-m-d_His') . '.csv';
        $data = $this->getBaseQuery()->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM para apertura perfecta en Excel y Google Sheets
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Funcionario / Docente',
                'Correo Institucional',
                'RUT',
                'Cargo / Roles',
                'Total Agendadas',
                'Realizadas',
                'Canceladas / Reagendadas',
                'Abiertas / Pendientes',
                'Ausentes / No Asistio',
                'Tasa Realizacion (%)',
            ], ';');

            foreach ($data as $docente) {
                $displayRoles = array_diff($docente->active_roles, ['superadmin', 'externo']);
                $rolesTexto = !empty($displayRoles) ? implode(', ', array_map('ucfirst', $displayRoles)) : 'Docente';
                $tasa = $docente->total_agendadas > 0
                    ? round(($docente->total_realizadas / $docente->total_agendadas) * 100) . '%'
                    : '0%';

                fputcsv($file, [
                    $docente->nombreCompleto(),
                    $docente->email,
                    $docente->rutCompleto() ?? 'No registrado',
                    $rolesTexto,
                    $docente->total_agendadas,
                    $docente->total_realizadas,
                    $docente->total_canceladas,
                    $docente->total_abiertas,
                    $docente->total_ausentes,
                    $tasa,
                ], ';');
            }

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }
};

?>

<div>
    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto print:max-w-full">
        <!-- Header con Selector de Reportes y Botones de Exportación -->
        <div>
            <x-header :titulo="__('Módulo de Reportes')" :subtitulo="__(
                'Analítica, estadísticas consolidadas y métricas de desempeño por funcionario.',
            )" icono="chart-bar">
                <div class="flex items-center gap-2">
                    <a href="{{ route('reportes.imprimir.profesores', ['periodo' => $periodo, 'cargo' => $cargo, 'search' => $search]) }}" target="_blank">
                        <flux:button variant="ghost" icon="printer">
                            {{ __('Imprimir / PDF') }}
                        </flux:button>
                    </a>
                    <flux:button variant="primary" icon="arrow-down-tray" wire:click="exportarExcel">
                        {{ __('Exportar Excel') }}
                    </flux:button>
                </div>
            </x-header>
        </div>

        <!-- Selector de Tipo de Reporte -->
        <flux:card class="p-4 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 print:hidden">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-[#00376e]/10 dark:bg-blue-900/30 rounded-lg text-[#00376e] dark:text-blue-400">
                        <flux:icon.document-chart-bar class="size-6" />
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-zinc-400">Tipo de Reporte</div>
                        <div class="text-base font-bold text-zinc-900 dark:text-zinc-100">Entrevistas por Profesor</div>
                    </div>
                </div>

                <div class="w-full sm:w-72">
                    <flux:select wire:model.live="tipoReporte">
                        <flux:select.option value="entrevistas_profesor">{{ __('📊 Entrevistas por Profesor') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </flux:card>

        <!-- Filters Bento Grid (Diseño idéntico a Funcionarios) -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 print:hidden">
            <div class="md:col-span-8">
                <flux:card class="h-full flex items-center">
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-6 w-full">
                        <flux:field class="flex-1 w-full md:w-56">
                            <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Búsqueda') }}
                            </flux:label>
                            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                                placeholder="Buscar por nombre o RUT..." />
                        </flux:field>

                        <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                        <flux:field class="w-full md:w-44 overflow-hidden">
                            <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Período') }}
                            </flux:label>
                            <flux:select wire:model.live="periodo">
                                <flux:select.option value="ano_actual">{{ __('Año Actual (' . now()->year . ')') }}</flux:select.option>
                                <flux:select.option value="mes_actual">{{ __('Mes Actual') }}</flux:select.option>
                                <flux:select.option value="primer_semestre">{{ __('1er Semestre') }}</flux:select.option>
                                <flux:select.option value="segundo_semestre">{{ __('2do Semestre') }}</flux:select.option>
                                <flux:select.option value="todo">{{ __('Todo el Historial') }}</flux:select.option>
                            </flux:select>
                        </flux:field>

                        <div class="h-12 w-px bg-zinc-200 dark:bg-zinc-700 hidden md:block"></div>

                        <flux:field class="w-full md:w-44">
                            <flux:label class="mb-2 uppercase tracking-widest text-[10px] font-bold text-zinc-500 dark:text-zinc-400">
                                {{ __('Cargo (Rol)') }}
                            </flux:label>
                            <flux:select wire:model.live="cargo">
                                <flux:select.option value="todos">{{ __('Todos los Cargos') }}</flux:select.option>
                                <flux:select.option value="docente">{{ __('Docentes') }}</flux:select.option>
                                <flux:select.option value="directivo">{{ __('Directivos') }}</flux:select.option>
                                <flux:select.option value="psicosocial">{{ __('Psicosocial') }}</flux:select.option>
                                <flux:select.option value="inspector">{{ __('Inspectores') }}</flux:select.option>
                                <flux:select.option value="asistente">{{ __('Asistentes') }}</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>
                </flux:card>
            </div>

            <!-- Tarjeta Métrica Negra -->
            <div class="md:col-span-4">
                <flux:card class="h-full flex items-center justify-between bg-zinc-900 border-none !text-white dark:bg-zinc-800 shadow-md">
                    <div>
                        <div class="text-[10px] uppercase tracking-widest font-bold opacity-70">
                            {{ __('Total Funcionarios') }}
                        </div>
                        <div class="text-4xl font-bold mt-1 tracking-tight">{{ $this->funcionarios->total() }}</div>
                        <div class="text-xs text-zinc-300 mt-1 font-medium">
                            {{ $this->totalStats['agendadas'] }} entrevistas en total ({{ $this->totalStats['realizadas'] }} realizadas)
                        </div>
                    </div>
                    <div class="p-3 bg-white/10 rounded-full">
                        <flux:icon.users class="size-8" />
                    </div>
                </flux:card>
            </div>
        </div>

        <!-- Tabla de Datos -->
        <flux:card>
            <flux:table :paginate="$this->funcionarios">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'nombres'" :direction="$sortDirection"
                        wire:click="sort('nombres')">{{ __('Nombre del Funcionario') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'rut_numero'" :direction="$sortDirection"
                        wire:click="sort('rut_numero')">{{ __('RUT') }}</flux:table.column>
                    <flux:table.column>{{ __('Cargo') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'total_agendadas'" :direction="$sortDirection"
                        wire:click="sort('total_agendadas')" class="text-center">{{ __('Agendadas') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'total_realizadas'" :direction="$sortDirection"
                        wire:click="sort('total_realizadas')" class="text-center">{{ __('Realizadas') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'total_canceladas'" :direction="$sortDirection"
                        wire:click="sort('total_canceladas')" class="text-center">{{ __('Canceladas') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'total_abiertas'" :direction="$sortDirection"
                        wire:click="sort('total_abiertas')" class="text-center">{{ __('Abiertas') }}</flux:table.column>
                    <flux:table.column class="text-right print:hidden">{{ __('Acciones') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->funcionarios as $funcionario)
                        <flux:table.row :key="$funcionario->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <flux:avatar class="print:hidden">
                                        @if ($funcionario->avatar)
                                            <img src="{{ $funcionario->avatar }}" 
                                                 alt="{{ $funcionario->nombres }}" 
                                                 class="rounded-lg size-full object-cover" 
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='';"
                                                 referrerpolicy="no-referrer">
                                            <span style="display: none;">{{ $funcionario->initials() }}</span>
                                        @else
                                            <span>{{ $funcionario->initials() }}</span>
                                        @endif
                                    </flux:avatar>
                                    <div>
                                        <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                            {{ $funcionario->nombreCompleto() }}
                                        </div>
                                        <div class="text-xs text-zinc-500">{{ $funcionario->email }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            
                            <flux:table.cell class="font-mono text-xs">
                                {{ $funcionario->rutCompleto() ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @php
                                        $roleLabels = [
                                            'docente' => ['label' => 'Docente', 'color' => 'blue'],
                                            'inspector' => ['label' => 'Inspector', 'color' => 'indigo'],
                                            'asistente' => ['label' => 'Asistente', 'color' => 'teal'],
                                            'psicosocial' => ['label' => 'Psicosocial', 'color' => 'cyan'],
                                            'recepcion' => ['label' => 'Recepción', 'color' => 'emerald'],
                                            'directivo' => ['label' => 'Directivo', 'color' => 'violet'],
                                            'administrador' => ['label' => 'Administrador', 'color' => 'rose'],
                                            'solicitante_adquisiciones' => ['label' => 'Solicitante Adq.', 'color' => 'amber'],
                                            'ti' => ['label' => 'Personal TI', 'color' => 'sky'],
                                        ];
                                        $displayRoles = array_diff($funcionario->active_roles, ['superadmin', 'externo']);
                                    @endphp
                                    @forelse ($displayRoles as $role)
                                        @php
                                            $info = $roleLabels[$role] ?? ['label' => ucfirst($role), 'color' => 'zinc'];
                                        @endphp
                                        <flux:badge size="sm" :color="$info['color']">{{ $info['label'] }}</flux:badge>
                                    @empty
                                        <flux:badge size="sm" color="blue">Docente</flux:badge>
                                    @endforelse
                                </div>
                            </flux:table.cell>

                            {{-- AGENDADAS --}}
                            <flux:table.cell class="text-center font-bold text-zinc-800 dark:text-zinc-200">
                                <span class="px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 rounded-md font-mono text-sm">
                                    {{ $funcionario->total_agendadas }}
                                </span>
                            </flux:table.cell>

                            {{-- REALIZADAS --}}
                            <flux:table.cell class="text-center font-bold">
                                @if($funcionario->total_realizadas > 0)
                                    <span class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50 rounded-md font-mono text-sm">
                                        {{ $funcionario->total_realizadas }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 font-mono text-sm">0</span>
                                @endif
                            </flux:table.cell>

                            {{-- CANCELADAS O REAGENDADAS --}}
                            <flux:table.cell class="text-center font-bold">
                                @if($funcionario->total_canceladas > 0)
                                    <span class="px-2.5 py-1 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50 rounded-md font-mono text-sm">
                                        {{ $funcionario->total_canceladas }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 font-mono text-sm">0</span>
                                @endif
                            </flux:table.cell>

                            {{-- ABIERTAS / PENDIENTES --}}
                            <flux:table.cell class="text-center font-bold">
                                @if($funcionario->total_abiertas > 0)
                                    <span class="px-2.5 py-1 bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/50 rounded-md font-mono text-sm">
                                        {{ $funcionario->total_abiertas }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 font-mono text-sm">0</span>
                                @endif
                            </flux:table.cell>

                            {{-- ACCIONES --}}
                            <flux:table.cell class="text-right print:hidden">
                                <flux:button size="xs" variant="ghost" icon="eye" wire:click="verDetalle({{ $funcionario->id }})" title="Ver detalle de citas">
                                    {{ __('Ver Citas') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="text-center py-10 text-zinc-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon.magnifying-glass class="size-8 text-zinc-400" />
                                    <p class="font-medium text-sm">No se encontraron funcionarios con los filtros seleccionados.</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <!-- Modal de Detalle de Citas del Profesor -->
        <flux:modal wire:model="modalDetalle" class="min-w-[90vw] md:min-w-[48rem] max-h-[85vh] overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            <flux:icon.calendar-days class="size-5 text-[#00376e] dark:text-blue-400" />
                            Detalle de Entrevistas: {{ $this->selectedDocente?->nombreCompleto() }}
                        </flux:heading>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            {{ $this->selectedDocente?->email }} • {{ $this->selectedDocente?->rutCompleto() ?? 'Sin RUT' }}
                        </flux:text>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse ($this->detalleEntrevistas as $cita)
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                                        {{ \Carbon\Carbon::parse($cita->fecha)->translatedFormat('d M, Y') }} — {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }} hrs
                                    </span>
                                    
                                    {{-- Badge de Estado --}}
                                    @if($cita->estado === 'realizada')
                                        <flux:badge size="sm" color="emerald">Realizada</flux:badge>
                                    @elseif($cita->estado === 'cancelada')
                                        <flux:badge size="sm" color="red">Cancelada</flux:badge>
                                    @elseif($cita->estado === 'ingresada')
                                        <flux:badge size="sm" color="amber">En Recinto</flux:badge>
                                    @elseif($cita->estado === 'abierta')
                                        <flux:badge size="sm" color="sky">Abierta</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ ucfirst($cita->estado) }}</flux:badge>
                                    @endif
                                </div>

                                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                    Estudiante: {{ $cita->estudiante?->nombreCompleto() ?? 'No registrado' }}
                                    <span class="text-xs font-normal text-zinc-500">({{ $cita->estudiante?->curso?->nombreCompleto() ?? 'Sin curso' }})</span>
                                </p>
                                
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                    <strong>Apoderado:</strong> {{ $cita->estudiante?->apoderado_nombres ? $cita->estudiante->apoderado_nombres . ' ' . $cita->estudiante->apoderado_apellido_pat : 'Sin nombre' }}
                                    • <strong>Motivo:</strong> {{ $cita->motivo }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 self-end md:self-center shrink-0">
                                <flux:button size="xs" variant="primary" href="{{ route('entrevistas.bitacora', $cita->id) }}" wire:navigate icon="document-text">
                                    {{ __('Ver Bitácora') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-zinc-500">
                            <flux:icon.calendar class="size-8 mx-auto text-zinc-400 mb-2" />
                            <p class="text-sm font-medium">Este docente no registra entrevistas en el período seleccionado.</p>
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cerrar') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
