<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Models\TiTask;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Url;

new #[Title('Tareas de TI')] class extends Component {
    use WithPagination;

    // Fecha Seleccionada vía Calendario (Nullable para tolerar deselección en Flux Calendar)
    #[Url]
    public ?string $fechaSeleccionada = null;

    // Filtros
    public string $search = '';

    #[Url]
    public string $frecuenciaTab = 'diaria'; // diaria, semanal, semestral, anual, unica, todas

    #[Url]
    public string $filtroEstado = 'todos'; // todos, pendiente, completada, vencida

    #[Url]
    public string $filtroPrioridad = 'todas';

    #[Url]
    public string $filtroCategoria = 'todas';

    // Modal Unificado (Información + Avances + Cierre)
    public bool $showModalUnificado = false;
    public bool $showModalConfirmarCierre = false;
    public ?TiTask $selectedTask = null;
    public string $notas_cierre = '';

    // Modal Crear / Editar Tarea
    public bool $showModalTask = false;
    public ?int $editingTaskId = null;
    public string $titulo = '';
    public string $descripcion = '';
    public string $frecuencia = 'diaria';
    public string $prioridad = 'media';
    public string $categoria = 'Soporte';
    public string $fecha_programada = '';
    public ?string $fecha_vencimiento = null;
    public ?int $asignado_a = null;
    public bool $es_recurrente = true;

    public function mount(): void
    {
        if (empty($this->fechaSeleccionada)) {
            $this->fechaSeleccionada = now('America/Santiago')->toDateString();
        }
        $this->fecha_programada = $this->fechaSeleccionada;
        TiTask::generarTareasDelDia(now('America/Santiago'));
    }

    public function getFechaActual(): Carbon
    {
        if (empty($this->fechaSeleccionada)) {
            return now('America/Santiago');
        }

        try {
            return Carbon::parse($this->fechaSeleccionada);
        } catch (\Throwable) {
            return now('America/Santiago');
        }
    }

    public function updatedFechaSeleccionada($value = null): void
    {
        if (empty($value)) {
            $this->fechaSeleccionada = now('America/Santiago')->toDateString();
        } else {
            try {
                $this->fechaSeleccionada = Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                $this->fechaSeleccionada = now('America/Santiago')->toDateString();
            }
        }
        $this->resetPage();
    }

    public function abrirModalUnificado(int $id): void
    {
        $this->resetValidation();
        $this->selectedTask = TiTask::with(['asignado', 'creador'])->findOrFail($id);
        $this->notas_cierre = $this->selectedTask->notas_cierre ?? '';
        $this->showModalUnificado = true;
    }

    // Métodos alias para retrocompatibilidad
    public function verDetalle(int $id): void
    {
        $this->abrirModalUnificado($id);
    }

    public function abrirModalCompletar(int $id): void
    {
        $this->abrirModalUnificado($id);
    }

    public function updatedFrecuenciaTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    #[\Livewire\Attributes\Computed]
    public function tituloPeriodo(): string
    {
        $fecha = $this->getFechaActual();

        if ($this->frecuenciaTab === 'semanal') {
            $inicio = $fecha->copy()->startOfWeek()->format('d M');
            $fin = $fecha->copy()->endOfWeek()->format('d M Y');
            return "Semana del $inicio al $fin";
        } elseif ($this->frecuenciaTab === 'semestral') {
            $semestre = $fecha->month <= 6 ? '1° Semestre' : '2° Semestre';
            return "$semestre " . $fecha->year;
        } elseif ($this->frecuenciaTab === 'anual') {
            return 'Año ' . $fecha->year;
        }

        return ucfirst($fecha->translatedFormat('l d \d\e F, Y'));
    }

    #[\Livewire\Attributes\Computed]
    public function statsPeriodo(): array
    {
        $fecha = $this->getFechaActual();
        $fechaStr = $fecha->toDateString();
        $today = now('America/Santiago')->toDateString();

        $base = TiTask::query();

        if ($this->frecuenciaTab === 'diaria') {
            $base->where('frecuencia', 'diaria')->whereDate('fecha_programada', $fechaStr);
        } elseif ($this->frecuenciaTab === 'semanal') {
            $inicio = $fecha->copy()->startOfWeek()->toDateString();
            $fin = $fecha->copy()->endOfWeek()->toDateString();
            $base->where('frecuencia', 'semanal')->whereBetween('fecha_programada', [$inicio, $fin]);
        } elseif ($this->frecuenciaTab === 'semestral') {
            $mes = $fecha->month;
            $year = $fecha->year;
            $inicio = $mes <= 6 ? "$year-01-01" : "$year-07-01";
            $fin = $mes <= 6 ? "$year-06-30" : "$year-12-31";
            $base->where('frecuencia', 'semestral')->whereBetween('fecha_programada', [$inicio, $fin]);
        } elseif ($this->frecuenciaTab === 'anual') {
            $base->where('frecuencia', 'anual')->whereYear('fecha_programada', $fecha->year);
        } elseif ($this->frecuenciaTab === 'unica') {
            $base->where('frecuencia', 'unica')->whereDate('fecha_programada', $fechaStr);
        } else {
            $base->whereDate('fecha_programada', $fechaStr);
        }

        $all = $base->get();

        return [
            'total' => $all->count(),
            'completadas' => $all->where('estado', 'completada')->count(),
            'pendientes' => $all->whereIn('estado', ['pendiente', 'en_progreso'])->count(),
            'vencidas' => $all->filter(fn ($t) => $t->estado !== 'completada' && $t->fecha_programada && $t->fecha_programada->toDateString() < $today)->count(),
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function usuarios(): \Illuminate\Database\Eloquent\Collection
    {
        return User::orderBy('nombres')->orderBy('apellido_pat')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function tareas()
    {
        $fecha = $this->getFechaActual();
        $fechaStr = $fecha->toDateString();
        $today = now('America/Santiago')->toDateString();

        $query = TiTask::query()
            ->with(['asignado', 'creador'])
            ->when(trim($this->search) !== '', function ($q) {
                $search = trim($this->search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('titulo', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%")
                        ->orWhere('categoria', 'like', "%{$search}%")
                        ->orWhere('notas_cierre', 'like', "%{$search}%");
                });
            })
            ->when($this->filtroPrioridad !== 'todas', fn ($q) => $q->where('prioridad', $this->filtroPrioridad))
            ->when($this->filtroCategoria !== 'todas', fn ($q) => $q->where('categoria', $this->filtroCategoria));

        if ($this->frecuenciaTab === 'diaria') {
            $query->where('frecuencia', 'diaria')
                  ->whereDate('fecha_programada', $fechaStr);
        } elseif ($this->frecuenciaTab === 'semanal') {
            $inicio = $fecha->copy()->startOfWeek()->toDateString();
            $fin = $fecha->copy()->endOfWeek()->toDateString();
            $query->where('frecuencia', 'semanal')
                  ->whereBetween('fecha_programada', [$inicio, $fin]);
        } elseif ($this->frecuenciaTab === 'semestral') {
            $mes = $fecha->month;
            $year = $fecha->year;
            $inicio = $mes <= 6 ? "$year-01-01" : "$year-07-01";
            $fin = $mes <= 6 ? "$year-06-30" : "$year-12-31";
            $query->where('frecuencia', 'semestral')
                  ->whereBetween('fecha_programada', [$inicio, $fin]);
        } elseif ($this->frecuenciaTab === 'anual') {
            $query->where('frecuencia', 'anual')
                  ->whereYear('fecha_programada', $fecha->year);
        } elseif ($this->frecuenciaTab === 'unica') {
            $query->where('frecuencia', 'unica')
                  ->whereDate('fecha_programada', $fechaStr);
        } else {
            $query->whereDate('fecha_programada', $fechaStr);
        }

        if ($this->filtroEstado === 'completada') {
            $query->where('estado', 'completada');
        } elseif ($this->filtroEstado === 'pendiente') {
            $query->whereIn('estado', ['pendiente', 'en_progreso']);
        } elseif ($this->filtroEstado === 'vencida') {
            $query->whereIn('estado', ['pendiente', 'en_progreso'])
                  ->whereDate('fecha_programada', '<', $today);
        }

        return $query
            ->orderByRaw("CASE WHEN estado = 'en_progreso' THEN 1 WHEN estado = 'pendiente' THEN 2 WHEN estado = 'completada' THEN 3 ELSE 4 END")
            ->orderBy('fecha_programada', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(30);
    }

    public function abrirModalCrear(): void
    {
        $this->resetValidation();
        $this->editingTaskId = null;
        $this->titulo = '';
        $this->descripcion = '';
        $this->frecuencia = $this->frecuenciaTab !== 'todas' ? $this->frecuenciaTab : 'diaria';
        $this->prioridad = 'media';
        $this->categoria = 'Soporte';
        $this->fecha_programada = $this->fechaSeleccionada;
        $this->fecha_vencimiento = null;
        $this->asignado_a = auth()->id();
        $this->es_recurrente = true;
        $this->showModalTask = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $this->resetValidation();
        $task = TiTask::findOrFail($id);
        $this->editingTaskId = $task->id;
        $this->titulo = $task->titulo;
        $this->descripcion = $task->descripcion ?? '';
        $this->frecuencia = $task->frecuencia;
        $this->prioridad = $task->prioridad;
        $this->categoria = $task->categoria ?? 'Soporte';
        $this->fecha_programada = $task->fecha_programada ? $task->fecha_programada->format('Y-m-d') : $this->fechaSeleccionada;
        $this->fecha_vencimiento = $task->fecha_vencimiento ? $task->fecha_vencimiento->format('Y-m-d') : null;
        $this->asignado_a = $task->asignado_a;
        $this->es_recurrente = $task->es_recurrente;
        $this->showModalTask = true;
    }

    public function guardarTarea(): void
    {
        $this->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'frecuencia' => 'required|in:diaria,semanal,semestral,anual,unica',
            'prioridad' => 'required|in:baja,media,alta,critica',
            'categoria' => 'nullable|string|max:50',
            'fecha_programada' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_programada',
            'asignado_a' => 'nullable|exists:users,id',
            'es_recurrente' => 'boolean',
        ]);

        if ($this->editingTaskId) {
            $task = TiTask::findOrFail($this->editingTaskId);
            $task->update([
                'titulo' => $this->titulo,
                'descripcion' => $this->descripcion,
                'frecuencia' => $this->frecuencia,
                'prioridad' => $this->prioridad,
                'categoria' => $this->categoria,
                'fecha_programada' => $this->fecha_programada,
                'fecha_vencimiento' => $this->fecha_vencimiento,
                'asignado_a' => $this->asignado_a,
                'es_recurrente' => $this->es_recurrente,
            ]);
            Flux::toast('Tarea actualizada correctamente.');
        } else {
            TiTask::create([
                'titulo' => $this->titulo,
                'descripcion' => $this->descripcion,
                'frecuencia' => $this->frecuencia,
                'prioridad' => $this->prioridad,
                'categoria' => $this->categoria,
                'estado' => 'pendiente',
                'fecha_programada' => $this->fecha_programada,
                'fecha_vencimiento' => $this->fecha_vencimiento,
                'asignado_a' => $this->asignado_a,
                'creado_por' => auth()->id(),
                'es_recurrente' => $this->frecuencia === 'unica' ? false : $this->es_recurrente,
            ]);
            Flux::toast('Tarea creada exitosamente.');
        }

        $this->showModalTask = false;
    }

    public function guardarAvance(): void
    {
        if (! $this->selectedTask) {
            return;
        }

        $this->selectedTask->update([
            'notas_cierre' => $this->notas_cierre,
            'estado' => $this->selectedTask->estado === 'pendiente' ? 'en_progreso' : $this->selectedTask->estado,
        ]);

        Flux::toast('Avance guardado correctamente.');
        $this->showModalUnificado = false;
    }

    public function solicitarConfirmacionCierre(): void
    {
        $this->validate([
            'notas_cierre' => 'required|string|min:1',
        ], [
            'notas_cierre.required' => 'Es obligatorio escribir un mensaje u observación antes de cerrar la tarea (ej: OK).',
        ]);

        $this->showModalConfirmarCierre = true;
    }

    public function confirmarCierreDefinitivo(): void
    {
        if (! $this->selectedTask) {
            return;
        }

        $siguiente = $this->selectedTask->completar($this->notas_cierre);

        $msg = 'Tarea marcada como completada.';
        if ($siguiente) {
            $msg .= ' Se programó la siguiente recurrencia para el ' . $siguiente->fecha_programada->format('d/m/Y') . '.';
        }

        Flux::toast($msg);
        $this->showModalConfirmarCierre = false;
        $this->showModalUnificado = false;
        $this->selectedTask = null;
    }

    // Mantener confirmarCompletar para retrocompatibilidad con tests existentes
    public function confirmarCompletar(): void
    {
        if ($this->selectedTask) {
            $this->solicitarConfirmacionCierre();
            $this->confirmarCierreDefinitivo();
        }
    }

    public function reabrirTarea(int $id): void
    {
        $task = TiTask::findOrFail($id);
        $task->update([
            'estado' => 'pendiente',
            'fecha_completada' => null,
        ]);

        Flux::toast('Tarea reabierta correctamente.');
        if ($this->selectedTask && $this->selectedTask->id === $id) {
            $this->showModalUnificado = false;
        }
    }

    public function irAHoy(): void
    {
        $this->fechaSeleccionada = now('America/Santiago')->toDateString();
        TiTask::generarTareasDelDia($this->fechaSeleccionada);
        $this->resetPage();
    }

    public function eliminarTarea(int $id): void
    {
        $task = TiTask::findOrFail($id);
        $task->delete();
        Flux::toast('Tarea eliminada correctamente.');
    }
}; ?>

<div class="space-y-6">
    <!-- Header principal -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">Gestión de Tareas de TI</flux:heading>
            <flux:subheading size="lg">
                {{ $this->tituloPeriodo }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="calendar" wire:click="irAHoy">
                Hoy ({{ now('America/Santiago')->format('d/m') }})
            </flux:button>
        </div>
    </div>

    <!-- Navegación por Pestañas de Frecuencia -->
    <div class="border-b border-zinc-200 dark:border-zinc-800">
        <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
            @php
                $tabs = [
                    'diaria' => '⚡ Diarias',
                    'semanal' => '📅 Semanales',
                    'semestral' => '🏛️ Semestrales',
                    'anual' => '🎯 Anuales',
                    'unica' => '📌 Puntuales',
                    'todas' => 'Todas las Frecuencias',
                ];
            @endphp
            @foreach($tabs as $key => $label)
                <button
                    wire:click="$set('frecuenciaTab', '{{ $key }}')"
                    class="py-2.5 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors duration-150 {{ $frecuenciaTab === $key ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Bento Grid principal (8 Cols Lista / 4 Cols Calendario & Stats) -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">

        <!-- Columna de Tareas y Filtros (8/12) -->
        <div class="xl:col-span-8 space-y-4">
            
            <!-- Barra de Filtros + Botón Nueva Tarea -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between bg-zinc-50 dark:bg-zinc-900/50 p-3 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <div class="flex-1 w-full lg:max-w-md">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar por título, descripción o notas..." icon="magnifying-glass" />
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                    <flux:select wire:model.live="filtroEstado" class="w-full sm:w-36">
                        <option value="todos">Estado: Todos</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="completada">Completadas</option>
                        <option value="vencida">🔴 Vencidas</option>
                    </flux:select>

                    <flux:select wire:model.live="filtroPrioridad" class="w-full sm:w-36">
                        <option value="todas">Prioridad: Todas</option>
                        <option value="critica">🔴 Crítica</option>
                        <option value="alta">🟠 Alta</option>
                        <option value="media">🟡 Media</option>
                        <option value="baja">🟢 Baja</option>
                    </flux:select>

                    <flux:select wire:model.live="filtroCategoria" class="w-full sm:w-40">
                        <option value="todas">Categoría: Todas</option>
                        <option value="Servidores">Servidores</option>
                        <option value="Redes">Redes</option>
                        <option value="Equipos/Salas">Equipos/Salas</option>
                        <option value="Soporte">Soporte</option>
                        <option value="Mantenimiento">Mantenimiento</option>
                        <option value="Respaldos">Respaldos</option>
                        <option value="Licencias">Licencias</option>
                    </flux:select>

                    <flux:button variant="primary" icon="plus" wire:click="abrirModalCrear" class="w-full sm:w-auto shrink-0">
                        Nueva Tarea
                    </flux:button>
                </div>
            </div>

            <!-- Listado / Tabla de Tareas -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm">
                @if($this->tareas->isEmpty())
                    <div class="py-12 text-center">
                        <flux:icon name="calendar" class="size-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                        <h3 class="text-base font-semibold text-zinc-800 dark:text-zinc-200">
                            No hay tareas programadas para este periodo
                        </h3>
                        <p class="text-sm text-zinc-500 mt-1">
                            Selecciona otro día en el calendario o crea una nueva tarea.
                        </p>
                        <div class="mt-4">
                            <flux:button size="sm" variant="outline" wire:click="abrirModalCrear">Crear Tarea para este Día</flux:button>
                        </div>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/50 text-zinc-500 font-medium">
                                    <th class="py-3 px-4 w-12 text-center">Estado</th>
                                    <th class="py-3 px-4">Tarea</th>
                                    <th class="py-3 px-4">Frecuencia</th>
                                    <th class="py-3 px-4">Prioridad</th>
                                    <th class="py-3 px-4">Fecha Programada</th>
                                    <th class="py-3 px-4">Asignado a</th>
                                    <th class="py-3 px-4 text-right">Opciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @php $todayStr = now('America/Santiago')->toDateString(); @endphp
                                @foreach($this->tareas as $t)
                                    @php
                                        $esCompletada = $t->estado === 'completada';
                                        $esVencida = !$esCompletada && $t->fecha_programada && $t->fecha_programada->toDateString() < $todayStr;
                                    @endphp
                                    <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition-colors {{ $esVencida ? 'bg-rose-50/40 dark:bg-rose-950/20' : '' }}">
                                        <!-- Botón de Estado / Ticket Verde (Abre Modal Unificado) -->
                                        <td class="py-3 px-4 text-center">
                                            @if($esCompletada)
                                                <button wire:click="abrirModalUnificado({{ $t->id }})" title="Tarea completada (Clic para ver detalle)" class="text-emerald-600 dark:text-emerald-400 hover:opacity-80 transition-opacity p-1 cursor-pointer">
                                                    <flux:icon name="check-circle" class="size-6" variant="solid" />
                                                </button>
                                            @else
                                                <button wire:click="abrirModalUnificado({{ $t->id }})" title="{{ $esVencida ? 'Tarea vencida (Clic para registrar avance/cierre)' : 'Abrir tarea' }}" class="{{ $esVencida ? 'text-rose-500 hover:text-rose-600' : 'text-zinc-300 hover:text-emerald-500 dark:text-zinc-600 dark:hover:text-emerald-400' }} transition-colors p-1 cursor-pointer">
                                                    <flux:icon name="{{ $esVencida ? 'exclamation-circle' : 'clock' }}" class="size-6" />
                                                </button>
                                            @endif
                                        </td>

                                        <!-- Título y Descripción -->
                                        <td class="py-3 px-4">
                                            <button wire:click="abrirModalUnificado({{ $t->id }})" title="Ver información y avances" class="font-semibold text-zinc-900 dark:text-zinc-100 hover:text-primary-600 dark:hover:text-primary-400 hover:underline text-left transition-colors cursor-pointer block {{ $esCompletada ? 'text-zinc-600 dark:text-zinc-400 line-through' : '' }}">
                                                {{ $t->titulo }}
                                            </button>
                                            @if($t->descripcion)
                                                <div class="text-xs text-zinc-500 max-w-xs sm:max-w-sm truncate mt-0.5" title="{{ $t->descripcion }}">
                                                    {{ Str::limit($t->descripcion, 50) }}
                                                </div>
                                            @endif

                                            <!-- Muestra notas de avance / cierre -->
                                            @if($t->notas_cierre)
                                                <div class="mt-1 text-xs p-1.5 rounded {{ $esCompletada ? 'bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-800 dark:text-emerald-300' : 'bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 text-blue-800 dark:text-blue-300' }} italic">
                                                    {{ $esCompletada ? '💬' : '📝' }} <strong>{{ $esCompletada ? 'Cierre:' : 'Avance:' }}</strong> {{ $t->notas_cierre }}
                                                </div>
                                            @endif

                                            <div class="flex items-center gap-2 mt-1">
                                                @if($t->categoria)
                                                    <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-medium">
                                                        {{ $t->categoria }}
                                                    </span>
                                                @endif
                                                @if($esVencida)
                                                    <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 font-semibold">
                                                        🔴 Vencida
                                                    </span>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Frecuencia -->
                                        <td class="py-3 px-4">
                                            @php
                                                $frecColors = [
                                                    'diaria' => 'sky',
                                                    'semanal' => 'indigo',
                                                    'semestral' => 'purple',
                                                    'anual' => 'amber',
                                                    'unica' => 'zinc',
                                                ];
                                                $color = $frecColors[$t->frecuencia] ?? 'zinc';
                                            @endphp
                                            <flux:badge size="sm" color="{{ $color }}">
                                                {{ ucfirst($t->frecuencia) }}
                                            </flux:badge>
                                        </td>

                                        <!-- Prioridad -->
                                        <td class="py-3 px-4">
                                            @php
                                                $prioColors = [
                                                    'critica' => 'red',
                                                    'alta' => 'orange',
                                                    'media' => 'yellow',
                                                    'baja' => 'green',
                                                ];
                                            @endphp
                                            <flux:badge size="sm" color="{{ $prioColors[$t->prioridad] ?? 'zinc' }}">
                                                {{ ucfirst($t->prioridad) }}
                                            </flux:badge>
                                        </td>

                                        <!-- Fechas -->
                                        <td class="py-3 px-4">
                                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                                {{ $t->fecha_programada ? $t->fecha_programada->format('d/m/Y') : 'Sin fecha' }}
                                            </div>
                                            @if($t->fecha_vencimiento)
                                                <div class="text-[11px] text-zinc-500">Vence: {{ $t->fecha_vencimiento->format('d/m/Y') }}</div>
                                            @endif
                                            @if($t->fecha_completada)
                                                <div class="text-[11px] text-emerald-600 dark:text-emerald-400">Listo: {{ $t->fecha_completada->format('d/m H:i') }}</div>
                                            @endif
                                        </td>

                                        <!-- Asignado -->
                                        <td class="py-3 px-4">
                                            @if($t->asignado)
                                                <div class="flex items-center gap-1.5">
                                                    <div class="size-6 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300 flex items-center justify-center text-[10px] font-bold">
                                                        {{ $t->asignado->initials() }}
                                                    </div>
                                                    <span class="text-xs text-zinc-700 dark:text-zinc-300 truncate max-w-[120px]">{{ $t->asignado->nombreCompleto() }}</span>
                                                </div>
                                            @else
                                                <span class="text-xs text-zinc-400 italic">Sin asignar</span>
                                            @endif
                                        </td>

                                        <!-- Opciones -->
                                        <td class="py-3 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                @if($esCompletada)
                                                    <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="reabrirTarea({{ $t->id }})" title="Reabrir tarea" />
                                                @else
                                                    <flux:button size="xs" variant="ghost" icon="document-text" wire:click="abrirModalUnificado({{ $t->id }})" title="Ver detalle / Anotar avance" />
                                                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="abrirModalEditar({{ $t->id }})" title="Editar tarea" />
                                                @endif
                                                <flux:button size="xs" variant="ghost" icon="trash" class="text-rose-600 hover:text-rose-700 dark:text-rose-400" wire:click="eliminarTarea({{ $t->id }})" wire:confirm="¿Seguro que deseas eliminar esta tarea?" title="Eliminar tarea" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                        {{ $this->tareas->links() }}
                    </div>
                @endif
            </div>

        </div>

        <!-- Columna Lateral del Calendario (4/12) -->
        <div class="xl:col-span-4 space-y-4">
            
            <!-- Calendario Interactivo con Flux -->
            <flux:card class="bg-white dark:bg-zinc-900 shadow-sm p-4">
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                    <flux:icon name="calendar" class="size-4 text-primary-600 dark:text-primary-400" />
                    <span>Seleccionar Fecha</span>
                </div>
                <flux:calendar wire:model.live.debounce.250ms="fechaSeleccionada" />
            </flux:card>

            <!-- Tarjetas de Estadísticas del Periodo Seleccionado -->
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
                    <span class="text-[11px] font-medium text-zinc-500 uppercase">Total Periodo</span>
                    <div class="text-xl font-bold text-zinc-900 dark:text-white mt-0.5">{{ $this->statsPeriodo['total'] }}</div>
                </div>

                <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
                    <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 uppercase">Completadas</span>
                    <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $this->statsPeriodo['completadas'] }}</div>
                </div>

                <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
                    <span class="text-[11px] font-medium text-blue-600 dark:text-blue-400 uppercase">Pendientes</span>
                    <div class="text-xl font-bold text-blue-600 dark:text-blue-400 mt-0.5">{{ $this->statsPeriodo['pendientes'] }}</div>
                </div>

                <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
                    <span class="text-[11px] font-medium text-rose-600 dark:text-rose-400 uppercase">Vencidas</span>
                    <div class="text-xl font-bold text-rose-600 dark:text-rose-400 mt-0.5">{{ $this->statsPeriodo['vencidas'] }}</div>
                </div>
            </div>

        </div>

    </div>

    <!-- Modal Crear / Editar Tarea -->
    <flux:modal wire:model="showModalTask" class="w-full max-w-xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingTaskId ? 'Editar Tarea de TI' : 'Nueva Tarea de TI' }}</flux:heading>
                <flux:subheading>Define los detalles de la tarea técnica y su frecuencia de ejecución.</flux:subheading>
            </div>

            <form wire:submit="guardarTarea" class="space-y-4">
                <flux:input label="Título de la Tarea" wire:model="titulo" placeholder="ej. Revisión de respaldos en Servidor NAS" required class="w-full" />

                <flux:textarea label="Descripción u Observaciones" wire:model="descripcion" placeholder="Detalla los pasos o requerimientos específicos..." rows="3" class="w-full" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:select label="Frecuencia" wire:model="frecuencia" class="w-full">
                        <option value="diaria">⚡ Diaria</option>
                        <option value="semanal">📅 Semanal</option>
                        <option value="semestral">🏛️ Semestral</option>
                        <option value="anual">🎯 Anual</option>
                        <option value="unica">📌 Puntual (Única)</option>
                    </flux:select>

                    <flux:select label="Prioridad" wire:model="prioridad" class="w-full">
                        <option value="baja">🟢 Baja</option>
                        <option value="media">🟡 Media</option>
                        <option value="alta">🟠 Alta</option>
                        <option value="critica">🔴 Crítica</option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:select label="Categoría" wire:model="categoria" class="w-full">
                        <option value="Servidores">Servidores</option>
                        <option value="Redes">Redes / Wi-Fi</option>
                        <option value="Equipos/Salas">Equipos & Salas de Computación</option>
                        <option value="Soporte">Soporte a Usuarios / Docentes</option>
                        <option value="Mantenimiento">Mantenimiento Preventivo</option>
                        <option value="Respaldos">Respaldos & Seguridad</option>
                        <option value="Licencias">Licencias & Software</option>
                    </flux:select>

                    <flux:select label="Responsable Asignado" wire:model="asignado_a" class="w-full">
                        <option value="">Sin asignar</option>
                        @foreach($this->usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->nombreCompleto() ?: $u->email }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input type="date" label="Fecha Programada" wire:model="fecha_programada" required class="w-full" />
                    <flux:input type="date" label="Fecha Vencimiento (Opcional)" wire:model="fecha_vencimiento" class="w-full" />
                </div>

                @if($frecuencia !== 'unica')
                    <div class="pt-2">
                        <flux:checkbox label="Generar automáticamente la siguiente tarea al marcarla como completada" wire:model="es_recurrente" />
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row items-center justify-end gap-2 pt-4">
                    <flux:button variant="ghost" class="w-full sm:w-auto" wire:click="$set('showModalTask', false)">Cancelar</flux:button>
                    <flux:button variant="primary" class="w-full sm:w-auto" type="submit">Guardar Tarea</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal Unificado (Información Completa + Avances + Cierre) -->
    <flux:modal wire:model="showModalUnificado" class="w-full max-w-xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
        @if($selectedTask)
            <div class="space-y-5">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <flux:badge size="sm" color="{{ match($selectedTask->frecuencia) { 'diaria' => 'sky', 'semanal' => 'indigo', 'semestral' => 'purple', 'anual' => 'amber', default => 'zinc' } }}">
                            {{ ucfirst($selectedTask->frecuencia) }}
                        </flux:badge>
                        <flux:badge size="sm" color="{{ match($selectedTask->prioridad) { 'critica' => 'red', 'alta' => 'orange', 'media' => 'yellow', default => 'green' } }}">
                            {{ ucfirst($selectedTask->prioridad) }}
                        </flux:badge>
                        @if($selectedTask->categoria)
                            <span class="text-xs px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 font-medium text-zinc-600 dark:text-zinc-400">
                                {{ $selectedTask->categoria }}
                            </span>
                        @endif
                    </div>
                    <flux:heading size="xl">{{ $selectedTask->titulo }}</flux:heading>
                </div>

                <!-- Información de la Tarea -->
                <div class="space-y-3 text-sm">
                    @if($selectedTask->descripcion)
                        <div class="p-3.5 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700/60">
                            <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider block mb-1">Descripción Completa:</span>
                            <p class="whitespace-pre-line text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed">{{ $selectedTask->descripcion }}</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <div>
                            <span class="text-zinc-500 block font-medium">Fecha Programada:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedTask->fecha_programada ? $selectedTask->fecha_programada->format('d/m/Y') : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block font-medium">Fecha Vencimiento:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedTask->fecha_vencimiento ? $selectedTask->fecha_vencimiento->format('d/m/Y') : 'Sin fecha limite' }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block font-medium">Estado:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200 capitalize">{{ $selectedTask->estado }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block font-medium">Asignado a:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedTask->asignado ? $selectedTask->asignado->nombreCompleto() : 'Sin asignar' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Cuadro de Texto de Avances y Notas de Cierre -->
                <div class="pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:textarea
                        label="Notas de Avance / Observaciones {{ $selectedTask->estado !== 'completada' ? '*' : '' }}"
                        wire:model="notas_cierre"
                        placeholder="Escribe aquí tus observaciones o el detalle del trabajo realizado..."
                        rows="3"
                        class="w-full"
                        :disabled="$selectedTask->estado === 'completada'"
                    />
                </div>

                <!-- Botones del Modal -->
                <div class="flex flex-col sm:flex-row items-center justify-end gap-2 pt-2">
                    <flux:button variant="ghost" class="w-full sm:w-auto" wire:click="$set('showModalUnificado', false)">
                        Cancelar
                    </flux:button>

                    @if($selectedTask->estado !== 'completada')
                        <flux:button variant="outline" class="w-full sm:w-auto" wire:click="guardarAvance">
                            Guardar Avance
                        </flux:button>
                        <flux:button variant="primary" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-bold" wire:click="solicitarConfirmacionCierre">
                            Cerrar Tarea
                        </flux:button>
                    @else
                        <flux:button variant="outline" class="w-full sm:w-auto text-amber-600 hover:text-amber-700" wire:click="reabrirTarea({{ $selectedTask->id }})">
                            Reabrir Tarea
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Modal Secundario de Confirmación de Cierre (Componente Flux) -->
    <flux:modal wire:model="showModalConfirmarCierre" class="w-full max-w-md p-6 text-center space-y-4">
        <div class="size-12 mx-auto rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 flex items-center justify-center">
            <flux:icon name="check-circle" class="size-8" variant="solid" />
        </div>

        <div>
            <flux:heading size="lg">¿Confirmar Cierre de Tarea?</flux:heading>
            <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                La tarea se marcará como <strong>Completada</strong>. Si es una tarea recurrente, se programará automáticamente la siguiente instancia.
            </flux:subheading>
        </div>

        <div class="flex items-center justify-center gap-3 pt-2">
            <flux:button variant="ghost" wire:click="$set('showModalConfirmarCierre', false)">
                Cancelar
            </flux:button>
            <flux:button variant="primary" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold" wire:click="confirmarCierreDefinitivo">
                Sí, Cerrar Tarea
            </flux:button>
        </div>
    </flux:modal>
</div>
