<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
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
        'error_message',
        'sent_at',
        'reference_type',
        'reference_id',
        'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
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
