<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SolicitudAcceso extends Notification
{
    use Queueable;

    public $user;

    public string $rol;

    /**
     * Create a new notification instance.
     */
    public function __construct($user, string $rol)
    {
        $this->user = $user;
        $this->rol = $rol;
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
            'user_id' => $this->user->id,
            'titulo' => 'Solicitud de Acceso',
            'mensaje' => $this->user->nombres.' ha solicitado acceso al sistema con el rol de '.ucfirst($this->rol).'.',
            'url' => route('funcionarios.ficha', $this->user->id),
            'icon' => 'user-plus',
            'color' => 'blue',
        ];
    }
}
