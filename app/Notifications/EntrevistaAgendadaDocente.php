<?php

namespace App\Notifications;

use App\Models\Entrevista;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntrevistaAgendadaDocente extends Notification implements ShouldQueue
{
    use Queueable;

    public $entrevista;

    /**
     * Create a new notification instance.
     */
    public function __construct(Entrevista $entrevista)
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $estudiante = $this->entrevista->estudiante;
        $fecha = Carbon::parse($this->entrevista->fecha)->translatedFormat('l d \d\e F, Y');
        $hora = Carbon::parse($this->entrevista->hora)->format('H:i');

        return (new MailMessage)
            ->subject('Nueva Entrevista Agendada: '.$estudiante->nombreCompleto())
            ->greeting('Hola '.$notifiable->nombres.',')
            ->line('Se ha agendado una nueva entrevista en tu calendario.')
            ->line('**Estudiante:** '.$estudiante->nombreCompleto().' ('.($estudiante->curso ? $estudiante->curso->nombreCompleto() : 'Sin curso').')')
            ->line('**Apoderado:** '.($estudiante->apoderado_nombres ? $estudiante->apoderado_nombres.' '.$estudiante->apoderado_apellido_pat : 'No registrado'))
            ->line('**Fecha:** '.$fecha)
            ->line('**Hora:** '.$hora)
            ->line('**Modalidad/Lugar:** '.$this->entrevista->lugar)
            ->line('**Motivo:** '.ucfirst($this->entrevista->motivo))
            ->line('**Urgencia:** '.ucfirst($this->entrevista->urgencia))
            ->action('Ver Entrevista', url('/entrevistas'))
            ->line('Gracias por utilizar nuestro sistema de gestión.')
            ->salutation("Saludos cordiales,\nEquipo TI NHHS");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
