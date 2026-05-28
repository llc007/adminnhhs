<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Entrevista;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    public $kpiHoy = 0;
    public $kpiMes = 0;
    public $kpiActivos = 0;
    public $kpiCanceladas = 0;
    
    public $topDocentes = [];
    public $urgencias = [];
    public $motivos = [];
    
    public $tasaAsistencia = 0;
    public $totalAsistencias = 0;
    public $totalAusencias = 0;

    public function mount()
    {
        $schoolId = auth()->user()->current_school_id;
        $hoy = now('America/Santiago')->format('Y-m-d');
        $mesInicio = now('America/Santiago')->startOfMonth()->format('Y-m-d');
        $mesFin = now('America/Santiago')->endOfMonth()->format('Y-m-d');

        // KPIs
        $this->kpiHoy = Entrevista::where('school_id', $schoolId)->whereDate('fecha', $hoy)->count();
        $this->kpiMes = Entrevista::where('school_id', $schoolId)->whereBetween('fecha', [$mesInicio, $mesFin])->count();
        $this->kpiActivos = Entrevista::where('school_id', $schoolId)
                            ->whereDate('fecha', $hoy)
                            ->whereIn('estado', ['ingresada', 'en_curso', 'pendiente'])
                            ->count();
                            
        $this->kpiCanceladas = Entrevista::where('school_id', $schoolId)
                                ->whereBetween('fecha', [$mesInicio, $mesFin])
                                ->whereIn('estado', ['cancelada', 'ausente'])
                                ->count();

        // Top 5 Docentes
        $this->topDocentes = DB::table('entrevistas')
            ->join('users', 'entrevistas.user_id', '=', 'users.id')
            ->where('entrevistas.school_id', $schoolId)
            ->whereBetween('entrevistas.fecha', [$mesInicio, $mesFin])
            ->select('users.nombres', 'users.apellido_pat', DB::raw('count(*) as total'))
            ->groupBy('users.id', 'users.nombres', 'users.apellido_pat')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Urgencias (del mes)
        $urgs = Entrevista::where('school_id', $schoolId)->whereBetween('fecha', [$mesInicio, $mesFin])
            ->select('urgencia', DB::raw('count(*) as total'))
            ->groupBy('urgencia')
            ->pluck('total', 'urgencia')->toArray();
        $totalUrgs = array_sum($urgs) ?: 1;
        $this->urgencias = [
            'normal' => ['count' => $urgs['normal'] ?? 0, 'pct' => round((($urgs['normal'] ?? 0) / $totalUrgs) * 100)],
            'prioritario' => ['count' => $urgs['prioritario'] ?? 0, 'pct' => round((($urgs['prioritario'] ?? 0) / $totalUrgs) * 100)],
            'urgente' => ['count' => $urgs['urgente'] ?? 0, 'pct' => round((($urgs['urgente'] ?? 0) / $totalUrgs) * 100)],
        ];

        // Motivos (del mes)
        $mots = Entrevista::where('school_id', $schoolId)->whereBetween('fecha', [$mesInicio, $mesFin])
            ->select('motivo', DB::raw('count(*) as total'))
            ->groupBy('motivo')
            ->pluck('total', 'motivo')->toArray();
        
        $this->motivos = [];
        foreach($mots as $m => $count) {
            $this->motivos[$m] = ['count' => $count, 'pct' => round(($count / $totalUrgs) * 100)];
        }
        arsort($this->motivos);

        // Asistencia Histórica
        $asistieron = Entrevista::where('school_id', $schoolId)->where('estado', 'realizada')->count();
        $ausentes = Entrevista::where('school_id', $schoolId)->where('estado', 'ausente')->count();
        $this->totalAsistencias = $asistieron;
        $this->totalAusencias = $ausentes;
        $tot = $asistieron + $ausentes;
        $this->tasaAsistencia = $tot > 0 ? round(($asistieron / $tot) * 100, 1) : 0;
    }
};
?>



