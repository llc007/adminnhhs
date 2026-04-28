<?php

use Livewire\Component;
use App\Models\Entrevista;
use App\Models\Bitacora;

new class extends Component {
    public Entrevista $entrevista;
    public ?Bitacora $bitacora = null;

    // Campos de Formulario
    public string $resumen = '';
    public string $observaciones = '';
    
    // Arrays Dinámicos
    public array $acuerdos = [];
    public string $nuevoAcuerdoTitulo = '';
    public string $nuevoAcuerdoDesc = '';

    public array $adjuntosDrive = [];
    
    // Modal state for links
    public bool $modalAdjunto = false;
    public string $nuevoAdjuntoNombre = '';
    public string $nuevoAdjuntoUrl = '';

    // Modal No Realizada
    public bool $modalNoRealizada = false;
    public string $motivoNoRealizada = '';
    public string $estadoNoRealizada = '';

    // Modal Reagendar
    public bool $modalReagendar = false;
    public string $nuevaFecha = '';
    public string $nuevaHora = '';

    public function mount(Entrevista $entrevista)
    {
        $this->entrevista = $entrevista->load(['estudiante.curso', 'user']);
        
        $user = auth()->user();
        $isOwner = $entrevista->user_id === $user->id;
        $isAllowedAdmin = $user->hasRole(['administrador', 'directivo', 'superadmin', 'psicosocial']);
        
        if (!$isOwner && !$isAllowedAdmin) {
            abort(403, 'Acesso Denegado: Registro confidencial. Solo el profesional a cargo o directivos pueden visualizar esta bitácora.');
        }

        if ($this->entrevista->bitacora) {
            $this->bitacora = $this->entrevista->bitacora;
            $this->resumen = $this->bitacora->resumen ?? '';
            $this->observaciones = $this->bitacora->observaciones ?? '';
            $this->acuerdos = $this->bitacora->acuerdos ?? [];
            $this->adjuntosDrive = $this->bitacora->adjuntos_drive ?? [];
        }
    }

    public function agregarAcuerdoRapido()
    {
        if (trim($this->nuevoAcuerdoTitulo) !== '') {
            $this->acuerdos[] = [
                'titulo' => trim($this->nuevoAcuerdoTitulo),
                'descripcion' => trim($this->nuevoAcuerdoDesc)
            ];
            $this->nuevoAcuerdoTitulo = '';
            $this->nuevoAcuerdoDesc = '';
        }
    }

    public function borrarAcuerdo($index)
    {
        unset($this->acuerdos[$index]);
        $this->acuerdos = array_values($this->acuerdos);
    }

    public function abrirModalAdjunto()
    {
        $this->nuevoAdjuntoNombre = '';
        $this->nuevoAdjuntoUrl = '';
        $this->modalAdjunto = true;
    }

    public function guardarEnlaceDrive()
    {
        $this->validate([
            'nuevoAdjuntoNombre' => 'required|min:3',
            'nuevoAdjuntoUrl' => 'required|url',
        ], [
            'nuevoAdjuntoNombre.required' => 'Asigne un nombre corto al archivo (Ej: Informe_Psicologo).',
            'nuevoAdjuntoUrl.required' => 'Debe pegar el enlace para vincular el documento.',
            'nuevoAdjuntoUrl.url' => 'Ingrese un link válido (https://...).',
        ]);

        $this->adjuntosDrive[] = [
            'nombre' => $this->nuevoAdjuntoNombre,
            'url' => $this->nuevoAdjuntoUrl,
            'fecha' => now()->format('M d, Y')
        ];

        $this->modalAdjunto = false;
    }

    public function quitarAdjunto($index)
    {
        unset($this->adjuntosDrive[$index]);
        $this->adjuntosDrive = array_values($this->adjuntosDrive);
    }

    public function guardarBorrador()
    {
        $this->guardarColeccion('borrador');
        \Flux::toast('Borrador actualizado correctamente.', variant: 'success');
    }

    public function finalizarBitacora()
    {
        $this->guardarColeccion('finalizado');
        
        // Si la entrevista estaba 'ingresada' y no se había marcado salida manual, el cierre asume la salida.
        if ($this->entrevista->estado === 'ingresada' && !str_contains($this->entrevista->mensaje_recepcion ?? '', '[SALIDA]')) {
            $hora = now('America/Santiago')->format('H:i');
            $notaSalida = "[SALIDA] El apoderado se retiró del recinto a las {$hora}.";
            
            $nuevoMensaje = $this->entrevista->mensaje_recepcion 
                ? $this->entrevista->mensaje_recepcion . "\n\n" . $notaSalida 
                : $notaSalida;

            $this->entrevista->update(['mensaje_recepcion' => $nuevoMensaje]);
        }

        // Cerrar el ciclo de vida de la entrevista
        $this->entrevista->update(['estado' => 'realizada']);
        
        \Flux::toast('Bitácora finalizada y entrevista cerrada exitosamente.', variant: 'success');
        return redirect()->route('entrevistas.agenda');
    }

    public function abrirModalReagendar()
    {
        $this->nuevaFecha = $this->entrevista->fecha;
        $this->nuevaHora = \Carbon\Carbon::parse($this->entrevista->hora)->format('H:i');
        $this->modalReagendar = true;
    }

    public function confirmarReagendamiento()
    {
        $this->validate([
            'nuevaFecha' => 'required|date',
            'nuevaHora' => 'required',
        ]);

        $this->entrevista->update([
            'fecha' => $this->nuevaFecha,
            'hora' => $this->nuevaHora,
        ]);

        \Flux::toast('La entrevista ha sido reagendada.', variant: 'success');
        $this->modalReagendar = false;
        
        $this->entrevista->refresh();
    }

    public function abrirModalNoRealizada()
    {
        $this->motivoNoRealizada = '';
        $this->estadoNoRealizada = '';
        $this->modalNoRealizada = true;
    }

    public function setMotivoPredeterminado($motivo, $estado)
    {
        $this->motivoNoRealizada = $motivo;
        $this->estadoNoRealizada = $estado;
    }

    public function marcarNoRealizada()
    {
        $this->validate([
            'motivoNoRealizada' => 'required|min:5',
            'estadoNoRealizada' => 'required|in:ausente,cancelada',
        ]);

        if (!$this->bitacora) {
            $this->bitacora = new Bitacora(['entrevista_id' => $this->entrevista->id]);
        }
        
        $notaExtra = "MOTIVO NO REALIZADA: " . $this->motivoNoRealizada;
        $this->bitacora->observaciones = $this->observaciones ? $this->observaciones . "\n\n" . $notaExtra : $notaExtra;
        $this->bitacora->estado_formulario = 'no_realizada';
        $this->bitacora->save();

        $this->entrevista->update(['estado' => $this->estadoNoRealizada]);

        \Flux::toast('Entrevista marcada como no realizada correctamente.', variant: 'warning');
        return redirect()->route('entrevistas.agenda');
    }

    public function reabrirBitacora()
    {
        if (!auth()->user()->hasRole(['administrador', 'superadmin'])) {
            abort(403, 'No tienes permisos para reabrir bitácoras.');
        }

        $this->entrevista->update(['estado' => 'pendiente']);
        
        if ($this->bitacora) {
            $this->bitacora->update(['estado_formulario' => 'borrador']);
        }

        \Flux::toast('Bitácora reabierta. El docente puede volver a editarla.', variant: 'success');
        $this->entrevista->refresh();
    }

    private function guardarColeccion($estado)
    {
        if (!$this->bitacora) {
            $this->bitacora = new Bitacora(['entrevista_id' => $this->entrevista->id]);
        }
        
        $this->bitacora->resumen = $this->resumen;
        $this->bitacora->observaciones = $this->observaciones;
        $this->bitacora->acuerdos = $this->acuerdos;
        $this->bitacora->adjuntos_drive = $this->adjuntosDrive;
        $this->bitacora->estado_formulario = $estado;
        
        $this->bitacora->save();
    }
};
?>
<div class="max-w-7xl mx-auto w-full pb-12">
    <!-- Header Rápido -->
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs text-zinc-500 mb-2 uppercase tracking-widest font-semibold">
                <span>Calendario de Entrevistas</span>
                <flux:icon.chevron-right class="size-3" />
                <span>Bitácora Oficial</span>
            </nav>
            <flux:heading size="xl" class="text-[#00376e] dark:text-blue-400 font-extrabold">{{ __('Registro de Sesión') }}</flux:heading>
            <p class="text-zinc-500 text-sm mt-1 flex items-center gap-2">
                <flux:icon.document-text class="size-4" />
                Completando bitácora para la entrevista de protocolo #{{ $entrevista->id }}
            </p>
        </div>
        
        @php
            $isCerrada = in_array($entrevista->estado, ['realizada', 'ausente', 'cancelada']);
        @endphp

        <div class="flex items-center gap-3 w-full md:w-auto">
            @if(!$isCerrada)
                <flux:button variant="ghost" class="w-full md:w-auto cursor-pointer" wire:click="abrirModalReagendar">
                    {{ __('Reagendar') }}
                </flux:button>
                <flux:button variant="ghost" class="w-full md:w-auto cursor-pointer" wire:click="guardarBorrador">
                    {{ __('Guardar Borrador') }}
                </flux:button>
                <flux:button variant="danger" icon="x-circle" class="w-full md:w-auto cursor-pointer" wire:click="abrirModalNoRealizada">
                    {{ __('No Realizada') }}
                </flux:button>
                <flux:button variant="primary" icon="check-circle" class="w-full md:w-auto bg-gradient-to-br from-[#00376e] to-blue-800 hover:from-blue-800 hover:to-blue-900 transition-colors cursor-pointer" wire:click="finalizarBitacora">
                    {{ __('Finalizar Entrevista') }}
                </flux:button>
            @else
                @if($entrevista->estado === 'realizada')
                    <flux:button variant="primary" icon="check-circle" class="w-full md:w-auto bg-emerald-600 text-white hover:bg-emerald-600 pointer-events-none opacity-90 border-0" disabled>
                        {{ __('Entrevista Finalizada') }}
                    </flux:button>
                @else
                    <flux:button variant="danger" icon="x-circle" class="w-full md:w-auto pointer-events-none opacity-90" disabled>
                        {{ __('Entrevista ' . ucfirst($entrevista->estado)) }}
                    </flux:button>
                @endif
                
                @if(auth()->user()->hasRole(['administrador', 'superadmin']))
                    <flux:modal.trigger name="reabrir-bitacora">
                        <flux:button variant="ghost" icon="arrow-path" class="w-full md:w-auto cursor-pointer">
                            {{ __('Reabrir Bitácora') }}
                        </flux:button>
                    </flux:modal.trigger>

                    <flux:modal name="reabrir-bitacora" class="min-w-[22rem]">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg">¿Reabrir bitácora?</flux:heading>
                                <flux:text class="mt-2">
                                    Estás a punto de reabrir esta bitácora y devolverla al estado "pendiente".<br>
                                    Esto permitirá que el docente vuelva a editar su contenido.
                                </flux:text>
                            </div>
                            <div class="flex gap-2">
                                <flux:spacer />
                                <flux:modal.close>
                                    <flux:button variant="ghost">Cancelar</flux:button>
                                </flux:modal.close>
                                <flux:modal.close>
                                    <flux:button variant="primary" wire:click="reabrirBitacora">Sí, reabrir</flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>
                @endif
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
        
        <!-- Columna Izquierda (8/12) -->
        <div class="xl:col-span-8 space-y-8">
            
            <!-- Identidad del Estudiante -->
            <flux:card class="border-l-4 border-l-[#00376e] bg-white dark:bg-zinc-900 shadow-sm">
                <div class="flex flex-col sm:flex-row gap-6">
                    <!-- Placeholder visual del estudiante -->
                    <div class="w-16 h-16 rounded-full bg-zinc-100 dark:bg-zinc-800 border-2 border-zinc-200 dark:border-zinc-700 flex items-center justify-center shrink-0">
                        <flux:icon.user class="size-8 text-zinc-400" />
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-x-8 gap-y-4 flex-1">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] uppercase font-bold text-zinc-400 mb-1 tracking-wider">Estudiante citado</label>
                            <p class="text-lg font-bold text-[#00376e] dark:text-zinc-100 leading-tight">
                                {{ $entrevista->estudiante->nombreCompleto() ?? 'No registrado' }}
                            </p>
                            <p class="text-xs text-zinc-500 font-medium mt-1">
                                {{ $entrevista->estudiante->rutCompleto() ?? '-' }} • {{ $entrevista->estudiante->curso->nombreCompleto() ?? '-' }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-zinc-400 mb-1 tracking-wider">Apoderado Titular</label>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">
                                {{ $entrevista->estudiante->apoderado_nombres ? $entrevista->estudiante->apoderado_nombres . ' ' . $entrevista->estudiante->apoderado_apellido_pat : 'Sin nombre registrado' }}
                            </p>
                            <p class="text-[10px] text-zinc-500 mt-1 uppercase">{{ $entrevista->estudiante->apoderado_parentesco ?? 'Vínculo' }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-zinc-400 mb-1 tracking-wider">Agendada el</label>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">{{ \Carbon\Carbon::parse($entrevista->fecha)->translatedFormat('d M, Y') }}</p>
                            <p class="text-[10px] text-zinc-500 mt-1 uppercase">{{ \Carbon\Carbon::parse($entrevista->hora)->format('H:i') }} hrs</p>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Textos Largos de Bitácora -->
            <flux:card class="space-y-10 shadow-sm bg-white dark:bg-zinc-900">
                
                <!-- Resumen -->
                <section>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center">
                            <flux:icon.document-text class="size-5 text-blue-700 dark:text-blue-300" />
                        </div>
                        <h2 class="text-xl font-bold text-[#00376e] dark:text-zinc-100">Resumen de la Conversación</h2>
                    </div>
                    <div class="relative">
                        <flux:textarea 
                            wire:model.defer="resumen" 
                            rows="6" 
                            placeholder="Describa los puntos clave analizados durante la reunión. Sea objetivo y profesional..." 
                            :disabled="$isCerrada"
                        />
                    </div>
                </section>

                <!-- Acuerdos Rápidos (Dinámicos) -->
                <section>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center">
                            <flux:icon.hand-raised class="size-5 text-emerald-700 dark:text-emerald-300" />
                        </div>
                        <h2 class="text-xl font-bold text-[#00376e] dark:text-zinc-100">Acuerdos o Compromisos</h2>
                    </div>
                    
                    <div class="space-y-4">
                        @foreach($acuerdos as $index => $acuerdo)
                            <div class="flex items-start gap-3 bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border-l-2 border-emerald-500 group relative">
                                <div class="shrink-0 flex text-emerald-500 font-extrabold text-lg mt-0.5 w-6">#{{ $index + 1 }}</div>
                                <div class="flex-1 pr-6">
                                    <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ is_array($acuerdo) ? $acuerdo['titulo'] : $acuerdo }}
                                    </p>
                                    @if(is_array($acuerdo) && !empty($acuerdo['descripcion']))
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1 leading-relaxed whitespace-pre-wrap">{{ $acuerdo['descripcion'] }}</p>
                                    @endif
                                </div>
                                @if(!$isCerrada)
                                <button type="button" wire:click="borrarAcuerdo({{ $index }})" class="absolute right-4 top-4 text-zinc-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100">
                                    <flux:icon.trash class="size-4" />
                                </button>
                                @endif
                            </div>
                        @endforeach

                        <!-- Agregar Nuevo -->
                        @if(!$isCerrada)
                        <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 focus-within:border-[#00376e] transition-colors">
                            <p class="text-xs font-bold text-[#00376e] dark:text-blue-400 uppercase tracking-widest mb-3">Ingresar nuevo compromiso</p>
                            <div class="space-y-3">
                                <flux:input 
                                    wire:model="nuevoAcuerdoTitulo" 
                                    placeholder="Título. Ej: Derivación a Psicopedagogo..." 
                                />
                                <flux:textarea 
                                    wire:model="nuevoAcuerdoDesc" 
                                    rows="2" 
                                    placeholder="Descripción breve (Opcional)..." 
                                />
                                <div class="flex justify-end mt-2">
                                    <flux:button size="sm" variant="ghost" wire:click="agregarAcuerdoRapido">Agregar a la lista</flux:button>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </section>

                <!-- Observaciones Internas -->
                <section>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                            <flux:icon.eye class="size-5 text-amber-700 dark:text-amber-300" />
                        </div>
                        <h2 class="text-xl font-bold text-[#00376e] dark:text-zinc-100">Observaciones Generales</h2>
                    </div>
                    <flux:textarea 
                        wire:model.defer="observaciones" 
                        rows="4" 
                        placeholder="Notas actitudinales, estado anímico, u observaciones que no necesariamente son acuerdos concretos..." 
                        :disabled="$isCerrada"
                    />
                </section>
            </flux:card>
        </div>

        <!-- Columna Derecha (Metadata Pura) -->
        <div class="xl:col-span-4 space-y-6">
            
            <!-- Information Pane -->
            <flux:card class="bg-zinc-50 dark:bg-zinc-800/40 p-6 shadow-sm border border-zinc-200 dark:border-zinc-700">
                <h3 class="text-xs font-bold text-[#00376e] dark:text-blue-400 uppercase tracking-wider mb-4 border-b border-zinc-200 dark:border-zinc-700 pb-3">Estado Analítico</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-zinc-500 font-medium tracking-wide">Estado Ficha</span>
                        <flux:badge color="zinc" size="sm">{{ ucfirst($bitacora?->estado_formulario ?? 'Nuevo Borrador') }}</flux:badge>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-zinc-500 font-medium tracking-wide">Visibilidad</span>
                        <span class="flex items-center gap-1 text-zinc-700 dark:text-zinc-300 font-semibold">
                            <flux:icon.lock-closed class="size-3" /> Privada
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-zinc-500 font-medium tracking-wide">Último Guardado</span>
                        <span class="text-zinc-700 dark:text-zinc-300 font-semibold">{{ $bitacora?->updated_at ? $bitacora->updated_at->diffForHumans() : 'No guardado aún' }}</span>
                    </div>
                </div>
            </flux:card>

            <!-- Detalles Originales de la Cita -->
            <flux:card class="bg-blue-50 dark:bg-blue-900/10 p-6 shadow-sm border border-blue-100 dark:border-blue-800/30">
                <h3 class="text-xs font-bold text-[#00376e] dark:text-blue-400 uppercase tracking-wider mb-4 border-b border-blue-200 dark:border-blue-800/50 pb-3">Detalles de Agendamiento</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-[10px] font-bold text-[#00376e]/70 dark:text-blue-300/70 uppercase tracking-widest mb-1">Motivo Principal</p>
                        <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ ucfirst($entrevista->motivo ?? 'No especificado') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-[#00376e]/70 dark:text-blue-300/70 uppercase tracking-widest mb-1">Nivel de Urgencia</p>
                        <flux:badge color="{{ $entrevista->urgencia === 'alta' ? 'red' : ($entrevista->urgencia === 'media' ? 'amber' : 'zinc') }}" size="sm">{{ ucfirst($entrevista->urgencia ?? 'normal') }}</flux:badge>
                    </div>
                    @if($entrevista->notas_previas)
                    <div>
                        <p class="text-[10px] font-bold text-[#00376e]/70 dark:text-blue-300/70 uppercase tracking-widest mb-1">Observaciones previas</p>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 italic">"{{ $entrevista->notas_previas }}"</p>
                    </div>
                    @endif
                </div>
            </flux:card>

            <!-- Insight de Recepción -->
            @if($entrevista->hora_llegada)
            <div class="bg-amber-50 dark:bg-amber-900/10 p-5 rounded-xl border border-amber-200 dark:border-amber-800/30">
                <div class="flex items-start gap-4">
                    <flux:icon.clock class="size-6 text-amber-600 shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <p class="font-bold text-sm text-amber-900 dark:text-amber-400 mb-1">Registro de Recepción</p>
                        <p class="text-xs font-medium text-amber-700 dark:text-amber-500/80 leading-relaxed">
                            El apoderado fue registrado y derivado al recinto a las {{ \Carbon\Carbon::parse($entrevista->hora_llegada)->format('H:i') }}.
                        </p>

                        @if($entrevista->mensaje_recepcion)
                            <div class="mt-3 p-3 bg-white/50 dark:bg-amber-900/30 rounded-lg border border-amber-200/50 dark:border-amber-800/50">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-amber-800/70 dark:text-amber-500/70 mb-1">Nota de Recepcionista:</p>
                                <p class="text-xs font-bold text-amber-900 dark:text-amber-400 whitespace-pre-wrap">"{{ $entrevista->mensaje_recepcion }}"</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- Módulo en la nube: Documentos Adjuntos -->
            <flux:card class="p-6 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-xs font-bold text-[#00376e] dark:text-blue-400 uppercase tracking-wider flex items-center gap-2">
                        <flux:icon.cloud-arrow-up class="size-4" />
                        Nube de Adjuntos
                    </h3>
                </div>
                
                <div class="space-y-3">
                    @forelse($adjuntosDrive as $index => $archivo)
                        <div class="group relative flex items-center justify-between gap-3 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:border-blue-300 transition-colors cursor-pointer" onclick="window.open('{{ $archivo['url'] }}', '_blank')">
                            <div class="flex flex-1 items-center gap-3 overflow-hidden">
                                <flux:icon.link class="size-5 text-blue-500 shrink-0" />
                                <div class="overflow-hidden">
                                    <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 group-hover:text-blue-600 truncate block">
                                        {{ $archivo['nombre'] }}
                                    </span>
                                    <p class="text-[10px] text-zinc-400">Vinculado {{ $archivo['fecha'] ?? 'Recientemente' }}</p>
                                </div>
                            </div>
                            <!-- Delete Button -->
                            @if(!$isCerrada)
                            <button type="button" wire:click.stop="quitarAdjunto({{ $index }})" class="p-1.5 text-zinc-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all shrink-0 z-10" title="Desvincular archivo">
                                <flux:icon.trash class="size-4" />
                            </button>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-6 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl bg-zinc-50 dark:bg-zinc-800/20">
                            <flux:icon.folder class="size-8 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                            <p class="text-xs font-medium text-zinc-500">No hay documentos de Drive vinculados a esta cita.</p>
                        </div>
                    @endforelse
                </div>

                @if(!$isCerrada)
                <button 
                    type="button" 
                    wire:click="abrirModalAdjunto"
                    class="w-full mt-5 flex items-center justify-center gap-2 py-3 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 rounded-lg text-xs font-bold text-zinc-600 dark:text-zinc-300 transition-colors"
                >
                    <flux:icon.plus class="size-4" />
                    Vincular Archivo Google Drive
                </button>
                @endif
            </flux:card>
        </div>
    </div>

    <!-- Modal Enlace Drive -->
    <flux:modal wire:model="modalAdjunto" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon.cloud class="size-5 text-blue-500" />
                    Vincular desde Google Drive
                </flux:heading>
                <flux:text class="mt-1">Pegue el enlace público o compartido de su Documento de Drive. Esto no ocupará espacio ni cuota en el servidor.</flux:text>
            </div>

            <form wire:submit.prevent="guardarEnlaceDrive" class="space-y-5">
                <flux:input 
                    wire:model="nuevoAdjuntoNombre" 
                    label="Nombre del Archivo" 
                    placeholder="Ej: Informe Psicopedagógico 2024" 
                    required 
                />
                
                <flux:input 
                    wire:model="nuevoAdjuntoUrl" 
                    label="Enlace a Google Drive" 
                    placeholder="https://drive.google.com/file/d/..." 
                    type="url"
                    required 
                />

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button wire:click="$set('modalAdjunto', false)" variant="ghost">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">Vincular Archivo</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal No Realizada -->
    <flux:modal wire:model="modalNoRealizada" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2 text-red-600">
                    <flux:icon.x-circle class="size-5" />
                    Marcar como No Realizada
                </flux:heading>
                <flux:text class="mt-1">Seleccione un motivo rápido o escriba el suyo. Esto cerrará la entrevista y afectará la hoja de vida.</flux:text>
            </div>

            <div class="flex flex-col gap-2">
                <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-1">Motivos Rápidos</span>
                <div class="flex flex-wrap gap-2">
                    <flux:badge as="button" wire:click="setMotivoPredeterminado('Apoderado no asistió a la cita programada sin dar aviso previo.', 'ausente')" class="cursor-pointer border hover:bg-zinc-100 dark:hover:bg-zinc-700">Apoderado no asistió (Ausente)</flux:badge>
                    <flux:badge as="button" wire:click="setMotivoPredeterminado('Apoderado avisó previamente que no asistiría.', 'cancelada')" class="cursor-pointer border hover:bg-zinc-100 dark:hover:bg-zinc-700">Avisó inasistencia (Cancelada)</flux:badge>
                    <flux:badge as="button" wire:click="setMotivoPredeterminado('Error al agendar la cita. Entrevista anulada.', 'cancelada')" class="cursor-pointer border hover:bg-zinc-100 dark:hover:bg-zinc-700">Error al agendar (Cancelada)</flux:badge>
                </div>
            </div>

            <form wire:submit.prevent="marcarNoRealizada" class="space-y-5">
                <flux:select wire:model="estadoNoRealizada" label="Estado Oficial" required>
                    <option value="" disabled>Seleccione un estado...</option>
                    <option value="ausente">Ausente (No se presentó a la cita)</option>
                    <option value="cancelada">Cancelada (Avisó previamente o fue error)</option>
                </flux:select>

                <flux:textarea 
                    wire:model="motivoNoRealizada" 
                    label="Motivo o Justificación" 
                    placeholder="Detalle la razón exacta..." 
                    rows="3"
                    required 
                />

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button wire:click="$set('modalNoRealizada', false)" variant="ghost">Volver</flux:button>
                    <flux:button type="submit" variant="danger">Confirmar y Cerrar Cita</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal Reagendar -->
    <flux:modal wire:model="modalReagendar" class="md:w-[28rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon.calendar-days class="size-5 text-blue-500" />
                    Reagendar Entrevista
                </flux:heading>
                <flux:text class="mt-1">Cambia la fecha o la hora de la cita. Esto actualizará el registro inmediatamente.</flux:text>
            </div>

            <form wire:submit.prevent="confirmarReagendamiento" class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <flux:date-picker wire:model="nuevaFecha" label="Nueva Fecha" with-today required />
                    <flux:time-picker wire:model="nuevaHora" label="Nueva Hora" min="08:00" max="18:30" interval="15" time-format="24-hour" required />
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button wire:click="$set('modalReagendar', false)" variant="ghost">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">Confirmar Cambio</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
