<?php

namespace App\Http\Controllers;

use App\Models\MailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from mail providers (Resend, Postmark, Brevo, etc.).
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info('Mail Webhook Received', ['payload' => $payload]);

        // 1. Detect Message ID
        $mailId = $payload['email_id']
            ?? $payload['data']['email_id']
            ?? $payload['message_id']
            ?? $payload['message-id']
            ?? $payload['MessageID']
            ?? null;

        if (! $mailId) {
            return response()->json(['error' => 'Message ID not found in payload'], 400);
        }

        // Clean the ID (remove angle brackets if present)
        $mailId = trim($mailId, '<>');

        // Find the mail log record
        $mailLog = MailLog::where('mail_id', $mailId)->first();

        if (! $mailLog) {
            // Check if it's in the data sub-object (e.g. Resend payload structure)
            if (isset($payload['data']['email_id'])) {
                $mailId = trim($payload['data']['email_id'], '<>');
                $mailLog = MailLog::where('mail_id', $mailId)->first();
            }
        }

        if (! $mailLog) {
            return response()->json(['message' => 'Mail log not found for ID: '.$mailId], 200);
        }

        // 2. Detect Event Status
        $type = $payload['type']
            ?? $payload['event']
            ?? $payload['RecordType']
            ?? null;

        $type = strtolower((string) $type);

        // 3. Extract description / error message
        $errorMessage = $payload['description']
            ?? $payload['data']['bounce']['description']
            ?? $payload['reason']
            ?? $payload['message']
            ?? $payload['Description']
            ?? null;

        // Map events to status
        if (str_contains($type, 'bounce')) {
            $mailLog->update([
                'status' => 'bounced',
                'error_message' => $errorMessage ?: 'Rebote de entrega (casilla no existe o rechazo).',
            ]);
        } elseif (str_contains($type, 'deliver')) {
            $mailLog->update([
                'status' => 'delivered',
            ]);
        } elseif (str_contains($type, 'fail')) {
            $mailLog->update([
                'status' => 'failed',
                'error_message' => $errorMessage ?: 'Fallo en la entrega.',
            ]);
        }

        return response()->json(['success' => true]);
    }
}
