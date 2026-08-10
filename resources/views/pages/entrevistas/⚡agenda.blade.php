<?php

use Livewire\Component;
use App\Models\Entrevista;
use App\Models\User;
use App\Models\AnuncioAgenda;
use App\Models\AnuncioReaccion;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public string $fechaSeleccionada;
    public string $filtroTemporal = 'semana';

    // Tablón de Anuncios
    public bool $modalAnuncio = false;
    public ?int $anuncioIdEditando = null;
    public string $tituloAnuncio = '';
    public string $cuerpoAnuncio = '';
    public string $colorAnuncio = 'blue';
    public string $iconoAnuncio = 'megaphone';

    public function setFiltro($filtro)
    {
        $this->filtroTemporal = $filtro;
    }

    public function mount()
    {
        if (!auth()->user()->can('ver-entrevistas-propias') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }
        // Al entrar ver el día actual
        $this->fechaSeleccionada = now()->toDateString();

        if (session()->has('success')) {
            \Flux\Flux::toast(session('success'), variant: 'success');
        }
    }

    public function puedeEscribirMensajes(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        $schoolId = $user->current_school_id;
        if ($schoolId) {
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($schoolId);
        }

        return $user->hasPermissionTo('escribir-mensajes-agenda');
    }

    public function abrirModalNuevoAnuncio(): void
    {
        if (! $this->puedeEscribirMensajes()) {
            abort(403, 'No tienes permiso para publicar mensajes en la agenda.');
        }

        $this->anuncioIdEditando = null;
        $this->tituloAnuncio = '';
        $this->cuerpoAnuncio = '';
        $this->colorAnuncio = 'blue';
        $this->iconoAnuncio = 'megaphone';
        $this->modalAnuncio = true;
    }

    public function editarAnuncio(int $id): void
    {
        $anuncio = AnuncioAgenda::where('school_id', auth()->user()->current_school_id)->findOrFail($id);

        if (! $this->puedeEscribirMensajes() && $anuncio->user_id !== auth()->id()) {
            abort(403, 'No tienes permiso para editar este anuncio.');
        }

        $this->anuncioIdEditando = $anuncio->id;
        $this->tituloAnuncio = $anuncio->titulo;
        $this->cuerpoAnuncio = $anuncio->cuerpo;
        $this->colorAnuncio = $anuncio->color ?? 'blue';
        $this->iconoAnuncio = $anuncio->icono ?? 'megaphone';
        $this->modalAnuncio = true;
    }

    public function guardarAnuncio(): void
    {
        if (! $this->puedeEscribirMensajes()) {
            abort(403, 'No tienes permiso para publicar mensajes.');
        }

        $this->validate([
            'tituloAnuncio' => 'required|string|min:3|max:120',
            'cuerpoAnuncio' => 'required|string|min:5|max:1000',
            'colorAnuncio' => 'required|in:blue,amber,emerald,purple,rose',
        ], [
            'tituloAnuncio.required' => 'Ingrese el título del anuncio.',
            'cuerpoAnuncio.required' => 'Ingrese el mensaje o cuerpo del anuncio.',
        ]);

        if ($this->anuncioIdEditando) {
            $anuncio = AnuncioAgenda::where('school_id', auth()->user()->current_school_id)->findOrFail($this->anuncioIdEditando);
            $anuncio->update([
                'titulo' => $this->tituloAnuncio,
                'cuerpo' => $this->cuerpoAnuncio,
                'color' => $this->colorAnuncio,
                'icono' => $this->iconoAnuncio,
            ]);
            \Flux\Flux::toast('Anuncio actualizado correctamente.', variant: 'success');
        } else {
            AnuncioAgenda::create([
                'school_id' => auth()->user()->current_school_id,
                'user_id' => auth()->id(),
                'titulo' => $this->tituloAnuncio,
                'cuerpo' => $this->cuerpoAnuncio,
                'color' => $this->colorAnuncio,
                'icono' => $this->iconoAnuncio,
                'activo' => true,
            ]);
            \Flux\Flux::toast('Anuncio publicado en el tablón.', variant: 'success');
        }

        $this->modalAnuncio = false;
    }

    public function eliminarAnuncio(int $id): void
    {
        $anuncio = AnuncioAgenda::where('school_id', auth()->user()->current_school_id)->findOrFail($id);

        if (! $this->puedeEscribirMensajes() && $anuncio->user_id !== auth()->id()) {
            abort(403, 'No tienes permiso para eliminar este anuncio.');
        }

        $anuncio->delete();
        \Flux\Flux::toast('Anuncio eliminado del tablón.', variant: 'warning');
    }

    public function reaccionarAnuncio(int $anuncioId, string $emoji): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $anuncio = AnuncioAgenda::where('school_id', auth()->user()->current_school_id)->find($anuncioId);
        if (! $anuncio) {
            return;
        }

        $existing = AnuncioReaccion::where('anuncio_agenda_id', $anuncioId)
            ->where('user_id', $userId)
            ->where('reaction', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            AnuncioReaccion::create([
                'anuncio_agenda_id' => $anuncioId,
                'user_id' => $userId,
                'reaction' => $emoji,
            ]);
        }
    }

    public function with(): array
    {
        $user = Auth::user() ?? User::first();
        $dateObj = Carbon::parse($this->fechaSeleccionada);

        $userId = $user->id;

        // Buscar TODAS las entrevistas del MES del usuario (Para métricas y agenda)
        $entrevistasMes = Entrevista::with(['estudiante.curso'])
            ->where('school_id', $user->current_school_id)
            ->where(function ($q) use ($userId, $user) {
                if (! $user->hasRole('superadmin')) {
                    $q->where('user_id', $userId);
                }
            })
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

        // Anuncios del Tablón
        $anuncios = AnuncioAgenda::with(['user', 'reacciones'])
            ->where('school_id', auth()->user()->current_school_id)
            ->where('activo', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'entrevistasLista' => $entrevistasLista,
            'tituloLista' => $tituloLista,
            'proxima' => $proxima,
            'user' => $user,
            'realizadas' => $realizadas,
            'totalMes' => $totalMes,
            'anuncios' => $anuncios,
        ];
    }
};
?>
<div class="max-w-7xl mx-auto w-full pb-12">
    <x-entrevistas.header 
        titulo="Mi Agenda" 
        subtitulo="Resumen de agenda diaria y accesos rápidos" 
        icono="calendar" 
    />

    <div class="space-y-10">

        @if (session()->has('success'))
            <flux:card class="bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200">
                <div class="flex items-center gap-3">
                    <flux:icon.check-circle class="size-6 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <div>
                        <p class="font-bold text-sm">{{ session('success') }}</p>
                        <p class="text-xs opacity-90">La cita ha sido registrada exitosamente en tu agenda.</p>
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- Próxima Entrevista (Hero Banner) -->
        @if ($proxima)
            <section
                class="rounded-2xl bg-gradient-to-r from-[#00376e] to-blue-800 p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 overflow-hidden relative shadow-lg">
                <div class="relative z-10 text-white">
                    <h2 class="text-2xl font-extrabold mb-2 flex items-center gap-2">Próxima Entrevista <span class="text-xs font-mono text-blue-200 bg-white/10 px-2 py-0.5 rounded-md font-bold">#{{ $proxima->id }}</span></h2>
                    <p class="text-blue-100 font-medium flex items-center gap-2 text-sm sm:text-base">
                        @if ($proxima->estado === 'ingresada')
                            <span class="relative flex h-3 w-3">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            El apoderado de <strong>{{ $proxima->estudiante->nombreCompleto() ?? 'Sin nombre' }}</strong> ya está en la
                            institución esperando.
                        @else
                            <flux:icon.clock class="size-5" />
                            A las {{ \Carbon\Carbon::parse($proxima->hora)->format('H:i') }} con el apoderado de
                            <strong>{{ $proxima->estudiante->nombreCompleto() ?? 'Sin nombre' }}</strong>
                        @endif
                    </p>
                </div>
                <div class="relative z-10 w-full md:w-auto">
                    @can('view', $proxima)
                    <flux:button href="{{ route('entrevistas.bitacora', $proxima->id) }}" variant="ghost"
                        class="w-full md:w-auto bg-white/10 hover:bg-white/20 text-white border-0 font-bold px-6 py-2">
                        <flux:icon.document-text class="size-4 mr-2" />
                        Comenzar Bitácora
                    </flux:button>
                    @endcan
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
                        <flux:button size="sm" variant="{{ $filtroTemporal === 'dia' ? 'primary' : 'ghost' }}" class="text-xs px-3 {{ $filtroTemporal !== 'dia' ? 'text-zinc-600 dark:text-zinc-400' : '' }}" wire:click="setFiltro('dia')">Día</flux:button>
                        <flux:button size="sm" variant="{{ $filtroTemporal === 'semana' ? 'primary' : 'ghost' }}" class="text-xs px-3 {{ $filtroTemporal !== 'semana' ? 'text-zinc-600 dark:text-zinc-400' : '' }}" wire:click="setFiltro('semana')">Semana</flux:button>
                        <flux:button size="sm" variant="{{ $filtroTemporal === 'mes' ? 'primary' : 'ghost' }}" class="text-xs px-3 {{ $filtroTemporal !== 'mes' ? 'text-zinc-600 dark:text-zinc-400' : '' }}" wire:click="setFiltro('mes')">Mes</flux:button>
                    </div>
                </div>

                <div class="space-y-4">
                    @forelse($entrevistasLista as $cita)
                        @php
                            $estasRendido = in_array($cita->estado, ['realizada', 'ausente', 'cancelada']);
                            $borderColors = [
                                'pendiente' => 'border-amber-400',
                                'ingresada' => 'border-emerald-500',
                                'realizada' => 'border-zinc-300 dark:border-zinc-700',
                                'ausente'   => 'border-red-400',
                                'cancelada' => 'border-zinc-400 dark:border-zinc-600',
                            ];
                        @endphp
                        <div
                            class="group bg-white dark:bg-zinc-900 p-5 rounded-2xl flex items-center gap-6 border-l-4 {{ $borderColors[$cita->estado] ?? 'border-blue-400' }} shadow-sm hover:shadow-md transition-all {{ $estasRendido ? 'opacity-60 grayscale hover:grayscale-0' : '' }}">

                            <!-- Hora Block -->
                            <div class="text-center min-w-[70px]">
                                <p class="text-[10px] font-bold text-zinc-400 uppercase truncate mb-0.5">{{ \Carbon\Carbon::parse($cita->fecha)->format('d M') }}</p>
                                <p class="text-sm font-bold {{ $estasRendido ? 'text-zinc-500' : 'text-[#00376e] dark:text-blue-400' }}">
                                    {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</p>
                                <p class="text-[10px] font-bold font-mono text-zinc-400 dark:text-zinc-500 mt-0.5">#{{ $cita->id }}</p>
                            </div>

                            <!-- Main Info -->
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-1">
                                    <h4 class="font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ $cita->estudiante->nombreCompleto() ?? 'Sin nombre' }}</h4>

                                    @if ($cita->es_confidencial)
                                        <flux:badge color="purple" size="sm">🔒 Confidencial</flux:badge>
                                    @endif

                                    @if ($cita->user_id !== auth()->id())
                                        <flux:badge color="indigo" size="sm">🤝 Compartida</flux:badge>
                                    @endif

                                    @if ($cita->estado === 'pendiente')
                                        <flux:badge color="amber" size="sm">Pendiente</flux:badge>
                                    @elseif($cita->estado === 'ingresada')
                                        <flux:badge color="emerald" size="sm" class="animate-pulse">En Recepción
                                        </flux:badge>
                                    @elseif($cita->estado === 'realizada')
                                        <flux:badge color="zinc" size="sm">Realizada</flux:badge>
                                    @elseif($cita->estado === 'ausente')
                                        <flux:badge color="red" size="sm">Ausente</flux:badge>
                                    @elseif($cita->estado === 'cancelada')
                                        <flux:badge color="zinc" size="sm">Cancelada</flux:badge>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500 flex items-center gap-2">
                                    <flux:icon.user class="size-3" />
                                    Apod: {{ $cita->estudiante->apoderado_nombres ?? 'Desconocido' }} • Curso:
                                    {{ $cita->estudiante?->curso?->nombreCompleto() ?? 'Sin Curso' }}
                                </p>
                            </div>

                            <!-- Acciones -->
                            <div class="flex gap-2 relative">
                                @can('view', $cita)
                                    @if (in_array($cita->estado, ['realizada', 'ausente', 'cancelada']))
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
                                @endcan
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

                @if (auth()->user()->can('crear-entrevistas') || auth()->user()->hasRole('superadmin'))
                    <div class="mt-6 flex justify-center">
                        <flux:button href="{{ route('entrevistas.crear') }}" variant="primary" icon="plus" class="w-full sm:w-auto">
                            Agendar Nueva Entrevista
                        </flux:button>
                    </div>
                @endif
            </div>

            <!-- Stats, Calendar & Tablón de Anuncios (Columna 4/12) -->
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

                <!-- Tablón de Anuncios y Mensajes de Estado (Debajo del Calendario) -->
                <div wire:poll.10s class="space-y-4 pt-2">
                    <style>
                        @keyframes popNoticeEntry {
                            0% {
                                opacity: 0;
                                transform: translateY(-20px) scale(0.92);
                                filter: brightness(1.2);
                            }
                            60% {
                                transform: translateY(4px) scale(1.02);
                                filter: brightness(1);
                            }
                            100% {
                                opacity: 1;
                                transform: translateY(0) scale(1);
                                filter: brightness(1);
                            }
                        }
                        .animate-notice-card {
                            animation: popNoticeEntry 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                        }
                    </style>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-600"></span>
                            </div>
                            <h4 class="font-extrabold text-[#00376e] dark:text-blue-300 text-base flex items-center gap-1.5">
                                Anuncios
                            </h4>
                        </div>

                        @if($this->puedeEscribirMensajes())
                            <flux:button size="xs" variant="primary" icon="plus" wire:click="abrirModalNuevoAnuncio" class="bg-gradient-to-r from-[#00376e] to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold shadow-sm">
                                Nuevo Aviso
                            </flux:button>
                        @endif
                    </div>

                    @php
                        $colorClasses = [
                            'blue' => 'from-blue-500/10 via-blue-500/5 to-transparent border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-100',
                            'amber' => 'from-amber-500/10 via-amber-500/5 to-transparent border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-100',
                            'emerald' => 'from-emerald-500/10 via-emerald-500/5 to-transparent border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-100',
                            'purple' => 'from-purple-500/10 via-purple-500/5 to-transparent border-purple-200 dark:border-purple-800/60 text-purple-900 dark:text-purple-100',
                            'rose' => 'from-rose-500/10 via-rose-500/5 to-transparent border-rose-200 dark:border-rose-800/60 text-rose-900 dark:text-rose-100',
                        ];
                        $badgeColors = [
                            'blue' => 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                            'amber' => 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                            'emerald' => 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                            'purple' => 'bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                            'rose' => 'bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                        ];
                        $stickers = ['👍', '❤️', '🚀', '👏', '💡'];
                        $userId = auth()->id();
                    @endphp

                    <div class="space-y-3">
                        @forelse($anuncios as $index => $anuncio)
                            @php
                                $esNuevo = \Carbon\Carbon::parse($anuncio->created_at)->greaterThan(now('America/Santiago')->subMinutes(30));
                            @endphp
                            <div 
                                wire:key="anuncio-card-{{ $anuncio->id }}"
                                class="animate-notice-card group relative rounded-2xl bg-gradient-to-br {{ $colorClasses[$anuncio->color] ?? $colorClasses['blue'] }} bg-white dark:bg-zinc-900 border p-4 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 overflow-hidden"
                                style="animation-delay: {{ $index * 0.08 }}s;"
                            >
                                {{-- Decoración superior animada --}}
                                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 group-hover:h-1.5 transition-all"></div>

                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold border uppercase tracking-wider {{ $badgeColors[$anuncio->color] ?? $badgeColors['blue'] }}">
                                            📢 Aviso
                                        </span>
                                        @if($esNuevo)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 uppercase tracking-widest animate-pulse">
                                                <span class="relative flex h-1.5 w-1.5">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                                                </span>
                                                ¡Nuevo!
                                            </span>
                                        @endif
                                    </div>

                                    @if($this->puedeEscribirMensajes() || $anuncio->user_id === auth()->id())
                                        <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1">
                                            <button type="button" wire:click="editarAnuncio({{ $anuncio->id }})" class="text-zinc-400 hover:text-blue-600 p-1 cursor-pointer" title="Editar aviso">
                                                <flux:icon.pencil-square class="size-3.5" />
                                            </button>
                                            <button type="button" wire:confirm="¿Seguro que deseas eliminar este aviso?" wire:click="eliminarAnuncio({{ $anuncio->id }})" class="text-zinc-400 hover:text-red-600 p-1 cursor-pointer" title="Eliminar aviso">
                                                <flux:icon.trash class="size-3.5" />
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <h5 class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mb-1 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ $anuncio->titulo }}
                                </h5>

                                <p class="text-xs text-zinc-600 dark:text-zinc-300 leading-relaxed whitespace-pre-line">
                                    {{ $anuncio->cuerpo }}
                                </p>

                                {{-- Fila de Stickers / Reacciones Interactivas --}}
                                <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800/60 flex items-center gap-1.5 flex-wrap">
                                    @foreach($stickers as $emoji)
                                        @php
                                            $reaccionesEmoji = $anuncio->reacciones->where('reaction', $emoji);
                                            $count = $reaccionesEmoji->count();
                                            $userReacted = $reaccionesEmoji->where('user_id', $userId)->count() > 0;
                                        @endphp
                                        <button 
                                            type="button" 
                                            wire:click="reaccionarAnuncio({{ $anuncio->id }}, '{{ $emoji }}')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border transition-all duration-200 cursor-pointer active:scale-95 hover:scale-110 {{ $userReacted ? 'bg-blue-100 dark:bg-blue-900/60 border-blue-400 dark:border-blue-600 text-blue-900 dark:text-blue-100 font-bold shadow-sm' : 'bg-zinc-50 dark:bg-zinc-800/60 border-zinc-200 dark:border-zinc-700/80 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                                            title="Reaccionar con {{ $emoji }}"
                                        >
                                            <span class="text-sm select-none">{{ $emoji }}</span>
                                            @if($count > 0)
                                                <span class="text-[11px] font-extrabold {{ $userReacted ? 'text-blue-700 dark:text-blue-300' : 'text-zinc-500 dark:text-zinc-400' }}">{{ $count }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-center p-6 bg-gradient-to-b from-zinc-50 to-white dark:from-zinc-900/50 dark:to-zinc-900 rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800 space-y-2">
                                <div class="inline-flex items-center justify-center size-10 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-500 mb-1 animate-bounce">
                                    <flux:icon.megaphone class="size-5" />
                                </div>
                                <h5 class="text-xs font-bold text-zinc-600 dark:text-zinc-400">Sin mensajes en el tablón</h5>
                                <p class="text-[11px] text-zinc-400 max-w-xs mx-auto">
                                    Aquí aparecerán las notas y anuncios de estado para la agenda institucional.
                                </p>
                                @if($this->puedeEscribirMensajes())
                                    <button type="button" wire:click="abrirModalNuevoAnuncio" class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-800 underline cursor-pointer">
                                        <flux:icon.plus class="size-3" /> Publicar primer mensaje
                                    </button>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal Publicar/Editar Anuncio en Tablón -->
    <flux:modal wire:model="modalAnuncio" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon.megaphone class="size-5 text-[#00376e]" />
                    {{ $anuncioIdEditando ? 'Editar Mensaje de Estado' : 'Publicar Nuevo Anuncio' }}
                </flux:heading>
                <flux:text class="mt-1 text-xs">Escribe un aviso informativo que se mostrará destacado en la agenda del establecimiento.</flux:text>
            </div>

            <form wire:submit.prevent="guardarAnuncio" class="space-y-4">
                <flux:input wire:model="tituloAnuncio" label="Título del Aviso" placeholder="Ej: Recordatorio Reunión de Apoderados / Box 3 no disponible" required />
                <flux:error name="tituloAnuncio" />

                <flux:textarea wire:model="cuerpoAnuncio" label="Mensaje o Cuerpo del Texto" rows="4" placeholder="Escribe el detalle del anuncio..." required />
                <flux:error name="cuerpoAnuncio" />

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500 mb-2">Color Destacado:</label>
                    <div class="grid grid-cols-5 gap-2">
                        <label class="flex flex-col items-center gap-1 p-2 rounded-xl border cursor-pointer {{ $colorAnuncio === 'blue' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40' : 'border-zinc-200 dark:border-zinc-800' }}">
                            <input type="radio" wire:model.live="colorAnuncio" value="blue" class="sr-only" />
                            <span class="size-4 rounded-full bg-blue-500"></span>
                            <span class="text-[10px] font-bold text-zinc-600 dark:text-zinc-400">Azul</span>
                        </label>
                        <label class="flex flex-col items-center gap-1 p-2 rounded-xl border cursor-pointer {{ $colorAnuncio === 'amber' ? 'border-amber-500 bg-amber-50 dark:bg-amber-950/40' : 'border-zinc-200 dark:border-zinc-800' }}">
                            <input type="radio" wire:model.live="colorAnuncio" value="amber" class="sr-only" />
                            <span class="size-4 rounded-full bg-amber-500"></span>
                            <span class="text-[10px] font-bold text-zinc-600 dark:text-zinc-400">Ámbar</span>
                        </label>
                        <label class="flex flex-col items-center gap-1 p-2 rounded-xl border cursor-pointer {{ $colorAnuncio === 'emerald' ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40' : 'border-zinc-200 dark:border-zinc-800' }}">
                            <input type="radio" wire:model.live="colorAnuncio" value="emerald" class="sr-only" />
                            <span class="size-4 rounded-full bg-emerald-500"></span>
                            <span class="text-[10px] font-bold text-zinc-600 dark:text-zinc-400">Verde</span>
                        </label>
                        <label class="flex flex-col items-center gap-1 p-2 rounded-xl border cursor-pointer {{ $colorAnuncio === 'purple' ? 'border-purple-500 bg-purple-50 dark:bg-purple-950/40' : 'border-zinc-200 dark:border-zinc-800' }}">
                            <input type="radio" wire:model.live="colorAnuncio" value="purple" class="sr-only" />
                            <span class="size-4 rounded-full bg-purple-500"></span>
                            <span class="text-[10px] font-bold text-zinc-600 dark:text-zinc-400">Morado</span>
                        </label>
                        <label class="flex flex-col items-center gap-1 p-2 rounded-xl border cursor-pointer {{ $colorAnuncio === 'rose' ? 'border-rose-500 bg-rose-50 dark:bg-rose-950/40' : 'border-zinc-200 dark:border-zinc-800' }}">
                            <input type="radio" wire:model.live="colorAnuncio" value="rose" class="sr-only" />
                            <span class="size-4 rounded-full bg-rose-500"></span>
                            <span class="text-[10px] font-bold text-zinc-600 dark:text-zinc-400">Rosa</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button wire:click="$set('modalAnuncio', false)" variant="ghost">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" icon="check">{{ $anuncioIdEditando ? 'Guardar Cambios' : 'Publicar Anuncio' }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
