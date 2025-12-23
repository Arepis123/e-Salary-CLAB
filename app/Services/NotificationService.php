<?php

namespace App\Services;

use App\Mail\NotificationEmail;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send notification using a template
     */
    public function sendFromTemplate(
        NotificationTemplate $template,
        User $recipient,
        array $data = [],
        ?User $sender = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $attachments = [],
        ?string $customMessage = null
    ): NotificationLog {
        // Render template with data
        $rendered = $template->render($data);

        // Append custom message if provided
        $body = $rendered['body'];
        if ($customMessage) {
            $body .= "\n\n".$customMessage;
        }

        // Create log entry
        $log = NotificationLog::create([
            'notification_template_id' => $template->id,
            'user_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'type' => $template->type,
            'subject' => $rendered['subject'],
            'body' => $body,
            'attachments' => $attachments,
            'status' => 'pending',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'sent_by' => $sender?->id ?? auth()->id(),
        ]);

        // Send based on type
        try {
            if ($template->type === 'email') {
                $this->sendEmail($log);
            }

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Failed to send notification', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * Send custom notification without template
     */
    public function sendCustom(
        User $recipient,
        string $subject,
        string $body,
        string $type = 'email',
        ?User $sender = null
    ): NotificationLog {
        $log = NotificationLog::create([
            'user_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'type' => $type,
            'subject' => $subject,
            'body' => $body,
            'status' => 'pending',
            'sent_by' => $sender?->id ?? auth()->id(),
        ]);

        try {
            if ($type === 'email') {
                $this->sendEmail($log);
            }

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * Send email notification
     */
    protected function sendEmail(NotificationLog $log): void
    {
        $attachments = $log->attachments ?? [];

        Mail::to($log->recipient_email)
            ->send(new NotificationEmail($log->subject, $log->body, $attachments));
    }

    /**
     * Send bulk notifications to multiple recipients
     */
    public function sendBulk(
        NotificationTemplate $template,
        array $recipients,
        array $data = [],
        ?User $sender = null
    ): array {
        $logs = [];

        foreach ($recipients as $recipient) {
            $logs[] = $this->sendFromTemplate(
                $template,
                $recipient,
                $data,
                $sender
            );
        }

        return $logs;
    }
}
