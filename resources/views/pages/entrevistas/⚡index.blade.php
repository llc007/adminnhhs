<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Entrevista;
use App\Models\User;
use App\Models\Curso;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $profesor_id = '';
    public $curso_id = '';
    public $fecha = '';
    public $estado = '';
    public $filtroTemporal = ''; // dia, semana, mes

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
        $this->reset(['search', 'profesor_id', 'curso_id', 'fecha', 'estado', 'filtroTemporal']);
        $this->resetPage();
    }

    public function render()
    {
        $docentes = User::whereHas('entrevistas')->orderBy('nombres')->get();
        $cursos = Curso::orderBy('modalidad')->orderBy('nivel')->orderBy('letra')->get();

        $query = Entrevista::with(['estudiante.curso', 'user'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc');

        if (!empty($this->search)) {
            $query
                ->whereHas('estudiante', function ($q) {
                    $q->where('nombres', 'like', '%' . $this->search . '%')
                        ->orWhere('apellido_pat', 'like', '%' . $this->search . '%')
                        ->orWhere('apellido_mat', 'like', '%' . $this->search . '%')
                        ->orWhere('rut', 'like', '%' . $this->search . '%');
                })
                ->orWhereHas('user', function ($q) {
                    $q->where('nombres', 'like', '%' . $this->search . '%')->orWhere('apellido_pat', 'like', '%' . $this->search . '%');
                });
        }

        if (!empty($this->profesor_id)) {
            $query->where('user_id', $this->profesor_id);
        }

        if (!empty($this->curso_id)) {
            $query->whereHas('estudiante', function ($q) {
                $q->where('curso_id', $this->curso_id);
            });
        }

        $anchor = !empty($this->fecha) ? \Carbon\Carbon::parse($this->fecha) : now();

        if (!empty($this->filtroTemporal)) {
            if ($this->filtroTemporal === 'dia') {
                $query->whereDate('fecha', $anchor->toDateString());
            } elseif ($this->filtroTemporal === 'semana') {
                $query->whereBetween('fecha', [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()]);
            } elseif ($this->filtroTemporal === 'mes') {
                $query->whereMonth('fecha', $anchor->month)->whereYear('fecha', $anchor->year);
            }
        } elseif (!empty($this->fecha)) {
            $query->whereDate('fecha', $anchor->toDateString());
        }

        if (!empty($this->estado)) {
            $query->where('estado', $this->estado);
        }

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
    <x-entrevistas.header 
        titulo="Historial General de Entrevistas" 
        subtitulo="Registro unificado de atención a estudiantes y apoderados." 
        icono="document-text" 
    >
        <div class="flex gap-3">
            <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar</flux:button>
            <flux:button variant="primary" icon="document-arrow-down"
                class="bg-gradient-to-br from-[#00376e] to-blue-800">Exportar (Excel)</flux:button>
        </div>
    </x-entrevistas.header>

    <!-- Bento Filter Section -->
    <flux:card class="p-6 md:p-8 bg-zinc-50 dark:bg-zinc-800/40 shadow-sm border border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center gap-2 text-[#00376e] dark:text-blue-400 font-bold mb-4">
            <flux:icon.funnel class="size-4" />
            <span class="uppercase tracking-widest text-xs">Panel de Filtros</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-6">
            <!-- Search Text -->
            <flux:field class="lg:col-span-2">
                <flux:label>Buscar Texto</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar Estudiante o Apoderado..." />
            </flux:field>

            <!-- Dropdown: Profesor -->
            <flux:field>
                <flux:label>Filtrar por Profesor</flux:label>
                <flux:select wire:model.live="profesor_id">
                    <flux:select.option value="">Todos los docentes</flux:select.option>
                    @foreach ($docentes as $docente)
                        <flux:select.option value="{{ $docente->id }}">{{ $docente->nombres }} {{ $docente->apellido_pat }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <!-- Dropdown: Curso -->
            <flux:field>
                <flux:label>Filtrar por Curso</flux:label>
                <flux:select wire:model.live="curso_id">
                    <flux:select.option value="">Todos los cursos</flux:select.option>
                    @foreach ($cursos as $curso)
                        <flux:select.option value="{{ $curso->id }}">{{ $curso->nombreCompleto() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <!-- Temporal -->
            <flux:field>
                <flux:label>Temporalidad <span class="text-[10px] text-zinc-400 font-normal ml-1">{{ $fecha ? '(' . \Carbon\Carbon::parse($fecha)->format('d/m') . ')' : '' }}</span></flux:label>
                <div class="flex gap-1 bg-zinc-100 dark:bg-zinc-800 p-1 rounded-lg">
                    <flux:dropdown position="bottom-start" class="flex-1">
                        <button type="button" class="w-full h-full text-xs py-1.5 rounded-md font-bold flex items-center justify-center gap-1 {{ (empty($filtroTemporal) && !empty($fecha)) || $filtroTemporal === 'dia' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors" wire:click="setFiltroTemporal('dia')">
                            <flux:icon.calendar class="size-3" /> Día
                        </button>
                        <flux:menu class="p-2 min-w-[280px]">
                            <flux:calendar wire:model.live="fecha" />
                        </flux:menu>
                    </flux:dropdown>
                    
                    <button class="flex-1 text-xs py-1.5 rounded-md font-bold {{ $filtroTemporal === 'semana' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors" wire:click="setFiltroTemporal('semana')">Semana</button>
                    <button class="flex-1 text-xs py-1.5 rounded-md font-bold {{ $filtroTemporal === 'mes' ? 'bg-[#00376e] text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition-colors" wire:click="setFiltroTemporal('mes')">Mes</button>
                </div>
            </flux:field>

            <!-- Dropdown: Estado -->
            <flux:field>
                <flux:label>Estado</flux:label>
                <flux:select wire:model.live="estado">
                    <flux:select.option value="">Todos los estados</flux:select.option>
                    <flux:select.option value="pendiente">Pendiente</flux:select.option>
                    <flux:select.option value="ingresada">En Recepción</flux:select.option>
                    <flux:select.option value="realizada">Realizada</flux:select.option>
                    <flux:select.option value="cancelada">Cancelada</flux:select.option>
                </flux:select>
            </flux:field>
        </div>

    </flux:card>

    <!-- Data Table -->
    <flux:card class="overflow-hidden shadow-sm">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Fecha y Hora</flux:table.column>
                <flux:table.column>Estudiante</flux:table.column>
                <flux:table.column>Profesor a cargo</flux:table.column>
                <flux:table.column>Motivo</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column class="text-right">Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($entrevistas as $entrevista)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar class="size-4 text-zinc-400" />
                                <span
                                    class="font-semibold text-zinc-800 dark:text-zinc-200">{{ \Carbon\Carbon::parse($entrevista->fecha)->translatedFormat('d M, Y') }}</span>
                            </div>
                            <p class="text-xs text-zinc-500 ml-6">
                                {{ \Carbon\Carbon::parse($entrevista->hora)->format('H:i') }} hrs</p>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <div
                                    class="size-8 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center text-xs font-bold text-blue-700 dark:text-blue-300">
                                    {{ substr($entrevista->estudiante->nombres ?? '?', 0, 1) }}{{ substr($entrevista->estudiante->apellido_pat ?? '?', 0, 1) }}
                                </div>
                                <div>
                                    <span
                                        class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $entrevista->estudiante->nombreCompleto() ?? '-' }}</span>
                                    <p class="text-[10px] text-zinc-500">
                                        {{ $entrevista->estudiante->curso?->nombreCompleto() ?? 'Sin Curso' }}</p>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $entrevista->user?->nombres }} {{ $entrevista->user?->apellido_pat }}
                            </p>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm" class="uppercase text-[10px]">
                                {{ $entrevista->motivo ?? 'General' }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($entrevista->estado === 'realizada')
                                <flux:badge color="emerald" size="sm" icon="check-circle">Realizada</flux:badge>
                            @elseif($entrevista->estado === 'ingresada')
                                <flux:badge color="blue" size="sm">En Recepción</flux:badge>
                            @elseif($entrevista->estado === 'pendiente')
                                <flux:badge color="amber" size="sm" icon="clock">Pendiente</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ ucfirst($entrevista->estado) }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:button size="sm" variant="subtle"
                                href="{{ route('entrevistas.bitacora', $entrevista->id) }}">Ver Bitácora</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="py-12 text-center text-zinc-500">
                                <flux:icon.magnifying-glass class="size-8 mx-auto opacity-50 mb-3" />
                                <p>No se encontraron entrevistas con los filtros seleccionados.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="p-4 bg-zinc-50 dark:bg-zinc-800/20 border-t border-zinc-200 dark:border-zinc-700">
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
                <p class="text-[10px] uppercase tracking-widest font-bold text-zinc-500 dark:text-zinc-400">Pendientes</p>
                <h3 class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $pendientesMes }}</h3>
            </div>
        </flux:card>

        <flux:card class="border-l-4 border-l-red-500 flex items-center gap-4">
            <div class="bg-red-100 dark:bg-red-500/10 p-3 rounded-lg text-red-600 dark:text-red-400">
                <flux:icon.x-circle class="size-8" />
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest font-bold text-zinc-500 dark:text-zinc-400">No Realizadas</p>
                <h3 class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ $canceladasMes }}</h3>
            </div>
        </flux:card>
    </div>
</div>
