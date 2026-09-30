<?php

namespace App\Listeners;

use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSent;

class LogSentMessage
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        $to = collect($event->message->getTo())
            ->map(fn ($address) => $address->getAddress())
            ->implode(', ');

        $subject = $event->message->getSubject() ?? '(Sin Asunto)';

        $mailId = null;
        if (isset($event->sent) && method_exists($event->sent, 'getSymfonySentMessage')) {
            $mailId = trim($event->sent->getSymfonySentMessage()->getMessageId(), '<>');
        }

        MailLog::create([
            'mail_id' => $mailId,
            'to' => $to,
            'subject' => $subject,
            'body' => null,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
