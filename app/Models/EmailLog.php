<?php

namespace App\Models;

use App\Models\Concerns\TracksEmailDelivery;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use TracksEmailDelivery;

    protected $fillable = [
        'message_id',
        'mailable',
        'to_email',
        'to_name',
        'from_email',
        'from_name',
        'subject',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'opened_at',
        'bounced_at',
        'bounce_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'bounced_at' => 'datetime',
    ];

    /**
     * Short, human-friendly label for the email source (Mailable class basename
     * or a fallback to the subject).
     */
    public function getSourceLabelAttribute(): string
    {
        if ($this->mailable) {
            return class_basename($this->mailable);
        }

        return $this->subject ?: 'Email';
    }
}
