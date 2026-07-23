<?php

namespace App\Notifications;

use App\Models\Bitacora;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BitacoraResumenNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Bitacora $bitacora;

    public string $destinatarioNombre;

    /**
     * Create a new notification instance.
     */
    public function __construct(Bitacora $bitacora, string $destinatarioNombre = 'Apoderado/a')
    {
        $this->bitacora = $bitacora;
        $this->destinatarioNombre = $destinatarioNombre;
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

        $mail = (new MailMessage)
            ->subject('Resumen de Entrevista y Compromisos - Liceo New Heaven High School')
            ->greeting('Estimado/a '.$this->destinatarioNombre.',')
            ->line('Le enviamos una copia del resumen de la entrevista sostenida sobre el estudiante **'.($estudiante ? $estudiante->nombreCompleto() : 'N/A').'** con el/la profesional **'.($docente ? $docente->nombreCompleto() : 'N/A').'** realizada el día '.$fecha.'.')
            ->line('### Resumen de la Conversación:')
            ->line($this->bitacora->resumen ?: 'Sin detalle registrado.');

        if (! empty($this->bitacora->acuerdos) && is_array($this->bitacora->acuerdos)) {
            $mail->line('### Acuerdos y Compromisos Alcanzados:');
            foreach ($this->bitacora->acuerdos as $acuerdo) {
                $titulo = $acuerdo['titulo'] ?? '';
                $desc = $acuerdo['descripcion'] ?? '';
                $mail->line('• **'.$titulo.'**: '.$desc);
            }
        }

        $mail->line('Agradecemos su constante colaboración en el proceso formativo y académico.')
            ->salutation('Atentamente,'."\n".'Equipo Directivo y Docente'."\n".'Liceo New Heaven High School');

        return $mail;
    }
}
