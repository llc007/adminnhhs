<?php

namespace App\Notifications;

use App\Models\Entrevista;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntrevistaCompartidaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Entrevista $entrevista;

    public User $otorgadoPor;

    /**
     * Create a new notification instance.
     */
    public function __construct(Entrevista $entrevista, User $otorgadoPor)
    {
        $this->entrevista = $entrevista;
        $this->otorgadoPor = $otorgadoPor;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $estudiante = $this->entrevista->estudiante;
        $fecha = Carbon::parse($this->entrevista->fecha)->translatedFormat('l d \d\e F, Y');
        $profesional = $this->otorgadoPor->nombreCompleto();
        $confidencialTexto = $this->entrevista->es_confidencial ? ' (Entrevista Confidencial)' : '';

        return (new MailMessage)
            ->subject('Acceso Compartido a Entrevista: '.$estudiante->nombreCompleto().$confidencialTexto)
            ->greeting('Hola '.$notifiable->nombres.',')
            ->line('El profesional **'.$profesional.'** te ha concedido acceso para revisar la siguiente entrevista:')
            ->line('**Estudiante:** '.$estudiante->nombreCompleto().' ('.($estudiante->curso ? $estudiante->curso->nombreCompleto() : 'Sin curso').')')
            ->line('**Fecha:** '.$fecha)
            ->line('**Motivo:** '.ucfirst($this->entrevista->motivo))
            ->action('Ver Entrevista en Plataforma', route('entrevistas.bitacora', ['entrevista' => $this->entrevista->id]))
            ->line('Puedes consultar el registro y la bitácora directamente ingresando a la plataforma.')
            ->salutation("Saludos cordiales,\nSistema de Entrevistas NHHS");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
