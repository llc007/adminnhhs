<?php

use App\Models\EvaluacionDirectivo;
use App\Models\EvaluacionDirectivoAsignacion;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.blank')] #[Title('Evaluación de Directivos y Departamentos')] class extends Component
{
    public ?int $schoolId = null;

    public string $pinIngresado = '';

    public bool $pinValidado = false;

    public ?string $departamento = null;

    public ?int $evaluadoId = null;

    public string $paso = 'pin'; // 'pin' | 'departamentos' | 'persona' | 'encuesta' | 'completado'

    /** @var array<string, int> */
    public array $respuestas = [];

    public string $intentoId = '';

    public ?string $mensajeError = null;

    /** @var array<int, string> */
    public array $evaluadosEnSesion = [];

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
        $this->evaluadosEnSesion = session("encuesta_dir_completados_{$this->schoolId}", []);

        $pinGuardado = session("encuesta_dir_pin_valor_{$this->schoolId}");
        if ($pinGuardado && $school->encuestasDirectivosHabilitadas() && $pinGuardado === $school->pinEncuestaDirectivos()) {
            $this->pinValidado = true;
            $this->paso = 'departamentos';
        }
    }

    public function validarPin(): void
    {
        $school = $this->school;
        if (! $school || ! $school->encuestasDirectivosHabilitadas()) {
            $this->mensajeError = 'El periodo de evaluación de directivos se encuentra cerrado.';

            return;
        }

        $pin = trim($this->pinIngresado);
        if ($pin === '') {
            $this->mensajeError = 'Debes ingresar el PIN de acceso.';

            return;
        }

        if ($pin !== $school->pinEncuestaDirectivos()) {
            $this->mensajeError = 'PIN incorrecto. Verifica el código con la comisión encargada.';

            return;
        }

        $this->pinValidado = true;
        $this->mensajeError = null;
        $this->paso = 'departamentos';

        session(["encuesta_dir_pin_valor_{$this->schoolId}" => $pin]);
    }

    public function bloquearPin(): void
    {
        session()->forget([
            "encuesta_dir_pin_valor_{$this->schoolId}",
            "encuesta_dir_completados_{$this->schoolId}",
        ]);

        $this->pinValidado = false;
        $this->pinIngresado = '';
        $this->departamento = null;
        $this->evaluadoId = null;
        $this->respuestas = [];
        $this->evaluadosEnSesion = [];
        $this->mensajeError = null;
        $this->paso = 'pin';
    }

    public function reiniciarSesionDocente(): void
    {
        session()->forget("encuesta_dir_completados_{$this->schoolId}");
        $this->evaluadosEnSesion = [];
        $this->departamento = null;
        $this->evaluadoId = null;
        $this->respuestas = [];
        $this->mensajeError = null;
        $this->paso = 'departamentos';
    }

    public function seleccionarDepartamento(string $depto): void
    {
        if (! $this->pinValidado) {
            $this->paso = 'pin';

            return;
        }

        if (! array_key_exists($depto, EvaluacionDirectivo::DEPARTAMENTOS)) {
            return;
        }

        $this->departamento = $depto;
        $this->evaluadoId = null;
        $this->respuestas = [];
        $this->mensajeError = null;

        $integrantes = $this->integrantesDepartamentoActual;

        // Si el departamento no tiene integrantes individuales asignados, se evalúa al Departamento en General
        if ($integrantes->isEmpty()) {
            $this->evaluadoId = null;
            $this->intentoId = (string) Str::uuid();
            $this->paso = 'encuesta';

            return;
        }

        // Si solo hay 1 persona asignada a este departamento, entra directo a la encuesta
        if ($integrantes->count() === 1) {
            $this->evaluadoId = $integrantes->first()->user_id;
            $this->intentoId = (string) Str::uuid();
            $this->paso = 'encuesta';

            return;
        }

        $this->paso = 'persona';
    }

    public function seleccionarPersona(int $userId): void
    {
        if (! $this->pinValidado || ! $this->departamento) {
            $this->paso = 'departamentos';

            return;
        }

        $asignacion = EvaluacionDirectivoAsignacion::query()
            ->where('school_id', $this->schoolId)
            ->where('departamento', $this->departamento)
            ->where('user_id', $userId)
            ->first();

        if (! $asignacion) {
            return;
        }

        $this->evaluadoId = $asignacion->user_id;
        $this->respuestas = [];
        $this->intentoId = (string) Str::uuid();
        $this->mensajeError = null;
        $this->paso = 'encuesta';
    }

    public function volverADepartamentos(): void
    {
        $this->departamento = null;
        $this->evaluadoId = null;
        $this->respuestas = [];
        $this->mensajeError = null;
        $this->paso = 'departamentos';
    }

    public function responderPregunta(int $numero, int $puntaje): void
    {
        if (! $this->departamento) {
            return;
        }

        $opcionesNa = EvaluacionDirectivo::opcionesNoAplica($this->departamento, $numero);
        $esValida = array_key_exists($puntaje, EvaluacionDirectivo::ALTERNATIVAS)
            || array_key_exists($puntaje, $opcionesNa);

        if (! $esValida) {
            return;
        }

        $this->respuestas[(string) $numero] = $puntaje;
        $this->mensajeError = null;
    }

    public function enviarEncuesta(): void
    {
        if ($this->paso !== 'encuesta' || empty($this->respuestas) || ! $this->departamento) {
            return;
        }

        // Si el departamento tiene integrantes asignados, debe haberse seleccionado uno
        if ($this->integrantesDepartamentoActual->isNotEmpty() && ! $this->asignacionActual) {
            return;
        }

        if (! $this->pinValidado) {
            $this->paso = 'pin';

            return;
        }

        if (! $this->school?->encuestasDirectivosHabilitadas()) {
            $this->mensajeError = 'El periodo de evaluación se encuentra cerrado.';

            return;
        }

        $preguntas = $this->preguntasActuales;
        $respuestasLimpias = [];

        foreach (array_keys($preguntas) as $num) {
            $key = (string) $num;
            if (! isset($this->respuestas[$key])) {
                $this->mensajeError = "Por favor responde todas las preguntas antes de enviar (falta la pregunta N° {$num}).";

                return;
            }

            $val = (int) $this->respuestas[$key];
            $opcionesNa = EvaluacionDirectivo::opcionesNoAplica($this->departamento, $num);
            $esValida = array_key_exists($val, EvaluacionDirectivo::ALTERNATIVAS)
                || array_key_exists($val, $opcionesNa);

            if (! $esValida) {
                $this->mensajeError = "Por favor responde todas las preguntas antes de enviar (falta la pregunta N° {$num}).";

                return;
            }

            $respuestasLimpias[$key] = $val;
        }

        // Protección anti doble toque
        if ($this->intentoId !== '') {
            if (! Cache::add("eval_directivo_intento_{$this->intentoId}", true, 120)) {
                $this->respuestas = [];
                $this->paso = 'completado';

                return;
            }
        }

        $totalPreguntas = count($preguntas);
        $respuestasValidas = array_filter($respuestasLimpias, fn (int $v) => $v >= 0);
        $cantidadValidas = count($respuestasValidas);
        $sumaValidas = array_sum($respuestasValidas);

        $puntajeMaximo = $totalPreguntas * 3;
        $promedio = $cantidadValidas > 0 ? round($sumaValidas / $cantidadValidas, 2) : 0.0;
        $puntajeObtenido = $cantidadValidas > 0
            ? (int) round(($sumaValidas / $cantidadValidas) * $totalPreguntas)
            : 0;

        $evaluadoUserId = $this->asignacionActual?->user_id;

        EvaluacionDirectivo::create([
            'school_id' => $this->schoolId,
            'evaluado_id' => $evaluadoUserId,
            'departamento' => $this->departamento,
            'respuestas' => $respuestasLimpias,
            'puntaje_obtenido' => $puntajeObtenido,
            'puntaje_maximo' => $puntajeMaximo,
            'promedio' => $promedio,
            'registrado_por_id' => auth()->id(),
        ]);

        $marca = $evaluadoUserId ? "{$this->departamento}:{$evaluadoUserId}" : "{$this->departamento}:general";
        if (! in_array($marca, $this->evaluadosEnSesion, true)) {
            $this->evaluadosEnSesion[] = $marca;
            session(["encuesta_dir_completados_{$this->schoolId}" => $this->evaluadosEnSesion]);
        }

        $this->respuestas = [];
        $this->intentoId = '';
        $this->mensajeError = null;
        $this->paso = 'completado';
    }

    #[Computed]
    public function school(): ?School
    {
        return $this->schoolId ? School::find($this->schoolId) : null;
    }

    #[Computed]
    public function asignacionesPorDepartamento()
    {
        return EvaluacionDirectivoAsignacion::query()
            ->with('user')
            ->where('school_id', $this->schoolId)
            ->get()
            ->groupBy('departamento');
    }

    #[Computed]
    public function integrantesDepartamentoActual()
    {
        if (! $this->departamento) {
            return collect();
        }

        return EvaluacionDirectivoAsignacion::query()
            ->with('user')
            ->where('school_id', $this->schoolId)
            ->where('departamento', $this->departamento)
            ->get();
    }

    #[Computed]
    public function asignacionActual(): ?EvaluacionDirectivoAsignacion
    {
        if (! $this->departamento || ! $this->evaluadoId) {
            return null;
        }

        return EvaluacionDirectivoAsignacion::query()
            ->with('user')
            ->where('school_id', $this->schoolId)
            ->where('departamento', $this->departamento)
            ->where('user_id', $this->evaluadoId)
            ->first();
    }

    /**
     * @return array<int, array{texto: string, no_aplica?: array<int, string>}>
     */
    #[Computed]
    public function preguntasActuales(): array
    {
        return $this->departamento ? EvaluacionDirectivo::preguntasDe($this->departamento) : [];
    }
};
?>

