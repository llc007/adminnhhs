<?php

namespace App\Listeners;

use App\Models\MailLog;
use App\Models\School;
use Illuminate\Notifications\Events\NotificationSending;

class FilterNotificationEmails
{
    /**
     * Handle the event.
     */
    public function handle(NotificationSending $event): ?bool
    {
        // Only intercept the mail channel
        if ($event->channel !== 'mail') {
            return null;
        }

        $schoolId = null;

        // Check for school_id dynamically across notification properties
        foreach (get_object_vars($event->notification) as $property) {
            if (is_object($property)) {
                if (isset($property->school_id)) {
                    $schoolId = $property->school_id;
                    break;
                }
                if (isset($property->entrevista) && is_object($property->entrevista) && isset($property->entrevista->school_id)) {
                    $schoolId = $property->entrevista->school_id;
                    break;
                }
            }
        }

        // Fallback to notifiable or authenticated user
        if (! $schoolId && method_exists($event->notifiable, 'currentSchool')) {
            $schoolId = $event->notifiable->current_school_id ?? null;
        }

        if (! $schoolId && auth()->check()) {
            $schoolId = auth()->user()->current_school_id ?? null;
        }

        if ($schoolId) {
            $school = School::find($schoolId);
            if ($school && ! $school->emailsEnabled()) {
                // Render and log as "not_sent" in MailLog
                $to = null;
                if (isset($event->notifiable->routes['mail'])) {
                    $to = is_array($event->notifiable->routes['mail'])
                        ? implode(', ', $event->notifiable->routes['mail'])
                        : (string) $event->notifiable->routes['mail'];
                } else {
                    $to = $event->notifiable->email ?? 'Destinatario';
                }

                $mailMessage = method_exists($event->notification, 'toMail')
                    ? $event->notification->toMail($event->notifiable)
                    : null;

                $subject = $mailMessage ? ($mailMessage->subject ?? 'Notificación del sistema') : 'Notificación';
                $body = '';

                if ($mailMessage) {
                    if (method_exists($mailMessage, 'render')) {
                        try {
                            $body = (string) $mailMessage->render();
                        } catch (\Throwable $e) {
                            $body = implode("\n", $mailMessage->introLines ?? []);
                        }
                    } else {
                        $body = implode("\n", $mailMessage->introLines ?? []);
                    }
                }

                MailLog::create([
                    'mail_id' => null,
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'not_sent',
                    'error_message' => 'Envío bloqueado: El módulo de envío de correos está desactivado.',
                    'sent_at' => now(),
                ]);

                // Cancel email transmission
                return false;
            }
        }

        return null;
    }
}
