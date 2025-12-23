<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayrollReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contractorName;

    public $contractorClabNo;

    public $pendingWorkers;

    public $totalWorkers;

    public $periodMonth;

    public $reminderMessage;

    /**
     * Create a new message instance.
     */
    public function __construct($contractorName, $contractorClabNo, $pendingWorkers, $totalWorkers, $periodMonth, $message)
    {
        $this->contractorName = $contractorName;
        $this->contractorClabNo = $contractorClabNo;
        $this->pendingWorkers = $pendingWorkers;
        $this->totalWorkers = $totalWorkers;
        $this->periodMonth = $periodMonth;
        $this->reminderMessage = $message;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Payroll Submission Reminder - '.$this->periodMonth)
            ->view('emails.payroll-reminder');
    }
}
