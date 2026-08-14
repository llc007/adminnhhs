<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Entrevista;
use App\Models\User;
use App\Models\Curso;

use Livewire\Attributes\Url;

new class extends Component {
    use WithPagination;

    #[Url]
    public $searchId = '';

    #[Url]
    public $search = '';

    #[Url]
    public $profesor_id = '';

    #[Url]
    public $curso_id = '';

    #[Url]
    public $fecha = '';

    #[Url]
    public $estado = '';

    #[Url]
    public $filtroTemporal = ''; // dia, semana, mes

    public ?int $entrevistaIdAEliminar = null;
    public bool $modalEliminar = false;

    public function mount()
    {
        $user = auth()->user();
        if (
            !$user->can('ver-entrevistas-general') &&
            !$user->can('ver-entrevistas-propias') &&
            !$user->hasRole(['superadmin', 'estudiante'])
        ) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }
    }

    public function updating($field)
    {
        $this->resetPage();
    }

    public function setFiltroTemporal($filtro)
    {
        if ($this->filtroTemporal === $filtro) {
            $this->filtroTemporal = '';
            return;
        }
        $this->filtroTemporal = $filtro;
        if (empty($this->fecha)) {
            $this->fecha = now()->toDateString();
        }
    }

    public function clearFilters()
    {
        $this->reset(['searchId', 'search', 'profesor_id', 'curso_id', 'fecha', 'estado', 'filtroTemporal']);
        $this->resetPage();
    }

    public function confirmarEliminacion(int $id): void
    {
        $entrevista = Entrevista::findOrFail($id);
        if (auth()->user()->cannot('delete', $entrevista)) {
            abort(403, 'No tienes permiso para eliminar esta entrevista.');
        }

        $this->entrevistaIdAEliminar = $id;
        $this->modalEliminar = true;
    }

    public function eliminarEntrevista(): void
    {
        if (!$this->entrevistaIdAEliminar) {
            return;
        }

        $entrevista = Entrevista::findOrFail($this->entrevistaIdAEliminar);

        $user = auth()->user();
        if (!$user->can('delete', $entrevista)) {
            abort(403, 'No tienes permiso para eliminar esta entrevista.');
        }

        $entrevista->delete();

        $this->modalEliminar = false;
        $this->entrevistaIdAEliminar = null;

        session()->flash('message', 'Entrevista eliminada correctamente.');
    }

    private function getFilteredQuery()
    {
        $query = Entrevista::with(['estudiante.curso', 'user'])
            ->where('school_id', auth()->user()->current_school_id);

        $user = auth()->user();

        if ($user->hasRole('estudiante')) {
            $estudiante = \App\Models\Estudiante::where('user_id', $user->id)->first();
            if ($estudiante) {
                $query->where('estudiante_id', $estudiante->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (! $user->hasRole('superadmin')) {
            $userId = $user->id;
            $canGeneral = $user->can('ver-entrevistas-general') || $user->can('ver-bitacoras');
            $canConfidenciales = $user->can('ver-entrevistas-confidenciales') || $user->hasRole('psicosocial');

            $query->where(function ($q) use ($userId, $canGeneral, $canConfidenciales) {
                // Mis entrevistas creadas o explícitamente compartidas conmigo
                $q->where('user_id', $userId)
                    ->orWhereHas('accesosCompartidos', function ($sub) use ($userId) {
                        $sub->where('user_id', $userId);
                    });

                // Si tiene permiso general, ve todas las entrevistas en el historial
                if ($canGeneral) {
                    $q->orWhereRaw('1 = 1');
                } elseif ($canConfidenciales) {
                    $q->orWhere('es_confidencial', true);
                }
            });
        }

        if (! empty($this->searchId)) {
            $cleanId = ltrim(trim($this->searchId), '#');
            if (is_numeric($cleanId)) {
                $query->where('id', (int) $cleanId);
            }
        }

        if (! empty($this->search)) {
            $words = array_filter(explode(' ', trim($this->search)));
            $query->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $w = trim($word);
                    if ($w === '') {
                        continue;
                    }
                    $q->where(function ($sub) use ($w) {
                        $sub->whereHas('estudiante', function ($sq) use ($w) {
                            $sq->where('nombres_csv', 'like', '%'.$w.'%')
                                ->orWhere('rut_numero', 'like', '%'.$w.'%');
                        })->orWhereHas('user', function ($sq) use ($w) {
                            $sq->where('nombres', 'like', '%'.$w.'%')
                                ->orWhere('apellido_pat', 'like', '%'.$w.'%')
                                ->orWhere('apellido_mat', 'like', '%'.$w.'%');
                        });
                    });
                }
            });
        }

        if (! empty($this->profesor_id)) {
            $query->where('user_id', $this->profesor_id);
        }

        if (! empty($this->curso_id)) {
            $query->whereHas('estudiante', function ($q) {
                $q->where('curso_id', $this->curso_id);
            });
        }

        $anchor = ! empty($this->fecha) ? \Carbon\Carbon::parse($this->fecha) : now();

        if (! empty($this->filtroTemporal)) {
            if ($this->filtroTemporal === 'dia') {
                $query->whereDate('fecha', $anchor->toDateString());
            } elseif ($this->filtroTemporal === 'semana') {
                $query->whereBetween('fecha', [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()]);
            } elseif ($this->filtroTemporal === 'mes') {
                $query->whereMonth('fecha', $anchor->month)->whereYear('fecha', $anchor->year);
            }
        } elseif (! empty($this->fecha)) {
            $query->whereDate('fecha', $anchor->toDateString());
        }

        if (! empty($this->estado)) {
            if ($this->estado === 'cancelada') {
                $query->whereIn('estado', ['cancelada', 'ausente']);
            } else {
                $query->where('estado', $this->estado);
            }
        }

        return $query
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc');
    }

    public function export()
    {
        $entrevistas = $this->getFilteredQuery()->get();

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=historial_entrevistas_' . now()->format('Y-m-d_H-i-s') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['ID', 'Fecha', 'Hora', 'Docente', 'Estudiante', 'Curso', 'Apoderado', 'RUT Apoderado', 'Teléfono', 'Motivo', 'Estado'];

        $callback = function () use ($entrevistas, $columns) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM to ensure Excel opens it correctly with Spanish accents
            fprintf($file, chr(0xef) . chr(0xbb) . chr(0xbf));

            fputcsv($file, $columns, ';');

            foreach ($entrevistas as $entrevista) {
                fputcsv($file, [$entrevista->id, $entrevista->fecha, $entrevista->hora, $entrevista->user ? $entrevista->user->nombres . ' ' . $entrevista->user->apellido_pat : 'N/A', $entrevista->estudiante ? $entrevista->estudiante->nombres . ' ' . $entrevista->estudiante->apellido_pat : 'N/A', $entrevista->estudiante && $entrevista->estudiante->curso ? $entrevista->estudiante->curso->nombreCompleto() : 'N/A', $entrevista->apoderado_nombre, $entrevista->apoderado_rut, $entrevista->apoderado_telefono, $entrevista->motivo, ucfirst($entrevista->estado)], ';');
            }

            fclose($file);
        };

        return response()->streamDownload($callback, 'historial_entrevistas_' . now()->format('Y-m-d_H-i-s') . '.csv', $headers);
    }

    public function render()
    {
        $docentes = User::whereHas('entrevistas')->orderBy('nombres')->get();
        $cursos = Curso::orderBy('modalidad')->orderBy('nivel')->orderBy('letra')->get();

        $query = $this->getFilteredQuery();

        $entrevistas = $query->paginate(15);

        // Métricas dinámicas basadas en los filtros actuales
        $baseQuery = clone $query;
        $totalMes = (clone $baseQuery)->count();
        $realizadasMes = (clone $baseQuery)->where('estado', 'realizada')->count();
        $pendientesMes = (clone $baseQuery)->whereIn('estado', ['pendiente', 'ingresada'])->count();
        $canceladasMes = (clone $baseQuery)->whereIn('estado', ['cancelada', 'ausente'])->count();

        $porcentaje = $totalMes > 0 ? round(($realizadasMes / $totalMes) * 100) : 0;

        return view('pages.entrevistas.⚡index', [
            'entrevistas' => $entrevistas,
            'docentes' => $docentes,
            'cursos' => $cursos,
            'porcentaje' => $porcentaje,
            'pendientesMes' => $pendientesMes,
            'canceladasMes' => $canceladasMes,
        ]);
    }
};
?>
<div class="max-w-7xl mx-auto w-full pb-12 space-y-8">

    <!-- Page Header -->
    @if(auth()->user()->hasRole('estudiante'))
        <x-entrevistas.header titulo="Mi Historial de Entrevistas"
            subtitulo="Registro de tus atenciones y citaciones programadas." icono="document-text">
            <div class="flex gap-3">
                <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        </x-entrevistas.header>
    @else
        <x-entrevistas.header titulo="Historial General de Entrevistas"
            subtitulo="Registro unificado de atención a estudiantes y apoderados." icono="document-text">
            <div class="flex gap-3">
                <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>

                @can('export', App\Models\Entrevista::class)
                    <flux:button variant="primary" icon="document-arrow-down" wire:click="export"
                        class="bg-gradient-to-br from-[#00376e] to-blue-800">Exportar (Excel)</flux:button>
                @endcan
            </div>
        </x-entrevistas.header>
    @endif

    <!-- Bento Filter Section -->
    <flux:card class="p-3 sm:p-4 bg-zinc-50 dark:bg-zinc-800/40 shadow-sm border border-zinc-200 dark:border-zinc-700 mb-4">
        <div class="flex items-center gap-1.5 text-[#00376e] dark:text-blue-400 font-bold mb-2">
            <flux:icon.funnel class="size-3.5" />
            <span class="uppercase tracking-widest text-[10px]">Panel de Filtros</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2 items-end">
            <!-- Buscar por ID -->
            <flux:field class="lg:col-span-1">
                <flux:label class="text-[11px]">ID</flux:label>
                <flux:input size="sm" class="!text-[11px]" wire:model.live.debounce.300ms="searchId" placeholder="# ID" />
            </flux:field>

            @if(!auth()->user()->hasRole('estudiante'))
                <!-- Search Text -->
                <flux:field class="lg:col-span-3">
                    <flux:label class="text-[11px]">Buscar Texto</flux:label>
                    <flux:input size="sm" class="!text-[11px]" wire:model.live.debounce.300ms="search" placeholder="Estudiante o Apoderado..." />
                </flux:field>
            @endif

            <!-- Dropdown: Profesor -->
            <flux:field class="{{ auth()->user()->hasRole('estudiante') ? 'lg:col-span-3' : 'lg:col-span-2' }}">
                <flux:label class="text-[11px]">Profesor</flux:label>
                <flux:select size="sm" class="!text-[11px]" wire:model.live="profesor_id">
                    <flux:select.option value="">Todos los docentes</flux:select.option>
                    @foreach ($docentes as $docente)
                        <flux:select.option value="{{ $docente->id }}">{{ $docente->nombres }}
                            {{ $docente->apellido_pat }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            @if(!auth()->user()->hasRole('estudiante'))
                <!-- Dropdown: Curso -->
                <flux:field class="lg:col-span-2">
                    <flux:label class="text-[11px]">Curso</flux:label>
                    <flux:select size="sm" class="!text-[11px]" wire:model.live="curso_id">
                        <flux:select.option value="">Todos los cursos</flux:select.option>
                        @foreach ($cursos as $curso)
                            <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            @endif

            <!-- Temporal -->
            <flux:field class="{{ auth()->user()->hasRole('estudiante') ? 'lg:col-span-4' : 'lg:col-span-2' }}">
                <flux:label class="text-[11px]">Temporalidad <span
                        class="text-[9px] text-zinc-400 font-normal ml-0.5">{{ $fecha ? '(' . \Carbon\Carbon::parse($fecha)->format('d/m') . ')' : '' }}</span>
                </flux:label>
                <div class="flex gap-0.5 bg-zinc-100 dark:bg-zinc-800 p-0.5 rounded-md">
                    <flux:dropdown position="bottom-start" class="flex-1">
                        <button type="button"
                            class="w-full h-full text-[10px] py-1 rounded-md font-bold flex items-center justify-center gap-1 {{ (empty($filtroTemporal) && !empty($fecha)) || $filtroTemporal === 'dia' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors"
                            wire:click="setFiltroTemporal('dia')">
                            <flux:icon.calendar class="size-3" /> Día
                        </button>
                        <flux:menu class="p-2 min-w-[280px]">
                            <flux:calendar wire:model.live="fecha" />
                        </flux:menu>
                    </flux:dropdown>

                    <button
                        class="flex-1 text-[10px] py-1 rounded-md font-bold {{ $filtroTemporal === 'semana' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors"
                        wire:click="setFiltroTemporal('semana')">Semana</button>
                    <button
                        class="flex-1 text-[10px] py-1 rounded-md font-bold {{ $filtroTemporal === 'mes' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors"
                        wire:click="setFiltroTemporal('mes')">Mes</button>
                </div>
            </flux:field>

            <!-- Dropdown: Estado -->
            <flux:field class="{{ auth()->user()->hasRole('estudiante') ? 'lg:col-span-4' : 'lg:col-span-2' }}">
                <flux:label class="text-[11px]">Estado</flux:label>
                <flux:select size="sm" class="!text-[11px]" wire:model.live="estado">
                    <flux:select.option value="">Todos los estados</flux:select.option>
                    <flux:select.option value="pendiente">Pendientes</flux:select.option>
                    <flux:select.option value="ingresada">En Recepción</flux:select.option>
                    <flux:select.option value="abierta">Abiertas</flux:select.option>
                    <flux:select.option value="realizada">Realizadas</flux:select.option>
                    <flux:select.option value="cancelada">Canceladas</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
    </flux:card>

    <!-- Data Table -->
    <flux:card class="overflow-hidden shadow-sm p-0">
        <div class="px-5 py-1.5 overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-14 text-[11px]">ID</flux:table.column>
                    <flux:table.column class="text-[11px]">Fecha y Hora</flux:table.column>
                    <flux:table.column class="text-[11px]">Estudiante</flux:table.column>
                    <flux:table.column class="text-[11px]">Profesor a cargo</flux:table.column>
                    <flux:table.column class="text-[11px]">Motivo</flux:table.column>
                    <flux:table.column class="text-[11px]">Estado</flux:table.column>
                    <flux:table.column class="text-right text-[11px]">Acciones</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($entrevistas as $entrevista)
                        <flux:table.row class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                            <flux:table.cell class="py-1.5">
                                <span class="font-mono text-[11px] font-bold text-zinc-600 dark:text-zinc-400">#{{ $entrevista->id }}</span>
                            </flux:table.cell>

                            <flux:table.cell class="py-1.5">
                                <div class="flex items-center gap-1 text-[11px] font-semibold text-zinc-800 dark:text-zinc-200">
                                    <flux:icon.calendar class="size-3 text-zinc-400 shrink-0" />
                                    <span>{{ \Carbon\Carbon::parse($entrevista->fecha)->translatedFormat('d M, Y') }}</span>
                                    <span class="text-zinc-400 font-normal ml-0.5">{{ \Carbon\Carbon::parse($entrevista->hora)->format('H:i') }} hrs</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="py-1.5">
                                <div class="leading-tight">
                                    <span class="text-[11px] font-bold text-zinc-900 dark:text-zinc-100 block">{{ $entrevista->estudiante ? $entrevista->estudiante->nombreCompleto() : '-' }}</span>
                                    <span class="text-[9px] text-zinc-400 font-medium uppercase block mt-0.5">{{ $entrevista->estudiante && $entrevista->estudiante->curso ? $entrevista->estudiante->curso->nombreCompleto() : 'Sin Curso' }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="py-1.5">
                                <span class="text-[11px] font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $entrevista->user?->nombres }} {{ $entrevista->user?->apellido_pat }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell class="py-1.5">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <flux:badge color="zinc" size="xs" class="uppercase text-[9px] py-0.5 px-1.5 font-bold tracking-wide">
                                        {{ $entrevista->motivo ?? 'General' }}</flux:badge>
                                    @if ($entrevista->es_confidencial)
                                        <flux:badge color="purple" size="xs" icon="lock-closed" class="uppercase text-[9px] py-0.5 px-1.5 font-bold tracking-wide">
                                            Confidencial</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="py-1.5">
                                @if ($entrevista->estado === 'realizada')
                                    <flux:badge color="emerald" size="xs" icon="check-circle" class="uppercase text-[9px] py-0.5 px-1.5 font-bold tracking-wide">Realizada</flux:badge>
                                @elseif($entrevista->estado === 'abierta')
                                    <flux:badge color="sky" size="xs" icon="arrow-right-start-on-rectangle" class="uppercase text-[9px] py-0.5 px-1.5 font-bold tracking-wide">Abierta</flux:badge>
                                @elseif($entrevista->estado === 'ingresada')
                                    <flux:badge color="blue" size="xs" class="uppercase text-[9px] py-0.5 px-1.5 font-bold tracking-wide">En Recepción</flux:badge>
                                @elseif($entrevista->estado === 'pendiente')
                                    <flux:badge color="amber" size="xs" icon="clock" class="uppercase text-[9px] py-0.5 px-1.5 font-bold tracking-wide">Pendiente</flux:badge>
                                @else
                                    <flux:badge color="red" size="xs" class="uppercase text-[9px] py-0.5 px-1.5 font-bold tracking-wide">{{ ucfirst($entrevista->estado) }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="py-1.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('view', $entrevista)
                                        <flux:button size="xs" variant="subtle"
                                            href="{{ route('entrevistas.bitacora', $entrevista->id) }}" class="font-bold text-[10px]">Ver Bitácora</flux:button>
                                    @else
                                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500 font-medium italic inline-flex items-center gap-1 py-1 px-2 bg-zinc-100 dark:bg-zinc-800 rounded-md">
                                            <flux:icon.lock-closed class="size-3 text-purple-500" /> Bitácora Privada
                                        </span>
                                    @endcan

                                    @can('delete', $entrevista)
                                        <flux:button size="xs" variant="danger" icon="trash"
                                            wire:click="confirmarEliminacion({{ $entrevista->id }})" title="Eliminar entrevista"></flux:button>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">
                                <div class="py-8 text-center text-zinc-500">
                                    <flux:icon.magnifying-glass class="size-6 mx-auto opacity-50 mb-2" />
                                    <p class="text-xs">No se encontraron entrevistas con los filtros seleccionados.</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="p-3 sm:px-5 bg-zinc-50 dark:bg-zinc-800/20 border-t border-zinc-200 dark:border-zinc-700">
            {{ $entrevistas->links(data: ['scrollTo' => false]) }}
        </div>
    </flux:card>

    <!-- Stats/Insights Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4">
        <flux:card
            class="bg-gradient-to-br from-[#00376e] to-[#004d97] p-6 text-white border-0 flex items-center gap-4">
            <div class="bg-white/10 p-3 rounded-lg">
                <flux:icon.check-badge class="size-8" />
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest font-bold opacity-80">Cumplimiento (Filtro Actual)</p>
                <h3 class="text-2xl font-black">{{ $porcentaje }}%</h3>
            </div>
        </flux:card>

        <flux:card class="border-l-4 border-l-amber-500 flex items-center gap-4">
            <div class="bg-amber-100 dark:bg-amber-500/10 p-3 rounded-lg text-amber-600 dark:text-amber-400">
                <flux:icon.clock class="size-8" />
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest font-bold text-zinc-500 dark:text-zinc-400">Pendientes
                </p>
                <h3 class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $pendientesMes }}</h3>
            </div>
        </flux:card>

        <flux:card class="border-l-4 border-l-red-500 flex items-center gap-4">
            <div class="bg-red-100 dark:bg-red-500/10 p-3 rounded-lg text-red-600 dark:text-red-400">
                <flux:icon.x-circle class="size-8" />
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest font-bold text-zinc-500 dark:text-zinc-400">No
                    Realizadas</p>
                <h3 class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $canceladasMes }}</h3>
            </div>
        </flux:card>
    </div>

    <!-- Modal Confirmar Eliminación -->
    <flux:modal wire:model="modalEliminar" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Eliminar Entrevista</flux:heading>
                <flux:subheading class="mt-2">
                    ¿Estás seguro de que deseas eliminar este registro de entrevista? Esta acción es permanente y borrará la cita y su bitácora asociada.
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('modalEliminar', false)">Cancelar</flux:button>
                <flux:button variant="danger" wire:click="eliminarEntrevista">Sí, Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
