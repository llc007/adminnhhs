<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Models\TiTask;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;

new #[Title('Tareas de TI')] class extends Component {
    use WithPagination;

    // Filtros
    public string $search = '';
    public string $frecuenciaTab = 'todas'; // todas, diaria, semanal, semestral, anual, unica
    public string $filtroEstado = 'todos'; // todos, pendiente, en_progreso, completada, vencida
    public string $filtroPrioridad = 'todas';
    public string $filtroCategoria = 'todas';

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

        return [
            'pendientes' => $totalPendientes,
            'completadas_hoy' => $completadasHoy,
            'vencidas' => $vencidas,
            'diarias_pendientes' => $diariasCount,
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function usuarios(): \Illuminate\Database\Eloquent\Collection
    {
        return User::orderBy('name')->get();
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
                        ->orWhere('categoria', 'like', "%{$search}%");
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
            ->when($this->filtroEstado !== 'todos', function ($q) use ($hoy) {
                if ($this->filtroEstado === 'vencida') {
                    $q->whereIn('estado', ['pendiente', 'en_progreso'])
                      ->where(function ($sub) use ($hoy) {
                          $sub->where('fecha_vencimiento', '<', $hoy)
                              ->orWhere(function ($q2) use ($hoy) {
                                  $q2->whereNull('fecha_vencimiento')->where('fecha_programada', '<', $hoy);
                              });
                      });
                } else {
                    $q->where('estado', $this->filtroEstado);
                }
            })
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 1 WHEN estado = 'en_progreso' THEN 2 WHEN estado = 'completada' THEN 3 ELSE 4 END")
            ->orderBy('fecha_programada', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(15);
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
        $this->completingTaskId = $id;
        $this->notas_cierre = '';
        $this->showModalCompletar = true;
    }

    public function confirmarCompletar(): void
    {
        if (! $this->completingTaskId) {
            return;
        }

        $task = TiTask::findOrFail($this->completingTaskId);
        $siguiente = $task->completar($this->notas_cierre);

        $msg = 'Tarea marcada como completada.';
        if ($siguiente) {
            $msg .= ' Se ha programado automáticamente la siguiente recurrencia para el ' . $siguiente->fecha_programada->format('d/m/Y') . '.';
        }

        Flux::toast($msg);
        $this->showModalCompletar = false;
        $this->completingTaskId = null;
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
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">Gestión de Tareas de TI</flux:heading>
            <flux:subheading size="lg">Administra las tareas diarias, semanales, semestrales y anuales del departamento técnico</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="primary" icon="plus" wire:click="abrirModalCrear">
                Nueva Tarea
            </flux:button>
        </div>
    </div>

    <!-- Tarjetas de estadísticas -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

    <!-- Navegación por Pestañas de Frecuencia -->
    <div class="border-b border-zinc-200 dark:border-zinc-800">
        <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
            @php
                $tabs = [
                    'todas' => 'Todas las Tareas',
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
                    class="py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors duration-150 {{ $frecuenciaTab === $key ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Barra de Filtros -->
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between bg-zinc-50 dark:bg-zinc-900/50 p-3 rounded-xl border border-zinc-200 dark:border-zinc-800">
        <div class="flex-1 max-w-md">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar por título, descripción o categoría..." icon="magnifying-glass" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:select wire:model.live="filtroEstado" class="w-40">
                <option value="todos">Estado: Todos</option>
                <option value="pendiente">Pendientes</option>
                <option value="en_progreso">En Progreso</option>
                <option value="completada">Completadas</option>
                <option value="vencida">⚠️ Vencidas</option>
            </flux:select>

            <flux:select wire:model.live="filtroPrioridad" class="w-40">
                <option value="todas">Prioridad: Todas</option>
                <option value="critica">🔴 Crítica</option>
                <option value="alta">🟠 Alta</option>
                <option value="media">🟡 Media</option>
                <option value="baja">🟢 Baja</option>
            </flux:select>

            <flux:select wire:model.live="filtroCategoria" class="w-44">
                <option value="todas">Categoría: Todas</option>
                <option value="Servidores">Servidores</option>
                <option value="Redes">Redes</option>
                <option value="Equipos/Salas">Equipos/Salas</option>
                <option value="Soporte">Soporte</option>
                <option value="Mantenimiento">Mantenimiento</option>
                <option value="Respaldos">Respaldos</option>
                <option value="Licencias">Licencias</option>
            </flux:select>
        </div>
    </div>

    <!-- Listado / Tabla de Tareas -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm">
        @if($this->tareas->isEmpty())
            <div class="py-12 text-center">
                <flux:icon name="check-badge" class="size-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                <h3 class="text-base font-semibold text-zinc-800 dark:text-zinc-200">No hay tareas registradas</h3>
                <p class="text-sm text-zinc-500 mt-1">No se encontraron tareas con los filtros seleccionados.</p>
                <div class="mt-4">
                    <flux:button size="sm" variant="outline" wire:click="abrirModalCrear">Crear Primera Tarea</flux:button>
                </div>
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
                            <th class="py-3 px-4">Fecha Programada</th>
                            <th class="py-3 px-4">Asignado a</th>
                            <th class="py-3 px-4 text-right">Opciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach($this->tareas as $t)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition-colors {{ $t->es_vencida ? 'bg-rose-50/30 dark:bg-rose-950/20' : '' }}">
                                <!-- Checkbox / Botón de Acción rápida -->
                                <td class="py-3 px-4 text-center">
                                    @if($t->estado === 'completada')
                                        <button wire:click="cambiarEstado({{ $t->id }}, 'pendiente')" title="Marcar como pendiente" class="text-emerald-600 dark:text-emerald-400 hover:opacity-80 transition-opacity">
                                            <flux:icon name="check-circle" class="size-6" variant="solid" />
                                        </button>
                                    @else
                                        <button wire:click="abrirModalCompletar({{ $t->id }})" title="Marcar como completada" class="text-zinc-300 hover:text-emerald-500 dark:text-zinc-600 dark:hover:text-emerald-400 transition-colors">
                                            <flux:icon name="clock" class="size-6" />
                                        </button>
                                    @endif
                                </td>

                                <!-- Título y Descripción -->
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 {{ $t->estado === 'completada' ? 'line-through text-zinc-400 dark:text-zinc-500' : '' }}">
                                        {{ $t->titulo }}
                                    </div>
                                    @if($t->descripcion)
                                        <div class="text-xs text-zinc-500 line-clamp-1 mt-0.5">{{ $t->descripcion }}</div>
                                    @endif
                                    <div class="flex items-center gap-2 mt-1">
                                        @if($t->categoria)
                                            <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-medium">
                                                {{ $t->categoria }}
                                            </span>
                                        @endif
                                        @if($t->es_vencida)
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

                                <!-- Fecha Programada -->
                                <td class="py-3 px-4">
                                    <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $t->fecha_programada ? $t->fecha_programada->format('d/m/Y') : 'Sin fecha' }}
                                    </div>
                                    @if($t->fecha_vencimiento)
                                        <div class="text-[11px] text-zinc-500">Vence: {{ $t->fecha_vencimiento->format('d/m/Y') }}</div>
                                    @endif
                                    @if($t->fecha_completada)
                                        <div class="text-[11px] text-emerald-600 dark:text-emerald-400">Listo: {{ $t->fecha_completada->format('d/m/Y H:i') }}</div>
                                    @endif
                                </td>

                                <!-- Asignado -->
                                <td class="py-3 px-4">
                                    @if($t->asignado)
                                        <div class="flex items-center gap-1.5">
                                            <div class="size-6 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300 flex items-center justify-center text-[10px] font-bold">
                                                {{ substr($t->asignado->name, 0, 2) }}
                                            </div>
                                            <span class="text-xs text-zinc-700 dark:text-zinc-300 truncate max-w-[120px]">{{ $t->asignado->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400 italic">Sin asignar</span>
                                    @endif
                                </td>

                                <!-- Opciones -->
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="abrirModalEditar({{ $t->id }})" title="Editar tarea" />
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

    <!-- Modal Crear / Editar Tarea -->
    <flux:modal wire:model="showModalTask" class="w-full max-w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingTaskId ? 'Editar Tarea de TI' : 'Nueva Tarea de TI' }}</flux:heading>
                <flux:subheading>Define los detalles de la tarea técnica y su frecuencia de ejecución.</flux:subheading>
            </div>

            <form wire:submit="guardarTarea" class="space-y-4">
                <flux:input label="Título de la Tarea" wire:model="titulo" placeholder="ej. Revisión de respaldos en Servidor NAS" required />

                <flux:textarea label="Descripción u Observaciones" wire:model="descripcion" placeholder="Detalla los pasos o requerimientos específicos..." rows="3" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:select label="Frecuencia" wire:model="frecuencia">
                        <option value="diaria">⚡ Diaria</option>
                        <option value="semanal">📅 Semanal</option>
                        <option value="semestral">🏛️ Semestral</option>
                        <option value="anual">🎯 Anual</option>
                        <option value="unica">📌 Puntual (Única)</option>
                    </flux:select>

                    <flux:select label="Prioridad" wire:model="prioridad">
                        <option value="baja">🟢 Baja</option>
                        <option value="media">🟡 Media</option>
                        <option value="alta">🟠 Alta</option>
                        <option value="critica">🔴 Crítica</option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:select label="Categoría" wire:model="categoria">
                        <option value="Servidores">Servidores</option>
                        <option value="Redes">Redes / Wi-Fi</option>
                        <option value="Equipos/Salas">Equipos & Salas de Computación</option>
                        <option value="Soporte">Soporte a Usuarios / Docentes</option>
                        <option value="Mantenimiento">Mantenimiento Preventivo</option>
                        <option value="Respaldos">Respaldos & Seguridad</option>
                        <option value="Licencias">Licencias & Software</option>
                    </flux:select>

                    <flux:select label="Responsable Asignado" wire:model="asignado_a">
                        <option value="">Sin asignar</option>
                        @foreach($this->usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input type="date" label="Fecha Programada" wire:model="fecha_programada" required />
                    <flux:input type="date" label="Fecha Vencimiento (Opcional)" wire:model="fecha_vencimiento" />
                </div>

                @if($frecuencia !== 'unica')
                    <div class="pt-2">
                        <flux:checkbox label="Generar automáticamente la siguiente tarea al marcarla como completada" wire:model="es_recurrente" />
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showModalTask', false)">Cancelar</flux:button>
                    <flux:button variant="primary" type="submit">Guardar Tarea</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal Completar Tarea -->
    <flux:modal wire:model="showModalCompletar" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Completar Tarea</flux:heading>
                <flux:subheading>¿Deseas agregar alguna nota de cierre u observación al finalizar?</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:textarea label="Notas de Cierre (Opcional)" wire:model="notas_cierre" placeholder="ej. Todo verificado correctamente, respaldo guardado en volumen secundario." rows="3" />

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    💡 Si la tarea es recurrente, el sistema programará automáticamente el siguiente ciclo.
                </p>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showModalCompletar', false)">Cancelar</flux:button>
                    <flux:button variant="primary" wire:click="confirmarCompletar">Confirmar y Completar</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
