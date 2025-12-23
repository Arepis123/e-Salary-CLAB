<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'trigger_type',
        'trigger_days_before',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'variables' => null,
    ];

    /**
     * Get notification logs for this template
     */
    public function logs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Replace variables in subject and body with actual values
     */
    public function render(array $data): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', $value, $subject);
            $body = str_replace('{{'.$key.'}}', $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Get available variable placeholders
     */
    public static function getAvailableVariables(string $triggerType): array
    {
        return match ($triggerType) {
            'auto_payment_deadline', 'auto_overdue' => [
                'client_name' => 'Client Name',
                'invoice_number' => 'Invoice Number',
                'amount' => 'Amount Due',
                'due_date' => 'Due Date',
                'period' => 'Payroll Period',
                'days_remaining' => 'Days Remaining',
            ],
            'auto_submission_deadline' => [
                'client_name' => 'Client Name',
                'deadline_date' => 'Submission Deadline',
                'period' => 'Payroll Period',
                'days_remaining' => 'Days Remaining',
            ],
            'manual' => [
                'client_name' => 'Client Name',
                'message' => 'Custom Message',
            ],
            default => [],
        };
    }
}
