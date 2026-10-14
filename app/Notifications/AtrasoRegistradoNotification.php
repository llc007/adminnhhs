<?php

namespace App\Notifications;

use App\Models\Atraso;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AtrasoRegistradoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Atraso $atraso;

    public int $totalMes;

    /**
     * Create a new notification instance.
     */
    public function __construct(Atraso $atraso, ?int $totalMes = null)
    {
        $this->atraso = $atraso;
        $this->totalMes = $totalMes ?? ($atraso->estudiante ? $atraso->estudiante->atrasosMesActualCount() : 1);
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
        $estudiante = $this->atraso->estudiante;
        $estudianteNombre = $estudiante ? $estudiante->nombreCompleto() : 'Estudiante';
        $cursoNombre = $this->atraso->curso?->nombreCompleto() ?? $estudiante?->curso?->nombreCompleto() ?? 'Curso no especificado';
        $schoolName = $this->atraso->school?->name ?? 'Liceo New Heaven High School';
        $apoderadoNombre = $estudiante?->apoderadoNombreCompleto();
        $greeting = ! empty($apoderadoNombre) ? "Estimado/a {$apoderadoNombre}," : 'Estimado/a apoderado/a,';

        $fecha = Carbon::parse($this->atraso->fecha)->translatedFormat('l d \d\e F, Y');
        $hora = Carbon::parse($this->atraso->hora)->format('H:i');
        $minutos = (int) $this->atraso->minutos_atraso;

        $mail = (new MailMessage)
            ->subject("[Atraso] Notificación de Ingreso: {$estudianteNombre} - {$cursoNombre}")
            ->greeting($greeting)
            ->line("Le informamos que el/la estudiante **{$estudianteNombre}** ({$cursoNombre}) ha ingresado al establecimiento con registro de atraso el día **{$fecha}** a las **{$hora} hrs**".($minutos > 0 ? " (+{$minutos} min de atraso)." : '.'));

        if ($this->atraso->estado === 'justificado') {
            $detalleJustificado = 'Justificado'.($this->atraso->motivo ? " ({$this->atraso->motivo})" : '');
            $mail->line("**Estado:** {$detalleJustificado}");
        }

        $mail->line("**Registro en el mes:** Atraso N° **{$this->totalMes}** durante el presente mes.");

        if ($this->totalMes >= 3) {
            $mail->line('⚠️ **Aviso Importante:** Al acumular 3 o más atrasos en el mes se activa el protocolo de citación de apoderado de acuerdo al Reglamento Interno de Convivencia Escolar.');
        }

        $mail->line('Le recordamos la importancia de la puntualidad en el proceso pedagógico y formativo de su pupilo/a.')
            ->salutation('Atentamente, Inspectoría - New Heaven High School');

        return $mail;
    }
}
