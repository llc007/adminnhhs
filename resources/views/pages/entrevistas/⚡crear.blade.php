<?php

use Livewire\Component;

new class extends Component {
    public string $searchEstudiante = '';
    public ?int $estudianteId = null;

    // Lista de resultados mostrada dinámicamente
    public $resultadosBusqueda = [];

    // Campos de la entrevista
    public string $fecha = '';
    public string $hora = '';
    public string $urgencia = 'normal';
    public string $motivo = '';
    public string $notas = '';

    public function updatedSearchEstudiante()
    {
        if (strlen($this->searchEstudiante) >= 3) {
            $this->resultadosBusqueda = \App\Models\Estudiante::query()
                ->with(['curso'])
                ->where('school_id', auth()->user()->current_school_id)
                ->where(function ($q) {
                    $q->where('nombres_csv', 'like', '%' . $this->searchEstudiante . '%')->orWhere('rut_numero', 'like', '%' . $this->searchEstudiante . '%');
                })
                ->take(5)
                ->get();

            // Si el texto ya no coincide, limpiamos el estudiante seleccionado previamente
            $this->estudianteId = null;
        } else {
            $this->resultadosBusqueda = [];
            $this->estudianteId = null;
        }
    }

    public function seleccionarEstudiante($id)
    {
        $this->estudianteId = $id;
        $estudiante = \App\Models\Estudiante::find($id);

        $this->searchEstudiante = $estudiante ? $estudiante->nombreCompleto() : '';
        $this->resultadosBusqueda = [];
    }

    #[\Livewire\Attributes\Computed]
    public function estudiante()
    {
        if (!$this->estudianteId) {
            return null;
        }
        return \App\Models\Estudiante::with('curso')->find($this->estudianteId);
    }

    public function agendar()
    {
        $this->validate(
            [
                'estudianteId' => ['required'],
                'fecha' => ['required', 'date'],
                'hora' => ['required'],
                'urgencia' => ['required', 'in:normal,prioritario,urgente'],
                'motivo' => ['required', 'string'],
                'notas' => ['nullable', 'string'],
            ],
            [
                'estudianteId.required' => 'Debe seleccionar un estudiante de la lista.',
            ],
        );

        // Por ahora lo simularemos. Luego crearemos la base de datos "Entrevistas"

        // \App\Models\Entrevista::create([...]);

        Flux::toast('Entrevista agendada con éxito.', 'success');

        // Reset form
        $this->reset(['estudianteId', 'searchEstudiante', 'fecha', 'hora', 'urgencia', 'motivo', 'notas']);
    }

    public function mount()
    {
        // Preseleccionar fecha a hoy en zona horaria de Chile
        $this->fecha = now('America/Santiago')->format('Y-m-d');

        // Por defecto iniciar a las 09:00
        $this->hora = '09:00';
    }
};
?>

