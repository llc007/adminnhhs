<?php

use App\Models\Curso;
use App\Models\EvaluacionDocente;
use App\Models\School;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.blank')] #[Title('Encuesta de Evaluación Docente')] class extends Component
{
    public ?int $schoolId = null;

    #[Url]
    public string $tipo = '';

    public string $pinIngresado = '';

    public bool $pinValidado = false;

    public string $tipoRespondente = 'estudiante';

    public ?int $profesorId = null;

    public bool $conJefatura = false;

    public string $busquedaProfesor = '';

    public string $paso = 'pin'; // 'pin' | 'seleccion' | 'encuesta' | 'completado'

    /** @var array<string, int> */
    public array $respuestas = [];

    public ?string $mensajeError = null;

    public function mount(?int $schoolId = null): void
    {
        $school = $schoolId
            ? School::find($schoolId)
            : (auth()->check() && auth()->user()->current_school_id
                ? School::find(auth()->user()->current_school_id)
                : School::first());

        if (! $school) {
            abort(404, 'No se encontró el establecimiento.');
        }

        $this->schoolId = $school->id;

        if (in_array($this->tipo, ['estudiante', 'apoderado'], true)) {
            $this->tipoRespondente = $this->tipo;
        }

        // Verificar si la tablet ya desbloqueó la sesión con el PIN vigente
        $pinGuardado = session("encuesta_pin_valor_{$this->schoolId}");
        $tipoGuardado = session("encuesta_pin_tipo_{$this->schoolId}");

        if ($pinGuardado && $school->encuestasHabilitadas()) {
            if ($tipoGuardado === 'estudiante' && $pinGuardado === $school->pinEncuestaEstudiante() && ($this->tipo === '' || $this->tipo === 'estudiante')) {
                $this->pinValidado = true;
                $this->tipoRespondente = 'estudiante';
                $this->paso = 'seleccion';
            } elseif ($tipoGuardado === 'apoderado' && $pinGuardado === $school->pinEncuestaApoderado() && ($this->tipo === '' || $this->tipo === 'apoderado')) {
                $this->pinValidado = true;
                $this->tipoRespondente = 'apoderado';
                $this->paso = 'seleccion';
            }
        }
    }

    public function validarPin(): void
    {
        $school = $this->school;
        if (! $school || ! $school->encuestasHabilitadas()) {
            $this->mensajeError = 'El periodo de encuestas se encuentra cerrado actualmente.';

            return;
        }

        $pin = trim($this->pinIngresado);
        if ($pin === '') {
            $this->mensajeError = 'Debes ingresar el PIN de acceso.';

            return;
        }

        $pinEst = $school->pinEncuestaEstudiante();
        $pinApo = $school->pinEncuestaApoderado();

        if ($this->tipo === 'estudiante') {
            if ($pin !== $pinEst) {
                $this->mensajeError = 'El PIN ingresado no corresponde a la Encuesta de Estudiantes.';

                return;
            }
            $this->tipoRespondente = 'estudiante';
        } elseif ($this->tipo === 'apoderado') {
            if ($pin !== $pinApo) {
                $this->mensajeError = 'El PIN ingresado no corresponde a la Encuesta de Apoderados.';

                return;
            }
            $this->tipoRespondente = 'apoderado';
        } else {
            if ($pin === $pinEst) {
                $this->tipoRespondente = 'estudiante';
            } elseif ($pin === $pinApo) {
                $this->tipoRespondente = 'apoderado';
            } else {
                $this->mensajeError = 'PIN incorrecto. Verifica el código con la persona encargada.';

                return;
            }
        }

        $this->pinValidado = true;
        $this->mensajeError = null;
        $this->paso = 'seleccion';

        session([
            "encuesta_pin_valor_{$this->schoolId}" => $pin,
            "encuesta_pin_tipo_{$this->schoolId}" => $this->tipoRespondente,
        ]);
    }

    public function bloquearPin(): void
    {
        session()->forget([
            "encuesta_pin_valor_{$this->schoolId}",
            "encuesta_pin_tipo_{$this->schoolId}",
        ]);

        $this->pinValidado = false;
        $this->pinIngresado = '';
        $this->profesorId = null;
        $this->respuestas = [];
        $this->mensajeError = null;
        $this->paso = 'pin';
    }

    public function seleccionarProfesor(int $id): void
    {
        if (! $this->pinValidado) {
            $this->paso = 'pin';

            return;
        }

        $profesor = User::query()
            ->whereHas('schools', fn ($q) => $q->where('school_id', $this->schoolId))
            ->find($id);

        if (! $profesor) {
            return;
        }

        $this->profesorId = $profesor->id;
        $this->conJefatura = Curso::query()
            ->where('school_id', $this->schoolId)
            ->where('jefe_id', $profesor->id)
            ->exists();
        $this->mensajeError = null;
    }

    public function comenzarEncuesta(): void
    {
        if (! $this->pinValidado) {
            $this->paso = 'pin';

            return;
        }

        if (! $this->school?->encuestasHabilitadas()) {
            $this->mensajeError = 'El periodo de encuestas ha sido deshabilitado.';

            return;
        }

        if (! $this->profesorSeleccionado) {
            $this->mensajeError = 'Debes seleccionar un profesor o profesora para comenzar.';

            return;
        }

        $this->respuestas = [];
        $this->mensajeError = null;
        $this->paso = 'encuesta';
    }

    public function responderPregunta(int $numero, int $puntaje): void
    {
        if (! array_key_exists($puntaje, EvaluacionDocente::ALTERNATIVAS)) {
            return;
        }

        $this->respuestas[(string) $numero] = $puntaje;
        $this->mensajeError = null;
    }

    public function enviarEncuesta(): void
    {
        if (! $this->pinValidado) {
            $this->paso = 'pin';

            return;
        }

        if (! $this->school?->encuestasHabilitadas()) {
            $this->mensajeError = 'El periodo de encuestas se encuentra cerrado.';

            return;
        }

        if (! $this->profesorSeleccionado) {
            $this->paso = 'seleccion';

            return;
        }

        $preguntas = $this->preguntasActuales;
        $respuestasLimpias = [];

        foreach (array_keys($preguntas) as $num) {
            $key = (string) $num;
            if (! isset($this->respuestas[$key]) || ! array_key_exists((int) $this->respuestas[$key], EvaluacionDocente::ALTERNATIVAS)) {
                $this->mensajeError = "Por favor responde todas las preguntas antes de enviar (falta la pregunta N° {$num}).";

                return;
            }
            $respuestasLimpias[$key] = (int) $this->respuestas[$key];
        }

        $totalPreguntas = count($preguntas);
        $puntajeObtenido = array_sum($respuestasLimpias);
        $puntajeMaximo = $totalPreguntas * 3;
        $promedio = $totalPreguntas > 0 ? round($puntajeObtenido / $totalPreguntas, 2) : 0;

        EvaluacionDocente::create([
            'school_id' => $this->schoolId,
            'profesor_id' => $this->profesorSeleccionado->id,
            'tipo_respondente' => $this->tipoRespondente,
            'con_jefatura' => $this->conJefatura,
            'respuestas' => $respuestasLimpias,
            'puntaje_obtenido' => $puntajeObtenido,
            'puntaje_maximo' => $puntajeMaximo,
            'promedio' => $promedio,
            'registrado_por_id' => auth()->id(),
        ]);

        $this->mensajeError = null;
        $this->paso = 'completado';
    }

    public function nuevaEncuestaMismoProfesor(): void
    {
        $this->respuestas = [];
        $this->mensajeError = null;
        $this->paso = 'encuesta';
    }

    public function nuevaEncuestaOtroProfesor(): void
    {
        $this->profesorId = null;
        $this->respuestas = [];
        $this->mensajeError = null;
        $this->busquedaProfesor = '';
        $this->paso = 'seleccion';
    }

    #[Computed]
    public function school(): ?School
    {
        return $this->schoolId ? School::find($this->schoolId) : null;
    }

    #[Computed]
    public function profesores()
    {
        return User::query()
            ->whereHas('schools', fn ($q) => $q->where('school_id', $this->schoolId))
            ->whereDoesntHave('roles', function ($q) {
                $q->where('roles.team_id', $this->schoolId)
                    ->where('roles.name', 'estudiante');
            })
            ->with(['jefeDeCursos' => fn ($q) => $q->where('school_id', $this->schoolId)])
            ->withCount([
                'evaluacionesDocente as respuestas_recibidas_count' => fn ($q) => $q
                    ->where('school_id', $this->schoolId)
                    ->where('tipo_respondente', $this->tipoRespondente),
            ])
            ->when(strlen(trim($this->busquedaProfesor)) >= 1, function ($query) {
                $term = '%'.trim($this->busquedaProfesor).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('nombres', 'like', $term)
                        ->orWhere('apellido_pat', 'like', $term)
                        ->orWhere('apellido_mat', 'like', $term);
                });
            })
            ->orderBy('nombres')
            ->orderBy('apellido_pat')
            ->get();
    }

    #[Computed]
    public function profesorSeleccionado(): ?User
    {
        if (! $this->profesorId) {
            return null;
        }

        return User::query()
            ->whereHas('schools', fn ($q) => $q->where('school_id', $this->schoolId))
            ->with(['jefeDeCursos' => fn ($q) => $q->where('school_id', $this->schoolId)])
            ->find($this->profesorId);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function preguntasActuales(): array
    {
        return EvaluacionDocente::preguntasPara($this->tipoRespondente, $this->conJefatura);
    }
};
?>

