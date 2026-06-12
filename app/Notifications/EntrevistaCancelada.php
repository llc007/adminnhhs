<?php

namespace App\Notifications;

use App\Models\Entrevista;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntrevistaCancelada extends Notification implements ShouldQueue
{
    use Queueable;

    public Entrevista $entrevista;

    public string $destinatario; // 'docente' o 'apoderado'

    /**
     * Create a new notification instance.
     */
    public function __construct(Entrevista $entrevista, string $destinatario)
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

        // Obtener la bitácora para extraer las observaciones o el motivo de no realización
        $bitacora = $this->entrevista->bitacora;
        $motivo = '';

        if ($bitacora && ! empty($bitacora->observaciones)) {
            // Intentar extraer el motivo registrado
            if (preg_match('/MOTIVO NO REALIZADA:\s*(.*)$/is', $bitacora->observaciones, $matches)) {
                $motivo = trim($matches[1]);
            } else {
                $motivo = trim($bitacora->observaciones);
            }
        }

        if (empty($motivo)) {
            $motivo = $this->entrevista->estado === 'cancelada'
                ? 'Cancelación por mutuo acuerdo o error de registro.'
                : 'Apoderado no se presentó a la cita programada.';
        }

        $mail = new MailMessage;

        if ($this->destinatario === 'apoderado') {
            if ($this->entrevista->estado === 'cancelada') {
                $mail->subject('Entrevista Cancelada - Colegio New Heaven High School')
                    ->greeting('Estimado/a apoderado/a,')
                    ->line('Le informamos que la entrevista que tenía programada respecto a su pupilo/a **'.$estudiante->nombreCompleto().'** con el/la docente **'.$docente->nombreCompleto().'** ha sido **cancelada**.')
                    ->line('**Fecha original de la cita:** '.$fecha)
                    ->line('**Hora:** '.$hora.' hrs')
                    ->line('**Justificación o motivo registrado:**')
                    ->line('> '.$motivo)
                    ->line('Si requiere agendar una nueva citación, le solicitamos comunicarse con el establecimiento para coordinar una nueva fecha.')
                    ->salutation("Atentamente,\nDirección Académica\nColegio New Heaven High School");
            } else {
                // estado 'ausente'
                $mail->subject('Registro de Inasistencia a Entrevista - Colegio New Heaven High School')
                    ->greeting('Estimado/a apoderado/a,')
                    ->line('Le informamos que se ha registrado una **inasistencia (ausencia)** a la entrevista que tenía programada respecto a su pupilo/a **'.$estudiante->nombreCompleto().'** con el/la docente **'.$docente->nombreCompleto().'**.')
                    ->line('**Fecha de la cita programada:** '.$fecha)
                    ->line('**Hora:** '.$hora.' hrs')
                    ->line('**Detalle o motivo registrado:**')
                    ->line('> '.$motivo)
                    ->line('Le recordamos que estas instancias son fundamentales para el acompañamiento y éxito académico de su pupilo/a. Le solicitamos ponerse en contacto con el establecimiento a la brevedad para reagendar la entrevista.')
                    ->salutation("Atentamente,\nDirección Académica\nColegio New Heaven High School");
            }
        } else {
            // destinatario 'docente'
            $estadoTexto = $this->entrevista->estado === 'cancelada' ? 'cancelada' : 'registrada como inasistencia (ausente)';

            $mail->subject('Actualización de Cita: '.ucfirst($this->entrevista->estado).' - '.$estudiante->nombreCompleto())
                ->greeting('Estimado/a docente '.$notifiable->nombres.',')
                ->line('Le notificamos que la entrevista agendada en su calendario ha sido **'.$estadoTexto.'**.')
                ->line('**Estudiante:** '.$estudiante->nombreCompleto().' ('.($estudiante->curso ? $estudiante->curso->nombreCompleto() : 'Sin curso').')')
                ->line('**Fecha programada:** '.$fecha)
                ->line('**Hora:** '.$hora.' hrs')
                ->line('**Motivo o justificación registrada:**')
                ->line('> '.$motivo)
                ->action('Ver Calendario', url('/entrevistas'))
                ->line('Gracias por utilizar nuestro sistema de gestión.');
        }

        return $mail;
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
