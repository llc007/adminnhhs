<?php

namespace App\Notifications;

use App\Models\Prestamo;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrestamoRegistrado extends Notification implements ShouldQueue
{
    use Queueable;

    public Prestamo $prestamo;

    /**
     * Create a new notification instance.
     */
    public function __construct(Prestamo $prestamo)
    {
        $this->prestamo = $prestamo;
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
        $fechaPrestamo = Carbon::parse($this->prestamo->fecha_prestamo)->translatedFormat('l d \d\e F, Y');
        $fechaEstimada = Carbon::parse($this->prestamo->fecha_devolucion_estimada)->translatedFormat('l d \d\e F, Y');

        $detallesArticulo = $this->prestamo->nombre_articulo;
        if ($this->prestamo->marca || $this->prestamo->modelo) {
            $detallesArticulo .= ' ('.trim($this->prestamo->marca.' '.$this->prestamo->modelo).')';
        }
        if ($this->prestamo->numero_serie) {
            $detallesArticulo .= ' [S/N: '.$this->prestamo->numero_serie.']';
        }

        $mail = (new MailMessage)
            ->subject('Registro de Préstamo de Insumo - Liceo New Heaven High School')
            ->greeting('Estimado/a '.$notifiable->nombres.',')
            ->line('Le informamos que se ha registrado un préstamo de insumo tecnológico a su nombre por el departamento de Informática (TI).')
            ->line('**Artículo:** '.$detallesArticulo)
            ->line('**Cantidad:** '.$this->prestamo->cantidad)
            ->line('**Fecha de Entrega:** '.$fechaPrestamo)
            ->line('**Fecha Estimada de Devolución:** '.$fechaEstimada);

        if ($this->prestamo->observaciones) {
            $mail->line('**Observaciones registradas:**')
                ->line('> '.$this->prestamo->observaciones);
        }

        return $mail->line('Le recordamos que es responsable de la integridad y cuidado del insumo prestado, debiendo devolverlo en la fecha de vencimiento.')
            ->action('Ver Mis Préstamos', url('/ti/mis-prestamos'))
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
