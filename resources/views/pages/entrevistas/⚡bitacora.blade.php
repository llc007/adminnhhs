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

    // Modal Firma Presencial
    public bool $modalFirmaPresencial = false;
    public string $firmanteNombre = '';
    public string $firmanteRutNumero = '';
    public string $firmanteRutDv = '';
    public string $firmaSvg = '';

    // Modal Firma Online
    public bool $modalFirmaOnline = false;
    public string $firmanteEmail = '';

    // Modal Enviar Resumen
    public bool $modalEnviarResumen = false;
    public bool $enviarApoderado = true;
    public string $emailApoderado = '';
    public bool $enviarEstudiante = false;
    public string $emailEstudiante = '';
    public bool $enviarOtro = false;
    public string $emailOtro = '';
    public string $nombreOtro = '';

    public function mount(Entrevista $entrevista)
    {
        $this->entrevista = $entrevista->load(['estudiante.curso', 'user', 'bitacora']);

        if ($this->entrevista->bitacora) {
            $this->bitacora = $this->entrevista->bitacora;
            $this->resumen = $this->bitacora->resumen ?? '';
            $this->observaciones = $this->bitacora->observaciones ?? '';
            $this->acuerdos = $this->bitacora->acuerdos ?? [];
            $this->adjuntosDrive = $this->bitacora->adjuntos_drive ?? [];
        }

        if (session()->has('status_toast')) {
            $toast = session('status_toast');
            \Flux::toast(
                heading: $toast['heading'],
                text: $toast['text'],
                variant: $toast['variant'] ?? 'success'
            );
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
        $this->authorize('update', $this->entrevista);
        $this->guardarColeccion('borrador');
        \Flux::toast('Borrador actualizado correctamente.', variant: 'success');
    }

    public function finalizarBitacora()
    {
        $this->authorize('update', $this->entrevista);
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
        
        session()->flash('status_toast', [
            'heading' => 'Entrevista Finalizada',
            'text' => 'La bitácora ha sido cerrada y guardada exitosamente.',
            'variant' => 'success',
        ]);

        return redirect()->route('entrevistas.bitacora', $this->entrevista->id);
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
        if (!auth()->user()->can('cancelar-entrevistas') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        $this->motivoNoRealizada = '';
        $this->estadoNoRealizada = '';
        $this->modalNoRealizada = true;
    }

    public function setMotivoPredeterminado($motivo, $estado)
    {
        if (!auth()->user()->can('cancelar-entrevistas') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        $this->motivoNoRealizada = $motivo;
        $this->estadoNoRealizada = $estado;
    }

    public function marcarNoRealizada()
    {
        if (!auth()->user()->can('cancelar-entrevistas') && !auth()->user()->hasRole('superadmin')) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
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

        // Enviar notificación de cancelación o inasistencia al Docente
        if ($this->entrevista->user) {
            $this->entrevista->user->notify(new \App\Notifications\EntrevistaCancelada($this->entrevista, 'docente'));
        }

        // Enviar notificación de cancelación o inasistencia al Apoderado (si tiene email válido)
        if ($this->entrevista->estudiante && !empty($this->entrevista->estudiante->apoderado_email)) {
            \Illuminate\Support\Facades\Notification::route('mail', $this->entrevista->estudiante->apoderado_email)
                ->notify(new \App\Notifications\EntrevistaCancelada($this->entrevista, 'apoderado'));
        }

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

    public function abrirModalFirmaPresencial()
    {
        if (in_array($this->entrevista->estado, ['realizada', 'ausente', 'cancelada'])) {
            abort(403, 'No se puede modificar la firma de una entrevista finalizada.');
        }

        $estudiante = $this->entrevista->estudiante;
        $this->firmanteNombre = ($this->bitacora && $this->bitacora->firmante_nombre) 
            ? $this->bitacora->firmante_nombre 
            : ($estudiante ? ($estudiante->apoderado_nombres ?? '') : '');

        if ($this->bitacora && $this->bitacora->firmante_rut) {
            $parts = explode('-', $this->bitacora->firmante_rut);
            $this->firmanteRutNumero = $parts[0] ?? '';
            $this->firmanteRutDv = $parts[1] ?? '';
        } else {
            $this->firmanteRutNumero = $estudiante ? ($estudiante->apoderado_rut_numero ?? '') : '';
            $this->firmanteRutDv = $estudiante ? ($estudiante->apoderado_rut_dv ?? '') : '';
        }

        $this->firmaSvg = ($this->bitacora && $this->bitacora->firma_svg) ? $this->bitacora->firma_svg : '';
        $this->modalFirmaPresencial = true;
    }

    public function guardarFirmaPresencial()
    {
        if (in_array($this->entrevista->estado, ['realizada', 'ausente', 'cancelada'])) {
            abort(403, 'No se puede modificar la firma de una entrevista finalizada.');
        }

        $this->validate([
            'firmanteNombre' => 'required|string|min:3|max:255',
            'firmanteRutNumero' => 'required|string|min:7|max:12',
        ], [
            'firmanteNombre.required' => 'Ingrese el nombre del firmante.',
            'firmanteRutNumero.required' => 'Ingrese el RUT del firmante.',
        ]);

        $this->guardarColeccion('borrador');

        $rutCompleto = trim($this->firmanteRutNumero);
        if (trim($this->firmanteRutDv) !== '') {
            $rutCompleto .= '-' . strtoupper(trim($this->firmanteRutDv));
        }

        $this->bitacora->update([
            'estado_firma' => 'firmada_presencial',
            'firmante_nombre' => mb_strtoupper($this->firmanteNombre, 'UTF-8'),
            'firmante_rut' => $rutCompleto,
            'firma_svg' => $this->firmaSvg ?: null,
            'firmado_at' => now(),
        ]);

        $this->modalFirmaPresencial = false;
        \Flux::toast('Firma presencial registrada correctamente.', variant: 'success');
    }

    public function abrirModalFirmaOnline()
    {
        if (in_array($this->entrevista->estado, ['realizada', 'ausente', 'cancelada'])) {
            abort(403, 'No se puede solicitar firma en una entrevista finalizada.');
        }

        $estudiante = $this->entrevista->estudiante;
        $this->firmanteEmail = ($this->bitacora && $this->bitacora->firmante_email) 
            ? $this->bitacora->firmante_email 
            : ($estudiante ? ($estudiante->apoderado_email ?? '') : '');
        $this->modalFirmaOnline = true;
    }

    public function enviarFirmaOnline()
    {
        if (in_array($this->entrevista->estado, ['realizada', 'ausente', 'cancelada'])) {
            abort(403, 'No se puede solicitar firma en una entrevista finalizada.');
        }

        $this->validate([
            'firmanteEmail' => 'required|email',
        ], [
            'firmanteEmail.required' => 'Ingrese el correo electrónico del firmante.',
            'firmanteEmail.email' => 'Ingrese un correo electrónico válido.',
        ]);

        $this->guardarColeccion('borrador');

        $token = \Illuminate\Support\Str::random(40);
        $this->bitacora->update([
            'firmante_email' => $this->firmanteEmail,
            'firma_token' => $token,
            'firma_token_expires_at' => now()->addDays(7),
        ]);

        $signedUrl = route('entrevistas.firma_publica', ['token' => $token]);

        \Illuminate\Support\Facades\Notification::route('mail', $this->firmanteEmail)
            ->notify(new \App\Notifications\BitacoraSolicitudFirmaNotification($this->bitacora, $signedUrl));

        $this->modalFirmaOnline = false;
        \Flux::toast('Solicitud de firma enviada por correo exitosamente.', variant: 'success');
    }

    public function abrirModalEnviarResumen()
    {
        $estudiante = $this->entrevista->estudiante;
        $this->emailApoderado = $estudiante ? ($estudiante->apoderado_email ?? '') : '';
        $this->emailEstudiante = $estudiante ? ($estudiante->email ?? '') : '';
        $this->modalEnviarResumen = true;
    }

    public function enviarResumenCorreos()
    {
        $this->guardarColeccion('finalizado');

        $enviados = 0;

        if ($this->enviarApoderado && !empty($this->emailApoderado)) {
            \Illuminate\Support\Facades\Notification::route('mail', $this->emailApoderado)
                ->notify(new \App\Notifications\BitacoraResumenNotification($this->bitacora, 'Apoderado/a'));
            $enviados++;
        }

        if ($this->enviarEstudiante && !empty($this->emailEstudiante)) {
            \Illuminate\Support\Facades\Notification::route('mail', $this->emailEstudiante)
                ->notify(new \App\Notifications\BitacoraResumenNotification($this->bitacora, 'Estudiante'));
            $enviados++;
        }

        if ($this->enviarOtro && !empty($this->emailOtro)) {
            \Illuminate\Support\Facades\Notification::route('mail', $this->emailOtro)
                ->notify(new \App\Notifications\BitacoraResumenNotification($this->bitacora, $this->nombreOtro ?: 'Familiar/Tutor'));
            $enviados++;
        }

        $this->modalEnviarResumen = false;

        return $this->finalizarBitacora();
    }

    private function guardarColeccion($estado)
    {
        if (trim($this->nuevoAcuerdoTitulo) !== '') {
            $this->acuerdos[] = [
                'titulo' => trim($this->nuevoAcuerdoTitulo),
                'descripcion' => trim($this->nuevoAcuerdoDesc),
            ];
            $this->nuevoAcuerdoTitulo = '';
            $this->nuevoAcuerdoDesc = '';
        }

        if (!$this->bitacora) {
            $this->bitacora = Bitacora::firstOrNew(['entrevista_id' => $this->entrevista->id]);
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
            @if ($isCerrada)
                @if($entrevista->estado === 'realizada')
                    <flux:badge color="emerald" icon="check-circle" class="p-2 text-xs font-bold">
                        {{ __('Entrevista Realizada') }}
                    </flux:badge>
                @else
                    <flux:badge color="red" icon="x-circle" class="p-2 text-xs font-bold">
                        {{ __('Entrevista ' . ucfirst($entrevista->estado)) }}
                    </flux:badge>
                @endif

                @if (auth()->user()->hasRole('superadmin'))
                    <flux:modal.trigger name="reabrir-bitacora">
                        <flux:button variant="ghost" icon="arrow-path" class="cursor-pointer">
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
            
            <!-- Panel de Acciones y Firma Digital (En Columna Derecha - Primero) -->
            <flux:card class="p-5 shadow-sm border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 rounded-xl space-y-4">
                <div class="border-b border-zinc-100 dark:border-zinc-800 pb-3">
                    <h3 class="text-xs font-bold text-[#00376e] dark:text-blue-400 uppercase tracking-wider flex items-center gap-2 mb-2">
                        <flux:icon.command-line class="size-4" />
                        Acciones de Bitácora
                    </h3>

                    {{-- Badge Estado de Firma --}}
                    @if ($bitacora && $bitacora->estado_firma === 'firmada_presencial')
                        <div class="flex items-center justify-center gap-2 py-2 px-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 rounded-lg text-xs font-bold text-emerald-700 dark:text-emerald-300">
                            <flux:icon.check-circle class="size-4 text-emerald-600 shrink-0" />
                            <span>Firmada Presencial ({{ $bitacora->firmante_nombre }})</span>
                        </div>
                    @elseif ($bitacora && $bitacora->estado_firma === 'firmada_online')
                        <div class="flex items-center justify-center gap-2 py-2 px-3 bg-cyan-50 dark:bg-cyan-950/50 border border-cyan-200 dark:border-cyan-800 rounded-lg text-xs font-bold text-cyan-700 dark:text-cyan-300">
                            <flux:icon.envelope class="size-4 text-cyan-600 shrink-0" />
                            <span>Firmada Online ({{ $bitacora->firmante_nombre }})</span>
                        </div>
                    @else
                        <div class="flex items-center justify-center gap-2 py-2 px-3 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 rounded-lg text-xs font-bold text-amber-700 dark:text-amber-300">
                            <flux:icon.clock class="size-4 text-amber-600 shrink-0" />
                            <span>Pendiente de Firma Digital</span>
                        </div>
                    @endif

                    {{-- Visualización de la Firma Guardada --}}
                    @if ($bitacora && $bitacora->firma_svg)
                        <div class="mt-2.5 p-3 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl space-y-1.5 text-center">
                            <div class="flex items-center justify-between text-[10px] uppercase font-bold text-zinc-400">
                                <span>Firma Registrada</span>
                                <span>{{ $bitacora->firmado_at ? $bitacora->firmado_at->format('d/m/Y H:i') : '' }}</span>
                            </div>
                            <div class="p-1 bg-zinc-50 dark:bg-zinc-900 rounded-lg flex justify-center border border-zinc-100 dark:border-zinc-800">
                                <img src="{{ $bitacora->firma_svg }}" alt="Firma Digital" class="max-h-20 object-contain" />
                            </div>
                            <div class="text-[11px] text-zinc-600 dark:text-zinc-300 font-semibold truncate">
                                {{ $bitacora->firmante_nombre }} ({{ $bitacora->firmante_rut }})
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Botones de Acción Uniformes --}}
                <div class="space-y-2">
                    @if (!$isCerrada)
                        {{-- 1. Firmar Presencialmente --}}
                        @if ($bitacora && in_array($bitacora->estado_firma, ['firmada_presencial', 'firmada_online']))
                            <button 
                                type="button" 
                                wire:click="abrirModalFirmaPresencial"
                                class="w-full flex items-center gap-3 px-3.5 py-2.5 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/50 border border-emerald-200 dark:border-emerald-800 rounded-lg text-xs font-semibold text-emerald-800 dark:text-emerald-300 transition-colors cursor-pointer"
                            >
                                <flux:icon.pencil-square class="size-4 text-emerald-600 shrink-0" />
                                <span class="flex-1 text-left">Ver / Refirmar Presencial</span>
                                <flux:icon.chevron-right class="size-3.5 text-emerald-500" />
                            </button>
                        @else
                            <button 
                                type="button" 
                                wire:click="abrirModalFirmaPresencial"
                                class="w-full flex items-center gap-3 px-3.5 py-2.5 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-800/60 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition-colors cursor-pointer"
                            >
                                <flux:icon.pencil-square class="size-4 text-amber-600 shrink-0" />
                                <span class="flex-1 text-left">Firmar Presencialmente</span>
                                <flux:icon.chevron-right class="size-3.5 text-zinc-400" />
                            </button>
                        @endif

                        {{-- 2. Enviar a Firma por Correo --}}
                        <button 
                            type="button" 
                            wire:click="abrirModalFirmaOnline"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-800/60 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition-colors cursor-pointer"
                        >
                            <flux:icon.paper-airplane class="size-4 text-cyan-600 shrink-0" />
                            <span class="flex-1 text-left">Enviar a Firma por Correo</span>
                            <flux:icon.chevron-right class="size-3.5 text-zinc-400" />
                        </button>
                    @endif

                    {{-- 3. Reagendar Cita --}}
                    @if(!$isCerrada)
                        <button 
                            type="button" 
                            wire:click="abrirModalReagendar"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-800/60 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition-colors cursor-pointer"
                        >
                            <flux:icon.calendar-days class="size-4 text-indigo-600 shrink-0" />
                            <span class="flex-1 text-left">Reagendar Cita</span>
                            <flux:icon.chevron-right class="size-3.5 text-zinc-400" />
                        </button>
                    @endif

                    {{-- 4. Guardar Borrador --}}
                    @if(!$isCerrada)
                        <button 
                            type="button" 
                            wire:click="guardarBorrador"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-800/60 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs font-semibold text-zinc-700 dark:text-zinc-200 transition-colors cursor-pointer"
                        >
                            <flux:icon.bookmark class="size-4 text-zinc-600 dark:text-zinc-400 shrink-0" />
                            <span class="flex-1 text-left">Guardar Borrador</span>
                            <flux:icon.chevron-right class="size-3.5 text-zinc-400" />
                        </button>
                    @endif

                    <div class="pt-3 space-y-2 border-t border-zinc-100 dark:border-zinc-800">
                        {{-- 5. No Realizada --}}
                        @if(!$isCerrada && (auth()->user()->can('cancelar-entrevistas') || auth()->user()->hasRole('superadmin')))
                            <button 
                                type="button" 
                                wire:click="abrirModalNoRealizada"
                                class="w-full flex items-center justify-center gap-2 py-2.5 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-900/50 border border-red-200 dark:border-red-800 rounded-lg text-xs font-bold text-red-600 dark:text-red-400 transition-colors cursor-pointer"
                            >
                                <flux:icon.x-circle class="size-4 shrink-0" />
                                <span>Marcar como No Realizada</span>
                            </button>
                        @endif

                        {{-- 6. Finalizar Entrevista --}}
                        @if(!$isCerrada)
                            <button 
                                type="button" 
                                wire:click="abrirModalEnviarResumen"
                                class="w-full flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-[#00376e] to-blue-800 hover:from-blue-800 hover:to-blue-900 text-white rounded-lg text-xs font-bold shadow-sm transition-all cursor-pointer"
                            >
                                <flux:icon.check-circle class="size-4 shrink-0" />
                                <span>Finalizar Entrevista</span>
                            </button>
                        @else
                            @if($entrevista->estado === 'realizada')
                                <div class="w-full flex items-center justify-center gap-2 py-2.5 bg-emerald-600 text-white rounded-lg text-xs font-bold">
                                    <flux:icon.check-circle class="size-4" />
                                    <span>Entrevista Finalizada</span>
                                </div>
                            @else
                                <div class="w-full flex items-center justify-center gap-2 py-2.5 bg-red-600 text-white rounded-lg text-xs font-bold">
                                    <flux:icon.x-circle class="size-4" />
                                    <span>Entrevista {{ ucfirst($entrevista->estado) }}</span>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </flux:card>

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

    <!-- Modal Firma Presencial -->
    <flux:modal wire:model="modalFirmaPresencial" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon.pencil-square class="size-5 text-blue-600" />
                    Firma Presencial del Apoderado / Asistente
                </flux:heading>
                <flux:text class="mt-1">Confirme o modifique el nombre y RUT del asistente antes de realizar la firma en pantalla.</flux:text>
            </div>

            <form wire:submit.prevent="guardarFirmaPresencial" class="space-y-4">
                <flux:input wire:model="firmanteNombre" label="Nombre Completo del Firmante" placeholder="EJ: MARÍA PAZ LÓPEZ" required class="uppercase" />
                <flux:error name="firmanteNombre" />

                <div class="flex gap-2 items-end">
                    <flux:input wire:model="firmanteRutNumero" label="RUT del Firmante" placeholder="12345678" class="flex-1" required />
                    <flux:input wire:model="firmanteRutDv" label="DV" placeholder="K" class="w-16 uppercase" maxlength="1" />
                </div>
                <flux:error name="firmanteRutNumero" />

                {{-- Canvas Pad Táctil / Mouse --}}
                <div x-data="{
                    canvas: null,
                    ctx: null,
                    isDrawing: false,
                    hasDrawn: false,
                    init() {
                        this.$nextTick(() => {
                            this.setupCanvas();
                        });
                    },
                    setupCanvas() {
                        this.canvas = this.$refs.canvas;
                        if (!this.canvas) return;
                        this.ctx = this.canvas.getContext('2d');
                        
                        const rect = this.canvas.getBoundingClientRect();
                        const w = (rect.width && rect.width > 0) ? Math.round(rect.width) : 450;
                        const h = (rect.height && rect.height > 0) ? Math.round(rect.height) : 150;

                        this.canvas.width = w;
                        this.canvas.height = h;

                        this.ctx.lineWidth = 3;
                        this.ctx.lineCap = 'round';
                        this.ctx.lineJoin = 'round';
                        this.ctx.strokeStyle = '#020617';

                        if ($wire.firmaSvg && !this.hasDrawn) {
                            const img = new Image();
                            img.onload = () => {
                                if (this.ctx && this.canvas) {
                                    this.ctx.drawImage(img, 0, 0, this.canvas.width, this.canvas.height);
                                    this.hasDrawn = true;
                                }
                            };
                            img.src = $wire.firmaSvg;
                        }

                        if (!this.canvas.dataset.listenersAttached) {
                            this.canvas.dataset.listenersAttached = 'true';

                            const getPos = (e) => {
                                const r = this.canvas.getBoundingClientRect();
                                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                                const scaleX = r.width > 0 ? (this.canvas.width / r.width) : 1;
                                const scaleY = r.height > 0 ? (this.canvas.height / r.height) : 1;
                                return {
                                    x: (clientX - r.left) * scaleX,
                                    y: (clientY - r.top) * scaleY
                                };
                            };

                            const onDown = (e) => {
                                e.preventDefault();
                                if (e.pointerId && this.canvas.setPointerCapture) {
                                    try { this.canvas.setPointerCapture(e.pointerId); } catch(err) {}
                                }
                                this.isDrawing = true;
                                this.hasDrawn = true;
                                const pos = getPos(e);
                                this.ctx.beginPath();
                                this.ctx.moveTo(pos.x, pos.y);
                            };

                            const onMove = (e) => {
                                if (!this.isDrawing) return;
                                e.preventDefault();
                                const pos = getPos(e);
                                this.ctx.lineTo(pos.x, pos.y);
                                this.ctx.stroke();
                            };

                            const onUp = (e) => {
                                if (this.isDrawing) {
                                    this.isDrawing = false;
                                    if (e.pointerId && this.canvas.releasePointerCapture) {
                                        try { this.canvas.releasePointerCapture(e.pointerId); } catch(err) {}
                                    }
                                    $wire.set('firmaSvg', this.canvas.toDataURL('image/png'));
                                }
                            };

                            this.canvas.addEventListener('pointerdown', onDown);
                            this.canvas.addEventListener('pointermove', onMove);
                            this.canvas.addEventListener('pointerup', onUp);
                            this.canvas.addEventListener('pointercancel', onUp);

                            this.canvas.addEventListener('mousedown', onDown);
                            this.canvas.addEventListener('mousemove', onMove);
                            this.canvas.addEventListener('mouseup', onUp);

                            this.canvas.addEventListener('touchstart', onDown, { passive: false });
                            this.canvas.addEventListener('touchmove', onMove, { passive: false });
                            this.canvas.addEventListener('touchend', onUp);
                        }
                    },
                    clearCanvas() {
                        this.setupCanvas();
                        if (this.canvas && this.ctx) {
                            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                            this.hasDrawn = false;
                            $wire.set('firmaSvg', '');
                        }
                    }
                }" wire:ignore class="space-y-2 pt-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-500">Trazar Firma Digital:</label>
                    <div class="relative border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-950 overflow-hidden">
                        <canvas 
                            x-ref="canvas" 
                            class="w-full h-36 touch-none cursor-crosshair bg-white"
                        ></canvas>
                        <div x-show="!hasDrawn" class="absolute inset-0 flex items-center justify-center pointer-events-none text-xs text-zinc-400">
                            Firme aquí con mouse o pantalla táctil
                        </div>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <button type="button" @click="clearCanvas()" class="text-zinc-500 hover:text-zinc-700 underline cursor-pointer">Limpiar trazado</button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button wire:click="$set('modalFirmaPresencial', false)" variant="ghost">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" icon="check">Guardar Firma</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal Firma Online -->
    <flux:modal wire:model="modalFirmaOnline" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon.paper-airplane class="size-5 text-blue-600" />
                    Enviar Enlace a Firma Digital por Correo
                </flux:heading>
                <flux:text class="mt-1">Se enviará un correo con un enlace seguro para que el apoderado o firmante apruebe y firme los acuerdos.</flux:text>
            </div>

            <form wire:submit.prevent="enviarFirmaOnline" class="space-y-4">
                <flux:input wire:model="firmanteEmail" label="Correo Electrónico del Firmante" type="email" placeholder="apoderado@correo.com" required />
                <flux:error name="firmanteEmail" />

                <div class="flex justify-end gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button wire:click="$set('modalFirmaOnline', false)" variant="ghost">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" icon="paper-airplane">Enviar Solicitud</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal Enviar Resumen -->
    <flux:modal wire:model="modalEnviarResumen" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <flux:icon.envelope class="size-5 text-[#00376e]" />
                    Enviar Resumen de Entrevista y Compromisos
                </flux:heading>
                <flux:text class="mt-1">Seleccione a quiénes desea enviar el resumen formal y compromisos alcanzados.</flux:text>
                <div class="mt-2 p-2 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 rounded-lg text-xs text-amber-800 dark:text-amber-300 flex items-center gap-2">
                    <flux:icon.shield-exclamation class="size-4 shrink-0" />
                    <span>Nota: Las observaciones generales internas NO serán incluidas en el correo.</span>
                </div>
            </div>

            <form wire:submit.prevent="enviarResumenCorreos" class="space-y-5">
                <div class="space-y-4">
                    {{-- Opción Apoderado --}}
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-2">
                        <flux:checkbox wire:model.live="enviarApoderado" label="Enviar a Apoderado/a Titular" />
                        @if($enviarApoderado)
                            <flux:input wire:model="emailApoderado" type="email" placeholder="correo.apoderado@gmail.com" class="text-xs" />
                        @endif
                    </div>

                    {{-- Opción Estudiante --}}
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-2">
                        <flux:checkbox wire:model.live="enviarEstudiante" label="Enviar a Estudiante" />
                        @if($enviarEstudiante)
                            <flux:input wire:model="emailEstudiante" type="email" placeholder="estudiante@colegio.cl" class="text-xs" />
                        @endif
                    </div>

                    {{-- Opción Otro Correo --}}
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-3">
                        <flux:checkbox wire:model.live="enviarOtro" label="Enviar a otro destinatario / familiar" />
                        @if($enviarOtro)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <flux:input wire:model="nombreOtro" placeholder="Nombre (Ej: Tía María)" class="text-xs" />
                                <flux:input wire:model="emailOtro" type="email" placeholder="familiar@correo.com" class="text-xs" />
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button wire:click="$set('modalEnviarResumen', false)" variant="ghost">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" icon="check-circle" class="bg-gradient-to-r from-[#00376e] to-blue-800 hover:from-blue-800 hover:to-blue-900 text-white font-bold">Enviar Correo(s) y finalizar entrevista</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
