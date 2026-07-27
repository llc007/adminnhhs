<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Entrevista;
use App\Notifications\RespuestaAsistenciaDocenteNotification;

new #[Layout('layouts.blank')] #[Title('Confirmación de Asistencia a Entrevista')] class extends Component {
    public string $token = '';
    public ?Entrevista $entrevista = null;
    public bool $tokenValido = false;
    public string $estadoAsistencia = 'pendiente';
    public string $emailRespuesta = '';
    public string $motivoRechazo = '';
    public bool $exitoso = false;
    public bool $mostrarFormularioRechazo = false;

    public function mount(string $token)
    {
        $this->token = $token;
        $this->entrevista = Entrevista::with(['estudiante.curso', 'user'])
            ->where('confirmacion_token', $token)
            ->first();

        if (! $this->entrevista) {
            $this->tokenValido = false;
            return;
        }

        $this->tokenValido = true;
        $this->estadoAsistencia = $this->entrevista->estado_asistencia ?? 'pendiente';

        if ($this->entrevista->correo_citacion_enviado) {
            $this->emailRespuesta = $this->entrevista->correo_citacion_enviado;
        } elseif ($this->entrevista->estudiante && $this->entrevista->estudiante->apoderado_email) {
            $this->emailRespuesta = $this->entrevista->estudiante->apoderado_email;
        }
    }

    public function confirmarAsistencia()
    {
        if (! $this->tokenValido || $this->estadoAsistencia !== 'pendiente') {
            return;
        }

        $this->validate([
            'emailRespuesta' => 'required|email|max:255',
        ], [
            'emailRespuesta.required' => 'Debe ingresar su correo electrónico para confirmar.',
            'emailRespuesta.email' => 'Ingrese un correo electrónico válido.',
        ]);

        $this->entrevista->update([
            'estado_asistencia' => 'confirmada',
            'confirmado_at' => now('America/Santiago'),
            'confirmado_desde_email' => trim($this->emailRespuesta),
        ]);

        if ($this->entrevista->user) {
            $this->entrevista->user->notify(new RespuestaAsistenciaDocenteNotification($this->entrevista));
        }

        $this->estadoAsistencia = 'confirmada';
        $this->exitoso = true;
    }

    public function abrirFormularioRechazo()
    {
        $this->mostrarFormularioRechazo = true;
    }

    public function rechazarAsistencia()
    {
        if (! $this->tokenValido || $this->estadoAsistencia !== 'pendiente') {
            return;
        }

        $this->validate([
            'emailRespuesta' => 'required|email|max:255',
            'motivoRechazo' => 'required|string|min:5|max:1000',
        ], [
            'emailRespuesta.required' => 'Debe ingresar su correo electrónico.',
            'emailRespuesta.email' => 'Ingrese un correo electrónico válido.',
            'motivoRechazo.required' => 'Por favor explique brevemente el motivo por el cual no podrá asistir.',
            'motivoRechazo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $this->entrevista->update([
            'estado_asistencia' => 'rechazada',
            'confirmado_at' => now('America/Santiago'),
            'confirmado_desde_email' => trim($this->emailRespuesta),
            'motivo_rechazo_asistencia' => trim($this->motivoRechazo),
        ]);

        if ($this->entrevista->user) {
            $this->entrevista->user->notify(new RespuestaAsistenciaDocenteNotification($this->entrevista));
        }

        $this->estadoAsistencia = 'rechazada';
        $this->exitoso = true;
    }
};
?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 py-8 px-4 flex justify-center items-start font-sans">
    <div class="w-full max-w-2xl bg-white dark:bg-zinc-900 shadow-xl rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 md:p-8 space-y-6">

        {{-- Header institucional --}}
        <div class="text-center border-b border-zinc-100 dark:border-zinc-800 pb-6">
            <div class="inline-flex items-center justify-center size-12 rounded-xl bg-[#00376e] text-white mb-3 shadow-md">
                <flux:icon.calendar-days class="size-6" />
            </div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Liceo New Heaven High School</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 uppercase tracking-wider font-medium">
                Confirmación de Asistencia a Entrevista
            </p>
        </div>

        @if (! $tokenValido)
            <div class="p-6 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl text-center space-y-3">
                <flux:icon.exclamation-triangle class="size-10 text-red-500 mx-auto" />
                <h2 class="text-lg font-bold text-red-700 dark:text-red-400">Enlace No Válido o No Encontrado</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    El enlace para confirmar esta entrevista no existe o ya no está disponible. Por favor, comuníquese con el colegio.
                </p>
            </div>
        @elseif ($estadoAsistencia === 'confirmada')
            <div class="p-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-xl text-center space-y-3">
                <flux:icon.check-circle class="size-12 text-emerald-600 dark:text-emerald-400 mx-auto" />
                <h2 class="text-xl font-bold text-emerald-800 dark:text-emerald-300">
                    ¡Asistencia Confirmada!
                </h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Ha confirmado su asistencia para la cita con el/la docente <strong>{{ $entrevista->user ? $entrevista->user->nombreCompleto() : 'Docente' }}</strong>.
                </p>
                <p class="text-xs text-zinc-500">
                    Respondió desde: <strong>{{ $entrevista->confirmado_desde_email }}</strong> el {{ $entrevista->confirmado_at ? $entrevista->confirmado_at->setTimezone('America/Santiago')->format('d/m/Y H:i hrs') : 'Registrado' }}
                </p>
            </div>

            {{-- Detalle de la Cita --}}
            <div class="bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-2 text-sm">
                <p><strong>Estudiante:</strong> {{ $entrevista->estudiante ? $entrevista->estudiante->nombreCompleto() : 'Estudiante' }}</p>
                <p><strong>Fecha y Hora:</strong> {{ \Carbon\Carbon::parse($entrevista->fecha)->translatedFormat('l d \d\e F, Y') }} a las {{ \Carbon\Carbon::parse($entrevista->hora)->format('H:i') }} hrs</p>
                <p><strong>Lugar / Modalidad:</strong> {{ $entrevista->lugar }}</p>
                <p><strong>Motivo:</strong> {{ ucfirst($entrevista->motivo) }}</p>
            </div>

        @elseif ($estadoAsistencia === 'rechazada')
            <div class="p-6 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-xl text-center space-y-3">
                <flux:icon.clock class="size-12 text-amber-600 dark:text-amber-400 mx-auto" />
                <h2 class="text-xl font-bold text-amber-800 dark:text-amber-300">
                    Respuesta Registrada: Inasistencia Informada
                </h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Se ha informado al docente que no podrá asistir a la entrevista en el horario agendado.
                </p>
                @if (! empty($entrevista->motivo_rechazo_asistencia))
                    <div class="text-left bg-white dark:bg-zinc-900 p-3 rounded-lg border border-amber-200 dark:border-amber-800/60 text-xs text-zinc-700 dark:text-zinc-300 mt-2">
                        <strong>Motivo enviado:</strong> "{{ $entrevista->motivo_rechazo_asistencia }}"
                    </div>
                @endif
                <p class="text-xs text-zinc-500 mt-2">
                    Respondió desde: <strong>{{ $entrevista->confirmado_desde_email }}</strong> el {{ $entrevista->confirmado_at ? $entrevista->confirmado_at->setTimezone('America/Santiago')->format('d/m/Y H:i hrs') : 'Registrado' }}
                </p>
            </div>

        @else
            {{-- Formulario para Confirmar o Rechazar --}}
            <div class="space-y-6">
                {{-- Ficha Resumen de la Entrevista --}}
                <div class="p-4 bg-blue-50/60 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40 rounded-xl space-y-2 text-sm text-zinc-800 dark:text-zinc-200">
                    <h3 class="font-bold text-[#00376e] dark:text-blue-300 text-base mb-1">Detalles de la Citación:</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs md:text-sm">
                        <p><strong>Estudiante:</strong> {{ $entrevista->estudiante ? $entrevista->estudiante->nombreCompleto() : 'Estudiante' }}</p>
                        <p><strong>Curso:</strong> {{ $entrevista->estudiante && $entrevista->estudiante->curso ? $entrevista->estudiante->curso->nombreCompleto() : 'Sin curso' }}</p>
                        <p><strong>Docente:</strong> {{ $entrevista->user ? $entrevista->user->nombreCompleto() : 'Docente' }}</p>
                        <p><strong>Lugar / Box:</strong> {{ $entrevista->lugar }}</p>
                        <p class="md:col-span-2"><strong>Fecha y Hora:</strong> {{ \Carbon\Carbon::parse($entrevista->fecha)->translatedFormat('l d \d\e F, Y') }} a las <span class="font-bold text-blue-700 dark:text-blue-300">{{ \Carbon\Carbon::parse($entrevista->hora)->format('H:i') }} hrs</span></p>
                        <p class="md:col-span-2"><strong>Motivo:</strong> {{ ucfirst($entrevista->motivo) }}</p>
                    </div>
                </div>

                {{-- Campo Correo --}}
                <div class="space-y-4">
                    <flux:input 
                        wire:model="emailRespuesta" 
                        label="Su Correo Electrónico (para confirmar la respuesta)" 
                        type="email" 
                        placeholder="apoderado@correo.com" 
                        required 
                    />
                    <flux:error name="emailRespuesta" />
                </div>

                @if (! $mostrarFormularioRechazo)
                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <flux:button wire:click="confirmarAsistencia" variant="primary" icon="check" class="flex-1 font-bold py-3">
                            Confirmar Asistencia
                        </flux:button>
                        <flux:button wire:click="abrirFormularioRechazo" variant="subtle" icon="x-mark" class="font-bold py-3 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30">
                            No Podré Asistir / Reagendar
                        </flux:button>
                    </div>
                @else
                    {{-- Caja para redactar motivo de rechazo --}}
                    <div class="p-4 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 rounded-xl space-y-4">
                        <h4 class="font-bold text-amber-800 dark:text-amber-300 text-sm">Informar Inasistencia o Solicitud de Reagendamiento</h4>
                        <flux:textarea 
                            wire:model="motivoRechazo" 
                            label="Indique la razón o posible horario disponible:" 
                            placeholder="Ej: Tengo turno de trabajo en ese horario, solicitaría si podemos agendar para el día viernes en la mañana..." 
                            rows="3" 
                        />
                        <flux:error name="motivoRechazo" />

                        <div class="flex justify-end gap-2 pt-2">
                            <flux:button wire:click="$set('mostrarFormularioRechazo', false)" variant="ghost" size="sm">
                                Cancelar
                            </flux:button>
                            <flux:button wire:click="rechazarAsistencia" variant="danger" icon="paper-airplane" size="sm" class="font-bold">
                                Enviar Justificación de Inasistencia
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
