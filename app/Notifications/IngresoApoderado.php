<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IngresoApoderado extends Notification implements ShouldQueue
{
    use Queueable;

    public $entrevista;

    /**
     * Create a new notification instance.
     */
    public function __construct($entrevista)
    {
        $this->entrevista = $entrevista;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $estudiante = $this->entrevista->estudiante;
        $estudianteNombre = $estudiante ? $estudiante->nombreCompleto() : 'Estudiante';
        $cursoNombre = $estudiante && $estudiante->curso ? $estudiante->curso->nombreCompleto() : 'Sin curso';
        $apoderadoNombre = $estudiante && $estudiante->apoderado_nombres
            ? trim($estudiante->apoderado_nombres.' '.$estudiante->apoderado_apellido_pat)
            : ($this->entrevista->apoderado_nombre ?: 'Apoderado');

        $horaLlegada = $this->entrevista->hora_llegada
            ? Carbon::parse($this->entrevista->hora_llegada)->format('H:i')
            : now('America/Santiago')->format('H:i');

        $mail = (new MailMessage)
            ->subject('[EN RECEPCIÓN] Apoderado en espera: '.$estudianteNombre)
            ->greeting('Hola '.($notifiable->nombres ?? 'Docente').',')
            ->line('Te informamos que el apoderado **'.$apoderadoNombre.'** (estudiante **'.$estudianteNombre.'** - '.$cursoNombre.') ha ingresado al recinto y se encuentra esperando.')
            ->line('**Lugar / Box de Atención:** '.($this->entrevista->lugar ?? 'Pendiente'))
            ->line('**Hora de Registro:** '.$horaLlegada.' hrs');

        if (! empty($this->entrevista->mensaje_recepcion)) {
            $mail->line('**Nota de Recepción:**')
                ->line('"'.$this->entrevista->mensaje_recepcion.'"');
        }

        return $mail
            ->action('Ir a la Bitácora', route('entrevistas.bitacora', ['entrevista' => $this->entrevista->id]))
            ->line('Por favor dirígete al lugar asignado para atender la entrevista.')
            ->salutation("Atentamente,\nSistema de Entrevistas NHHS");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'entrevista_id' => $this->entrevista->id,
            'titulo' => 'Apoderado en Recepción',
            'mensaje' => 'El apoderado de '.($this->entrevista->estudiante ? $this->entrevista->estudiante->nombreCompleto() : 'Estudiante').' ya se encuentra registrado y ha sido derivado a '.($this->entrevista->lugar ?? 'recepción').'.',
            'url' => route('entrevistas.bitacora', $this->entrevista->id),
            'icon' => 'user-plus',
            'color' => 'emerald',
        ];
    }
}
