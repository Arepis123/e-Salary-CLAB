<?php

namespace App\Models\Concerns;

/**
 * Shared delivery-event handling for email log models (NotificationLog,
 * EmailLog). Maps Brevo transactional webhook events to a status and records
 * the matching timestamp without ever regressing a confirmed success.
 *
 * Consuming models must have columns: status, delivered_at, opened_at,
 * bounced_at, bounce_reason (and optionally error_message).
 */
trait TracksEmailDelivery
{
    /**
     * Progress ranking for success statuses. A later webhook event must never
     * move the log "backwards" (e.g. a late `delivered` after an `opened`).
     */
    private static array $deliveryStatusRank = [
        'pending' => 0,
        'sent' => 1,
        'delivered' => 2,
        'opened' => 3,
        'clicked' => 4,
    ];

    /**
     * Map a Brevo transactional webhook event name to one of our statuses.
     */
    public static function mapBrevoEvent(string $event): ?string
    {
        return match (strtolower($event)) {
            'request', 'requests', 'sent' => 'sent',
            'delivered' => 'delivered',
            'opened', 'unique_opened', 'proxy_open' => 'opened',
            'click', 'clicks' => 'clicked',
            'hard_bounce', 'soft_bounce' => 'bounced',
            'spam', 'complaint' => 'spam',
            'blocked' => 'blocked',
            'deferred' => 'deferred',
            'error', 'invalid_email', 'unsubscribed' => 'failed',
            default => null,
        };
    }

    /**
     * Apply a Brevo delivery event to this log, recording the matching
     * timestamp and advancing the status without ever regressing a success.
     */
    public function applyDeliveryEvent(string $status, ?string $reason = null, ?\DateTimeInterface $at = null): void
    {
        $at ??= now();
        $attributes = [];

        // Record the event timestamp on its dedicated column
        match ($status) {
            'delivered' => $attributes['delivered_at'] = $this->delivered_at ?? $at,
            'opened', 'clicked' => $attributes['opened_at'] = $this->opened_at ?? $at,
            'bounced', 'spam', 'blocked' => $attributes['bounced_at'] = $this->bounced_at ?? $at,
            default => null,
        };

        if ($reason && in_array($status, ['bounced', 'spam', 'blocked', 'failed'], true)) {
            $attributes['bounce_reason'] = $reason;
        }

        // Failure states win over an in-progress success, but never overwrite a
        // confirmed open/click (the recipient clearly received it).
        $isFailure = in_array($status, ['bounced', 'spam', 'blocked', 'failed'], true);
        $currentRank = self::$deliveryStatusRank[$this->status] ?? 0;

        if ($isFailure) {
            if ($currentRank < self::$deliveryStatusRank['opened']) {
                $attributes['status'] = $status;
            }
        } else {
            $newRank = self::$deliveryStatusRank[$status] ?? 0;
            if ($newRank > $currentRank) {
                $attributes['status'] = $status;
            }
        }

        if ($attributes) {
            $this->update($attributes);
        }
    }
}
