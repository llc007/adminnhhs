<?php

namespace App\Notifications;

use App\Models\Entrevista;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RespuestaAsistenciaDocenteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Entrevista $entrevista;

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
        $estudianteNombre = $estudiante ? $estudiante->nombreCompleto() : 'Estudiante';
        $apoderadoNombre = $estudiante && $estudiante->apoderado_nombres
            ? trim($estudiante->apoderado_nombres.' '.$estudiante->apoderado_apellido_pat)
            : ($this->entrevista->apoderado_nombre ?: 'Apoderado');

        $fecha = Carbon::parse($this->entrevista->fecha)->translatedFormat('l d \d\e F, Y');
        $hora = Carbon::parse($this->entrevista->hora)->format('H:i');
        $isConfirmada = $this->entrevista->estado_asistencia === 'confirmada';

        $subject = $isConfirmada
            ? '[CONFIRMADA] Respuesta a Citación: '.$estudianteNombre
            : '[INASISTENCIA / RECHAZADA] Respuesta a Citación: '.$estudianteNombre;

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hola '.($notifiable->nombres ?? 'Docente').',');

        if ($isConfirmada) {
            $mail->line('El apoderado **'.$apoderadoNombre.'** ha **CONFIRMADO** su asistencia a la entrevista para el estudiante **'.$estudianteNombre.'**.')
                ->line('**Fecha:** '.$fecha)
                ->line('**Hora:** '.$hora)
                ->line('**Lugar:** '.$this->entrevista->lugar);
        } else {
            $mail->line('El apoderado **'.$apoderadoNombre.'** ha informado que **NO PODRÁ ASISTIR** a la entrevista para el estudiante **'.$estudianteNombre.'**.')
                ->line('**Fecha Cita:** '.$fecha.' a las '.$hora);

            if (! empty($this->entrevista->motivo_rechazo_asistencia)) {
                $mail->line('**Motivo / Justificación del Apoderado:**')
                    ->line('"'.$this->entrevista->motivo_rechazo_asistencia.'"');
            }
        }

        if (! empty($this->entrevista->confirmado_desde_email)) {
            $mail->line('**Respondió desde el correo:** '.$this->entrevista->confirmado_desde_email);
        }

        $bitacoraUrl = route('entrevistas.bitacora', ['entrevista' => $this->entrevista->id]);

        return $mail
            ->action('Ver Bitácora / Reagendar Cita', $bitacoraUrl)
            ->line('Puedes ingresar a la Bitácora para reagendar la fecha o cancelar la entrevista si corresponde.')
            ->salutation("Saludos cordiales,\nSistema de Entrevistas NHHS");
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
