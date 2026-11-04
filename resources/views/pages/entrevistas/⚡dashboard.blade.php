<?php

use App\Models\Entrevista;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public $kpiHoy = 0;

    public $kpiMes = 0;

    public $kpiActivos = 0;

    public $kpiCanceladas = 0;

    public int $hoyRealizadas = 0;

    public int $hoyPendientes = 0;

    public $topDocentes = [];

    public $urgencias = [];

    public $motivos = [];

    public array $comparativaMensual = [];

    public int $maxMensual = 1;

    public $tasaAsistencia = 0;

    public $totalAsistencias = 0;

    public $totalAusencias = 0;

    public $totalAgendadasAnio = 0;

    public $totalCanceladasAnio = 0;

    public $totalPendientesAnio = 0;

    public int $anioActual = 2026;

    public string $filtroAsistenciaPeriodo = 'anio';

    public string $etiquetaAsistenciaPeriodo = '';

    public $realizadasHastaHoy = 0;

    public $agendadasHastaHoy = 0;

    public $tasaEfectividad = 0;

    public string $nombreMes = '';

    public string $fechaHoyTexto = '';

    public string $fechaHoyLarga = '';

    public function updatedFiltroAsistenciaPeriodo(): void
    {
        $this->calcularAsistencia();
    }

    public function calcularAsistencia(): void
    {
        $schoolId = auth()->user()->current_school_id;
        $now = now('America/Santiago')->locale('es');
        $this->anioActual = (int) $now->format('Y');

        $query = Entrevista::where('school_id', $schoolId)->whereYear('fecha', $this->anioActual);

        if ($this->filtroAsistenciaPeriodo !== 'anio' && is_numeric($this->filtroAsistenciaPeriodo)) {
            $mesInt = (int) $this->filtroAsistenciaPeriodo;
            $query->whereMonth('fecha', $mesInt);
            $nombreMes = mb_convert_case(Carbon::create($this->anioActual, $mesInt, 1)->locale('es')->translatedFormat('F'), MB_CASE_TITLE);
            $this->etiquetaAsistenciaPeriodo = "{$nombreMes} {$this->anioActual}";
        } else {
            $this->etiquetaAsistenciaPeriodo = "Año {$this->anioActual}";
        }

        $this->totalAgendadasAnio = (clone $query)->count();
        $asistieron = (clone $query)->where('estado', 'realizada')->count();
        $ausentes = (clone $query)->where('estado', 'ausente')->count();
        $canceladas = (clone $query)->where('estado', 'cancelada')->count();
        $pendientes = (clone $query)->whereIn('estado', ['pendiente', 'ingresada', 'abierta'])->count();

        $this->totalAsistencias = $asistieron;
        $this->totalAusencias = $ausentes;
        $this->totalCanceladasAnio = $canceladas;
        $this->totalPendientesAnio = $pendientes;
        $tot = $asistieron + $ausentes;
        $this->tasaAsistencia = $tot > 0 ? round(($asistieron / $tot) * 100, 1) : 0;
    }

    public function mount(): void
    {
        $schoolId = auth()->user()->current_school_id;
        $now = now('America/Santiago')->locale('es');
        $hoy = $now->format('Y-m-d');
        $mesActualInt = (int) $now->format('n');
        $this->anioActual = (int) $now->format('Y');
        $mesInicio = $now->copy()->startOfMonth()->format('Y-m-d');
        $mesFin = $now->copy()->endOfMonth()->format('Y-m-d');

        $this->nombreMes = mb_convert_case($now->translatedFormat('F'), MB_CASE_TITLE);
        $this->fechaHoyTexto = 'Al '.$now->translatedFormat('j \d\e F');
        $this->fechaHoyLarga = ucfirst($now->translatedFormat('l j \d\e F, Y'));

        // KPIs Hoy y Mes
        $this->kpiHoy = Entrevista::where('school_id', $schoolId)->whereDate('fecha', $hoy)->count();
        $this->kpiMes = Entrevista::where('school_id', $schoolId)
            ->whereBetween('fecha', [$mesInicio, $mesFin])
            ->count();
        $this->kpiActivos = Entrevista::where('school_id', $schoolId)
            ->whereDate('fecha', $hoy)
            ->whereIn('estado', ['ingresada', 'abierta', 'en_curso'])
            ->count();
        $this->hoyRealizadas = Entrevista::where('school_id', $schoolId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'realizada')
            ->count();
        $this->hoyPendientes = Entrevista::where('school_id', $schoolId)
            ->whereDate('fecha', $hoy)
            ->where('estado', 'pendiente')
            ->count();

        $this->kpiCanceladas = Entrevista::where('school_id', $schoolId)
            ->whereBetween('fecha', [$mesInicio, $mesFin])
            ->whereIn('estado', ['cancelada', 'ausente'])
            ->count();

        // Efectividad (1 del mes a hoy)
        $realizadasHastaHoy = Entrevista::where('school_id', $schoolId)
            ->whereBetween('fecha', [$mesInicio, $hoy])
            ->where('estado', 'realizada')
            ->count();

        $agendadasHastaHoy = Entrevista::where('school_id', $schoolId)
            ->whereBetween('fecha', [$mesInicio, $hoy])
            ->count();

        $this->realizadasHastaHoy = $realizadasHastaHoy;
        $this->agendadasHastaHoy = $agendadasHastaHoy;
        $this->tasaEfectividad = $agendadasHastaHoy > 0 ? round(($realizadasHastaHoy / $agendadasHastaHoy) * 100) : 0;

        // Top 10 Funcionarios con más entrevistas agendadas en el mes
        $this->topDocentes = DB::table('entrevistas')
            ->join('users', 'entrevistas.user_id', '=', 'users.id')
            ->where('entrevistas.school_id', $schoolId)
            ->whereBetween('entrevistas.fecha', [$mesInicio, $mesFin])
            ->select(
                'users.id',
                'users.nombres',
                'users.apellido_pat',
                DB::raw('count(entrevistas.id) as total'),
                DB::raw("sum(case when entrevistas.estado = 'realizada' then 1 else 0 end) as realizadas"),
                DB::raw("sum(case when entrevistas.estado in ('cancelada', 'ausente') then 1 else 0 end) as no_realizadas")
            )
            ->groupBy('users.id', 'users.nombres', 'users.apellido_pat')
            ->orderByDesc('total')
            ->orderByDesc('realizadas')
            ->limit(10)
            ->get();

        // Comparativa mes a mes: Total agendadas vs No realizadas (ausentes + canceladas)
        $citasAnio = Entrevista::where('school_id', $schoolId)
            ->whereYear('fecha', $this->anioActual)
            ->select('fecha', 'estado')
            ->get();

        $agrupadoPorMes = [];
        foreach ($citasAnio as $cita) {
            $m = (int) Carbon::parse($cita->fecha)->format('n');
            if (! isset($agrupadoPorMes[$m])) {
                $agrupadoPorMes[$m] = ['agendadas' => 0, 'no_realizadas' => 0, 'realizadas' => 0];
            }
            $agrupadoPorMes[$m]['agendadas']++;
            if (in_array($cita->estado, ['ausente', 'cancelada'], true)) {
                $agrupadoPorMes[$m]['no_realizadas']++;
            } elseif ($cita->estado === 'realizada') {
                $agrupadoPorMes[$m]['realizadas']++;
            }
        }

        $nombresCortos = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        $mesesMostrar = range(3, 12);
        if (isset($agrupadoPorMes[1]) || $mesActualInt === 1) {
            array_unshift($mesesMostrar, 1);
        }
        if (isset($agrupadoPorMes[2]) || $mesActualInt === 2) {
            $pos = in_array(1, $mesesMostrar, true) ? 1 : 0;
            array_splice($mesesMostrar, $pos, 0, [2]);
        }

        $maxVal = 1;
        $this->comparativaMensual = [];
        foreach ($mesesMostrar as $mesNum) {
            $ag = $agrupadoPorMes[$mesNum]['agendadas'] ?? 0;
            $noReal = $agrupadoPorMes[$mesNum]['no_realizadas'] ?? 0;
            $real = $agrupadoPorMes[$mesNum]['realizadas'] ?? 0;
            if ($ag > $maxVal) {
                $maxVal = $ag;
            }
            $this->comparativaMensual[] = [
                'mes' => $mesNum,
                'etiqueta' => $nombresCortos[$mesNum],
                'agendadas' => $ag,
                'no_realizadas' => $noReal,
                'realizadas' => $real,
                'es_actual' => $mesNum === $mesActualInt,
            ];
        }
        $this->maxMensual = $maxVal;

        // Distribución Urgencia (del mes)
        $totalUrgs = max(1, $this->kpiMes);
        $urgs = Entrevista::where('school_id', $schoolId)
            ->whereBetween('fecha', [$mesInicio, $mesFin])
            ->select('urgencia', DB::raw('count(*) as total'))
            ->groupBy('urgencia')
            ->pluck('total', 'urgencia')
            ->toArray();

        $this->urgencias = [
            'normal' => ['count' => $urgs['normal'] ?? 0, 'pct' => round((($urgs['normal'] ?? 0) / $totalUrgs) * 100)],
            'prioritario' => ['count' => $urgs['prioritario'] ?? 0, 'pct' => round((($urgs['prioritario'] ?? 0) / $totalUrgs) * 100)],
            'urgente' => ['count' => $urgs['urgente'] ?? 0, 'pct' => round((($urgs['urgente'] ?? 0) / $totalUrgs) * 100)],
        ];

        // Motivos normalizados (del mes)
        $motsRaw = Entrevista::where('school_id', $schoolId)
            ->whereBetween('fecha', [$mesInicio, $mesFin])
            ->select('motivo', DB::raw('count(*) as total'))
            ->groupBy('motivo')
            ->get();

        $conteoMotivos = [];
        foreach ($motsRaw as $row) {
            $categoria = Entrevista::normalizarCategoria($row->motivo);
            $conteoMotivos[$categoria] = ($conteoMotivos[$categoria] ?? 0) + (int) $row->total;
        }
        arsort($conteoMotivos);

        $this->motivos = [];
        foreach ($conteoMotivos as $m => $count) {
            $this->motivos[$m] = ['count' => $count, 'pct' => round(($count / $totalUrgs) * 100)];
        }

        // Asistencia Año / Mes
        $this->calcularAsistencia();
    }
};
?>

