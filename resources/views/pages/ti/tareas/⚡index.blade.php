<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Models\TiTask;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Str;

new #[Title('Tareas de TI')] class extends Component {
    use WithPagination;

    // Vista Principal
    public string $vista = 'activas'; // 'activas' | 'archivadas'

    // Filtros
    public string $search = '';
    public string $frecuenciaTab = 'todas'; // todas, diaria, semanal, semestral, anual, unica
    public string $filtroEstado = 'todos'; // todos, pendiente, en_progreso, vencida
    public string $filtroPrioridad = 'todas';
    public string $filtroCategoria = 'todas';

    // Modal Ver Detalle
    public bool $showModalDetalle = false;
    public ?TiTask $selectedTaskForDetail = null;

    // Modal Crear / Editar
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

    // Modal Completar Tarea
    public bool $showModalCompletar = false;
    public ?int $completingTaskId = null;
    public string $notas_cierre = '';

    public function mount(): void
    {
        $this->fecha_programada = now()->format('Y-m-d');
    }

    public function verDetalle(int $id): void
    {
        $this->selectedTaskForDetail = TiTask::with(['asignado', 'creador'])->findOrFail($id);
        $this->showModalDetalle = true;
    }

    public function updatedVista(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFrecuenciaTab(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    #[\Livewire\Attributes\Computed]
    public function stats(): array
    {
        $hoy = now()->toDateString();
        $totalPendientes = TiTask::whereIn('estado', ['pendiente', 'en_progreso'])->count();
        $completadasHoy = TiTask::where('estado', 'completada')->whereDate('fecha_completada', $hoy)->count();
        $vencidas = TiTask::whereIn('estado', ['pendiente', 'en_progreso'])
            ->where(function ($q) use ($hoy) {
                $q->where('fecha_vencimiento', '<', $hoy)
                  ->orWhere(function ($q2) use ($hoy) {
                      $q2->whereNull('fecha_vencimiento')->where('fecha_programada', '<', $hoy);
                  });
            })->count();
        $diariasCount = TiTask::where('frecuencia', 'diaria')->whereIn('estado', ['pendiente', 'en_progreso'])->count();
        $totalArchivadas = TiTask::whereIn('estado', ['completada', 'omitida'])->count();

        return [
            'pendientes' => $totalPendientes,
            'completadas_hoy' => $completadasHoy,
            'vencidas' => $vencidas,
            'diarias_pendientes' => $diariasCount,
            'archivadas' => $totalArchivadas,
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
        $hoy = now()->toDateString();

        return TiTask::query()
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
            ->when($this->frecuenciaTab !== 'todas', function ($q) {
                $q->where('frecuencia', $this->frecuenciaTab);
            })
            ->when($this->filtroPrioridad !== 'todas', function ($q) {
                $q->where('prioridad', $this->filtroPrioridad);
            })
            ->when($this->filtroCategoria !== 'todas', function ($q) {
                $q->where('categoria', $this->filtroCategoria);
            })
            ->when($this->vista === 'activas', function ($q) use ($hoy) {
                if ($this->filtroEstado === 'vencida') {
                    $q->whereIn('estado', ['pendiente', 'en_progreso'])
                      ->where(function ($sub) use ($hoy) {
                          $sub->where('fecha_vencimiento', '<', $hoy)
                              ->orWhere(function ($q2) use ($hoy) {
                                  $q2->whereNull('fecha_vencimiento')->where('fecha_programada', '<', $hoy);
                              });
                      });
                } elseif ($this->filtroEstado !== 'todos') {
                    $q->where('estado', $this->filtroEstado);
                } else {
                    $q->whereIn('estado', ['pendiente', 'en_progreso']);
                }
            })
            ->when($this->vista === 'archivadas', function ($q) {
                $q->whereIn('estado', ['completada', 'omitida']);
            })
            ->when($this->vista === 'activas', function ($q) {
                $q->orderByRaw("CASE WHEN estado = 'en_progreso' THEN 1 WHEN estado = 'pendiente' THEN 2 ELSE 3 END")
                  ->orderBy('fecha_programada', 'asc')
                  ->orderBy('id', 'desc');
            })
            ->when($this->vista === 'archivadas', function ($q) {
                $q->orderBy('fecha_completada', 'desc')
                  ->orderBy('id', 'desc');
            })
            ->paginate(30);
    }

    public function abrirModalCrear(): void
    {
        $this->resetValidation();
        $this->editingTaskId = null;
        $this->titulo = '';
        $this->descripcion = '';
        $this->frecuencia = 'diaria';
        $this->prioridad = 'media';
        $this->categoria = 'Soporte';
        $this->fecha_programada = now()->format('Y-m-d');
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
        $this->fecha_programada = $task->fecha_programada ? $task->fecha_programada->format('Y-m-d') : now()->format('Y-m-d');
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

    public function cambiarEstado(int $id, string $nuevoEstado): void
    {
        $task = TiTask::findOrFail($id);

        if ($nuevoEstado === 'completada') {
            $this->abrirModalCompletar($id);
            return;
        }

        $task->update([
            'estado' => $nuevoEstado,
            'fecha_completada' => null,
        ]);

        Flux::toast('Estado de la tarea actualizado.');
    }

    public function abrirModalCompletar(int $id): void
    {
        $this->resetValidation();
        $task = TiTask::findOrFail($id);
        $this->completingTaskId = $task->id;
        $this->notas_cierre = $task->notas_cierre ?? '';
        $this->showModalCompletar = true;
    }

    public function guardarAvance(): void
    {
        if (! $this->completingTaskId) {
            return;
        }

        $task = TiTask::findOrFail($this->completingTaskId);
        $task->update([
            'notas_cierre' => $this->notas_cierre,
            'estado' => $task->estado === 'pendiente' ? 'en_progreso' : $task->estado,
        ]);

        Flux::toast('Avance guardado correctamente.');
        $this->showModalCompletar = false;
        $this->completingTaskId = null;
    }

    public function confirmarCompletar(): void
    {
        if (! $this->completingTaskId) {
            return;
        }

        $this->validate([
            'notas_cierre' => 'required|string|min:1',
        ], [
            'notas_cierre.required' => 'Es obligatorio escribir un mensaje u observación antes de finalizar (ej: OK).',
        ]);

        $task = TiTask::findOrFail($this->completingTaskId);
        $siguiente = $task->completar($this->notas_cierre);

        $msg = 'Tarea archivada como completada.';
        if ($siguiente) {
            $msg .= ' Se programó la siguiente recurrencia para el ' . $siguiente->fecha_programada->format('d/m/Y') . '.';
        }

        Flux::toast($msg);
        $this->showModalCompletar = false;
        $this->completingTaskId = null;
    }

    public function reabrirTarea(int $id): void
    {
        $task = TiTask::findOrFail($id);
        $task->update([
            'estado' => 'pendiente',
            'fecha_completada' => null,
        ]);

        Flux::toast('Tarea reabierta y movida a Activas.');
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
    <div>
        <flux:heading size="xl" level="1">Gestión de Tareas de TI</flux:heading>
        <flux:subheading size="lg">Administra las tareas técnicas periódicas y realiza seguimiento del trabajo del equipo</flux:subheading>
    </div>

    <!-- Selector de Modo de Vista (Activas vs Archivadas) -->
    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
        <div class="flex items-center gap-2 p-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
            <button
                wire:click="$set('vista', 'activas')"
                class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $vista === 'activas' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-sm font-semibold' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white' }}"
            >
                <flux:icon name="clipboard-document-list" class="size-4" />
                <span>Tareas Activas</span>
                <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold">
                    {{ $this->stats['pendientes'] }}
                </span>
            </button>

            <button
                wire:click="$set('vista', 'archivadas')"
                class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $vista === 'archivadas' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-sm font-semibold' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white' }}"
            >
                <flux:icon name="archive-box" class="size-4" />
                <span>Archivadas / Listas</span>
                <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-bold">
                    {{ $this->stats['archivadas'] }}
                </span>
            </button>
        </div>

        @if($vista === 'activas')
            <div class="hidden sm:flex items-center gap-3 text-xs text-zinc-500">
                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> {{ $this->stats['completadas_hoy'] }} listas hoy</span>
                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500"></span> {{ $this->stats['vencidas'] }} vencidas</span>
            </div>
        @endif
    </div>

    <!-- Navegación por Pestañas de Frecuencia -->
    <div class="border-b border-zinc-200 dark:border-zinc-800">
        <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
            @php
                $tabs = [
                    'todas' => 'Todas las Frecuencias',
                    'diaria' => '⚡ Diarias',
                    'semanal' => '📅 Semanales',
                    'semestral' => '🏛️ Semestrales',
                    'anual' => '🎯 Anuales',
                    'unica' => '📌 Puntuales',
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

    <!-- Barra de Filtros + Botón Nueva Tarea (Integrado) -->
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between bg-zinc-50 dark:bg-zinc-900/50 p-3 rounded-xl border border-zinc-200 dark:border-zinc-800">
        <div class="flex-1 w-full lg:max-w-md">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar por título, descripción o notas..." icon="magnifying-glass" />
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
            @if($vista === 'activas')
                <flux:select wire:model.live="filtroEstado" class="w-full sm:w-36">
                    <option value="todos">Estado: Todos</option>
                    <option value="pendiente">Pendientes</option>
                    <option value="en_progreso">En Progreso</option>
                    <option value="vencida">⚠️ Vencidas</option>
                </flux:select>
            @endif

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

    <!-- Listado / Tabla de Tareas (Prioridad visual superior en mobile) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm">
        @if($this->tareas->isEmpty())
            <div class="py-12 text-center">
                <flux:icon name="{{ $vista === 'activas' ? 'check-badge' : 'archive-box' }}" class="size-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                <h3 class="text-base font-semibold text-zinc-800 dark:text-zinc-200">
                    {{ $vista === 'activas' ? 'No hay tareas activas' : 'No hay tareas archivadas en el historial' }}
                </h3>
                <p class="text-sm text-zinc-500 mt-1">
                    {{ $vista === 'activas' ? 'No tienes tareas pendientes para mostrar con los filtros seleccionados.' : 'Las tareas que vayas completando o archivando aparecerán en esta sección.' }}
                </p>
                @if($vista === 'activas')
                    <div class="mt-4">
                        <flux:button size="sm" variant="outline" wire:click="abrirModalCrear">Crear Primera Tarea</flux:button>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/50 text-zinc-500 font-medium">
                            <th class="py-3 px-4 w-12 text-center">Acción</th>
                            <th class="py-3 px-4">Tarea</th>
                            <th class="py-3 px-4">Frecuencia</th>
                            <th class="py-3 px-4">Prioridad</th>
                            <th class="py-3 px-4">{{ $vista === 'activas' ? 'Fecha Programada' : 'Fecha Completada' }}</th>
                            <th class="py-3 px-4">Asignado a</th>
                            <th class="py-3 px-4 text-right">Opciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach($this->tareas as $t)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition-colors {{ $t->es_vencida && $vista === 'activas' ? 'bg-rose-50/30 dark:bg-rose-950/20' : '' }}">
                                <!-- Checkbox / Botón de Acción rápida -->
                                <td class="py-3 px-4 text-center">
                                    @if($vista === 'archivadas' || $t->estado === 'completada')
                                        <button wire:click="reabrirTarea({{ $t->id }})" title="Reabrir y mover a activas" class="text-emerald-600 dark:text-emerald-400 hover:opacity-80 transition-opacity p-1">
                                            <flux:icon name="check-circle" class="size-6" variant="solid" />
                                        </button>
                                    @else
                                        <button wire:click="abrirModalCompletar({{ $t->id }})" title="Completar y archivar" class="text-zinc-300 hover:text-emerald-500 dark:text-zinc-600 dark:hover:text-emerald-400 transition-colors p-1">
                                            <flux:icon name="clock" class="size-6" />
                                        </button>
                                    @endif
                                </td>

                                <!-- Título y Descripción -->
                                <td class="py-3 px-4">
                                    <button wire:click="verDetalle({{ $t->id }})" title="Ver detalle completo" class="font-semibold text-zinc-900 dark:text-zinc-100 hover:text-primary-600 dark:hover:text-primary-400 hover:underline text-left transition-colors cursor-pointer block {{ $vista === 'archivadas' ? 'text-zinc-600 dark:text-zinc-300' : '' }}">
                                        {{ $t->titulo }}
                                    </button>
                                    @if($t->descripcion)
                                        <div class="text-xs text-zinc-500 max-w-xs sm:max-w-sm truncate mt-0.5" title="{{ $t->descripcion }}">
                                            {{ Str::limit($t->descripcion, 50) }}
                                        </div>
                                    @endif

                                    <!-- Muestra notas de avance en activas o notas de cierre en archivadas -->
                                    @if($vista === 'activas' && $t->notas_cierre)
                                        <div class="mt-1 text-xs p-1.5 rounded bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 text-blue-800 dark:text-blue-300 italic">
                                            📝 <strong>Avance:</strong> {{ $t->notas_cierre }}
                                        </div>
                                    @elseif($vista === 'archivadas' && $t->notas_cierre)
                                        <div class="mt-1 text-xs p-1.5 rounded bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-800 dark:text-emerald-300 italic">
                                            💬 {{ $t->notas_cierre }}
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-2 mt-1">
                                        @if($t->categoria)
                                            <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-medium">
                                                {{ $t->categoria }}
                                            </span>
                                        @endif
                                        @if($t->es_vencida && $vista === 'activas')
                                            <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 font-semibold">
                                                ⚠️ Vencida
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
                                    @if($vista === 'activas')
                                        <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                            {{ $t->fecha_programada ? $t->fecha_programada->format('d/m/Y') : 'Sin fecha' }}
                                        </div>
                                        @if($t->fecha_vencimiento)
                                            <div class="text-[11px] text-zinc-500">Vence: {{ $t->fecha_vencimiento->format('d/m/Y') }}</div>
                                        @endif
                                    @else
                                        <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                            {{ $t->fecha_completada ? $t->fecha_completada->format('d/m/Y H:i') : 'Sin registro' }}
                                        </div>
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
                                        @if($vista === 'archivadas')
                                            <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="reabrirTarea({{ $t->id }})" title="Reabrir tarea" />
                                        @else
                                            <flux:button size="xs" variant="ghost" icon="document-text" wire:click="abrirModalCompletar({{ $t->id }})" title="Anotar avance / Completar" />
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

    <!-- Tarjetas de estadísticas (Posicionadas debajo de la tabla) -->
    @if($vista === 'activas')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 pt-2">
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Pendientes Totales</span>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ $this->stats['pendientes'] }}</div>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 rounded-lg">
                    <flux:icon name="clipboard-document-list" class="size-6" />
                </div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Completadas Hoy</span>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $this->stats['completadas_hoy'] }}</div>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 rounded-lg">
                    <flux:icon name="check-circle" class="size-6" />
                </div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Tareas Vencidas</span>
                    <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ $this->stats['vencidas'] }}</div>
                </div>
                <div class="p-3 bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 rounded-lg">
                    <flux:icon name="exclamation-triangle" class="size-6" />
                </div>
            </div>

            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Diarias por Hacer</span>
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $this->stats['diarias_pendientes'] }}</div>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 rounded-lg">
                    <flux:icon name="arrow-path" class="size-6" />
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Crear / Editar Tarea (Optimizado para Mobile / iPhone Pro Max) -->
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

    <!-- Modal Avances / Completar Tarea (Optimizado para Mobile) -->
    <flux:modal wire:model="showModalCompletar" class="w-full max-w-md max-h-[90vh] overflow-y-auto p-4 sm:p-6">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Avances y Cierre de Tarea</flux:heading>
                <flux:subheading>Anota el progreso actual o confirma el cierre definitivo de la tarea.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:textarea label="Notas de Cierre / Observaciones *" wire:model="notas_cierre" placeholder="ej. OK, o detalle del trabajo realizado..." rows="4" required class="w-full" />

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    💡 Puedes guardar tus anotaciones con <strong>Guardar Avance</strong> para continuar trabajando en la tarea, o presionar <strong>Confirmar y Archivar</strong> una vez que esté completamente terminada.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-end gap-2 pt-2">
                    <flux:button variant="ghost" class="w-full sm:w-auto" wire:click="$set('showModalCompletar', false)">Cancelar</flux:button>
                    <flux:button variant="outline" class="w-full sm:w-auto" wire:click="guardarAvance">
                        Guardar Avance
                    </flux:button>
                    <flux:button variant="primary" class="w-full sm:w-auto" wire:click="confirmarCompletar" wire:confirm="¿Estás seguro de finalizar y archivar esta tarea? Si es una tarea recurrente, se creará automáticamente el siguiente ciclo.">
                        Confirmar y Archivar
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <!-- Modal Ver Detalle Completo de Tarea (Optimizado para Mobile) -->
    <flux:modal wire:model="showModalDetalle" class="w-full max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
        @if($selectedTaskForDetail)
            <div class="space-y-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <flux:badge size="sm" color="{{ match($selectedTaskForDetail->frecuencia) { 'diaria' => 'sky', 'semanal' => 'indigo', 'semestral' => 'purple', 'anual' => 'amber', default => 'zinc' } }}">
                            {{ ucfirst($selectedTaskForDetail->frecuencia) }}
                        </flux:badge>
                        <flux:badge size="sm" color="{{ match($selectedTaskForDetail->prioridad) { 'critica' => 'red', 'alta' => 'orange', 'media' => 'yellow', default => 'green' } }}">
                            {{ ucfirst($selectedTaskForDetail->prioridad) }}
                        </flux:badge>
                        @if($selectedTaskForDetail->categoria)
                            <span class="text-xs px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 font-medium text-zinc-600 dark:text-zinc-400">
                                {{ $selectedTaskForDetail->categoria }}
                            </span>
                        @endif
                    </div>
                    <flux:heading size="xl">{{ $selectedTaskForDetail->titulo }}</flux:heading>
                </div>

                <div class="space-y-4 text-sm">
                    @if($selectedTaskForDetail->descripcion)
                        <div class="p-3 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700/60">
                            <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider block mb-1">Descripción Completa:</span>
                            <p class="whitespace-pre-line text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed">{{ $selectedTaskForDetail->descripcion }}</p>
                        </div>
                    @endif

                    @if($selectedTaskForDetail->notas_cierre)
                        <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-xl border border-blue-200 dark:border-blue-900 text-blue-900 dark:text-blue-200">
                            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider block mb-1">Avances / Observaciones:</span>
                            <p class="whitespace-pre-line text-sm italic">{{ $selectedTaskForDetail->notas_cierre }}</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <div>
                            <span class="text-zinc-500 block font-medium">Fecha Programada:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedTaskForDetail->fecha_programada ? $selectedTaskForDetail->fecha_programada->format('d/m/Y') : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block font-medium">Fecha Vencimiento:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedTaskForDetail->fecha_vencimiento ? $selectedTaskForDetail->fecha_vencimiento->format('d/m/Y') : 'Sin fecha limite' }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block font-medium">Estado:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200 capitalize">{{ $selectedTaskForDetail->estado }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block font-medium">Asignado a:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedTaskForDetail->asignado ? $selectedTaskForDetail->asignado->nombreCompleto() : 'Sin asignar' }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-end gap-2 pt-2">
                    <flux:button variant="ghost" class="w-full sm:w-auto" wire:click="$set('showModalDetalle', false)">Cerrar</flux:button>
                    @if($selectedTaskForDetail->estado !== 'completada')
                        <flux:button variant="outline" class="w-full sm:w-auto" wire:click="abrirModalCompletar({{ $selectedTaskForDetail->id }}); $set('showModalDetalle', false);">Anotar Avance / Completar</flux:button>
                        <flux:button variant="primary" class="w-full sm:w-auto" wire:click="abrirModalEditar({{ $selectedTaskForDetail->id }}); $set('showModalDetalle', false);">Editar</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
