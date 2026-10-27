<?php

namespace App\Mail;

use App\Models\PayrollSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an admin re-syncs an approved timesheet to the client's latest OT
 * data, which withdraws the approval until the new amount is re-confirmed.
 */
class PayrollApprovalWithdrawn extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollSubmission $submission,
        public int $workersUpdated
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payroll Under Re-review - '.$this->submission->month_year,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payroll-approval-withdrawn',
            text: 'emails.payroll-approval-withdrawn-text',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