<div class="flex flex-col gap-8 max-w-4xl mx-auto w-full pb-10">

    {{-- Breadcrumbs + Título --}}
    <div>
        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item icon="calendar-days" href="#" />
            <flux:breadcrumbs.item>{{ __('Gestión Académica') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Agendar Entrevista') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex items-start justify-between">
            <div>
                <flux:heading size="xl" level="1" class="text-[#00376e] dark:text-blue-400">
                    {{ __('Nueva Cita') }}</flux:heading>
                <flux:subheading size="lg" class="max-w-xl">
                    {{ __('Coordina una nueva reunión con un apoderado y agenda el box correspondiente.') }}
                </flux:subheading>
            </div>

            <flux:button variant="ghost" icon="x-mark">
                {{ __('Cancelar') }}
            </flux:button>
        </div>
    </div>

    <form wire:submit="agendar" class="space-y-8">

        {{-- Sección: Información del Estudiante --}}
        <flux:card>
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
                    <flux:icon.user class="size-5" />
                </div>
                <flux:heading size="lg">{{ __('Información del Estudiante') }}</flux:heading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative">
                {{-- Buscador Reactivo --}}
                <div class="relative z-10 w-full">
                    <flux:input wire:model.live.debounce.300ms="searchEstudiante"
                        :label="__('Buscar Estudiante por Nombre o RUT')" icon="magnifying-glass"
                        placeholder="Ej: Marcelo Paz..." autocomplete="off" />

                    {{-- Dropdown de resultados (se sobrepone) --}}
                    @if (count($resultadosBusqueda) > 0)
                        <div
                            class="absolute mt-1 w-full bg-white dark:bg-zinc-800 rounded-md shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden outline-none">
                            <ul class="max-h-60 overflow-y-auto">
                                @foreach ($resultadosBusqueda as $res)
                                    <li>
                                        <button type="button" wire:click="seleccionarEstudiante({{ $res->id }})"
                                            class="w-full text-left px-4 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition flex flex-col items-start gap-1 focus:outline-none focus:bg-zinc-100 dark:focus:bg-zinc-700">
                                            <span
                                                class="font-medium text-sm text-zinc-900 dark:text-white">{{ $res->nombreCompleto() }}</span>
                                            <div class="flex gap-2 items-center text-xs text-zinc-500">
                                                <span>{{ $res->rut_numero ? $res->rutCompleto() : 'Sin RUT' }}</span>
                                                @if ($res->curso)
                                                    <span
                                                        class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">{{ $res->curso->nombreCompleto() }}</span>
                                                @endif
                                            </div>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <flux:error name="estudianteId" />
                </div>

                {{-- Apoderado Auto-completado --}}
                <div class="pt-0 md:pt-0">
                    @if ($this->estudiante)
                        <flux:input :label="__('Apoderado Titular')"
                            value="{{ $this->estudiante->apoderado_nombres ? $this->estudiante->apoderado_nombres . ' ' . $this->estudiante->apoderado_apellido_pat : 'Sin apoderado registrado' }} {{ $this->estudiante->apoderado_parentesco ? '(' . $this->estudiante->apoderado_parentesco . ')' : '' }}"
                            disabled />
                    @else
                        <flux:input :label="__('Apoderado Titular')" placeholder="Primero busque un estudiante..."
                            disabled />
                    @endif
                </div>
            </div>
        </flux:card>

        {{-- Bento Grid: Nivel inferior --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

            {{-- Columna 1: Fecha y Hora (2 columnas de ancho) --}}
            <div class="md:col-span-2 space-y-8">
                <flux:card>
                    <div class="flex items-center gap-3 mb-6">
                        <div
                            class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400">
                            <flux:icon.calendar class="size-5" />
                        </div>
                        <flux:heading size="lg">{{ __('Fecha y Hora') }}</flux:heading>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <flux:date-picker wire:model="fecha" :label="__('Fecha')" with-today />
                        <flux:time-picker wire:model="hora" :label="__('Hora')" min="08:00" max="18:30"
                            interval="15" time-format="24-hour" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center gap-3 mb-6">
                        <div
                            class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg text-emerald-600 dark:text-emerald-400">
                            <flux:icon.chat-bubble-bottom-center-text class="size-5" />
                        </div>
                        <flux:heading size="lg">{{ __('Motivo de la Entrevista') }}</flux:heading>
                    </div>

                    <div class="space-y-6">
                        <flux:select wire:model="motivo" :label="__('Categoría Principal')">
                            <flux:select.option value="">{{ __('Seleccione un motivo') }}</flux:select.option>
                            <flux:select.option value="rendimiento">{{ __('Rendimiento Académico') }}
                            </flux:select.option>
                            <flux:select.option value="conducta">{{ __('Conducta y Convivencia') }}
                            </flux:select.option>
                            <flux:select.option value="asistencia">{{ __('Asistencia y Puntualidad') }}
                            </flux:select.option>
                            <flux:select.option value="personal">{{ __('Asunto Personal / Familiar') }}
                            </flux:select.option>
                            <flux:select.option value="psicopedagogico">{{ __('Evaluación Psicopedagógica') }}
                            </flux:select.option>
                            <flux:select.option value="otro">{{ __('Otro') }}</flux:select.option>
                        </flux:select>

                        <flux:textarea wire:model="notas" :label="__('Observaciones Adicionales (Opcional)')"
                            rows="3" placeholder="Breve descripción de los temas a tratar..." />
                    </div>
                </flux:card>
            </div>

            {{-- Columna 2: Urgencia y box --}}
            <div class="md:col-span-1 space-y-8">
                <flux:card class="bg-zinc-50 dark:bg-zinc-800/50">
                    <flux:heading size="lg" class="mb-4">{{ __('Urgencia') }}</flux:heading>

                    <flux:radio.group wire:model="urgencia">
                        <flux:radio value="normal" label="Normal" />
                        <flux:radio value="prioritario" label="Prioritario" />
                        <flux:radio value="urgente" label="Urgente" />
                    </flux:radio.group>
                </flux:card>

                {{-- Preview de Box (Informativo, más adelante se asignará) --}}
                <div
                    class="bg-blue-50 border border-blue-100 dark:bg-blue-900/10 dark:border-blue-800/30 p-6 rounded-xl text-center">
                    <flux:icon.building-office-2 class="size-8 mx-auto text-blue-500 mb-3" />
                    <h3 class="font-semibold px-2 text-blue-800 dark:text-blue-300">{{ __('Box Informativo') }}</h3>
                    <p class="text-sm mt-2 text-blue-600/80 dark:text-blue-400/80">
                        {{ __('La recepción asignará el box de atención una vez que el apoderado se registre en portería el día de la cita.') }}
                    </p>
                </div>
            </div>

        </div>

        {{-- Barra de Acción --}}
        <div
            class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-6 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost">
                {{ __('Cancelar') }}
            </flux:button>
            <flux:button type="submit" variant="primary" icon="check">
                {{ __('Confirmar y Agendar Cita') }}
            </flux:button>
        </div>
    </form>
</div>
