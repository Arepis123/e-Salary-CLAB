<?php

namespace App\Models;

use App\Models\Concerns\TracksEmailDelivery;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use TracksEmailDelivery;

    protected $fillable = [
        'notification_template_id',
        'user_id',
        'recipient_email',
        'recipient_phone',
        'type',
        'subject',
        'body',
        'attachments',
        'status',
        'message_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'opened_at',
        'bounced_at',
        'bounce_reason',
        'reference_type',
        'reference_id',
        'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'bounced_at' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Get the template that was used
     */
    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    /**
     * Get the recipient user
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who sent this notification
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Get the related reference (polymorphic-like)
     */
    public function reference()
    {
        if (! $this->reference_type || ! $this->reference_id) {
            return null;
        }

        return $this->reference_type::find($this->reference_id);
    }
}