<div class="min-h-screen bg-zinc-100 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 flex flex-col">
    {{-- Barra institucional superior --}}
    <header class="bg-[#00376e] text-white shadow-md">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-10 rounded-xl bg-white/15 text-white shrink-0">
                    <flux:icon.building-library class="size-6" />
                </div>
                <div>
                    <h1 class="text-sm sm:text-base font-bold tracking-tight">
                        {{ $this->school?->name ?? 'Liceo New Heaven High School' }}
                    </h1>
                    <p class="text-[11px] text-blue-200 uppercase tracking-wider font-semibold">
                        Evaluación Anónima de Directivos y Departamentos
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
                        <span class="hidden sm:inline">Salir / Bloquear</span>
                    </button>
                @endif

                @auth
                    @if (auth()->user()->hasRole(['comision_ed', 'superadmin']))
                        <a href="{{ route('encuestas.directivos.resultados') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1.5 text-xs font-medium text-white transition">
                            <flux:icon.chart-bar class="size-4" />
                            <span class="hidden sm:inline">Resultados</span>
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    {{-- SI LAS ENCUESTAS ESTÁN DESHABILITADAS --}}
    @if (! $this->school?->encuestasDirectivosHabilitadas())
        <main class="flex-1 max-w-lg w-full mx-auto px-4 sm:px-6 py-12 flex items-center justify-center">
            <div class="w-full bg-white dark:bg-zinc-900 rounded-2xl shadow-lg border border-zinc-200 dark:border-zinc-800 p-8 text-center space-y-5">
                <div class="mx-auto size-16 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <flux:icon.lock-closed class="size-8" />
                </div>
                <div class="space-y-2">
                    <h2 class="text-xl font-extrabold text-zinc-900 dark:text-white">
                        Periodo de Evaluación Cerrado
                    </h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        La recepción de respuestas de Evaluación de Directivos y Departamentos se encuentra deshabilitada en este momento.
                    </p>
                </div>
            </div>
        </main>

    {{-- PASO 0: INGRESO DE PIN --}}
    @elseif (! $pinValidado || $paso === 'pin')
        <main class="flex-1 max-w-md w-full mx-auto px-4 sm:px-6 py-12 flex items-center justify-center">
            <div class="w-full bg-white dark:bg-zinc-900 rounded-2xl shadow-lg border border-zinc-200 dark:border-zinc-800 p-6 sm:p-8 space-y-6">
                <div class="text-center space-y-2">
                    <div class="mx-auto size-14 rounded-2xl bg-[#00376e]/10 dark:bg-blue-950 text-[#00376e] dark:text-blue-400 flex items-center justify-center">
                        <flux:icon.key class="size-7" />
                    </div>
                    <h2 class="text-xl font-extrabold text-zinc-900 dark:text-white">
                        Evaluación de Directivos y Departamentos
                    </h2>
                    <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-950 px-3 py-1 text-xs font-bold text-[#00376e] dark:text-blue-300">
                        Encuesta para Cuerpo Docente
                    </span>
                    <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">
                        Ingresa el PIN de acceso para evaluar de forma anónima a los 8 departamentos del establecimiento.
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

    {{-- PASO 1: SELECCIONAR UNO DE LOS 8 DEPARTAMENTOS --}}
    @elseif ($paso === 'departamentos')
        <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-8 space-y-6">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-zinc-200 dark:border-zinc-800 p-6 sm:p-8 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-5">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                            Selecciona el Departamento a Evaluar
                        </h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Elige uno de los 8 departamentos. En aquellos con más de un integrante podrás seleccionar a la persona con la que trabajas directamente.
                        </p>
                    </div>

                    @if (count($evaluadosEnSesion) > 0)
                        <button type="button" wire:click="reiniciarSesionDocente"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 px-3 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-300 transition cursor-pointer shrink-0">
                            <flux:icon.arrow-path class="size-4" />
                            Nuevo Docente ({{ count($evaluadosEnSesion) }} eval.)
                        </button>
                    @endif
                </div>

                @if ($mensajeError)
                    <div class="rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 p-4 text-sm text-amber-800 dark:text-amber-300 flex items-center gap-3">
                        <flux:icon.exclamation-triangle class="size-5 shrink-0 text-amber-600" />
                        <span>{{ $mensajeError }}</span>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach (EvaluacionDirectivo::DEPARTAMENTOS as $clave => $info)
                        @php
                            $integrantes = $this->asignacionesPorDepartamento->get($clave, collect());
                            $cantPreguntas = count(EvaluacionDirectivo::preguntasDe($clave));
                            $evaluadosDepto = $integrantes->isEmpty()
                                ? (in_array("{$clave}:general", $evaluadosEnSesion, true) ? 1 : 0)
                                : $integrantes->filter(fn ($asig) => in_array("{$clave}:{$asig->user_id}", $evaluadosEnSesion, true))->count();
                        @endphp
                        <button type="button" wire:click="seleccionarDepartamento('{{ $clave }}')"
                            class="flex flex-col justify-between gap-3 p-5 rounded-xl border text-left transition cursor-pointer {{ $evaluadosDepto > 0
                                ? 'border-emerald-300 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20 hover:border-emerald-400'
                                : 'border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-800/40 hover:border-[#00376e] hover:bg-white dark:hover:bg-zinc-800' }}">
                            <div class="flex items-start justify-between gap-3 w-full">
                                <div>
                                    <span class="inline-flex items-center rounded-md bg-blue-100 dark:bg-blue-950 px-2 py-0.5 text-[11px] font-bold text-[#00376e] dark:text-blue-300">
                                        {{ $cantPreguntas }} preguntas
                                    </span>
                                    <h3 class="text-base font-extrabold text-zinc-900 dark:text-white mt-1.5">
                                        {{ $info['nombre'] }}
                                    </h3>
                                </div>

                                @if ($evaluadosDepto > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/60 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800 dark:text-emerald-300 shrink-0">
                                        <flux:icon.check-circle class="size-3.5" />
                                        Evaluado
                                    </span>
                                @else
                                    <flux:icon.chevron-right class="size-5 text-zinc-400 shrink-0 mt-1" />
                                @endif
                            </div>

                            <div class="w-full pt-2 border-t border-zinc-200/70 dark:border-zinc-700/60">
                                @if ($integrantes->isEmpty())
                                    <span class="inline-flex items-center gap-1 rounded-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300">
                                        Evaluación General del Departamento
                                    </span>
                                @else
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($integrantes as $asig)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 text-[11px] font-semibold text-zinc-700 dark:text-zinc-300">
                                                <span>{{ $asig->user?->nombreCompleto() }}</span>
                                                @if ($asig->etiqueta)
                                                    <span class="text-blue-600 dark:text-blue-400 font-bold">({{ $asig->etiqueta }})</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </main>

    {{-- PASO 2: ELEGIR A LA PERSONA DEL DEPARTAMENTO (BÁSICA / MEDIA / ETC.) --}}
    @elseif ($paso === 'persona' && $departamento)
        <main class="flex-1 max-w-2xl w-full mx-auto px-4 sm:px-6 py-10 space-y-6">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-zinc-200 dark:border-zinc-800 p-6 sm:p-8 space-y-6">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-5">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">
                            Departamento: {{ EvaluacionDirectivo::nombreDepartamento($departamento) }}
                        </span>
                        <h2 class="text-xl font-extrabold text-zinc-900 dark:text-white mt-0.5">
                            ¿A qué integrante vas a evaluar?
                        </h2>
                        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                            Selecciona a la persona de este departamento con la cual trabajas directamente (según tu ciclo o nivel).
                        </p>
                    </div>

                    <button type="button" wire:click="volverADepartamentos"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 px-3 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition cursor-pointer shrink-0">
                        <flux:icon.arrow-left class="size-4" />
                        Volver
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-3.5">
                    @foreach ($this->integrantesDepartamentoActual as $asig)
                        @php
                            $yaEvaluado = in_array("{$departamento}:{$asig->user_id}", $evaluadosEnSesion, true);
                        @endphp
                        <button type="button" wire:click="seleccionarPersona({{ $asig->user_id }})"
                            class="flex items-center justify-between gap-4 p-5 rounded-xl border-2 text-left transition cursor-pointer {{ $yaEvaluado
                                ? 'border-emerald-300 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20'
                                : 'border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-800/40 hover:border-[#00376e] hover:bg-blue-50/40 dark:hover:bg-zinc-800' }}">
                            <div class="flex items-center gap-4">
                                <div class="size-12 rounded-xl bg-[#00376e] text-white flex items-center justify-center font-extrabold text-base shrink-0 shadow-xs">
                                    {{ $asig->user?->initials() }}
                                </div>
                                <div>
                                    <p class="text-base sm:text-lg font-extrabold text-zinc-900 dark:text-white">
                                        {{ $asig->user?->nombreCompleto() }}
                                    </p>
                                    @if ($asig->etiqueta)
                                        <span class="inline-flex items-center rounded-md bg-blue-100 dark:bg-blue-950 px-2.5 py-0.5 text-xs font-bold text-[#00376e] dark:text-blue-300 mt-1">
                                            {{ $asig->etiqueta }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                @if ($yaEvaluado)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/60 px-2.5 py-1 text-xs font-bold text-emerald-800 dark:text-emerald-300">
                                        <flux:icon.check-circle class="size-4" />
                                        Ya evaluado
                                    </span>
                                @endif
                                <flux:icon.arrow-right class="size-5 text-[#00376e] dark:text-blue-400" />
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </main>

    {{-- PASO 3: RESPONDER EL CUESTIONARIO DEL DEPARTAMENTO --}}
    @elseif ($paso === 'encuesta' && $departamento)
        @php
            $preguntas = $this->preguntasActuales;
            $totalPreguntas = count($preguntas);
            $respondidas = count($respuestas);
            $porcentajeAvance = $totalPreguntas > 0 ? round(($respondidas / $totalPreguntas) * 100) : 0;
            $evaluadoUser = $this->asignacionActual?->user;
            $nombreDepartamentoActual = EvaluacionDirectivo::nombreDepartamento($departamento);
        @endphp

        {{-- Cabecera fija con el nombre del directivo/profesional evaluado o departamento general --}}
        <div class="sticky top-0 z-30 bg-white/95 dark:bg-zinc-900/95 backdrop-blur border-b-2 border-[#00376e] shadow-md">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="size-12 rounded-xl bg-[#00376e] text-white flex items-center justify-center font-extrabold text-base shrink-0 shadow">
                        @if ($evaluadoUser)
                            {{ $evaluadoUser->initials() }}
                        @else
                            <flux:icon.building-library class="size-6" />
                        @endif
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-md bg-blue-100 dark:bg-blue-900/60 px-2 py-0.5 text-[11px] font-bold text-[#00376e] dark:text-blue-200 uppercase">
                                {{ $nombreDepartamentoActual }}
                            </span>
                            @if ($this->asignacionActual?->etiqueta)
                                <span class="inline-flex items-center rounded-md bg-indigo-100 dark:bg-indigo-950 px-2 py-0.5 text-[11px] font-bold text-indigo-800 dark:text-indigo-300">
                                    {{ $this->asignacionActual->etiqueta }}
                                </span>
                            @elseif (! $evaluadoUser)
                                <span class="inline-flex items-center rounded-md bg-emerald-100 dark:bg-emerald-950 px-2 py-0.5 text-[11px] font-bold text-emerald-800 dark:text-emerald-300">
                                    Evaluación General del Departamento
                                </span>
                            @endif
                        </div>
                        <h2 class="text-lg sm:text-xl font-extrabold text-zinc-900 dark:text-white leading-tight mt-0.5">
                            {{ $evaluadoUser ? $evaluadoUser->nombreCompleto() : $nombreDepartamentoActual }}
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

                    <button type="button" wire:click="volverADepartamentos"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 px-3 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition cursor-pointer">
                        <flux:icon.arrows-right-left class="size-4" />
                        Cambiar Depto.
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

            @foreach ($preguntas as $numero => $datosPregunta)
                @php
                    $respuestaActual = $respuestas[(string) $numero] ?? null;
                    $opcionesNoAplica = $datosPregunta['no_aplica'] ?? [];
                @endphp
                <div wire:key="pregunta-dir-{{ $numero }}"
                    class="bg-white dark:bg-zinc-900 rounded-2xl border transition p-5 sm:p-6 shadow-xs {{ $respuestaActual !== null ? 'border-emerald-300 dark:border-emerald-800/70' : 'border-zinc-200 dark:border-zinc-800' }}">
                    <div class="flex items-start gap-3.5">
                        <span class="inline-flex items-center justify-center size-8 rounded-lg font-extrabold text-sm shrink-0 {{ $respuestaActual !== null ? 'bg-emerald-600 text-white' : 'bg-[#00376e] text-white' }}">
                            {{ $numero }}
                        </span>
                        <p class="text-sm sm:text-base font-semibold text-zinc-900 dark:text-zinc-100 leading-relaxed pt-0.5">
                            {{ $datosPregunta['texto'] }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
                        @foreach (EvaluacionDirectivo::ALTERNATIVAS as $puntaje => $etiqueta)
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

                    @if (! empty($opcionesNoAplica))
                        <div class="mt-3 space-y-2">
                            @foreach ($opcionesNoAplica as $valNa => $textoNa)
                                @php
                                    $activaNa = $respuestaActual !== null && (int) $respuestaActual === $valNa;
                                @endphp
                                <button type="button"
                                    wire:click="responderPregunta({{ $numero }}, {{ $valNa }})"
                                    class="w-full flex items-center justify-center gap-2 rounded-xl border-2 px-4 py-3 text-xs sm:text-sm font-bold transition cursor-pointer {{ $activaNa
                                        ? 'border-zinc-700 bg-zinc-700 text-white shadow-sm dark:border-zinc-500 dark:bg-zinc-600'
                                        : 'border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-50/70 dark:bg-zinc-800/40 text-zinc-600 dark:text-zinc-300 hover:border-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                                    @if ($activaNa)
                                        <flux:icon.check-circle class="size-5 shrink-0 text-emerald-300" />
                                    @else
                                        <flux:icon.minus-circle class="size-4 shrink-0 text-zinc-400" />
                                    @endif
                                    <span>{{ $textoNa }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
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

                <button type="button" wire:click="enviarEncuesta" wire:loading.attr="disabled" wire:target="enviarEncuesta"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-bold px-8 py-3.5 text-base shadow-md transition cursor-pointer shrink-0">
                    <flux:icon.paper-airplane wire:loading.remove wire:target="enviarEncuesta" class="size-5" />
                    <span wire:loading.remove wire:target="enviarEncuesta">Enviar Evaluación</span>
                    <span wire:loading wire:target="enviarEncuesta">Enviando...</span>
                </button>
            </div>
        </main>

    {{-- PASO 4: CONFIRMACIÓN Y CONTINUAR CON EL SIGUIENTE DEPARTAMENTO --}}
    @elseif ($paso === 'completado')
        <main class="flex-1 max-w-xl w-full mx-auto px-4 sm:px-6 py-12 flex items-center justify-center">
            <div class="w-full bg-white dark:bg-zinc-900 rounded-2xl shadow-lg border border-zinc-200 dark:border-zinc-800 p-8 text-center space-y-6">
                <div class="mx-auto size-16 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <flux:icon.check-badge class="size-10" />
                </div>

                <div class="space-y-2">
                    <h2 class="text-2xl font-extrabold text-zinc-900 dark:text-white">
                        ¡Evaluación registrada!
                    </h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Tu evaluación para <strong>{{ $this->asignacionActual?->user?->nombreCompleto() ?? ($departamento ? EvaluacionDirectivo::nombreDepartamento($departamento) : '') }}</strong>
                        @if ($this->asignacionActual?->user && $departamento)
                            ({{ EvaluacionDirectivo::nombreDepartamento($departamento) }})
                        @endif
                        fue guardada de forma anónima.
                    </p>
                </div>

                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex flex-col gap-3">
                    <button type="button" wire:click="volverADepartamentos"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#00376e] hover:bg-[#002a54] text-white font-bold px-6 py-3.5 text-sm shadow transition cursor-pointer">
                        <flux:icon.building-library class="size-5" />
                        Evaluar otro Departamento
                    </button>
                </div>
            </div>
        </main>
    @endif
</div>
