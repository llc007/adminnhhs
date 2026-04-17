<?php

use Livewire\Component;
use App\Models\Entrevista;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public string $fechaSeleccionada;
    public string $filtroTemporal = 'dia';

    public function setFiltro($filtro)
    {
        $this->filtroTemporal = $filtro;
    }

    public function mount()
    {
        // Al entrar ver el día actual
        $this->fechaSeleccionada = now()->toDateString();
    }

    public function render()
    {
        $user = Auth::user() ?? User::first();
        $dateObj = Carbon::parse($this->fechaSeleccionada);

        // Buscar TODAS las entrevistas del MES (Para métricas)
        $entrevistasMes = Entrevista::with(['estudiante.curso'])
            ->where('user_id', $user->id)
            ->whereMonth('fecha', $dateObj->month)
            ->whereYear('fecha', $dateObj->year)
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->get();

        // Filtrar las seleccionadas para el dia en curso
        $entrevistasHoy = $entrevistasMes->where('fecha', $this->fechaSeleccionada);

        // Próxima entrevista de hoy que esté pendiente o ingresada
        $proxima = $entrevistasHoy->whereIn('estado', ['pendiente', 'ingresada'])->first();

        // Aplicamos nuestro filtro temporal para la lista mostrada ("Agenda Activa")
        if ($this->filtroTemporal === 'dia') {
            $entrevistasLista = $entrevistasHoy;
            $tituloLista = Carbon::parse($this->fechaSeleccionada)->translatedFormat('l d \d\e F');
        } elseif ($this->filtroTemporal === 'semana') {
            $inicioSemana = Carbon::parse($this->fechaSeleccionada)->startOfWeek()->toDateString();
            $finSemana = Carbon::parse($this->fechaSeleccionada)->endOfWeek()->toDateString();
            $entrevistasLista = $entrevistasMes->whereBetween('fecha', [$inicioSemana, $finSemana]);
            $tituloLista = 'Semana del ' . Carbon::parse($this->fechaSeleccionada)->startOfWeek()->format('d M');
        } else {
            $entrevistasLista = $entrevistasMes;
            $tituloLista = Carbon::parse($this->fechaSeleccionada)->translatedFormat('F Y');
        }

        // Métricas Mensuales
        $totalMes = $entrevistasMes->count();
        $realizadas = $entrevistasMes->where('estado', 'realizada')->count();

        return view('pages.entrevistas.agenda', [
            'entrevistasLista' => $entrevistasLista,
            'tituloLista' => $tituloLista,
            'proxima' => $proxima,
            'user' => $user,
            'realizadas' => $realizadas,
            'totalMes' => $totalMes,
        ]);
    }
};
?>
<div class="max-w-7xl mx-auto w-full pb-12">
    <!-- Header Page Context -->
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-[#00376e] dark:text-blue-400 font-extrabold">
                {{ __('Panel del Docente') }}</flux:heading>
            <p class="text-zinc-500 text-sm mt-1 flex items-center gap-2">
                <flux:icon.calendar class="size-4" />
                Resumen de agenda diaria y accesos rápidos
            </p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block mr-2">
                <p class="text-xs font-bold text-[#00376e] dark:text-blue-400 leading-none">
                    {{ trim($user->nombres . ' ' . $user->apellido_pat) ?: 'Profesor' }}</p>
                <p class="text-[10px] text-zinc-500 uppercase mt-1">Docente Planta</p>
            </div>
            <div
                class="w-10 h-10 rounded-full bg-[#00376e] text-white flex items-center justify-center font-bold relative">
                {{ substr($user->nombres ?? 'P', 0, 1) }}
                <span
                    class="absolute top-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
            </div>
        </div>
    </div>

    <div class="space-y-10">

        <!-- Próxima Entrevista (Hero Banner) -->
        @if ($proxima)
            <section
                class="rounded-2xl bg-gradient-to-r from-[#00376e] to-blue-800 p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 overflow-hidden relative shadow-lg">
                <div class="relative z-10 text-white">
                    <h2 class="text-2xl font-extrabold mb-2">Próxima Entrevista</h2>
                    <p class="text-blue-100 font-medium flex items-center gap-2 text-sm sm:text-base">
                        @if ($proxima->estado === 'ingresada')
                            <span class="relative flex h-3 w-3">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            El apoderado de <strong>{{ $proxima->estudiante->nombres }}</strong> ya está en la
                            institución esperando.
                        @else
                            <flux:icon.clock class="size-5" />
                            A las {{ \Carbon\Carbon::parse($proxima->hora)->format('H:i') }} con el apoderado de
                            <strong>{{ $proxima->estudiante->nombres }}</strong>
                        @endif
                    </p>
                </div>
                <div class="relative z-10 w-full md:w-auto">
                    <flux:button href="{{ route('entrevistas.bitacora', $proxima->id) }}" variant="ghost"
                        class="w-full md:w-auto bg-white/10 hover:bg-white/20 text-white border-0 font-bold px-6 py-2">
                        <flux:icon.document-text class="size-4 mr-2" />
                        Comenzar Bitácora
                    </flux:button>
                </div>
                <!-- Abstract decor -->
                <div
                    class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/5 rounded-full blur-3xl pointer-events-none">
                </div>
            </section>
        @else
            <section
                class="rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-800 p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 relative shadow-lg">
                <div class="relative z-10 text-white">
                    <h2 class="text-2xl font-extrabold mb-1">Día Despejado</h2>
                    <p class="text-emerald-100 font-medium flex items-center gap-2 text-sm">
                        <flux:icon.face-smile class="size-5" />
                        No tienes entrevistas pendientes en la agenda en este momento.
                    </p>
                </div>
            </section>
        @endif

        <!-- Bento Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">

            <!-- Agenda List (Columna 8/12) -->
            <div class="xl:col-span-8 space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-[#00376e] dark:text-blue-400">
                        Agenda Activa
                        <span class="text-sm font-medium text-zinc-500 ml-2 capitalize">{{ $tituloLista }}</span>
                    </h3>
                    <div class="flex gap-1 bg-zinc-100 dark:bg-zinc-800 p-1 rounded-lg">
                        <flux:button size="sm" variant="{{ $filtroTemporal === 'dia' ? 'primary' : 'ghost' }}" class="text-xs px-3 {{ $filtroTemporal === 'dia' ? 'bg-[#00376e] text-white' : 'text-zinc-600' }}" wire:click="setFiltro('dia')">Día</flux:button>
                        <flux:button size="sm" variant="{{ $filtroTemporal === 'semana' ? 'primary' : 'ghost' }}" class="text-xs px-3 {{ $filtroTemporal === 'semana' ? 'bg-[#00376e] text-white' : 'text-zinc-600' }}" wire:click="setFiltro('semana')">Semana</flux:button>
                        <flux:button size="sm" variant="{{ $filtroTemporal === 'mes' ? 'primary' : 'ghost' }}" class="text-xs px-3 {{ $filtroTemporal === 'mes' ? 'bg-[#00376e] text-white' : 'text-zinc-600' }}" wire:click="setFiltro('mes')">Mes</flux:button>
                    </div>
                </div>

                <div class="space-y-4">
                    @forelse($entrevistasLista as $cita)
                        @php
                            $estasRendido = $cita->estado === 'realizada';
                            $borderColors = [
                                'pendiente' => 'border-amber-400',
                                'ingresada' => 'border-emerald-500',
                                'realizada' => 'border-zinc-300 dark:border-zinc-700',
                            ];
                        @endphp
                        <div
                            class="group bg-white dark:bg-zinc-900 p-5 rounded-2xl flex items-center gap-6 border-l-4 {{ $borderColors[$cita->estado] ?? 'border-blue-400' }} shadow-sm hover:shadow-md transition-all {{ $estasRendido ? 'opacity-60 grayscale hover:grayscale-0' : '' }}">

                            <!-- Hora Block -->
                            <div class="text-center min-w-[70px]">
                                <p class="text-[10px] font-bold text-zinc-400 uppercase truncate mb-0.5">{{ \Carbon\Carbon::parse($cita->fecha)->format('d M') }}</p>
                                <p class="text-sm font-bold {{ $estasRendido ? 'text-zinc-500' : 'text-[#00376e] dark:text-blue-400' }}">
                                    {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</p>
                                <p class="text-[10px] font-bold text-zinc-400 uppercase">
                                    {{ \Carbon\Carbon::parse($cita->hora)->format('A') }}</p>
                            </div>

                            <!-- Main Info -->
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-1">
                                    <h4 class="font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ $cita->estudiante->nombreCompleto() ?? 'Sin nombre' }}</h4>

                                    @if ($cita->estado === 'pendiente')
                                        <flux:badge color="amber" size="sm">Pendiente</flux:badge>
                                    @elseif($cita->estado === 'ingresada')
                                        <flux:badge color="emerald" size="sm" class="animate-pulse">En Recepción
                                        </flux:badge>
                                    @elseif($cita->estado === 'realizada')
                                        <flux:badge color="zinc" size="sm">Realizada</flux:badge>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500 flex items-center gap-2">
                                    <flux:icon.user class="size-3" />
                                    Apod: {{ $cita->estudiante->apoderado_nombres ?? 'Desconocido' }} • Curso:
                                    {{ $cita->estudiante->curso->nombre_corto ?? '' }}
                                </p>
                            </div>

                            <!-- Acciones -->
                            <div class="flex gap-2 relative">
                                @if ($cita->estado === 'realizada')
                                    <flux:button href="{{ route('entrevistas.bitacora', $cita->id) }}" size="sm"
                                        variant="ghost" class="text-zinc-500">
                                        <flux:icon.eye class="size-4" />
                                    </flux:button>
                                @else
                                    <flux:button href="{{ route('entrevistas.bitacora', $cita->id) }}" size="sm"
                                        variant="subtle"
                                        class="opacity-0 group-hover:opacity-100 transition-all font-bold text-[#00376e] bg-blue-50 dark:bg-blue-900/40">
                                        Llenar Bitácora
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div
                            class="text-center py-10 bg-white dark:bg-zinc-900 rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800">
                            <flux:icon.calendar class="size-10 mx-auto text-zinc-300 dark:text-zinc-700 mb-3" />
                            <h3 class="text-sm font-bold text-zinc-500">Sin entrevistas programadas para hoy</h3>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Stats & Mini Widgets (Columna 4/12) -->
            <div class="xl:col-span-4 space-y-8">

                <!-- Resumen Semanal -->
                <flux:card
                    class="bg-zinc-50 dark:bg-zinc-800/40 shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <h4 class="font-bold text-[#00376e] dark:text-blue-400 mb-4">Métricas Mensuales</h4>
                    <div class="space-y-4">
                        <div class="flex justify-between items-end">
                            <div>
                                <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">Entrevistas Realizadas</p>
                                <p class="text-3xl font-extrabold text-[#00376e] dark:text-blue-400">
                                    {{ $realizadas }}/{{ $totalMes }}</p>
                            </div>
                            <div class="h-10 w-20 flex items-end gap-1">
                                <!-- Fake Bar Chart -->
                                <div class="w-full bg-[#00376e]/20 h-[40%] rounded-t-sm"></div>
                                <div class="w-full bg-[#00376e]/20 h-[60%] rounded-t-sm"></div>
                                <div class="w-full bg-[#00376e] h-[90%] rounded-t-sm"></div>
                                <div class="w-full bg-[#00376e]/20 h-[30%] rounded-t-sm"></div>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <p class="text-xs text-zinc-500 leading-relaxed">
                                Estas son tus métricas del mes en curso relativas a tus entrevistas agendadas.
                            </p>
                        </div>
                    </div>
                </flux:card>

                <!-- Calendario Vivo con Flux -->
                <flux:card class="bg-white dark:bg-zinc-900 shadow-sm p-4">
                    <flux:calendar wire:model.live="fechaSeleccionada" />
                </flux:card>

            </div>
        </div>
    </div>
</div>