<div class="-m-6 lg:-m-8 p-4 sm:p-5 lg:p-6 2xl:p-8 min-h-full bg-slate-100/80 dark:bg-zinc-950/60">
<div class="max-w-7xl mx-auto w-full pb-8 2xl:pb-12 space-y-5 2xl:space-y-8 tabular-nums">

    <x-header titulo="Dashboard de Entrevistas"
        subtitulo="Seguimiento diario de atención a apoderados, participación docente y cumplimiento mensual." icono="chart-bar" />

    {{-- Banda de Pulso Operativo (Asimétrica, sin tarjetas repetitivas) --}}
    <section aria-label="Resumen operativo de entrevistas"
        class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-zinc-200 dark:divide-zinc-800">

            {{-- Bloque 1 (5 cols): Jornada de Hoy --}}
            <div class="lg:col-span-5 p-4 2xl:p-6 flex flex-col justify-between gap-3.5 2xl:gap-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm 2xl:text-base font-bold text-zinc-900 dark:text-zinc-100">
                            Jornada de hoy
                        </h2>
                        <p class="text-[11px] 2xl:text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            {{ $fechaHoyLarga }}
                        </p>
                    </div>
                    <a href="{{ route('entrevistas.index', ['fecha' => now('America/Santiago')->format('Y-m-d'), 'filtroTemporal' => 'dia']) }}"
                        wire:navigate
                        class="inline-flex items-center gap-1 text-xs font-semibold text-[#00376e] dark:text-blue-400 hover:underline">
                        <span>Ver agenda del día</span>
                        <flux:icon.arrow-up-right class="size-3.5" />
                    </a>
                </div>

                <div class="flex items-baseline gap-3 2xl:gap-4">
                    <span class="text-3xl 2xl:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                        {{ str_pad((string) $kpiHoy, 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <div class="text-xs text-zinc-600 dark:text-zinc-400 space-y-0.5">
                        <p class="font-semibold text-zinc-800 dark:text-zinc-200">citas programadas para hoy</p>
                        <p class="text-[11px] 2xl:text-xs">Monitoreo directo entre portería y salas de atención</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 2xl:gap-2.5 pt-2.5 2xl:pt-3 border-t border-zinc-100 dark:border-zinc-800/80">
                    <a href="{{ route('entrevistas.index', ['estado' => 'ingresada']) }}" wire:navigate
                        class="group rounded-xl px-2.5 py-1.5 2xl:px-3 2xl:py-2 bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/70 dark:border-amber-800/60 hover:border-amber-400 transition-colors">
                        <div class="flex items-center justify-between">
                            <span class="text-base 2xl:text-lg font-extrabold text-amber-800 dark:text-amber-300">{{ $kpiActivos }}</span>
                            <span class="size-2 rounded-full bg-amber-500 {{ $kpiActivos > 0 ? 'animate-pulse' : '' }}"></span>
                        </div>
                        <p class="text-[11px] font-medium text-amber-900/80 dark:text-amber-200/80 mt-0.5">En sala / espera</p>
                    </a>

                    <a href="{{ route('entrevistas.index', ['fecha' => now('America/Santiago')->format('Y-m-d'), 'filtroTemporal' => 'dia', 'estado' => 'pendiente']) }}" wire:navigate
                        class="group rounded-xl px-2.5 py-1.5 2xl:px-3 2xl:py-2 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/60 hover:border-zinc-300 transition-colors">
                        <span class="text-base 2xl:text-lg font-extrabold text-zinc-900 dark:text-zinc-100">{{ $hoyPendientes }}</span>
                        <p class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400 mt-0.5">Por iniciar</p>
                    </a>

                    <a href="{{ route('entrevistas.index', ['fecha' => now('America/Santiago')->format('Y-m-d'), 'filtroTemporal' => 'dia', 'estado' => 'realizada']) }}" wire:navigate
                        class="group rounded-xl px-2.5 py-1.5 2xl:px-3 2xl:py-2 bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/70 dark:border-emerald-800/60 hover:border-emerald-400 transition-colors">
                        <span class="text-base 2xl:text-lg font-extrabold text-emerald-700 dark:text-emerald-300">{{ $hoyRealizadas }}</span>
                        <p class="text-[11px] font-medium text-emerald-800/80 dark:text-emerald-200/80 mt-0.5">Realizadas hoy</p>
                    </a>
                </div>
            </div>

            {{-- Bloque 2 (4 cols): Volumen y Cumplimiento Mensual --}}
            <div class="lg:col-span-4 p-4 2xl:p-6 flex flex-col justify-between gap-3.5 2xl:gap-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm 2xl:text-base font-bold text-zinc-900 dark:text-zinc-100">
                            Movimiento de {{ $nombreMes }}
                        </h2>
                        <p class="text-[11px] 2xl:text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Balance de citaciones agendadas vs. bajas del mes
                        </p>
                    </div>
                    <a href="{{ route('entrevistas.index', ['fecha' => now('America/Santiago')->format('Y-m-d'), 'filtroTemporal' => 'mes']) }}"
                        wire:navigate
                        class="text-xs font-semibold text-[#00376e] dark:text-blue-400 hover:underline shrink-0">
                        Historial mes
                    </a>
                </div>

                <div class="flex items-baseline justify-between gap-4">
                    <div>
                        <div class="flex items-baseline gap-1.5 2xl:gap-2">
                            <span class="text-3xl 2xl:text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ $kpiMes }}</span>
                            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">agendadas</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-baseline justify-end gap-1.5">
                            <span class="text-xl 2xl:text-2xl font-bold text-rose-600 dark:text-rose-400">{{ $kpiCanceladas }}</span>
                            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">no realizadas</span>
                        </div>
                    </div>
                </div>

                @php
                    $baseMes = max(1, $kpiMes);
                    $pctRealizadasMes = min(100, round(($realizadasHastaHoy / $baseMes) * 100));
                    $pctNoConcretadasMes = min(100 - $pctRealizadasMes, round(($kpiCanceladas / $baseMes) * 100));
                @endphp

                <div class="space-y-1.5 2xl:space-y-2">
                    <div class="h-2 2xl:h-2.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex">
                        @if($pctRealizadasMes > 0)
                            <div class="bg-emerald-500 h-full" style="width: {{ $pctRealizadasMes }}%" title="Realizadas: {{ $realizadasHastaHoy }}"></div>
                        @endif
                        @if($pctNoConcretadasMes > 0)
                            <div class="bg-rose-500 h-full" style="width: {{ $pctNoConcretadasMes }}%" title="Canceladas o ausentes: {{ $kpiCanceladas }}"></div>
                        @endif
                    </div>
                    <div class="flex items-center justify-between text-[11px] 2xl:text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            {{ $realizadasHastaHoy }} realizadas
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-rose-500"></span>
                            {{ $kpiCanceladas }} ausentes / canceladas
                        </span>
                    </div>
                </div>
            </div>

            {{-- Bloque 3 (3 cols): Efectividad a la Fecha --}}
            <div class="lg:col-span-3 p-4 2xl:p-6 flex flex-col justify-between gap-3.5 2xl:gap-5 bg-zinc-50/60 dark:bg-zinc-800/20">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm 2xl:text-base font-bold text-zinc-900 dark:text-zinc-100">
                            Efectividad
                        </h2>
                        <p class="text-[11px] 2xl:text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            {{ $fechaHoyTexto }}
                        </p>
                    </div>
                    <flux:badge :color="$tasaEfectividad >= 80 ? 'emerald' : ($tasaEfectividad >= 60 ? 'amber' : 'zinc')" size="sm">
                        {{ $tasaEfectividad >= 80 ? 'Óptima' : ($tasaEfectividad >= 60 ? 'Regular' : 'En curso') }}
                    </flux:badge>
                </div>

                <div>
                    <span class="text-3xl 2xl:text-4xl font-extrabold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ $tasaEfectividad }}%
                    </span>
                    <p class="text-[11px] 2xl:text-xs text-zinc-600 dark:text-zinc-400 mt-1 2xl:mt-1.5 leading-relaxed">
                        <strong class="font-semibold text-zinc-900 dark:text-zinc-200">{{ $realizadasHastaHoy }}</strong> entrevistas formalizadas de <strong class="font-semibold text-zinc-900 dark:text-zinc-200">{{ $agendadasHastaHoy }}</strong> programadas hasta hoy.
                    </p>
                </div>

                <div class="pt-2.5 2xl:pt-3 border-t border-zinc-200/70 dark:border-zinc-800">
                    <a href="{{ route('entrevistas.index', ['estado' => 'realizada']) }}" wire:navigate
                        class="inline-flex items-center justify-between w-full text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:text-[#00376e] dark:hover:text-blue-400 transition-colors">
                        <span>Revisar actas realizadas</span>
                        <flux:icon.chevron-right class="size-4" />
                    </a>
                </div>
            </div>

        </div>
    </section>

    {{-- Área Principal: Top 10 Mayor Volumen de Atención (7 cols) + Diagnóstico y Comparativa Mensual (5 cols) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 2xl:gap-8 items-start">

        {{-- Columna Izquierda (7 cols): Top 10 Funcionarios con Mayor Volumen de Atención --}}
        <section class="lg:col-span-7 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="p-4 2xl:p-6 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 id="tabla-top-docentes-titulo" class="text-base 2xl:text-lg font-bold text-zinc-900 dark:text-zinc-100">
                        Mayor volumen de atención en {{ $nombreMes }}
                    </h2>
                    <p class="text-[11px] 2xl:text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Los 10 profesores y funcionarios con más entrevistas agendadas en el mes y su estado de concreción.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <flux:button variant="ghost" size="sm" icon="chart-bar" :href="route('reportes.index')" wire:navigate>
                        {{ __('Reporte completo') }}
                    </flux:button>
                </div>
            </div>

            @if(count($topDocentes) > 0)
                @php
                    $maxDocente = max(1, (int) ($topDocentes[0]->total ?? 1));
                @endphp
                <div tabindex="0" role="region" aria-labelledby="tabla-top-docentes-titulo" class="overflow-x-auto focus:outline-none focus:ring-2 focus:ring-[#00376e]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-800/50 text-[11px] font-semibold text-zinc-500 dark:text-zinc-400">
                                <th scope="col" class="py-2 2xl:py-3 pl-4 2xl:pl-6 pr-2 w-10 2xl:w-12">#</th>
                                <th scope="col" class="py-2 2xl:py-3 px-2.5 2xl:px-3">Profesor / Funcionario</th>
                                <th scope="col" class="py-2 2xl:py-3 px-2.5 2xl:px-3 w-40 sm:w-48 2xl:w-56">Proporción y avance</th>
                                <th scope="col" class="py-2 2xl:py-3 px-2.5 2xl:px-3 text-right">Realizadas</th>
                                <th scope="col" class="py-2 2xl:py-3 pl-2.5 pr-4 2xl:pr-6 text-right">Agendadas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800 text-xs 2xl:text-sm">
                            @foreach($topDocentes as $index => $docente)
                                @php
                                    $posicion = $index + 1;
                                    $totalDoc = (int) $docente->total;
                                    $realizadasDoc = (int) ($docente->realizadas ?? 0);
                                    $noRealizadasDoc = (int) ($docente->no_realizadas ?? 0);
                                    $widthTotal = round(($totalDoc / $maxDocente) * 100);
                                    $pctRealizadasBarra = $totalDoc > 0 ? round(($realizadasDoc / $totalDoc) * 100) : 0;
                                    $pctNoRealizadasBarra = $totalDoc > 0 ? min(100 - $pctRealizadasBarra, round(($noRealizadasDoc / $totalDoc) * 100)) : 0;
                                    $iniciales = mb_strtoupper(mb_substr($docente->nombres ?? 'D', 0, 1).mb_substr($docente->apellido_pat ?? '', 0, 1));
                                @endphp
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="py-2.5 2xl:py-3.5 pl-4 2xl:pl-6 pr-2 whitespace-nowrap align-middle">
                                        <span class="inline-flex items-center justify-center w-5 h-5 2xl:w-6 2xl:h-6 rounded-md text-[11px] 2xl:text-xs font-bold {{ $posicion <= 3 ? 'bg-[#00376e] text-white dark:bg-blue-500/20 dark:text-blue-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                            {{ $posicion }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 2xl:py-3.5 px-2.5 2xl:px-3 align-middle">
                                        <div class="flex items-center gap-2.5 2xl:gap-3">
                                            <div class="w-7 h-7 2xl:w-8 2xl:h-8 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-[#00376e] dark:text-blue-400 text-[11px] 2xl:text-xs font-bold flex items-center justify-center shrink-0">
                                                {{ $iniciales }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                                    {{ $docente->nombres }} {{ $docente->apellido_pat }}
                                                </div>
                                                @if($noRealizadasDoc > 0)
                                                    <div class="text-[10px] 2xl:text-[11px] text-rose-600 dark:text-rose-400">
                                                        {{ $noRealizadasDoc }} {{ $noRealizadasDoc === 1 ? 'no realizada' : 'no realizadas' }}
                                                    </div>
                                                @else
                                                    <div class="text-[10px] 2xl:text-[11px] text-zinc-500 dark:text-zinc-400">
                                                        Sin inasistencias registradas
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5 2xl:py-3.5 px-2.5 2xl:px-3 align-middle">
                                        <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-2 2xl:h-2.5 rounded-full overflow-hidden p-0.5">
                                            <div class="h-full rounded-full overflow-hidden flex bg-blue-200 dark:bg-blue-900/50" style="width: {{ max(8, $widthTotal) }}%">
                                                @if($pctRealizadasBarra > 0)
                                                    <div class="bg-[#00376e] dark:bg-blue-400 h-full" style="width: {{ $pctRealizadasBarra }}%" title="Realizadas: {{ $realizadasDoc }}"></div>
                                                @endif
                                                @if($pctNoRealizadasBarra > 0)
                                                    <div class="bg-rose-500 h-full" style="width: {{ $pctNoRealizadasBarra }}%" title="No realizadas: {{ $noRealizadasDoc }}"></div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5 2xl:py-3.5 px-2.5 2xl:px-3 text-right whitespace-nowrap align-middle">
                                        <span class="font-semibold text-emerald-700 dark:text-emerald-400">
                                            {{ $realizadasDoc }}
                                        </span>
                                        <span class="text-[11px] 2xl:text-xs text-zinc-400 dark:text-zinc-500 ml-0.5">
                                            ({{ $pctRealizadasBarra }}%)
                                        </span>
                                    </td>
                                    <td class="py-2.5 2xl:py-3.5 pl-2.5 pr-4 2xl:pr-6 text-right whitespace-nowrap align-middle">
                                        <span class="inline-flex items-baseline gap-1 font-extrabold text-zinc-900 dark:text-white">
                                            {{ $totalDoc }}
                                            <span class="text-[11px] 2xl:text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $totalDoc === 1 ? 'cita' : 'citas' }}</span>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-6 2xl:p-10 text-center max-w-md mx-auto space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 flex items-center justify-center mx-auto">
                        <flux:icon.user-group class="size-5" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                            Sin entrevistas registradas en {{ $nombreMes }}
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            A medida que el cuerpo docente agende entrevistas durante el mes, aquí aparecerá el ranking de los 10 funcionarios con mayor actividad.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-2.5 pt-1">
                        <flux:button variant="primary" size="sm" icon="plus" :href="route('entrevistas.crear')" wire:navigate>
                            {{ __('Agendar entrevista') }}
                        </flux:button>
                    </div>
                </div>
            @endif

            <div class="px-4 2xl:px-6 py-2.5 2xl:py-3.5 bg-zinc-50/70 dark:bg-zinc-800/30 border-t border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-4 text-zinc-500 dark:text-zinc-400">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-[#00376e] dark:bg-blue-400"></span>
                        Realizadas
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-blue-200 dark:bg-blue-900"></span>
                        Pendientes / En curso
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-rose-500"></span>
                        No realizadas
                    </span>
                </div>
                <a href="{{ route('entrevistas.agenda') }}" wire:navigate
                    class="font-semibold text-[#00376e] dark:text-blue-400 hover:underline shrink-0 inline-flex items-center gap-1">
                    <span>Ir a Mi Agenda</span>
                    <flux:icon.arrow-right class="size-3.5" />
                </a>
            </div>
        </section>

        {{-- Columna Derecha (5 cols): Diagnóstico del Mes + Comparativa Mensual de Asistencia --}}
        <div class="lg:col-span-5 space-y-5 2xl:space-y-6">

            {{-- Panel 1: Distribución de Urgencia y Motivos Principales --}}
            <section class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 2xl:p-6 space-y-4 2xl:space-y-6">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm 2xl:text-base font-bold text-zinc-900 dark:text-zinc-100">
                            Prioridad y motivos del mes
                        </h2>
                        <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ $kpiMes }} citas en {{ $nombreMes }}
                        </span>
                    </div>
                    <p class="text-[11px] 2xl:text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Clasificación por nivel de urgencia y temática principal de atención.
                    </p>
                </div>

                {{-- Barra apilada y desglose de Urgencia --}}
                <div class="space-y-2.5 2xl:space-y-3">
                    <div class="h-2 2xl:h-2.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex">
                        @if($urgencias['urgente']['pct'] > 0)
                            <div class="bg-rose-500 h-full" style="width: {{ $urgencias['urgente']['pct'] }}%" title="Urgente: {{ $urgencias['urgente']['count'] }}"></div>
                        @endif
                        @if($urgencias['prioritario']['pct'] > 0)
                            <div class="bg-amber-500 h-full" style="width: {{ $urgencias['prioritario']['pct'] }}%" title="Prioritario: {{ $urgencias['prioritario']['count'] }}"></div>
                        @endif
                        @if($urgencias['normal']['pct'] > 0)
                            <div class="bg-blue-500 h-full" style="width: {{ $urgencias['normal']['pct'] }}%" title="Normal: {{ $urgencias['normal']['count'] }}"></div>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-2 2xl:gap-2.5">
                        <div class="rounded-xl p-2.5 2xl:p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/60">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5 text-[11px] 2xl:text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                    <span class="size-2 rounded-full bg-blue-500"></span>
                                    Normal
                                </span>
                                <span class="text-[10px] 2xl:text-[11px] text-zinc-500">{{ $urgencias['normal']['pct'] }}%</span>
                            </div>
                            <p class="text-base 2xl:text-lg font-extrabold text-zinc-900 dark:text-white mt-0.5 2xl:mt-1">
                                {{ $urgencias['normal']['count'] }}
                            </p>
                        </div>

                        <div class="rounded-xl p-2.5 2xl:p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/60">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5 text-[11px] 2xl:text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                    <span class="size-2 rounded-full bg-amber-500"></span>
                                    Prioritario
                                </span>
                                <span class="text-[10px] 2xl:text-[11px] text-zinc-500">{{ $urgencias['prioritario']['pct'] }}%</span>
                            </div>
                            <p class="text-base 2xl:text-lg font-extrabold text-zinc-900 dark:text-white mt-0.5 2xl:mt-1">
                                {{ $urgencias['prioritario']['count'] }}
                            </p>
                        </div>

                        <div class="rounded-xl p-2.5 2xl:p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/70 dark:border-zinc-700/60">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5 text-[11px] 2xl:text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                    <span class="size-2 rounded-full bg-rose-500"></span>
                                    Urgente
                                </span>
                                <span class="text-[10px] 2xl:text-[11px] text-zinc-500">{{ $urgencias['urgente']['pct'] }}%</span>
                            </div>
                            <p class="text-base 2xl:text-lg font-extrabold text-zinc-900 dark:text-white mt-0.5 2xl:mt-1">
                                {{ $urgencias['urgente']['count'] }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Motivos Principales --}}
                <div class="pt-3.5 2xl:pt-5 border-t border-zinc-200 dark:border-zinc-800 space-y-2.5 2xl:space-y-3.5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs 2xl:text-sm font-bold text-zinc-900 dark:text-zinc-100">
                            Motivos principales de citación
                        </h3>
                        <a href="{{ route('reportes.index', ['tipoReporte' => 'problematicas_curso']) }}" wire:navigate
                            class="text-xs font-semibold text-[#00376e] dark:text-blue-400 hover:underline">
                            Ver por curso
                        </a>
                    </div>

                    @if (empty($motivos))
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 py-2">
                            {{ __('Aún no se registran motivos de citación durante este mes.') }}
                        </p>
                    @else
                        @php
                            $barColors = [
                                'bg-[#00376e] dark:bg-blue-500',
                                'bg-blue-600 dark:bg-blue-400',
                                'bg-sky-500 dark:bg-sky-400',
                                'bg-teal-500 dark:bg-teal-400',
                                'bg-emerald-500 dark:bg-emerald-400',
                                'bg-amber-500 dark:bg-amber-400',
                            ];
                        @endphp
                        <div class="space-y-2 2xl:space-y-3">
                            @foreach (array_slice($motivos, 0, 6, true) as $motivo => $data)
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs gap-2">
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-200 truncate" title="{{ ucfirst($motivo) }}">
                                            {{ ucfirst($motivo) }}
                                        </span>
                                        <span class="text-zinc-600 dark:text-zinc-400 shrink-0">
                                            <strong class="font-bold text-zinc-900 dark:text-white">{{ $data['count'] }}</strong>
                                            <span class="text-zinc-400 dark:text-zinc-500 ml-1">({{ $data['pct'] }}%)</span>
                                        </span>
                                    </div>
                                    <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-1.5 2xl:h-2 rounded-full overflow-hidden">
                                        <div class="{{ $barColors[$loop->index] ?? 'bg-zinc-500' }} h-full rounded-full"
                                            style="width: {{ max(4, $data['pct']) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- Panel 2: Gráfico Comparativo Mes a Mes (Agendadas vs. No Realizadas) --}}
            <section class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 2xl:p-6 space-y-3.5 2xl:space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm 2xl:text-base font-bold text-zinc-900 dark:text-zinc-100">
                            Comparativa mensual ({{ $anioActual }})
                        </h2>
                        <p class="text-[11px] 2xl:text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Evolución mes a mes de citas agendadas frente a no realizadas.
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] font-semibold shrink-0">
                        <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                            <span class="size-2.5 rounded-sm bg-[#00376e] dark:bg-blue-500"></span>
                            Agendadas
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                            <span class="size-2.5 rounded-sm bg-rose-500"></span>
                            No realizadas
                        </span>
                    </div>
                </div>

                {{-- Gráfico de barras comparativas mes a mes --}}
                <div class="pt-1">
                    <div class="grid gap-1.5 sm:gap-2 items-end h-32 2xl:h-40 pt-3 px-1 border-b border-zinc-200 dark:border-zinc-800"
                        style="grid-template-columns: repeat({{ count($comparativaMensual) }}, minmax(0, 1fr));">
                        @foreach($comparativaMensual as $itemMes)
                            @php
                                $alturaAgendadas = $itemMes['agendadas'] > 0
                                    ? max(8, round(($itemMes['agendadas'] / $maxMensual) * 100))
                                    : 0;
                                $alturaNoRealizadas = $itemMes['no_realizadas'] > 0
                                    ? max(8, round(($itemMes['no_realizadas'] / $maxMensual) * 100))
                                    : 0;
                            @endphp
                            <div class="flex flex-col items-center h-full justify-end group relative"
                                title="{{ $itemMes['etiqueta'] }} {{ $anioActual }}: {{ $itemMes['agendadas'] }} agendadas, {{ $itemMes['no_realizadas'] }} no realizadas ({{ $itemMes['realizadas'] }} realizadas)">
                                <div class="text-[10px] font-bold text-zinc-600 dark:text-zinc-300 mb-1 leading-none">
                                    {{ $itemMes['agendadas'] > 0 ? $itemMes['agendadas'] : '' }}
                                </div>
                                <div class="w-full flex items-end justify-center gap-0.5 h-20 2xl:h-28">
                                    <div class="w-2.5 sm:w-3 rounded-t bg-[#00376e] dark:bg-blue-500 transition-all"
                                        style="height: {{ $alturaAgendadas }}%"></div>
                                    <div class="w-2.5 sm:w-3 rounded-t bg-rose-500 transition-all"
                                        style="height: {{ $alturaNoRealizadas }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Etiquetas de meses e indicadores de no realizadas --}}
                    <div class="grid gap-1.5 sm:gap-2 pt-2 px-1 text-center"
                        style="grid-template-columns: repeat({{ count($comparativaMensual) }}, minmax(0, 1fr));">
                        @foreach($comparativaMensual as $itemMes)
                            <div class="space-y-0.5">
                                <div class="text-[11px] {{ $itemMes['es_actual'] ? 'font-extrabold text-[#00376e] dark:text-blue-400' : 'font-medium text-zinc-500 dark:text-zinc-400' }}">
                                    {{ $itemMes['etiqueta'] }}
                                </div>
                                <div class="text-[10px] font-semibold {{ $itemMes['no_realizadas'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-400 dark:text-zinc-600' }}"
                                    title="No realizadas en {{ $itemMes['etiqueta'] }}">
                                    {{ $itemMes['no_realizadas'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @php
                    $totalAnualAg = array_sum(array_column($comparativaMensual, 'agendadas'));
                    $totalAnualNoReal = array_sum(array_column($comparativaMensual, 'no_realizadas'));
                    $pctAnualNoReal = $totalAnualAg > 0 ? round(($totalAnualNoReal / $totalAnualAg) * 100, 1) : 0;
                @endphp

                <div class="pt-2.5 2xl:pt-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-400">
                    <span>
                        Acumulado {{ $anioActual }}: <strong class="font-bold text-zinc-900 dark:text-white">{{ $totalAnualAg }}</strong> agendadas
                    </span>
                    <span>
                        <strong class="font-bold text-rose-600 dark:text-rose-400">{{ $totalAnualNoReal }}</strong> no realizadas ({{ $pctAnualNoReal }}%)
                    </span>
                </div>
            </section>

        </div>

    </div>

    {{-- Fila 3: Balance Institucional de Asistencia de Apoderados --}}
    <section aria-label="Tasa de asistencia institucional"
        class="rounded-2xl bg-[#00376e] dark:bg-zinc-900 dark:border dark:border-zinc-800 text-white p-5 sm:p-6 2xl:p-8">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 2xl:gap-8">

            <div class="space-y-2 2xl:space-y-3 max-w-md">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base 2xl:text-lg font-bold text-white">
                        Tasa de Asistencia de Apoderados
                    </h2>
                    <label for="filtro-asistencia-periodo" class="sr-only">Período de asistencia</label>
                    <select id="filtro-asistencia-periodo" wire:model.live="filtroAsistenciaPeriodo"
                        class="bg-white/15 hover:bg-white/20 text-white text-xs font-semibold py-1 2xl:py-1.5 px-2.5 2xl:px-3 rounded-lg border border-white/25 focus:outline-none focus:ring-2 focus:ring-white cursor-pointer">
                        <option value="anio" class="text-zinc-900 font-normal">Año {{ $anioActual }} (Acumulado)</option>
                        <option value="1" class="text-zinc-900 font-normal">Enero {{ $anioActual }}</option>
                        <option value="2" class="text-zinc-900 font-normal">Febrero {{ $anioActual }}</option>
                        <option value="3" class="text-zinc-900 font-normal">Marzo {{ $anioActual }}</option>
                        <option value="4" class="text-zinc-900 font-normal">Abril {{ $anioActual }}</option>
                        <option value="5" class="text-zinc-900 font-normal">Mayo {{ $anioActual }}</option>
                        <option value="6" class="text-zinc-900 font-normal">Junio {{ $anioActual }}</option>
                        <option value="7" class="text-zinc-900 font-normal">Julio {{ $anioActual }}</option>
                        <option value="8" class="text-zinc-900 font-normal">Agosto {{ $anioActual }}</option>
                        <option value="9" class="text-zinc-900 font-normal">Septiembre {{ $anioActual }}</option>
                        <option value="10" class="text-zinc-900 font-normal">Octubre {{ $anioActual }}</option>
                        <option value="11" class="text-zinc-900 font-normal">Noviembre {{ $anioActual }}</option>
                        <option value="12" class="text-zinc-900 font-normal">Diciembre {{ $anioActual }}</option>
                    </select>
                </div>

                <div class="flex items-baseline gap-3">
                    <span class="text-3xl 2xl:text-5xl font-extrabold tracking-tight text-white">
                        {{ $tasaAsistencia }}%
                    </span>
                    <span class="text-xs font-semibold text-blue-100">
                        {{ $etiquetaAsistenciaPeriodo }}
                    </span>
                </div>

                <p class="text-[11px] 2xl:text-xs text-blue-100 leading-relaxed">
                    Calculada sobre las entrevistas concluidas (asistencias efectivas frente a inasistencias registradas del apoderado).
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-5 2xl:gap-6 pt-4 lg:pt-0 border-t lg:border-t-0 border-white/15 w-full lg:w-auto">
                <div>
                    <p class="text-[11px] 2xl:text-xs font-semibold text-blue-200">Agendadas</p>
                    <p class="text-xl 2xl:text-2xl font-extrabold text-white mt-0.5 2xl:mt-1">{{ $totalAgendadasAnio }}</p>
                    <p class="text-[10px] 2xl:text-[11px] text-blue-200 mt-0.5">Total período</p>
                </div>

                <div class="sm:border-l sm:border-white/15 sm:pl-4 2xl:pl-5">
                    <p class="text-[11px] 2xl:text-xs font-semibold text-emerald-300">Asistieron</p>
                    <p class="text-xl 2xl:text-2xl font-extrabold text-white mt-0.5 2xl:mt-1">{{ $totalAsistencias }}</p>
                    <p class="text-[10px] 2xl:text-[11px] text-blue-200 mt-0.5">Realizadas</p>
                </div>

                <div class="sm:border-l sm:border-white/15 sm:pl-4 2xl:pl-5">
                    <p class="text-[11px] 2xl:text-xs font-semibold text-amber-300">Ausentes</p>
                    <p class="text-xl 2xl:text-2xl font-extrabold text-white mt-0.5 2xl:mt-1">{{ $totalAusencias }}</p>
                    <p class="text-[10px] 2xl:text-[11px] text-blue-200 mt-0.5">Inasistencias</p>
                </div>

                <div class="sm:border-l sm:border-white/15 sm:pl-4 2xl:pl-5">
                    <p class="text-[11px] 2xl:text-xs font-semibold text-rose-300">Canceladas</p>
                    <p class="text-xl 2xl:text-2xl font-extrabold text-white mt-0.5 2xl:mt-1">{{ $totalCanceladasAnio }}</p>
                    <p class="text-[10px] 2xl:text-[11px] text-blue-200 mt-0.5">Anuladas</p>
                </div>

                <div class="col-span-2 sm:col-span-1 sm:border-l sm:border-white/15 sm:pl-4 2xl:pl-5">
                    <p class="text-[11px] 2xl:text-xs font-semibold text-sky-200">Pendientes</p>
                    <p class="text-xl 2xl:text-2xl font-extrabold text-white mt-0.5 2xl:mt-1">{{ $totalPendientesAnio }}</p>
                    <p class="text-[10px] 2xl:text-[11px] text-blue-200 mt-0.5">Por realizar</p>
                </div>
            </div>

        </div>
    </section>

</div>
</div>
