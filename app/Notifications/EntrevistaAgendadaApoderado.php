<?php

namespace App\Notifications;

use App\Models\Entrevista;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntrevistaAgendadaApoderado extends Notification implements ShouldQueue
{
    use Queueable;

    public $entrevista;

    public string $destinatario;

    /**
     * Create a new notification instance.
     */
    public function __construct(Entrevista $entrevista, string $destinatario = 'apoderado')
    {
        $this->entrevista = $entrevista;
        $this->destinatario = $destinatario;
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
        $docente = $this->entrevista->user;
        $fecha = Carbon::parse($this->entrevista->fecha)->translatedFormat('l d \d\e F, Y');
        $hora = Carbon::parse($this->entrevista->hora)->format('H:i');

        $mail = (new MailMessage)
            ->subject('Citación a Entrevista - Liceo New Heaven High School');

        if ($this->destinatario === 'estudiante') {
            $mail->greeting('Estimado/a estudiante,')
                ->line('Te informamos que has sido citado/a a una entrevista por el/la docente **'.$docente->nombreCompleto().'**.');
        } else {
            $mail->greeting('Estimado/a apoderado/a,')
                ->line('Le informamos que ha sido citado a una entrevista respecto al estudiante **'.$estudiante->nombreCompleto().'**, por el/la docente **'.$docente->nombreCompleto().'**.');
        }

        return $mail
            ->line('**Fecha de la entrevista:** '.$fecha)
            ->line('**Hora:** '.$hora)
            ->line('**Modalidad:** '.$this->entrevista->lugar)
            ->line('Le solicitamos puntualidad. Si la entrevista es presencial, por favor anuncie su llegada en recepción.')
            ->line('Si no puede asistir, le rogamos comunicarse con el establecimiento a la brevedad para reagendar.')
            ->salutation("Atentamente,\nDirección Académica\nLiceo New Heaven High School");
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
