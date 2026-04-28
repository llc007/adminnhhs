<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalidaApoderado extends Notification
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
        return ['database'];
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
            'titulo' => 'Apoderado se ha Retirado',
            'mensaje' => 'Recepción ha confirmado la salida del apoderado de ' . $this->entrevista->estudiante->nombreCompleto() . '.',
            'url' => route('entrevistas.bitacora', $this->entrevista->id),
            'icon' => 'arrow-right-start-on-rectangle',
            'color' => 'amber',
        ];
    }
}
