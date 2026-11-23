<?php

use App\Models\EvaluacionDocente;
use App\Models\EvaluacionDirectivo;
use App\Models\EvaluacionDirectivoAsignacion;
use App\Models\School;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Evaluación de Directivos y Departamentos — Resultados')] class extends Component
{
    #[Url]
    public string $departamentoVista = 'capellania';

    // Modal asignación de integrantes a los 8 departamentos
    public bool $modalAsignaciones = false;

    public string $asigDepartamento = 'capellania';

    public ?int $asigUserId = null;

    public string $asigEtiqueta = '';

    // Modal administración de PIN y estado
    public bool $modalAdminEncuesta = false;

    public bool $encuestaHabilitada = true;

    public string $pinDirectivos = '9012';

    // Modal reiniciar encuestas
    public bool $modalConfirmarReset = false;

    public string $tipoReset = 'todas'; // 'todas' o clave del departamento

    // Modal detalle por integrante (estilo Google Forms)
    public bool $modalDetalle = false;

    public ?int $evaluadoDetalleId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['comision_ed', 'superadmin'])) {
            abort(403, 'Solo los integrantes con el rol Comisión ED o Superadmin pueden acceder a esta sección.');
        }

        if (! array_key_exists($this->departamentoVista, EvaluacionDirectivo::DEPARTAMENTOS)) {
            $this->departamentoVista = 'capellania';
        }

        $school = $this->school;
        if ($school) {
            $this->encuestaHabilitada = $school->encuestasDirectivosHabilitadas();
            $this->pinDirectivos = $school->pinEncuestaDirectivos();
        }
    }

    public function cambiarDepartamento(string $depto): void
    {
        if (array_key_exists($depto, EvaluacionDirectivo::DEPARTAMENTOS)) {
            $this->departamentoVista = $depto;
            $this->asigDepartamento = $depto;
        }
    }

    public function abrirModalAsignaciones(?string $depto = null): void
    {
        if ($depto && array_key_exists($depto, EvaluacionDirectivo::DEPARTAMENTOS)) {
            $this->asigDepartamento = $depto;
        } else {
            $this->asigDepartamento = $this->departamentoVista;
        }

        $this->asigUserId = null;
        $this->asigEtiqueta = '';
        $this->modalAsignaciones = true;
    }

    public function agregarAsignacion(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['comision_ed', 'superadmin'])) {
            abort(403);
        }

        $this->validate([
            'asigDepartamento' => ['required', 'string'],
            'asigUserId' => ['required', 'integer', 'exists:users,id'],
            'asigEtiqueta' => ['nullable', 'string', 'max:80'],
        ], [
            'asigUserId.required' => 'Debes seleccionar a un funcionario.',
        ]);

        if (! array_key_exists($this->asigDepartamento, EvaluacionDirectivo::DEPARTAMENTOS)) {
            return;
        }

        $schoolId = $this->school?->id;
        if (! $schoolId) {
            return;
        }

        EvaluacionDirectivoAsignacion::updateOrCreate(
            [
                'school_id' => $schoolId,
                'user_id' => $this->asigUserId,
                'departamento' => $this->asigDepartamento,
            ],
            [
                'etiqueta' => trim($this->asigEtiqueta) !== '' ? trim($this->asigEtiqueta) : null,
            ]
        );

        $this->asigUserId = null;
        $this->asigEtiqueta = '';

        unset($this->todasAsignaciones, $this->tablaResultados, $this->kpis);

        Flux::toast(
            heading: 'Integrante asignado',
            text: 'El funcionario fue asignado al departamento correctamente.',
            variant: 'success',
        );
    }

    public function eliminarAsignacion(int $asignacionId): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['comision_ed', 'superadmin'])) {
            abort(403);
        }

        $schoolId = $this->school?->id;
        EvaluacionDirectivoAsignacion::query()
            ->where('school_id', $schoolId)
            ->where('id', $asignacionId)
            ->delete();

        unset($this->todasAsignaciones, $this->tablaResultados, $this->kpis);

        Flux::toast(
            heading: 'Asignación eliminada',
            text: 'El integrante fue removido del departamento.',
            variant: 'success',
        );
    }

    public function guardarConfiguracionEncuesta(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['comision_ed', 'superadmin'])) {
            abort(403);
        }

        $this->validate([
            'pinDirectivos' => ['required', 'string', 'min:3', 'max:10'],
        ], [
            'pinDirectivos.required' => 'El PIN de acceso es obligatorio.',
        ]);

        $school = $this->school;
        if (! $school) {
            return;
        }

        $school->actualizarConfigEncuestaDirectivos(
            $this->encuestaHabilitada,
            $this->pinDirectivos
        );

        $this->modalAdminEncuesta = false;

        Flux::toast(
            heading: 'Configuración actualizada',
            text: $this->encuestaHabilitada
                ? 'Las encuestas de Directivos están HABILITADAS.'
                : 'El completado de encuestas de Directivos ha sido DESHABILITADO.',
            variant: $this->encuestaHabilitada ? 'success' : 'warning',
        );
    }

    public function toggleEstadoRapido(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['superadmin', 'comision_ed'])) {
            abort(403);
        }

        $this->encuestaHabilitada = ! $this->encuestaHabilitada;
        $this->guardarConfiguracionEncuesta();
    }

    public function reiniciarEncuestas(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['superadmin', 'comision_ed'])) {
            abort(403);
        }

        $schoolId = $this->school?->id;
        if (! $schoolId) {
            return;
        }

        $query = EvaluacionDirectivo::query()->where('school_id', $schoolId);

        if ($this->tipoReset !== 'todas' && array_key_exists($this->tipoReset, EvaluacionDirectivo::DEPARTAMENTOS)) {
            $query->where('departamento', $this->tipoReset);
        }

        $eliminadas = $query->delete();

        $this->modalConfirmarReset = false;
        $this->modalDetalle = false;
        $this->evaluadoDetalleId = null;

        unset($this->kpis, $this->tablaResultados, $this->detalleEvaluado);

        Flux::toast(
            heading: 'Encuestas reiniciadas',
            text: "Se han eliminado {$eliminadas} evaluaciones de directivos correctamente.",
            variant: 'success',
        );
    }

    public function limpiarDuplicadas(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['superadmin', 'comision_ed'])) {
            abort(403);
        }

        $schoolId = $this->school?->id;
        if (! $schoolId) {
            return;
        }

        $todas = EvaluacionDirectivo::query()
            ->where('school_id', $schoolId)
            ->orderBy('departamento')
            ->orderBy('evaluado_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $idsAEliminar = [];
        /** @var EvaluacionDirectivo|null $prev */
        $prev = null;

        foreach ($todas as $actual) {
            if (
                $prev !== null
                && $prev->departamento === $actual->departamento
                && $prev->evaluado_id === $actual->evaluado_id
                && json_encode($prev->respuestas) === json_encode($actual->respuestas)
                && $prev->created_at
                && $actual->created_at
                && abs($actual->created_at->diffInSeconds($prev->created_at)) <= 30
            ) {
                $idsAEliminar[] = $actual->id;
            } else {
                $prev = $actual;
            }
        }

        if (! empty($idsAEliminar)) {
            EvaluacionDirectivo::whereIn('id', $idsAEliminar)->delete();
        }

        unset($this->kpis, $this->tablaResultados, $this->detalleEvaluado);

        $cant = count($idsAEliminar);
        Flux::toast(
            heading: $cant > 0 ? 'Duplicadas eliminadas' : 'Sin duplicadas detectadas',
            text: $cant > 0
                ? "Se detectaron y eliminaron {$cant} evaluaciones duplicadas por doble toque."
                : 'No se encontraron evaluaciones idénticas enviadas con pocos segundos de diferencia.',
            variant: $cant > 0 ? 'success' : 'info',
        );
    }

    public function eliminarRespuestaIndividual(int $evaluacionId): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole(['superadmin', 'comision_ed'])) {
            abort(403);
        }

        $schoolId = $this->school?->id;
        $eval = EvaluacionDirectivo::query()
            ->where('school_id', $schoolId)
            ->find($evaluacionId);

        if ($eval) {
            $eval->delete();
            unset($this->kpis, $this->tablaResultados, $this->detalleEvaluado);

            Flux::toast(
                heading: 'Respuesta eliminada',
                text: 'La evaluación seleccionada fue eliminada correctamente.',
                variant: 'success',
            );
        }
    }

    public function verDetalleEvaluado(int $userId): void
    {
        $this->evaluadoDetalleId = $userId;
        $this->modalDetalle = true;
    }

    #[Computed]
    public function school(): ?School
    {
        return auth()->user()?->currentSchool ?? School::first();
    }

    /**
     * @return array<int, array{texto: string, no_aplica?: array<int, string>}>
     */
    #[Computed]
    public function preguntasVista(): array
    {
        return EvaluacionDirectivo::preguntasDe($this->departamentoVista);
    }

    #[Computed]
    public function funcionariosColegio()
    {
        $schoolId = $this->school?->id;

        return User::query()
            ->whereHas('schools', fn ($q) => $q->where('school_id', $schoolId))
            ->whereDoesntHave('roles', function ($q) use ($schoolId) {
                $q->where('roles.team_id', $schoolId)
                    ->where('roles.name', 'estudiante');
            })
            ->orderBy('nombres')
            ->orderBy('apellido_pat')
            ->get();
    }

    #[Computed]
    public function todasAsignaciones()
    {
        $schoolId = $this->school?->id;

        return EvaluacionDirectivoAsignacion::query()
            ->with('user')
            ->where('school_id', $schoolId)
            ->get()
            ->groupBy('departamento');
    }

    /**
     * @return array{total_evaluaciones: int, integrantes_asignados: int, departamentos_evaluados: int, promedio_general: float}
     */
    #[Computed]
    public function kpis(): array
    {
        $schoolId = $this->school?->id;

        $evaluaciones = EvaluacionDirectivo::query()
            ->where('school_id', $schoolId)
            ->get(['evaluado_id', 'departamento', 'promedio']);

        $integrantesAsignados = EvaluacionDirectivoAsignacion::query()
            ->where('school_id', $schoolId)
            ->count();

        return [
            'total_evaluaciones' => $evaluaciones->count(),
            'integrantes_asignados' => $integrantesAsignados,
            'departamentos_evaluados' => $evaluaciones->pluck('departamento')->unique()->count(),
            'promedio_general' => $evaluaciones->isNotEmpty() ? round((float) $evaluaciones->avg('promedio'), 2) : 0.0,
        ];
    }

    #[Computed]
    public function tablaResultados()
    {
        $schoolId = $this->school?->id;
        $preguntas = $this->preguntasVista;
        $totalPreguntas = count($preguntas);
        $puntajeMaximoRef = $totalPreguntas * 3;

        $asignaciones = EvaluacionDirectivoAsignacion::query()
            ->with('user')
            ->where('school_id', $schoolId)
            ->where('departamento', $this->departamentoVista)
            ->get();

        $evaluacionesDepto = EvaluacionDirectivo::query()
            ->where('school_id', $schoolId)
            ->where('departamento', $this->departamentoVista)
            ->get()
            ->groupBy(fn (EvaluacionDirectivo $e) => $e->evaluado_id ?? 0);

        $armarFila = function ($evals, ?User $user, ?string $etiqueta, string $rowKey, int $detalleId) use ($preguntas, $puntajeMaximoRef) {
            $cantidad = $evals->count();

            $promediosPorPregunta = [];
            foreach (array_keys($preguntas) as $num) {
                $key = (string) $num;
                $valores = $evals
                    ->map(fn (EvaluacionDirectivo $e) => $e->respuestas[$key] ?? null)
                    ->filter(fn ($v) => $v !== null && (int) $v >= 0);

                $promediosPorPregunta[$num] = $valores->isNotEmpty()
                    ? round((float) $valores->avg(), 2)
                    : null;
            }

            $puntajeTotalPromedio = $cantidad > 0
                ? round((float) $evals->avg('puntaje_obtenido'), 1)
                : null;

            $promedioGeneral = $cantidad > 0
                ? round((float) $evals->avg('promedio'), 2)
                : null;

            $clasificacion = EvaluacionDocente::clasificacionPorcentual($puntajeTotalPromedio, $puntajeMaximoRef);

            return [
                'row_key' => $rowKey,
                'detalle_id' => $detalleId,
                'user' => $user,
                'nombre_mostrar' => $user ? $user->nombreCompleto() : EvaluacionDirectivo::nombreDepartamento($this->departamentoVista),
                'etiqueta' => $etiqueta,
                'cantidad' => $cantidad,
                'promedios_pregunta' => $promediosPorPregunta,
                'puntaje_total_promedio' => $puntajeTotalPromedio,
                'puntaje_maximo' => $puntajeMaximoRef,
                'promedio_general' => $promedioGeneral,
                'clasificacion' => $clasificacion,
            ];
        };

        $filas = $asignaciones->map(function (EvaluacionDirectivoAsignacion $asig) use ($evaluacionesDepto, $armarFila) {
            $evals = $evaluacionesDepto->get($asig->user_id, collect());

            return $armarFila($evals, $asig->user, $asig->etiqueta, 'asig-'.$asig->id, (int) $asig->user_id);
        });

        $evalsGenerales = $evaluacionesDepto->get(0, collect());
        if ($asignaciones->isEmpty() || $evalsGenerales->isNotEmpty()) {
            $filas->prepend(
                $armarFila($evalsGenerales, null, 'Evaluación General del Departamento', 'general-'.$this->departamentoVista, 0)
            );
        }

        return $filas;
    }

    #[Computed]
    public function detalleEvaluado(): ?array
    {
        if ($this->evaluadoDetalleId === null) {
            return null;
        }

        $schoolId = $this->school?->id;
        $esGeneral = $this->evaluadoDetalleId === 0;
        $nombreEvaluado = EvaluacionDirectivo::nombreDepartamento($this->departamentoVista);
        $etiqueta = 'Evaluación General del Departamento';

        if (! $esGeneral) {
            $asignacion = EvaluacionDirectivoAsignacion::query()
                ->with('user')
                ->where('school_id', $schoolId)
                ->where('departamento', $this->departamentoVista)
                ->where('user_id', $this->evaluadoDetalleId)
                ->first();

            if (! $asignacion || ! $asignacion->user) {
                return null;
            }

            $nombreEvaluado = $asignacion->user->nombreCompleto();
            $etiqueta = $asignacion->etiqueta;
        }

        $evals = EvaluacionDirectivo::query()
            ->where('school_id', $schoolId)
            ->where('departamento', $this->departamentoVista)
            ->when($esGeneral, fn ($q) => $q->whereNull('evaluado_id'), fn ($q) => $q->where('evaluado_id', $this->evaluadoDetalleId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $evaluacionesIndividuales = [];
        /** @var EvaluacionDirectivo|null $prevEval */
        $prevEval = null;
        foreach ($evals as $idx => $ev) {
            $esDuplicada = $prevEval !== null
                && json_encode($prevEval->respuestas) === json_encode($ev->respuestas)
                && $prevEval->created_at
                && $ev->created_at
                && abs($ev->created_at->diffInSeconds($prevEval->created_at)) <= 30;

            $evaluacionesIndividuales[] = [
                'id' => $ev->id,
                'numero' => $idx + 1,
                'hora' => $ev->created_at?->timezone('America/Santiago')->format('d/m H:i:s') ?? '—',
                'puntaje_obtenido' => $ev->puntaje_obtenido,
                'puntaje_maximo' => $ev->puntaje_maximo,
                'promedio' => $ev->promedio,
                'es_duplicada' => $esDuplicada,
            ];

            if (! $esDuplicada) {
                $prevEval = $ev;
            }
        }

        $preguntas = $this->preguntasVista;
        $desglose = [];

        foreach ($preguntas as $num => $datosPregunta) {
            $key = (string) $num;
            $todasRespuestas = $evals
                ->map(fn (EvaluacionDirectivo $e) => $e->respuestas[$key] ?? null)
                ->filter(fn ($v) => $v !== null);

            $valoresValidos = $todasRespuestas->filter(fn ($v) => (int) $v >= 0);
            $totalRespPregunta = $todasRespuestas->count();
            $totalValidas = $valoresValidos->count();
            $conteoAlternativas = [];

            foreach (EvaluacionDirectivo::ALTERNATIVAS as $pts => $label) {
                $count = $todasRespuestas->filter(fn ($v) => (int) $v === $pts)->count();
                $conteoAlternativas[$pts] = [
                    'label' => $label,
                    'count' => $count,
                    'porcentaje' => $totalRespPregunta > 0 ? round(($count / $totalRespPregunta) * 100) : 0,
                ];
            }

            $opcionesNa = $datosPregunta['no_aplica'] ?? [];
            foreach ($opcionesNa as $valNa => $textoNa) {
                $countNa = $todasRespuestas->filter(fn ($v) => (int) $v === $valNa)->count();
                $conteoAlternativas[$valNa] = [
                    'label' => $textoNa,
                    'count' => $countNa,
                    'porcentaje' => $totalRespPregunta > 0 ? round(($countNa / $totalRespPregunta) * 100) : 0,
                ];
            }

            $desglose[$num] = [
                'numero' => $num,
                'texto' => $datosPregunta['texto'],
                'total_respuestas' => $totalRespPregunta,
                'promedio' => $totalValidas > 0 ? round((float) $valoresValidos->avg(), 2) : null,
                'alternativas' => $conteoAlternativas,
            ];
        }

        $puntajePromedio = $evals->isNotEmpty() ? round((float) $evals->avg('puntaje_obtenido'), 1) : null;
        $puntajeMaximo = count($preguntas) * 3;
        $promedioGeneral = $evals->isNotEmpty() ? round((float) $evals->avg('promedio'), 2) : null;
        $clasificacion = EvaluacionDocente::clasificacionPorcentual($puntajePromedio, $puntajeMaximo);

        return [
            'nombre_evaluado' => $nombreEvaluado,
            'etiqueta' => $etiqueta,
            'total_evaluaciones' => $evals->count(),
            'puntaje_promedio' => $puntajePromedio ?? 0,
            'puntaje_maximo' => $puntajeMaximo,
            'promedio_general' => $promedioGeneral ?? 0,
            'clasificacion' => $clasificacion,
            'desglose' => $desglose,
            'evaluaciones_individuales' => $evaluacionesIndividuales,
        ];
    }
};
?>

<div class="flex flex-col gap-4 w-full max-w-7xl mx-auto">
    <x-header
        :titulo="__('Evaluación de Directivos y Departamentos')"
        :subtitulo="__('Resultados por departamento e integrante (Escala: Destacado = 3, Competente = 2, Básico = 1, Por lograr = 0).')"
        icono="building-library">
        <flux:button size="sm" variant="ghost" icon="qr-code" :href="route('encuestas.imprimir_qr')">
            {{ __('Imprimir QR y PINs') }}
        </flux:button>
        <flux:button size="sm" variant="filled" icon="user-plus" wire:click="abrirModalAsignaciones">
            {{ __('Asignar Integrantes (8 Deptos.)') }}
        </flux:button>
        <flux:button size="sm" variant="primary" icon="computer-desktop" :href="route('encuestas.directivos.kiosco', ['schoolId' => $this->school?->id])">
            {{ __('Abrir Encuesta Directivos') }}
        </flux:button>
    </x-header>

    {{-- Barra de Estado y Administración --}}
    <div class="rounded-xl border p-3 flex flex-col md:flex-row md:items-center justify-between gap-3 {{ $encuestaHabilitada ? 'bg-emerald-50/70 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/60' : 'bg-amber-50/80 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800' }}">
        <div class="flex items-center gap-3">
            <div class="size-9 rounded-lg flex items-center justify-center shrink-0 {{ $encuestaHabilitada ? 'bg-emerald-600 text-white' : 'bg-amber-600 text-white' }}">
                @if ($encuestaHabilitada)
                    <flux:icon.check-badge class="size-5" />
                @else
                    <flux:icon.lock-closed class="size-5" />
                @endif
            </div>
            <div>
                <span class="text-xs font-extrabold text-zinc-900 dark:text-white block">
                    Estado Encuesta Directivos: {{ $encuestaHabilitada ? 'HABILITADA' : 'DESHABILITADA (CERRADA)' }}
                </span>
                <span class="text-xs text-zinc-600 dark:text-zinc-300">
                    PIN de Acceso Docentes: <strong class="font-mono text-[#00376e] dark:text-blue-300">{{ $pinDirectivos }}</strong>
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="xs" variant="filled" icon="user-group" wire:click="abrirModalAsignaciones">
                Configurar Integrantes
            </flux:button>
            <flux:button size="xs" variant="ghost" icon="key" wire:click="$set('modalAdminEncuesta', true)">
                Modificar PIN
            </flux:button>
            <flux:button size="xs" variant="ghost" icon="sparkles" wire:click="limpiarDuplicadas">
                Limpiar Duplicadas
            </flux:button>
            <flux:button size="xs" variant="ghost" icon="trash" class="!text-red-600 hover:!bg-red-50 dark:!text-red-400 dark:hover:!bg-red-950/40"
                wire:click="$set('modalConfirmarReset', true)">
                Reiniciar
            </flux:button>
            <flux:button size="xs" :variant="$encuestaHabilitada ? 'danger' : 'primary'"
                icon="{{ $encuestaHabilitada ? 'lock-closed' : 'lock-open' }}"
                wire:click="toggleEstadoRapido">
                {{ $encuestaHabilitada ? 'Deshabilitar' : 'Habilitar' }}
            </flux:button>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <flux:card class="!p-3.5 flex items-center gap-3">
            <div class="size-10 rounded-xl bg-blue-100 dark:bg-blue-950 text-[#00376e] dark:text-blue-400 flex items-center justify-center shrink-0">
                <flux:icon.clipboard-document-check class="size-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Evaluaciones Recibidas</p>
                <p class="text-xl font-extrabold text-zinc-900 dark:text-white">{{ $this->kpis['total_evaluaciones'] }}</p>
            </div>
        </flux:card>

        <flux:card class="!p-3.5 flex items-center gap-3">
            <div class="size-10 rounded-xl bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <flux:icon.users class="size-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Integrantes Asignados</p>
                <p class="text-xl font-extrabold text-zinc-900 dark:text-white">{{ $this->kpis['integrantes_asignados'] }}</p>
            </div>
        </flux:card>

        <flux:card class="!p-3.5 flex items-center gap-3">
            <div class="size-10 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <flux:icon.building-library class="size-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Deptos. con Respuestas</p>
                <p class="text-xl font-extrabold text-zinc-900 dark:text-white">{{ $this->kpis['departamentos_evaluados'] }} / 8</p>
            </div>
        </flux:card>

        <flux:card class="!p-3.5 flex items-center gap-3">
            <div class="size-10 rounded-xl bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                <flux:icon.chart-bar class="size-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Promedio Gral. (Máx 3.0)</p>
                <p class="text-xl font-extrabold text-zinc-900 dark:text-white">{{ number_format($this->kpis['promedio_general'], 2) }}</p>
            </div>
        </flux:card>
    </div>

    {{-- Selector de los 8 Departamentos --}}
    <flux:card class="!p-3">
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach (EvaluacionDirectivo::DEPARTAMENTOS as $clave => $info)
                @php
                    $activo = $departamentoVista === $clave;
                    $cantAsig = $this->todasAsignaciones->get($clave, collect())->count();
                @endphp
                <button type="button" wire:click="cambiarDepartamento('{{ $clave }}')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $activo
                        ? 'bg-[#00376e] text-white shadow-xs'
                        : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}">
                    <span>{{ $info['nombre'] }}</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $activo ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
                        {{ $cantAsig }}
                    </span>
                </button>
            @endforeach
        </div>
    </flux:card>

    {{-- Tabla de Resultados del Departamento Seleccionado --}}
    <flux:card class="p-0 overflow-hidden">
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-800/30">
            <div>
                <h3 class="text-sm font-extrabold text-zinc-900 dark:text-white">
                    {{ EvaluacionDirectivo::nombreDepartamento($departamentoVista) }}
                </h3>
                <p class="text-xs text-zinc-500">
                    {{ count($this->preguntasVista) }} preguntas · Puntaje máximo: {{ count($this->preguntasVista) * 3 }} pts
                </p>
            </div>

            <flux:button size="xs" variant="ghost" icon="user-plus" wire:click="abrirModalAsignaciones('{{ $departamentoVista }}')">
                Asignar integrante a este depto.
            </flux:button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-zinc-100/80 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-[10px] uppercase tracking-wider font-bold text-zinc-600 dark:text-zinc-300">
                        <th class="py-2.5 px-3 sticky left-0 bg-zinc-100 dark:bg-zinc-800 z-10 min-w-52">Integrante / Ciclo</th>
                        <th class="py-2.5 px-2 text-center">Resp.</th>
                        @foreach ($this->preguntasVista as $num => $datosPregunta)
                            <th class="py-2.5 px-2 text-center" title="{{ $datosPregunta['texto'] }}">
                                <span class="cursor-help border-b border-dotted border-zinc-400">P{{ $num }}</span>
                            </th>
                        @endforeach
                        <th class="py-2.5 px-2.5 text-center bg-blue-50/60 dark:bg-blue-950/30 text-[#00376e] dark:text-blue-300">
                            Total Prom.
                        </th>
                        <th class="py-2.5 px-2.5 text-center">Categoría / %</th>
                        <th class="py-2.5 px-2 text-center">Prom.</th>
                        <th class="py-2.5 px-3 text-right">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($this->tablaResultados as $fila)
                        @php
                            $cant = $fila['cantidad'];
                            $clasif = $fila['clasificacion'];
                        @endphp
                        <tr wire:key="dir-res-{{ $fila['row_key'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition">
                            <td class="py-2.5 px-3 sticky left-0 bg-white dark:bg-zinc-900 z-10 font-semibold text-zinc-900 dark:text-zinc-100">
                                <div class="flex flex-col">
                                    <span>{{ $fila['nombre_mostrar'] }}</span>
                                    @if ($fila['etiqueta'])
                                        <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400">
                                            {{ $fila['etiqueta'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold {{ $cant > 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                    {{ $cant }}
                                </span>
                            </td>

                            @foreach (array_keys($this->preguntasVista) as $num)
                                @php
                                    $promP = $fila['promedios_pregunta'][$num] ?? null;
                                @endphp
                                <td class="py-2.5 px-2 text-center font-mono text-[11px]">
                                    @if ($promP !== null)
                                        <span class="font-semibold {{ $promP >= 2.5 ? 'text-emerald-700 dark:text-emerald-400' : ($promP >= 1.8 ? 'text-amber-700 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                            {{ number_format($promP, 1) }}
                                        </span>
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                            @endforeach

                            <td class="py-2.5 px-2.5 text-center bg-blue-50/30 dark:bg-blue-950/20 font-bold text-zinc-900 dark:text-white whitespace-nowrap">
                                @if ($fila['puntaje_total_promedio'] !== null)
                                    {{ number_format($fila['puntaje_total_promedio'], 1) }}
                                    <span class="text-[10px] font-normal text-zinc-500">/ {{ $fila['puntaje_maximo'] }}</span>
                                @else
                                    <span class="text-zinc-400 font-normal">Sin datos</span>
                                @endif
                            </td>

                            <td class="py-2.5 px-2.5 text-center whitespace-nowrap">
                                @if ($clasif !== null)
                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-extrabold {{ $clasif['badge_class'] }}">
                                        <span>{{ $clasif['nombre'] }}</span>
                                        <span class="font-mono text-[10px] opacity-85">({{ number_format($clasif['porcentaje'], 1) }}%)</span>
                                    </span>
                                @else
                                    <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                @endif
                            </td>

                            <td class="py-2.5 px-2 text-center whitespace-nowrap">
                                @if ($fila['promedio_general'] !== null)
                                    <span class="font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ number_format($fila['promedio_general'], 2) }}
                                    </span>
                                @else
                                    <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                @endif
                            </td>

                            <td class="py-2.5 px-3 text-right">
                                <flux:button size="xs" variant="ghost" icon="chart-bar-square"
                                    wire:click="verDetalleEvaluado({{ $fila['detalle_id'] }})"
                                    :disabled="$cant === 0">
                                    Resumen
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($this->preguntasVista) + 6 }}" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                                <p class="font-semibold">Aún no hay integrantes asignados al departamento de {{ EvaluacionDirectivo::nombreDepartamento($departamentoVista) }}.</p>
                                <div class="mt-2">
                                    <flux:button size="xs" variant="primary" icon="user-plus" wire:click="abrirModalAsignaciones('{{ $departamentoVista }}')">
                                        Asignar Integrantes Ahora
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Glosario de Preguntas del Departamento --}}
    <flux:card class="!p-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-300 mb-2.5">
            Preguntas de {{ EvaluacionDirectivo::nombreDepartamento($departamentoVista) }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 text-xs text-zinc-700 dark:text-zinc-300">
            @foreach ($this->preguntasVista as $num => $datosPregunta)
                <div class="flex items-start gap-2 p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                    <span class="inline-flex items-center justify-center size-5 rounded bg-[#00376e] text-white font-bold text-[10px] shrink-0 mt-0.5">
                        P{{ $num }}
                    </span>
                    <div class="space-y-1">
                        <p class="leading-snug">{{ $datosPregunta['texto'] }}</p>
                        @if (! empty($datosPregunta['no_aplica']))
                            @foreach ($datosPregunta['no_aplica'] as $textoNa)
                                <span class="inline-block rounded bg-zinc-200/80 dark:bg-zinc-700 px-1.5 py-0.5 text-[10px] font-medium text-zinc-600 dark:text-zinc-300">
                                    Opción N/A: {{ $textoNa }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>

    {{-- Modal de Asignación de Integrantes a los 8 Departamentos --}}
    <flux:modal wire:model.self="modalAsignaciones" class="max-w-2xl w-full">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Asignar Integrantes a Departamentos</flux:heading>
                <flux:text class="mt-1 text-xs">
                    Selecciona qué funcionario(s) integran cada departamento y agrega una etiqueta (ej. "Enseñanza Básica", "Enseñanza Media") para que los profesores identifiquen a quién evaluar.
                </flux:text>
            </div>

            <form wire:submit="agregarAsignacion" class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 bg-zinc-50/70 dark:bg-zinc-800/40 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">Departamento</label>
                        <flux:select size="sm" wire:model.live="asigDepartamento">
                            @foreach (EvaluacionDirectivo::DEPARTAMENTOS as $clave => $info)
                                <flux:select.option value="{{ $clave }}">{{ $info['nombre'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">Funcionario(a)</label>
                        <flux:select size="sm" wire:model="asigUserId">
                            <flux:select.option value="">Seleccionar...</flux:select.option>
                            @foreach ($this->funcionariosColegio as $func)
                                <flux:select.option value="{{ $func->id }}">{{ $func->nombreCompleto() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="asigUserId" />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">Ciclo / Etiqueta (Opcional)</label>
                        <flux:input size="sm" wire:model="asigEtiqueta" placeholder="Ej: Enseñanza Básica" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button size="sm" type="submit" variant="primary" icon="plus">
                        Guardar Asignación
                    </flux:button>
                </div>
            </form>

            <div class="space-y-3 max-h-[50vh] overflow-y-auto pr-1">
                @foreach (EvaluacionDirectivo::DEPARTAMENTOS as $clave => $info)
                    @php
                        $lista = $this->todasAsignaciones->get($clave, collect());
                    @endphp
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-extrabold text-zinc-900 dark:text-white">{{ $info['nombre'] }}</span>
                            <span class="text-[11px] text-zinc-500">{{ $lista->count() }} integrante(s)</span>
                        </div>

                        @if ($lista->isEmpty())
                            <p class="text-xs text-zinc-400 italic">Sin integrantes asignados</p>
                        @else
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($lista as $item)
                                    <div class="py-1.5 flex items-center justify-between gap-2 text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                                {{ $item->user?->nombreCompleto() }}
                                            </span>
                                            @if ($item->etiqueta)
                                                <span class="rounded bg-blue-100 dark:bg-blue-950 px-2 py-0.5 text-[10px] font-bold text-[#00376e] dark:text-blue-300">
                                                    {{ $item->etiqueta }}
                                                </span>
                                            @endif
                                        </div>
                                        <button type="button" wire:click="eliminarAsignacion({{ $item->id }})"
                                            class="text-red-600 hover:underline text-[11px] font-semibold cursor-pointer">
                                            Quitar
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button size="sm" variant="ghost" wire:click="$set('modalAsignaciones', false)">
                    Cerrar
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal de Configuración de PIN y Estado --}}
    <flux:modal wire:model.self="modalAdminEncuesta" class="max-w-md w-full">
        <form wire:submit="guardarConfiguracionEncuesta" class="space-y-5">
            <div>
                <flux:heading size="lg">Configurar Encuesta de Directivos</flux:heading>
                <flux:text class="mt-1 text-xs">
                    Administra la disponibilidad y el PIN de acceso para que los profesores evalúen a los 8 departamentos.
                </flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-3.5 flex items-center justify-between gap-4 bg-zinc-50 dark:bg-zinc-800/50">
                <div>
                    <p class="text-xs font-bold text-zinc-900 dark:text-white">Habilitar completado de encuestas</p>
                    <p class="text-[11px] text-zinc-500">Permite recibir respuestas mediante el código QR.</p>
                </div>
                <flux:switch wire:model.live="encuestaHabilitada" />
            </div>

            <div>
                <flux:input size="sm" wire:model="pinDirectivos" label="PIN Encuesta Directivos y Departamentos" placeholder="Ej: 9012" />
                <flux:error name="pinDirectivos" />
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button size="sm" variant="ghost" wire:click="$set('modalAdminEncuesta', false)">
                    Cancelar
                </flux:button>
                <flux:button size="sm" type="submit" variant="primary">
                    Guardar Cambios
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal de Confirmación para Reiniciar Encuestas --}}
    <flux:modal wire:model.self="modalConfirmarReset" class="max-w-md w-full">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                    <flux:icon.exclamation-triangle class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Reiniciar Evaluaciones de Directivos</flux:heading>
                    <flux:text class="mt-1 text-xs">
                        Esta acción eliminará permanentemente las evaluaciones registradas del ámbito seleccionado.
                    </flux:text>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-500 mb-1">
                    ¿Qué evaluaciones deseas eliminar?
                </label>
                <flux:select size="sm" wire:model="tipoReset">
                    <flux:select.option value="todas">Todos los 8 Departamentos</flux:select.option>
                    @foreach (EvaluacionDirectivo::DEPARTAMENTOS as $clave => $info)
                        <flux:select.option value="{{ $clave }}">Solo {{ $info['nombre'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button size="sm" variant="ghost" wire:click="$set('modalConfirmarReset', false)">
                    Cancelar
                </flux:button>
                <flux:button size="sm" variant="danger" icon="trash" wire:click="reiniciarEncuestas">
                    Sí, eliminar
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal de Detalle por Integrante Estilo Google Forms --}}
    <flux:modal wire:model.self="modalDetalle" class="max-w-2xl w-full">
        @if ($this->detalleEvaluado)
            @php
                $det = $this->detalleEvaluado;
            @endphp
            <div class="space-y-4">
                <div class="border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                        {{ EvaluacionDirectivo::nombreDepartamento($departamentoVista) }}
                        @if ($det['etiqueta'])
                            · {{ $det['etiqueta'] }}
                        @endif
                    </p>
                    <h2 class="text-lg font-extrabold text-zinc-900 dark:text-white mt-0.5">
                        {{ $det['nombre_evaluado'] }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-2 mt-1.5 text-xs text-zinc-600 dark:text-zinc-400">
                        <span><strong>{{ $det['total_evaluaciones'] }}</strong> evaluaciones</span>
                        <span>•</span>
                        <span>Total Prom.: <strong>{{ number_format($det['puntaje_promedio'], 1) }} / {{ $det['puntaje_maximo'] }} pts</strong></span>
                        @if ($det['clasificacion'] !== null)
                            <span>•</span>
                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-extrabold {{ $det['clasificacion']['badge_class'] }}">
                                <span>{{ $det['clasificacion']['nombre'] }}</span>
                                <span class="font-mono text-[10px] opacity-85">({{ number_format($det['clasificacion']['porcentaje'], 1) }}%)</span>
                            </span>
                        @endif
                        <span>•</span>
                        <span>Prom./Preg.: <strong>{{ number_format($det['promedio_general'], 2) }} / 3.00</strong></span>
                    </div>
                </div>

                <div class="space-y-3 max-h-[55vh] overflow-y-auto pr-1">
                    @foreach ($det['desglose'] as $item)
                        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 p-3 space-y-2 bg-zinc-50/50 dark:bg-zinc-800/30">
                            <div class="flex items-start justify-between gap-2.5">
                                <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                    <span class="text-[#00376e] dark:text-blue-400">P{{ $item['numero'] }}.</span>
                                    {{ $item['texto'] }}
                                </p>
                                @if ($item['promedio'] !== null)
                                    <span class="shrink-0 inline-flex items-center rounded-md bg-blue-100 dark:bg-blue-950 px-2 py-0.5 text-xs font-extrabold text-[#00376e] dark:text-blue-300">
                                        Prom: {{ number_format($item['promedio'], 2) }}
                                    </span>
                                @endif
                            </div>

                            <div class="space-y-1.5">
                                @foreach ($item['alternativas'] as $pts => $alt)
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="w-40 text-[11px] font-semibold text-zinc-700 dark:text-zinc-300 shrink-0 truncate" title="{{ $alt['label'] }}">
                                            {{ $alt['label'] }} {{ $pts >= 0 ? "({$pts}p)" : '(N/A)' }}
                                        </span>
                                        <div class="flex-1 h-2.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full {{ $pts === 3 ? 'bg-emerald-500' : ($pts === 2 ? 'bg-blue-500' : ($pts === 1 ? 'bg-amber-500' : ($pts === 0 ? 'bg-red-500' : 'bg-zinc-400'))) }}"
                                                style="width: {{ $alt['porcentaje'] }}%"></div>
                                        </div>
                                        <span class="w-16 text-right font-mono text-[11px] text-zinc-600 dark:text-zinc-400 shrink-0">
                                            {{ $alt['count'] }} ({{ $alt['porcentaje'] }}%)
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    {{-- Registro de Envíos Individuales --}}
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 p-3 space-y-2 bg-white dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                Registro de Envíos Individuales ({{ count($det['evaluaciones_individuales']) }})
                            </p>
                            <span class="text-[11px] text-zinc-500">Eliminar duplicadas o sobrantes</span>
                        </div>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-44 overflow-y-auto">
                            @foreach ($det['evaluaciones_individuales'] as $evInd)
                                <div class="py-1.5 flex items-center justify-between gap-2 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-zinc-500">#{{ $evInd['numero'] }}</span>
                                        <span class="font-mono text-[11px] text-zinc-600 dark:text-zinc-400">{{ $evInd['hora'] }}</span>
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                            {{ $evInd['puntaje_obtenido'] }}/{{ $evInd['puntaje_maximo'] }} pts ({{ number_format($evInd['promedio'], 2) }})
                                        </span>
                                        @if ($evInd['es_duplicada'])
                                            <span class="inline-flex items-center rounded bg-amber-100 dark:bg-amber-950 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:text-amber-300">
                                                Posible duplicada
                                            </span>
                                        @endif
                                    </div>
                                    <button type="button"
                                        wire:click="eliminarRespuestaIndividual({{ $evInd['id'] }})"
                                        class="inline-flex items-center gap-1 rounded px-2 py-1 text-[11px] font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40 transition cursor-pointer">
                                        <flux:icon.trash class="size-3.5" />
                                        Eliminar
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button size="sm" variant="ghost" wire:click="$set('modalDetalle', false)">
                        Cerrar
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
