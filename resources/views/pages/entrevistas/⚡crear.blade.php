<?php

use Livewire\Component;

new class extends Component {
    public string $searchEstudiante = '';
    public ?int $estudianteId = null;

    // Búsqueda por curso (híbrido)
    public string $filtroCursoId = '';
    public bool $modalEstudiantes = false;

    // Lista de resultados mostrada dinámicamente para la búsqueda rápida
    public $resultadosBusqueda = [];

    // Campos de la entrevista
    public string $fecha = '';
    public string $hora = '';
    public string $urgencia = 'normal';
    public string $motivo = '';
    public string $lugar = 'presencial';
    public string $notas = '';

    // Notificaciones configurables por el usuario
    public bool $notificarApoderado = true;
    public bool $notificarEstudiante = true;
    public bool $esConfidencial = false;

    // Mantenedor de categorías (Superadmin / Administrador)
    public bool $modalCategorias = false;
    public string $nuevaCategoriaNombre = '';
    public string $nuevaCategoriaDesc = '';
    public ?int $editingCategoriaId = null;

    #[\Livewire\Attributes\Computed]
    public function categorias()
    {
        $schoolId = auth()->user()->current_school_id;
        $categorias = \App\Models\CategoriaEntrevista::where('school_id', $schoolId)
            ->where('activo', true)
            ->orderBy('nombre', 'asc')
            ->get();

        if ($categorias->isEmpty()) {
            $defaultCategories = [
                'Rendimiento Académico',
                'Conducta y Convivencia',
                'Asistencia y Puntualidad',
                'Asunto Personal / Familiar',
                'Evaluación Psicopedagógica',
                'Otro',
            ];
            foreach ($defaultCategories as $nombre) {
                \App\Models\CategoriaEntrevista::create([
                    'school_id' => $schoolId,
                    'nombre' => $nombre,
                    'activo' => true,
                ]);
            }
            $categorias = \App\Models\CategoriaEntrevista::where('school_id', $schoolId)
                ->where('activo', true)
                ->orderBy('nombre', 'asc')
                ->get();
        }

        return $categorias;
    }

    #[\Livewire\Attributes\Computed]
    public function esAdmin()
    {
        return auth()->user()->hasRole(['superadmin', 'administrador']);
    }

    public function abrirModalCategorias()
    {
        if (! $this->esAdmin) {
            abort(403, 'No tienes permiso para gestionar categorías.');
        }

        $this->reset(['nuevaCategoriaNombre', 'nuevaCategoriaDesc', 'editingCategoriaId']);
        $this->modalCategorias = true;
    }

    public function guardarCategoria()
    {
        if (! $this->esAdmin) {
            abort(403, 'No tienes permiso para efectuar esta acción.');
        }

        $this->validate([
            'nuevaCategoriaNombre' => 'required|string|min:2|max:100',
        ], [
            'nuevaCategoriaNombre.required' => 'Ingrese el nombre de la categoría.',
            'nuevaCategoriaNombre.min' => 'El nombre debe tener al menos 2 caracteres.',
        ]);

        $schoolId = auth()->user()->current_school_id;

        if ($this->editingCategoriaId) {
            $cat = \App\Models\CategoriaEntrevista::where('school_id', $schoolId)->find($this->editingCategoriaId);
            if ($cat) {
                $cat->update([
                    'nombre' => trim($this->nuevaCategoriaNombre),
                    'descripcion' => trim($this->nuevaCategoriaDesc) ?: null,
                ]);
                \Flux::toast('Categoría actualizada exitosamente.', variant: 'success');
            }
        } else {
            $nuevaCat = \App\Models\CategoriaEntrevista::create([
                'school_id' => $schoolId,
                'nombre' => trim($this->nuevaCategoriaNombre),
                'descripcion' => trim($this->nuevaCategoriaDesc) ?: null,
                'activo' => true,
            ]);
            $this->motivo = $nuevaCat->nombre;
            \Flux::toast('Categoría agregada exitosamente.', variant: 'success');
        }

        $this->reset(['nuevaCategoriaNombre', 'nuevaCategoriaDesc', 'editingCategoriaId']);
        unset($this->categorias);
    }

    public function editarCategoria($id)
    {
        if (! $this->esAdmin) {
            abort(403, 'No tienes permiso para efectuar esta acción.');
        }

        $cat = \App\Models\CategoriaEntrevista::where('school_id', auth()->user()->current_school_id)->find($id);
        if ($cat) {
            $this->editingCategoriaId = $cat->id;
            $this->nuevaCategoriaNombre = $cat->nombre;
            $this->nuevaCategoriaDesc = $cat->descripcion ?? '';
        }
    }

    public function eliminarCategoria($id)
    {
        if (! $this->esAdmin) {
            abort(403, 'No tienes permiso para efectuar esta acción.');
        }

        $cat = \App\Models\CategoriaEntrevista::where('school_id', auth()->user()->current_school_id)->find($id);
        if ($cat) {
            $cat->delete();
            \Flux::toast('Categoría eliminada.', variant: 'warning');
        }

        unset($this->categorias);
    }

    public function updatedSearchEstudiante()
    {
        $term = trim($this->searchEstudiante);
        if (strlen($term) >= 2) {
            $words = array_filter(explode(' ', $term));

            $this->resultadosBusqueda = \App\Models\Estudiante::query()
                ->with(['curso'])
                ->activos()
                ->where('school_id', auth()->user()->current_school_id)
                ->where(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $w = trim($word);
                        if ($w === '') {
                            continue;
                        }
                        $q->where(function ($sub) use ($w) {
                            $sub->where('nombres_csv', 'like', '%' . $w . '%')
                                ->orWhere('rut_numero', 'like', '%' . $w . '%')
                                ->orWhereHas('user', function ($userQ) use ($w) {
                                    $userQ->where('nombres', 'like', '%' . $w . '%')
                                        ->orWhere('apellido_pat', 'like', '%' . $w . '%')
                                        ->orWhere('apellido_mat', 'like', '%' . $w . '%')
                                        ->orWhere('email', 'like', '%' . $w . '%');
                                });
                        });
                    }
                })
                ->take(8)
                ->get();

            $this->estudianteId = null;
        } else {
            $this->resultadosBusqueda = [];
            $this->estudianteId = null;
        }
    }

    public function seleccionarEstudiante($id)
    {
        $this->estudianteId = $id;
        $estudiante = \App\Models\Estudiante::with('user')->find($id);

        $this->searchEstudiante = $estudiante ? $estudiante->nombreCompleto() : '';
        $this->resultadosBusqueda = [];
        $this->modalEstudiantes = false; // Cerramos el modal por si venía de ahí

        if ($estudiante) {
            $this->notificarApoderado = !empty($estudiante->apoderado_email);
            $studentEmail = $estudiante->email ?? $estudiante->user?->email;
            $this->notificarEstudiante = !empty($studentEmail);
        } else {
            $this->notificarApoderado = true;
            $this->notificarEstudiante = true;
        }
    }

    public function updatedFiltroCursoId()
    {
        if ($this->filtroCursoId !== '') {
            $this->modalEstudiantes = true;
        }
    }

    public function abrirModalCurso()
    {
        if ($this->filtroCursoId !== '') {
            $this->modalEstudiantes = true;
        }
    }

    #[\Livewire\Attributes\Computed]
    public function cursos()
    {
        return \App\Models\Curso::where('school_id', auth()->user()->current_school_id)
            ->orderBy('modalidad', 'asc') // 'basica' aparece antes que 'media' por orden alfabético
            ->orderBy('nivel')
            ->orderBy('letra')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function alumnosDelCurso()
    {
        if ($this->filtroCursoId === '') {
            return collect();
        }

        return \App\Models\Estudiante::query()
            ->where('curso_id', $this->filtroCursoId)
            ->where('school_id', auth()->user()->current_school_id)
            ->activos()
            ->orderBy('nombres_csv', 'asc')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function estudiante()
    {
        if (!$this->estudianteId) {
            return null;
        }
        return \App\Models\Estudiante::with(['curso', 'user'])->find($this->estudianteId);
    }

    public $confirmarTope = false;

    public function updatedHora()
    {
        $this->confirmarTope = false;
    }

    public function updatedFecha()
    {
        $this->confirmarTope = false;
    }

    public function agendar()
    {
        if (!auth()->user()->can('crear-entrevistas') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        $this->validate(
            [
                'estudianteId' => ['required'],
                'fecha' => ['required', 'date'],
                'hora' => ['required'],
                'urgencia' => ['required', 'in:normal,prioritario,urgente'],
                'lugar' => ['required', 'in:presencial,online'],
                'motivo' => ['required', 'string'],
                'notas' => ['nullable', 'string'],
            ],
            [
                'estudianteId.required' => 'Debe seleccionar un estudiante.',
            ],
        );

        // Verificar tope de horario
        $tope = \App\Models\Entrevista::where('user_id', auth()->id())
            ->where('fecha', $this->fecha)
            ->where('hora', $this->hora)
            ->whereIn('estado', ['pendiente', 'ingresada', 'en_curso'])
            ->first();

        if ($tope && !$this->confirmarTope) {
            $this->addError('hora', '¡Advertencia! Ya tienes una entrevista con otro apoderado en esta misma fecha y hora. Vuelve a hacer clic en "Agendar" si deseas guardar de todos modos.');
            $this->confirmarTope = true;
            return;
        }

        $entrevista = \App\Models\Entrevista::create([
            'school_id' => auth()->user()->current_school_id,
            'user_id' => auth()->id(),
            'estudiante_id' => $this->estudianteId,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'lugar' => $this->lugar === 'online' ? 'Online' : 'Presencial',
            'urgencia' => $this->urgencia,
            'motivo' => $this->motivo,
            'notas_previas' => $this->notas,
            'estado' => 'pendiente',
            'es_confidencial' => $this->puedeCrearConfidencial ? $this->esConfidencial : false,
        ]);

        // Enviar notificación al Docente (usamos auth()->user() o el owner de la cita)
        auth()->user()->notify(new \App\Notifications\EntrevistaAgendadaDocente($entrevista));

        $emailEnviado = null;

        // Enviar notificación al Apoderado (si está activo y tiene email válido)
        if ($this->notificarApoderado && !empty($entrevista->estudiante->apoderado_email)) {
            \Illuminate\Support\Facades\Notification::route('mail', $entrevista->estudiante->apoderado_email)
                ->notify(new \App\Notifications\EntrevistaAgendadaApoderado($entrevista, 'apoderado'));
            $emailEnviado = $entrevista->estudiante->apoderado_email;
        }

        // Enviar notificación al Estudiante (si está activo y tiene email válido)
        $emailEstudiante = $entrevista->estudiante->email ?? $entrevista->estudiante->user?->email;
        if ($this->notificarEstudiante && !empty($emailEstudiante)) {
            \Illuminate\Support\Facades\Notification::route('mail', $emailEstudiante)
                ->notify(new \App\Notifications\EntrevistaAgendadaApoderado($entrevista, 'estudiante'));
            if (! $emailEnviado) {
                $emailEnviado = $emailEstudiante;
            }
        }

        if ($emailEnviado) {
            $entrevista->update(['correo_citacion_enviado' => $emailEnviado]);
        }

        // Feedback al usuario y redirección a Mi Agenda
        session()->flash('success', "Entrevista con el apoderado de {$entrevista->estudiante->nombreCompleto()} agendada con éxito.");

        $targetRoute = (auth()->user()->can('ver-entrevistas-propias') || auth()->user()->hasRole('superadmin'))
            ? route('entrevistas.agenda')
            : route('entrevistas.index');

        return $this->redirect($targetRoute, navigate: true);
    }

    #[\Livewire\Attributes\Computed]
    public function puedeCrearConfidencial(): bool
    {
        return auth()->user()->hasRole(['superadmin', 'psicosocial']) || auth()->user()->can('crear-entrevistas-confidenciales');
    }

    public function mount()
    {
        if (!auth()->user()->can('crear-entrevistas') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }

        if ($this->puedeCrearConfidencial) {
            $this->esConfidencial = true;
        }

        // Preseleccionar fecha a hoy en zona horaria de Chile
        $this->fecha = now('America/Santiago')->format('Y-m-d');

        // Por defecto iniciar a las 09:00
        $this->hora = '09:00';
    }
};
?>

<div class="flex flex-col gap-8 max-w-7xl mx-auto w-full pb-10">
    <div>
        <x-entrevistas.header 
            titulo="Nueva Entrevista" 
            subtitulo="Coordina una nueva reunión con un apoderado y agenda el box correspondiente." 
            icono="calendar-days" 
        >
            <flux:button variant="ghost" icon="x-mark" href="{{ route('entrevistas.agenda') }}" wire:navigate>
                {{ __('Cancelar') }}
            </flux:button>
        </x-entrevistas.header>
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative items-end">

                {{-- Selector Guiado por Curso --}}
                <div class="w-full">
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <flux:select wire:model.live="filtroCursoId" :label="__('1. Seleccionar por Curso')">
                                <flux:select.option value="" disabled>{{ __('Elige un curso...') }}
                                </flux:select.option>
                                @foreach ($this->cursos as $cur)
                                    <flux:select.option value="{{ $cur->id }}">{{ $cur->nombreCompleto() }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:button icon="users" wire:click="abrirModalCurso"
                            class="mb-0 h-10 w-10 shrink-0 flex items-center justify-center p-0"
                            :disabled="$filtroCursoId === ''" title="Ver lista de alumnos" />
                    </div>
                    <div wire:loading wire:target="filtroCursoId, abrirModalCurso" class="mt-2 flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400">
                        <flux:icon.arrow-path class="size-4 animate-spin shrink-0" />
                        <span>{{ __('Cargando nómina del curso...') }}</span>
                    </div>
                </div>

                {{-- Buscador Global Rápido --}}
                <div class="relative z-10 w-full">
                    <flux:input wire:model.live.debounce.300ms="searchEstudiante"
                        :label="__('2. O búsqueda rápida libre')" icon="magnifying-glass"
                        placeholder="Ej: Marcelo Paz (Nombre o RUT)..." autocomplete="off" />

                    <div wire:loading wire:target="searchEstudiante" class="mt-2 flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400">
                        <flux:icon.arrow-path class="size-4 animate-spin shrink-0" />
                        <span>{{ __('Buscando estudiantes...') }}</span>
                    </div>

                    {{-- Dropdown de resultados (se sobrepone) --}}
                    @if (count($resultadosBusqueda) > 0)
                        <div
                            class="absolute mt-1 w-full bg-white dark:bg-zinc-800 rounded-md shadow-lg border border-zinc-200 dark:border-zinc-700 z-50 overflow-hidden outline-none">
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
            </div>

            <flux:separator variant="subtle" class="my-6" />

            {{-- Apoderado Auto-completado & Estudiante Actual --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
                {{-- Card Estudiante --}}
                <div>
                    <div wire:loading wire:target="seleccionarEstudiante" class="w-full h-full min-h-[140px]">
                        <div class="flex items-center gap-3 bg-blue-50 p-5 rounded-xl border border-blue-200 dark:bg-blue-900/10 dark:border-blue-800/30 h-full">
                            <flux:icon.arrow-path class="size-6 text-blue-600 dark:text-blue-400 animate-spin shrink-0" />
                            <div>
                                <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">{{ __('Cargando Estudiante') }}</p>
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300 mt-0.5">{{ __('Obteniendo datos del alumno...') }}</p>
                            </div>
                        </div>
                    </div>

                    <div wire:loading.remove wire:target="seleccionarEstudiante" class="h-full">
                        @if ($this->estudiante)
                            @php
                                $emailEstudiante = $this->estudiante->email ?? $this->estudiante->user?->email;
                            @endphp
                            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-5 rounded-xl border border-zinc-200 dark:border-zinc-700 h-full flex flex-col justify-between space-y-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <flux:icon.check-circle class="size-5 text-green-500 shrink-0" />
                                        <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Estudiante Seleccionado') }}</span>
                                    </div>
                                    <p class="text-base font-bold text-zinc-900 dark:text-white">
                                        {{ $this->estudiante->nombreCompleto() }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 flex items-center gap-1.5">
                                        <flux:icon.envelope class="size-3.5 shrink-0" />
                                        <span class="truncate">{{ $emailEstudiante ?: __('Sin correo registrado') }}</span>
                                    </p>
                                </div>

                                @if ($emailEstudiante)
                                    <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700/60">
                                        <flux:checkbox wire:model.live="notificarEstudiante"
                                            :label="__('Enviar citación por correo al estudiante')"
                                            class="text-xs font-medium text-zinc-700 dark:text-zinc-300" />
                                    </div>
                                @else
                                    <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700/60">
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500 italic">{{ __('No se enviará correo (sin email de estudiante registrado)') }}</span>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center gap-3 bg-red-50 p-5 rounded-xl border border-red-200 dark:bg-red-900/10 dark:border-red-800/30 h-full">
                                <flux:icon.exclamation-circle class="size-6 text-red-500 shrink-0" />
                                <div>
                                    <p class="text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider">{{ __('Estudiante Seleccionado') }}</p>
                                    <p class="text-sm font-medium text-red-700 dark:text-red-300 mt-0.5">{{ __('Pendiente de selección') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Card Apoderado --}}
                <div>
                    <div wire:loading wire:target="seleccionarEstudiante" class="w-full h-full min-h-[140px]">
                        <div class="flex items-center gap-3 bg-blue-50 p-5 rounded-xl border border-blue-200 dark:bg-blue-900/10 dark:border-blue-800/30 h-full">
                            <flux:icon.arrow-path class="size-6 text-blue-600 dark:text-blue-400 animate-spin shrink-0" />
                            <div>
                                <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">{{ __('Cargando Apoderado') }}</p>
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300 mt-0.5">{{ __('Obteniendo datos del apoderado...') }}</p>
                            </div>
                        </div>
                    </div>

                    <div wire:loading.remove wire:target="seleccionarEstudiante" class="h-full">
                        @if ($this->estudiante)
                            @php
                                $nombreApoderado = $this->estudiante->apoderado_nombres
                                    ? trim($this->estudiante->apoderado_nombres . ' ' . $this->estudiante->apoderado_apellido_pat)
                                    : null;
                                $emailApoderado = $this->estudiante->apoderado_email;
                            @endphp
                            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-5 rounded-xl border border-zinc-200 dark:border-zinc-700 h-full flex flex-col justify-between space-y-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <flux:icon.user-group class="size-5 text-blue-500 shrink-0" />
                                        <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Apoderado Titular') }}</span>
                                    </div>
                                    <p class="text-base font-bold text-zinc-900 dark:text-white">
                                        {{ $nombreApoderado ?: __('Sin apoderado registrado') }}
                                        @if ($this->estudiante->apoderado_parentesco)
                                            <span class="text-xs font-normal text-zinc-500">({{ $this->estudiante->apoderado_parentesco }})</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 flex items-center gap-1.5">
                                        <flux:icon.envelope class="size-3.5 shrink-0" />
                                        <span class="truncate">{{ $emailApoderado ?: __('Sin correo registrado') }}</span>
                                    </p>
                                </div>

                                @if ($emailApoderado)
                                    <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700/60">
                                        <flux:checkbox wire:model.live="notificarApoderado"
                                            :label="__('Enviar citación por correo al apoderado titular')"
                                            class="text-xs font-medium text-zinc-700 dark:text-zinc-300" />
                                    </div>
                                @else
                                    <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700/60">
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500 italic">{{ __('No se enviará correo (sin email de apoderado registrado)') }}</span>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="bg-zinc-50 dark:bg-zinc-800/30 p-5 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700 h-full flex items-center">
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 italic">{{ __('Seleccione un estudiante para ver los datos del apoderado titular.') }}</p>
                            </div>
                        @endif
                    </div>
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
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg text-emerald-600 dark:text-emerald-400">
                                <flux:icon.chat-bubble-bottom-center-text class="size-5" />
                            </div>
                            <flux:heading size="lg">{{ __('Motivo de la Entrevista') }}</flux:heading>
                        </div>

                        @if($this->esAdmin)
                            <flux:button type="button" variant="subtle" size="xs" wire:click="abrirModalCategorias" icon="cog-6-tooth" class="font-bold text-emerald-700 dark:text-emerald-300">
                                {{ __('Mantenedor Categorías') }}
                            </flux:button>
                        @endif
                    </div>

                    <div class="space-y-6">
                        <flux:select wire:model="motivo" :label="__('Categoría Principal')">
                            <flux:select.option value="">{{ __('Seleccione un motivo') }}</flux:select.option>
                            @foreach($this->categorias as $cat)
                                <flux:select.option value="{{ $cat->nombre }}">{{ $cat->nombre }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:textarea wire:model="notas" :label="__('Observaciones Adicionales (Opcional)')"
                            rows="3" placeholder="Breve descripción de los temas a tratar..." />
                    </div>
                </flux:card>
            </div>

            {{-- Columna 2: Urgencia, Modalidad y box --}}
            <div class="md:col-span-1 space-y-8">
                <flux:card class="bg-zinc-50 dark:bg-zinc-800/50">
                    <flux:heading size="lg" class="mb-4">{{ __('Urgencia') }}</flux:heading>

                    <flux:radio.group wire:model="urgencia">
                        <flux:radio value="normal" label="Normal" />
                        <flux:radio value="prioritario" label="Prioritario" />
                        <flux:radio value="urgente" label="Urgente" />
                    </flux:radio.group>
                </flux:card>

                <flux:card class="bg-zinc-50 dark:bg-zinc-800/50">
                    <flux:heading size="lg" class="mb-4">{{ __('Modalidad') }}</flux:heading>

                    <flux:radio.group wire:model="lugar">
                        <flux:radio value="presencial" label="Presencial (En el Colegio)" />
                        <flux:radio value="online" label="Online (Videollamada)" />
                    </flux:radio.group>
                </flux:card>

                @if ($this->puedeCrearConfidencial)
                    <flux:card class="bg-purple-50/60 dark:bg-purple-950/20 border-purple-200 dark:border-purple-800">
                        <flux:field variant="inline">
                            <flux:switch wire:model="esConfidencial" />
                            <div>
                                <flux:label class="font-bold text-purple-950 dark:text-purple-300 flex items-center gap-1.5">
                                    🔒 Entrevista Confidencial / Privada
                                </flux:label>
                                <flux:description class="text-xs text-purple-800 dark:text-purple-400">
                                    {{ __('Solo tú, los miembros del Equipo Psicosocial y las personas a quienes les compartas acceso podrán consultar esta cita y su bitácora.') }}
                                </flux:description>
                            </div>
                        </flux:field>
                    </flux:card>
                @endif

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
            <flux:button variant="ghost" href="{{ route('entrevistas.agenda') }}" wire:navigate>
                {{ __('Cancelar') }}
            </flux:button>
            <flux:button type="submit" variant="primary" icon="check" :class="$confirmarTope ? 'bg-amber-600 hover:bg-amber-700 text-white border-none' : ''">
                {{ $confirmarTope ? __('Confirmar tope de horario') : __('Confirmar y Agendar Cita') }}
            </flux:button>
        </div>
    </form>

    {{-- Modal para seleccionar alumnos del curso --}}
    <flux:modal wire:model="modalEstudiantes" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Elegir Estudiante de la Nómina') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Seleccione con un clic al alumno que desea citar a entrevista.') }}
                </flux:text>
            </div>

            <div
                class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden flex flex-col max-h-[60vh]">
                <div
                    class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700 text-sm font-semibold flex justify-between text-zinc-600 dark:text-zinc-300">
                    <span>Nombre del Alumno</span>
                    <span>RUT</span>
                </div>

                <div class="overflow-y-auto w-full divide-y divide-zinc-100 dark:divide-zinc-800 relative min-h-[150px]">
                    <div wire:loading wire:target="filtroCursoId, abrirModalCurso, seleccionarEstudiante" class="absolute inset-0 bg-white/70 dark:bg-zinc-900/70 flex items-center justify-center z-10">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon.arrow-path class="size-6 animate-spin text-blue-600 dark:text-blue-400" />
                            <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400">{{ __('Cargando alumnos...') }}</span>
                        </div>
                    </div>

                    @forelse($this->alumnosDelCurso as $al)
                        <button type="button" wire:click="seleccionarEstudiante({{ $al->id }})"
                            class="w-full flex items-center justify-between px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800 focus:outline-none transition-colors group">
                            <span
                                class="text-sm font-medium text-zinc-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                {{ $al->nombreCompleto() }}
                            </span>
                            <span class="text-xs text-zinc-500 font-mono">
                                {{ $al->rutCompleto() ?? '-' }}
                            </span>
                        </button>
                    @empty
                        <div class="p-6 text-center text-zinc-500 text-sm">
                            Este curso no tiene alumnos registrados o no existe.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end">
                <flux:button wire:click="$set('modalEstudiantes', false)" variant="ghost">{{ __('Cerrar') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal Mantenedor de Categorías --}}
    @if($this->esAdmin)
        <flux:modal wire:model="modalCategorias" class="md:w-[32rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon.tag class="size-5 text-emerald-600 dark:text-emerald-400" />
                        Mantenedor de Categorías
                    </flux:heading>
                    <flux:subheading>
                        Administre las categorías de entrevistas para esta institución.
                    </flux:subheading>
                </div>

                <!-- Formulario Agregar / Editar Categoría -->
                <div class="bg-zinc-50 dark:bg-zinc-800/60 p-4 rounded-xl space-y-3 border border-zinc-200 dark:border-zinc-700">
                    <flux:field>
                        <flux:label class="text-xs">Nombre de la Categoría</flux:label>
                        <flux:input wire:model="nuevaCategoriaNombre" placeholder="Ej: Orientación Vocacional" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-xs">Descripción (Opcional)</flux:label>
                        <flux:input wire:model="nuevaCategoriaDesc" placeholder="Breve descripción..." />
                    </flux:field>

                    <div class="flex justify-end gap-2">
                        @if($editingCategoriaId)
                            <flux:button size="sm" variant="ghost" wire:click="$set('editingCategoriaId', null)">Cancelar edición</flux:button>
                        @endif
                        <flux:button size="sm" variant="primary" wire:click="guardarCategoria">
                            {{ $editingCategoriaId ? 'Guardar Cambios' : 'Agregar Categoría' }}
                        </flux:button>
                    </div>
                </div>

                <!-- Listado de Categorías Existentes -->
                <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                    <p class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-2">Categorías Registradas ({{ $this->categorias->count() }})</p>
                    @foreach($this->categorias as $cat)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs">
                            <div>
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $cat->nombre }}</span>
                                @if($cat->descripcion)
                                    <p class="text-[11px] text-zinc-500 truncate max-w-[200px]">{{ $cat->descripcion }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editarCategoria({{ $cat->id }})" title="Editar" />
                                <flux:button size="xs" variant="ghost" icon="trash" class="text-red-500 hover:text-red-700" wire:click="eliminarCategoria({{ $cat->id }})" title="Eliminar" />
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cerrar</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
