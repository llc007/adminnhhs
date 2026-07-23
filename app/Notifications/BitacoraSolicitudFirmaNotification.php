<?php

namespace App\Notifications;

use App\Models\Bitacora;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BitacoraSolicitudFirmaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Bitacora $bitacora;

    public string $signedUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bitacora $bitacora, string $signedUrl)
    {
        $this->bitacora = $bitacora;
        $this->signedUrl = $signedUrl;
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
        $entrevista = $this->bitacora->entrevista;
        $estudiante = $entrevista->estudiante;
        $docente = $entrevista->user;
        $fecha = Carbon::parse($entrevista->fecha)->translatedFormat('l d \d\e F, Y');

        return (new MailMessage)
            ->subject('Solicitud de Firma Digital de Bitácora - Liceo New Heaven High School')
            ->greeting('Estimado/a apoderado/a / firmante,')
            ->line('Le solicitamos ingresar al siguiente enlace seguro para revisar el acta y compromisos de la entrevista sostenida respecto al estudiante **'.($estudiante ? $estudiante->nombreCompleto() : 'N/A').'** con el/la profesional **'.($docente ? $docente->nombreCompleto() : 'N/A').'** efectuada el día '.$fecha.'.')
            ->line('Por favor haga clic en el siguiente botón para verificar sus datos y realizar la firma digital:')
            ->action('Revisar y Firmar Bitácora', $this->signedUrl)
            ->line('Este enlace es personal y seguro. Si tiene alguna consulta, por favor contacte a la administración del colegio.')
            ->salutation('Atentamente,'."\n".'Equipo Directivo y Docente'."\n".'Liceo New Heaven High School');
    }
}