<div class="max-w-7xl mx-auto w-full pb-10">

    {{-- Encabezado Principal --}}
    <div class="flex items-start justify-between mb-10">
        <div>
            <flux:heading size="xl" level="1" class="text-[#00376e] dark:text-blue-400">{{ __('Dashboard Analítico') }}</flux:heading>
            <flux:subheading size="lg" class="max-w-xl">
                {{ __('Monitoreo de gestión de entrevistas y rendimiento a nivel institucional.') }}
            </flux:subheading>
        </div>
        
        <div class="hidden md:flex items-center gap-3">
            <flux:button variant="primary" icon="arrow-path" wire:click="$refresh">{{ __('Actualizar') }}</flux:button>
        </div>
    </div>

    {{-- Fila 1: KPIs Principales --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        
        <!-- KPI 1: Hoy -->
        <a href="{{ route('entrevistas.index', ['fecha' => now('America/Santiago')->format('Y-m-d'), 'filtroTemporal' => 'dia']) }}" class="block no-underline">
            <flux:card class="border-t-4 border-t-blue-500 hover:shadow-md transition-shadow cursor-pointer h-full">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon.calendar-days class="size-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{{ __('Hoy') }}</span>
                </div>
                <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase">{{ __('Entrevistas Hoy') }}</p>
                <p class="text-4xl font-extrabold text-zinc-900 dark:text-white mt-1">{{ str_pad($kpiHoy, 2, '0', STR_PAD_LEFT) }}</p>
            </flux:card>
        </a>

        <!-- KPI 2: Mensual -->
        <a href="{{ route('entrevistas.index', ['fecha' => now('America/Santiago')->format('Y-m-d'), 'filtroTemporal' => 'mes']) }}" class="block no-underline">
            <flux:card class="border-t-4 border-t-indigo-500 hover:shadow-md transition-shadow cursor-pointer h-full">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon.calendar class="size-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{{ __('Este Mes') }}</span>
                </div>
                <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase">{{ __('Agendadas Mensual') }}</p>
                <p class="text-4xl font-extrabold text-zinc-900 dark:text-white mt-1">{{ str_pad($kpiMes, 2, '0', STR_PAD_LEFT) }}</p>
            </flux:card>
        </a>

        <!-- KPI 3: Activo -->
        <a href="{{ route('entrevistas.index', ['estado' => 'ingresada']) }}" class="block no-underline">
            <flux:card class="border-t-4 border-t-amber-500 hover:shadow-md transition-shadow cursor-pointer h-full">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 bg-amber-50 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon.clock class="size-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{{ __('Activo') }}</span>
                </div>
                <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase">{{ __('En Proceso / Espera') }}</p>
                <p class="text-4xl font-extrabold text-zinc-900 dark:text-white mt-1">{{ str_pad($kpiActivos, 2, '0', STR_PAD_LEFT) }}</p>
            </flux:card>
        </a>

        <!-- KPI 4: Canceladas -->
        <a href="{{ route('entrevistas.index', ['estado' => 'cancelada']) }}" class="block no-underline">
            <flux:card class="border-t-4 border-t-red-500 hover:shadow-md transition-shadow cursor-pointer h-full">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 bg-red-50 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon.no-symbol class="size-5 text-red-600 dark:text-red-400" />
                    </div>
                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{{ __('Mensual') }}</span>
                </div>
                <p class="text-zinc-500 dark:text-zinc-400 text-xs font-bold uppercase">{{ __('Canceladas / Fallidas') }}</p>
                <p class="text-4xl font-extrabold text-zinc-900 dark:text-white mt-1">{{ str_pad($kpiCanceladas, 2, '0', STR_PAD_LEFT) }}</p>
            </flux:card>
        </a>

    </div>

    {{-- Fila 2: Gráficos y Listas --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
        
        {{-- Desempeño Docente --}}
        <flux:card>
            <div class="flex items-center gap-3 mb-8">
                <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
                    <flux:icon.user-group class="size-5" />
                </div>
                <flux:heading size="lg">{{ __('Top 5: Volúmen de Citas') }}</flux:heading>
            </div>
            
            <div class="space-y-6">
                @forelse($topDocentes as $index => $docente)
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm font-semibold mb-1">
                            <span class="text-zinc-700 dark:text-zinc-300">{{ $docente->nombres }} {{ $docente->apellido_pat }}</span>
                            <span class="text-blue-600 dark:text-blue-400">{{ $docente->total }} citas</span>
                        </div>
                        <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-2.5 rounded-full overflow-hidden">
                            @php 
                                $max = count($topDocentes) > 0 ? $topDocentes[0]->total : 1; 
                                $width = ($docente->total / $max) * 100;
                            @endphp
                            <div class="bg-blue-500 dark:bg-blue-400 h-full rounded-full transition-all duration-1000" style="width: {{ $width }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4 text-zinc-500">
                        No hay datos registrados en este mes.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Análisis de Prioridad y Motivos --}}
        <div class="grid grid-rows-2 gap-8">
            <flux:card>
                <div class="flex justify-between items-center mb-6">
                    <flux:heading size="lg">{{ __('Distribución de Urgencia') }}</flux:heading>
                    <flux:icon.exclamation-triangle class="size-5 text-zinc-400" />
                </div>
                
                <div class="space-y-4">
                    {{-- Normal --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                            <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Normal</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ $urgencias['normal']['count'] }}</span>
                            <span class="text-xs text-zinc-500 ml-1">({{ $urgencias['normal']['pct'] }}%)</span>
                        </div>
                    </div>
                    
                    {{-- Prioritario --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                            <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Prioritario</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ $urgencias['prioritario']['count'] }}</span>
                            <span class="text-xs text-zinc-500 ml-1">({{ $urgencias['prioritario']['pct'] }}%)</span>
                        </div>
                    </div>

                    {{-- Urgente --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Urgente</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ $urgencias['urgente']['count'] }}</span>
                            <span class="text-xs text-zinc-500 ml-1">({{ $urgencias['urgente']['pct'] }}%)</span>
                        </div>
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex justify-between items-center mb-4">
                    <flux:heading size="lg">{{ __('Motivos Principales') }}</flux:heading>
                    <flux:icon.chat-bubble-left-right class="size-5 text-zinc-400" />
                </div>
                
                @if(empty($motivos))
                    <div class="text-center py-6 text-zinc-500">
                        {{ __('No hay motivos registrados en este mes.') }}
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <!-- Chart Area -->
                        <div class="md:col-span-6 flex justify-center items-center">
                            <div class="relative w-36 h-36" x-init="window.initMotivosChart ? window.initMotivosChart() : document.addEventListener('livewire:navigated', () => window.initMotivosChart())">
                                <canvas id="motivosChart"></canvas>
                                <!-- Inner Text of the Donut Chart -->
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-2xl font-extrabold text-zinc-900 dark:text-white">{{ array_sum(array_column($motivos, 'count')) }}</span>
                                    <span class="text-[8px] uppercase tracking-widest font-bold text-zinc-400 mt-0.5">{{ __('Citas') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Legend Area -->
                        <div class="md:col-span-6 space-y-2.5 max-h-36 overflow-y-auto pr-1">
                            @php
                                $colors = ['bg-blue-500', 'bg-indigo-500', 'bg-sky-500', 'bg-teal-500', 'bg-emerald-500', 'bg-cyan-500', 'bg-amber-500'];
                            @endphp
                            @foreach(array_slice($motivos, 0, 7) as $motivo => $data)
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-2.5 h-2.5 rounded-full shrink-0 {{ $colors[$loop->index] ?? 'bg-zinc-500' }}"></div>
                                        <span class="font-semibold text-zinc-700 dark:text-zinc-300 truncate" title="{{ ucfirst($motivo) }}">{{ ucfirst($motivo) }}</span>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="font-bold text-zinc-900 dark:text-white ml-1.5">{{ $data['count'] }}</span>
                                        <span class="text-[10px] text-zinc-500 ml-0.5">({{ $data['pct'] }}%)</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </flux:card>
        </div>

    </div>

    {{-- Fila 3: Métrica de Asistencia Institucional --}}
    <flux:card class="bg-gradient-to-br from-[#00376e] to-[#004d97] text-white overflow-hidden relative p-8 md:p-10 !border-0">
        {{-- Patrón de fondo opcional --}}
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(white 1px, transparent 1px); background-size: 20px 20px;"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="space-y-4 text-center md:text-left">
                <h3 class="text-xl font-bold text-white/90">Tasa de Asistencia Histórica de Apoderados</h3>
                <div class="flex flex-col md:flex-row items-center md:items-baseline gap-4">
                    <p class="text-6xl font-extrabold tracking-tighter text-white">{{ $tasaAsistencia }}%</p>
                    <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-bold text-white shadow-sm">Rendimiento Histórico</span>
                </div>
            </div>
            
            <div class="flex gap-8 bg-black/20 p-6 rounded-2xl backdrop-blur-md shadow-lg border border-white/10">
                <div class="text-center">
                    <p class="text-[10px] font-bold text-emerald-300 uppercase tracking-widest mb-1">Asistieron</p>
                    <p class="text-2xl font-bold text-white">{{ $totalAsistencias }}</p>
                    <p class="text-xs text-white/70 mt-1">Citas Realizadas</p>
                </div>
                <div class="w-px bg-white/20"></div>
                <div class="text-center">
                    <p class="text-[10px] font-bold text-red-300 uppercase tracking-widest mb-1">Ausentes</p>
                    <p class="text-2xl font-bold text-white">{{ $totalAusencias }}</p>
                    <p class="text-xs text-white/70 mt-1">No Realizadas</p>
                </div>
            </div>
        </div>
    </flux:card>

    <script>
        window.initMotivosChart = function() {
            if (typeof Chart === 'undefined') {
                setTimeout(window.initMotivosChart, 50);
                return;
            }

            const ctx = document.getElementById("motivosChart")?.getContext("2d");
            if (!ctx) return;
            
            if (window.motivosChartInstance) {
                window.motivosChartInstance.destroy();
            }
            
            const labels = @js(array_keys($motivos));
            const data = @js(array_column($motivos, 'count'));
            const capitalizedLabels = labels.map(l => l.charAt(0).toUpperCase() + l.slice(1));
            const colors = ['#3b82f6', '#6366f1', '#0ea5e9', '#14b8a6', '#10b981', '#06b6d4', '#f59e0b'];
            
            window.motivosChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: capitalizedLabels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, data.length),
                        borderWidth: 1,
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            padding: 10,
                            displayColors: true,
                            boxPadding: 6,
                        }
                    },
                    cutout: '75%',
                }
            });
        }

        // Initialize on Livewire navigate and load
        document.addEventListener('livewire:navigated', () => {
            window.initMotivosChart();
        });

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(() => window.initMotivosChart(), 50);
        }
    </script>

</div>