<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailSubject;

    public string $emailBody;

    public array $emailAttachments;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $body, array $attachments = [])
    {
        $this->emailSubject = $subject;
        $this->emailBody = $body;
        $this->emailAttachments = $attachments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Convert line breaks to HTML
        $formattedBody = nl2br(e($this->emailBody));

        return new Content(
            htmlString: $formattedBody,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        $disk = \Storage::disk('local');

        foreach ($this->emailAttachments as $attachment) {
            // Handle both old format (string) and new format (array with path and original_name)
            $filePath = is_array($attachment) ? $attachment['path'] : $attachment;
            $originalName = is_array($attachment) ? $attachment['original_name'] : null;

            // Use Laravel Storage to get the correct path
            $fullPath = $disk->path($filePath);

            if ($disk->exists($filePath)) {
                $mailAttachment = \Illuminate\Mail\Mailables\Attachment::fromPath($fullPath);

                // Use original filename if available
                if ($originalName) {
                    $mailAttachment->as($originalName);
                }

                $attachments[] = $mailAttachment;
            } else {
                \Log::warning('Attachment file not found', ['path' => $fullPath]);
            }
        }

        return $attachments;
    }
}