<div class="min-h-screen bg-zinc-100 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 flex flex-col">
    {{-- Barra institucional superior --}}
    <header class="bg-[#00376e] text-white shadow-md">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-white/15 text-white shrink-0">
                    <flux:icon.clipboard-document-check class="size-6" />
                </div>
                <div>
                    <h1 class="text-sm sm:text-base font-bold tracking-tight">
                        {{ $this->school?->name ?? 'Liceo New Heaven High School' }}
                    </h1>
                    <p class="text-[11px] text-blue-200 uppercase tracking-wider font-semibold">
                        Encuesta Anónima de Evaluación Docente
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 border border-emerald-400/30 px-3 py-1 text-xs font-medium text-emerald-100">
                    <flux:icon.shield-check class="size-4" />
                    100% Anónima
                </span>

                @if ($pinValidado)
                    <button type="button" wire:click="bloquearPin"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1.5 text-xs font-medium text-white transition cursor-pointer">
                        <flux:icon.lock-closed class="size-4" />
                        <span class="hidden sm:inline">Cambiar PIN</span>
                    </button>
                @endif

                @auth
                    @if (auth()->user()->hasRole(['comision_ed', 'superadmin']))
                        <a href="{{ route('encuestas.resultados') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1.5 text-xs font-medium text-white transition">
                            <flux:icon.chart-bar class="size-4" />
                            <span class="hidden sm:inline">Resultados</span>
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    {{-- SI LAS ENCUESTAS ESTÁN DESHABILITADAS POR EL SUPERADMIN --}}
    @if (! $this->school?->encuestasHabilitadas())
        <main class="flex-1 max-w-lg w-full mx-auto px-4 sm:px-6 py-12 flex items-center justify-center">
            <div class="w-full bg-white dark:bg-zinc-900 rounded-2xl shadow-lg border border-zinc-200 dark:border-zinc-800 p-8 text-center space-y-5">
                <div class="mx-auto size-16 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <flux:icon.lock-closed class="size-8" />
                </div>
                <div class="space-y-2">
                    <h2 class="text-xl font-extrabold text-zinc-900 dark:text-white">
                        Periodo de Encuestas Cerrado
                    </h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        La recepción de respuestas de Evaluación Docente se encuentra deshabilitada en este momento.
                    </p>
                </div>
            </div>
        </main>

    {{-- PASO 0: INGRESO DE PIN POR ENCUESTA --}}
    @elseif (! $pinValidado || $paso === 'pin')
        <main class="flex-1 max-w-md w-full mx-auto px-4 sm:px-6 py-12 flex items-center justify-center">
            <div class="w-full bg-white dark:bg-zinc-900 rounded-2xl shadow-lg border border-zinc-200 dark:border-zinc-800 p-6 sm:p-8 space-y-6">
                <div class="text-center space-y-2">
                    <div class="mx-auto size-14 rounded-2xl bg-[#00376e]/10 dark:bg-blue-950 text-[#00376e] dark:text-blue-400 flex items-center justify-center">
                        <flux:icon.key class="size-7" />
                    </div>
                    <h2 class="text-xl font-extrabold text-zinc-900 dark:text-white">
                        Acceso a Encuesta Docente
                    </h2>
                    @if ($tipo === 'estudiante')
                        <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-950 px-3 py-1 text-xs font-bold text-[#00376e] dark:text-blue-300">
                            Encuesta Estudiantes (10 Preguntas)
                        </span>
                    @elseif ($tipo === 'apoderado')
                        <span class="inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-950 px-3 py-1 text-xs font-bold text-indigo-800 dark:text-indigo-300">
                            Encuesta Apoderados (6 - 7 Preguntas)
                        </span>
                    @endif
                    <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">
                        Ingresa el PIN entregado por la comisión encargada para desbloquear la encuesta en este dispositivo.
                    </p>
                </div>

                @if ($mensajeError)
                    <div class="rounded-xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 p-3.5 text-xs sm:text-sm text-red-700 dark:text-red-300 flex items-center gap-2.5">
                        <flux:icon.exclamation-triangle class="size-5 shrink-0 text-red-600" />
                        <span>{{ $mensajeError }}</span>
                    </div>
                @endif

                <form wire:submit="validarPin" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2 text-center">
                            PIN de la Encuesta
                        </label>
                        <input type="text" wire:model="pinIngresado" inputmode="numeric" maxlength="10" autofocus
                            placeholder="••••"
                            class="w-full text-center text-2xl font-mono font-extrabold tracking-[0.35em] rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-3.5 px-4 text-zinc-900 dark:text-white focus:border-[#00376e] focus:ring-2 focus:ring-[#00376e]/20 outline-none" />
                    </div>

                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#00376e] hover:bg-[#002a54] text-white font-bold py-3.5 px-6 text-sm shadow-md transition cursor-pointer">
                        <flux:icon.lock-open class="size-5" />
                        Ingresar a la Encuesta
                    </button>
                </form>
            </div>
        </main>

    {{-- PASO 1: SELECCIÓN DE PROFESOR --}}
    @elseif ($paso === 'seleccion')
        @php
            $meta = $tipoRespondente === 'estudiante' ? EvaluacionDocente::META_ESTUDIANTES : EvaluacionDocente::META_APODERADOS;
        @endphp
        <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-8 space-y-6">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-zinc-200 dark:border-zinc-800 p-6 sm:p-8 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-5">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-bold {{ $tipoRespondente === 'estudiante' ? 'bg-blue-100 text-[#00376e] dark:bg-blue-950 dark:text-blue-300' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300' }}">
                                {{ $tipoRespondente === 'estudiante' ? 'Modalidad Estudiante (10 preguntas)' : 'Modalidad Apoderado (6-7 preguntas)' }}
                            </span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                            Selecciona al Docente a Evaluar
                        </h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Toca sobre el nombre del profesor o profesora que vas a evaluar.
                        </p>
                    </div>
                </div>

                @if ($mensajeError)
                    <div class="rounded-xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 p-4 text-sm text-red-700 dark:text-red-300 flex items-center gap-3">
                        <flux:icon.exclamation-triangle class="size-5 shrink-0 text-red-600" />
                        <span>{{ $mensajeError }}</span>
                    </div>
                @endif

                {{-- Buscador de profesor --}}
                <div>
                    <flux:input wire:model.live.debounce.200ms="busquedaProfesor" icon="magnifying-glass"
                        placeholder="Escribe el nombre o apellido del profesor(a)..." clearable />
                </div>

                {{-- Grilla de profesores --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-96 overflow-y-auto pr-1">
                    @forelse ($this->profesores as $profesor)
                        @php
                            $seleccionado = $profesorId === $profesor->id;
                            $cursosJefe = $profesor->jefeDeCursos->map(fn ($c) => $c->nombreAbreviado())->implode(', ');
                            $recibidas = (int) $profesor->respuestas_recibidas_count;
                        @endphp
                        <button type="button" wire:click="seleccionarProfesor({{ $profesor->id }})"
                            class="flex items-center justify-between gap-3 p-4 rounded-xl border text-left transition cursor-pointer {{ $seleccionado
                                ? 'border-[#00376e] bg-blue-50/80 dark:bg-blue-950/40 dark:border-blue-500 ring-2 ring-[#00376e]/20'
                                : 'border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/40 hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-white dark:hover:bg-zinc-800' }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0 {{ $seleccionado ? 'bg-[#00376e] text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200' }}">
                                    {{ $profesor->initials() }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ $profesor->nombreCompleto() }}
                                    </p>
                                    @if ($cursosJefe !== '')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-blue-700 dark:text-blue-300">
                                            Prof. Jefe: {{ $cursosJefe }}
                                        </span>
                                    @else
                                        <span class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                            Docente de Asignatura
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full {{ $recibidas >= $meta ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-zinc-200/70 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                    {{ $recibidas }}/{{ $meta }}
                                </span>
                                @if ($seleccionado)
                                    <flux:icon.check-circle class="size-6 text-[#00376e] dark:text-blue-400 shrink-0" />
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full py-10 text-center text-zinc-500 dark:text-zinc-400 text-sm">
                            No se encontraron profesores con ese nombre.
                        </div>
                    @endforelse
                </div>

                {{-- Configuración adicional si es Apoderado y hay profesor seleccionado --}}
                @if ($this->profesorSeleccionado)
                    <div class="rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/60 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wider font-bold text-blue-700 dark:text-blue-300">
                                Docente Seleccionado
                            </p>
                            <p class="text-lg font-extrabold text-zinc-900 dark:text-white">
                                {{ $this->profesorSeleccionado->nombreCompleto() }}
                            </p>
                            @if ($tipoRespondente === 'apoderado')
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-zinc-600 dark:text-zinc-300">Modalidad de evaluación apoderado:</span>
                                    <button type="button" wire:click="$set('conJefatura', true)"
                                        class="px-2.5 py-1 rounded-md text-xs font-semibold cursor-pointer transition {{ $conJefatura ? 'bg-[#00376e] text-white' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border border-zinc-300 dark:border-zinc-700' }}">
                                        Con Jefatura (7 preguntas)
                                    </button>
                                    <button type="button" wire:click="$set('conJefatura', false)"
                                        class="px-2.5 py-1 rounded-md text-xs font-semibold cursor-pointer transition {{ ! $conJefatura ? 'bg-[#00376e] text-white' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border border-zinc-300 dark:border-zinc-700' }}">
                                        Sin Jefatura (6 preguntas)
                                    </button>
                                </div>
                            @endif
                        </div>

                        <flux:button variant="primary" icon-trailing="arrow-right" wire:click="comenzarEncuesta"
                            class="w-full sm:w-auto !bg-[#00376e] hover:!bg-[#002a54] !text-white font-bold px-6 py-3">
                            Comenzar Encuesta
                        </flux:button>
                    </div>
                @endif
            </div>
        </main>

    {{-- PASO 2: RESPONDER LA ENCUESTA --}}
    @elseif ($paso === 'encuesta' && $this->profesorSeleccionado)
        @php
            $preguntas = $this->preguntasActuales;
            $totalPreguntas = count($preguntas);
            $respondidas = count($respuestas);
            $porcentajeAvance = $totalPreguntas > 0 ? round(($respondidas / $totalPreguntas) * 100) : 0;
        @endphp

        {{-- Cabecera fija con el nombre del profesor evaluado --}}
        <div class="sticky top-0 z-30 bg-white/95 dark:bg-zinc-900/95 backdrop-blur border-b-2 border-[#00376e] shadow-md">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="size-12 rounded-xl bg-[#00376e] text-white flex items-center justify-center font-extrabold text-base shrink-0 shadow">
                        {{ $this->profesorSeleccionado->initials() }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">
                                Profesor(a) Evaluado(a):
                            </span>
                            <span class="inline-flex items-center rounded-md bg-blue-100 dark:bg-blue-900/50 px-2 py-0.5 text-[11px] font-semibold text-blue-800 dark:text-blue-200">
                                {{ $tipoRespondente === 'estudiante' ? 'Encuesta Estudiante' : ($conJefatura ? 'Encuesta Apoderado · Con Jefatura' : 'Encuesta Apoderado · Sin Jefatura') }}
                            </span>
                        </div>
                        <h2 class="text-lg sm:text-xl font-extrabold text-zinc-900 dark:text-white leading-tight">
                            {{ $this->profesorSeleccionado->nombreCompleto() }}
                        </h2>
                    </div>
                </div>

                <div class="flex items-center justify-between sm:justify-end gap-4">
                    <div class="text-right">
                        <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                            {{ $respondidas }} de {{ $totalPreguntas }} respondidas
                        </span>
                        <div class="w-32 h-2 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden mt-1">
                            <div class="h-full bg-emerald-500 transition-all duration-300" style="width: {{ $porcentajeAvance }}%"></div>
                        </div>
                    </div>

                    <button type="button" wire:click="nuevaEncuestaOtroProfesor"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 px-3 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition cursor-pointer">
                        <flux:icon.arrows-right-left class="size-4" />
                        Cambiar profesor
                    </button>
                </div>
            </div>
        </div>

        <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 space-y-5">
            @if ($mensajeError)
                <div class="rounded-xl bg-red-50 dark:bg-red-950/60 border border-red-300 dark:border-red-800 p-4 text-sm font-medium text-red-700 dark:text-red-300 flex items-center gap-3 shadow-sm">
                    <flux:icon.exclamation-triangle class="size-5 shrink-0 text-red-600" />
                    <span>{{ $mensajeError }}</span>
                </div>
            @endif

            @foreach ($preguntas as $numero => $textoPregunta)
                @php
                    $respuestaActual = $respuestas[(string) $numero] ?? null;
                @endphp
                <div wire:key="pregunta-{{ $numero }}"
                    class="bg-white dark:bg-zinc-900 rounded-2xl border transition p-5 sm:p-6 shadow-xs {{ $respuestaActual !== null ? 'border-emerald-300 dark:border-emerald-800/70' : 'border-zinc-200 dark:border-zinc-800' }}">
                    <div class="flex items-start gap-3.5">
                        <span class="inline-flex items-center justify-center size-8 rounded-lg font-extrabold text-sm shrink-0 {{ $respuestaActual !== null ? 'bg-emerald-600 text-white' : 'bg-[#00376e] text-white' }}">
                            {{ $numero }}
                        </span>
                        <p class="text-sm sm:text-base font-semibold text-zinc-900 dark:text-zinc-100 leading-relaxed pt-0.5">
                            {{ $textoPregunta }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
                        @foreach (EvaluacionDocente::ALTERNATIVAS as $puntaje => $etiqueta)
                            @php
                                $activa = $respuestaActual !== null && (int) $respuestaActual === $puntaje;
                            @endphp
                            <button type="button"
                                wire:click="responderPregunta({{ $numero }}, {{ $puntaje }})"
                                class="flex items-center justify-center gap-2 rounded-xl border-2 px-4 py-3.5 text-sm font-bold transition cursor-pointer {{ $activa
                                    ? 'border-[#00376e] bg-[#00376e] text-white shadow-sm'
                                    : 'border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 text-zinc-700 dark:text-zinc-200 hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-zinc-800' }}">
                                @if ($activa)
                                    <flux:icon.check-circle class="size-5 shrink-0 text-emerald-300" />
                                @endif
                                <span>{{ $etiqueta }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($mensajeError)
                <div class="rounded-xl bg-red-50 dark:bg-red-950/60 border border-red-300 dark:border-red-800 p-4 text-sm font-medium text-red-700 dark:text-red-300 flex items-center gap-3">
                    <flux:icon.exclamation-triangle class="size-5 shrink-0 text-red-600" />
                    <span>{{ $mensajeError }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-5 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
                <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">
                    Verifica que hayas respondido las <strong>{{ $totalPreguntas }} preguntas</strong> antes de enviar. Tu respuesta es totalmente anónima.
                </p>

                <button type="button" wire:click="enviarEncuesta"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-8 py-3.5 text-base shadow-md transition cursor-pointer shrink-0">
                    <flux:icon.paper-airplane class="size-5" />
                    Enviar Encuesta
                </button>
            </div>
        </main>

    {{-- PASO 3: CONFIRMACIÓN Y REINICIO RÁPIDO --}}
    @elseif ($paso === 'completado')
        <main class="flex-1 max-w-xl w-full mx-auto px-4 sm:px-6 py-12 flex items-center justify-center">
            <div class="w-full bg-white dark:bg-zinc-900 rounded-2xl shadow-lg border border-zinc-200 dark:border-zinc-800 p-8 text-center space-y-6">
                <div class="mx-auto size-16 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <flux:icon.check-badge class="size-10" />
                </div>

                <div class="space-y-2">
                    <h2 class="text-2xl font-extrabold text-zinc-900 dark:text-white">
                        ¡Muchas gracias por tu respuesta!
                    </h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        La evaluación para <strong>{{ $this->profesorSeleccionado?->nombreCompleto() }}</strong> fue guardada de forma anónima.
                    </p>
                </div>

                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex flex-col gap-3">
                    <button type="button" wire:click="nuevaEncuestaOtroProfesor"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#00376e] hover:bg-[#002a54] text-white font-bold px-6 py-3.5 text-sm shadow transition cursor-pointer">
                        <flux:icon.users class="size-5" />
                        Evaluar a otro Profesor / Siguiente Persona
                    </button>

                    @if ($this->profesorSeleccionado)
                        <button type="button" wire:click="nuevaEncuestaMismoProfesor"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold px-6 py-3 text-xs transition cursor-pointer">
                            <flux:icon.arrow-path class="size-4" />
                            Siguiente persona para el mismo profesor ({{ $this->profesorSeleccionado->nombreCompleto() }})
                        </button>
                    @endif
                </div>
            </div>
        </main>
    @endif
</div>
