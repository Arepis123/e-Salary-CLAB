<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\MessageConverter;

/**
 * Records every outgoing email into the email_logs table (one row per
 * recipient), keyed by the Brevo message-id so the delivery webhook can later
 * update delivered/opened/bounced status. Captures all mail centrally without
 * touching individual send sites.
 */
class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        try {
            $email = MessageConverter::toEmail($event->sent->getOriginalMessage());
            $messageId = $event->sent->getMessageId();

            $from = $email->getFrom()[0] ?? null;
            $subject = $email->getSubject();

            // Best-effort originating Mailable class (not always available)
            $mailable = $event->data['__laravel_mailable'] ?? null;
            $mailable = is_string($mailable) ? $mailable : null;

            $recipients = $email->getTo();
            if (empty($recipients)) {
                return;
            }

            foreach ($recipients as $addr) {
                EmailLog::create([
                    'message_id' => $messageId,
                    'mailable' => $mailable,
                    'to_email' => $addr->getAddress(),
                    'to_name' => $addr->getName() ?: null,
                    'from_email' => $from?->getAddress(),
                    'from_name' => $from?->getName() ?: null,
                    'subject' => $subject,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Never let logging break an actual email send
            Log::warning('Failed to record outgoing email log', ['error' => $e->getMessage()]);
        }
    }
}
