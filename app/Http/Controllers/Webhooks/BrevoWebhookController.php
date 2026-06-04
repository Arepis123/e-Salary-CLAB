<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrevoWebhookController extends Controller
{
    /**
     * Receive Brevo transactional email events (delivered, opened, bounced, …)
     * and reflect them on the matching NotificationLog.
     *
     * Secured by a shared secret passed in the URL (?token=...), since Brevo
     * cannot send custom auth headers with webhooks.
     */
    public function email(Request $request)
    {
        $expected = config('services.brevo.webhook_token');

        if (! $expected || ! hash_equals($expected, (string) $request->query('token'))) {
            Log::warning('Brevo webhook rejected: invalid token', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Brevo may post a single event object or a batch. Read the JSON body
        // only (not query params like ?token) and normalise to a list.
        $payload = $request->json()->all();
        $events = array_is_list($payload) ? $payload : ($payload['events'] ?? $payload['items'] ?? [$payload]);

        $processed = 0;
        foreach ($events as $event) {
            if (is_array($event) && $this->handleEvent($event)) {
                $processed++;
            }
        }

        return response()->json(['received' => count($events), 'processed' => $processed]);
    }

    /**
     * Process one Brevo event and update the matching log.
     */
    private function handleEvent(array $event): bool
    {
        $eventName = $event['event'] ?? null;
        if (! $eventName) {
            return false;
        }

        $status = NotificationLog::mapBrevoEvent($eventName);
        if (! $status) {
            return false; // event we don't track (e.g. unsubscribe noise)
        }

        $reason = $event['reason'] ?? null;
        $at = $this->eventTime($event);
        $matched = false;

        // 1) Notification Center log (per-notification view)
        if ($log = $this->findNotificationLog($event)) {
            $log->applyDeliveryEvent($status, $reason, $at);
            $matched = true;
        }

        // 2) Generic all-outgoing-email log (one row per recipient)
        foreach ($this->findEmailLogs($event) as $emailLog) {
            $emailLog->applyDeliveryEvent($status, $reason, $at);
            $matched = true;
        }

        if (! $matched) {
            Log::info('Brevo webhook: no matching email log', [
                'event' => $eventName,
                'message_id' => $event['message-id'] ?? null,
                'email' => $event['email'] ?? null,
            ]);
        }

        return $matched;
    }

    /**
     * Build the list of message-id candidates (tolerant of <> brackets).
     */
    private function messageIdCandidates(array $event): array
    {
        $messageId = $event['message-id'] ?? $event['messageId'] ?? null;
        if (! $messageId) {
            return [];
        }

        $trimmed = trim($messageId, '<>');

        return array_values(array_unique([$messageId, $trimmed, '<'.$trimmed.'>']));
    }

    /**
     * Find matching EmailLog rows for an event (by message-id + recipient).
     *
     * @return \Illuminate\Support\Collection<int, EmailLog>
     */
    private function findEmailLogs(array $event)
    {
        $candidates = $this->messageIdCandidates($event);
        if (empty($candidates)) {
            return collect();
        }

        $query = EmailLog::whereIn('message_id', $candidates);

        // Events are per-recipient, so scope to the recipient when provided
        if ($email = $event['email'] ?? null) {
            $query->where('to_email', $email);
        }

        return $query->get();
    }

    /**
     * Locate the NotificationLog for an event, preferring the Brevo message-id
     * and falling back to the most recent email to that recipient.
     */
    private function findNotificationLog(array $event): ?NotificationLog
    {
        if ($candidates = $this->messageIdCandidates($event)) {
            $log = NotificationLog::whereIn('message_id', $candidates)
                ->latest('id')
                ->first();

            if ($log) {
                return $log;
            }
        }

        // Fallback: match by recipient on a recently sent email (last 7 days).
        if ($email = $event['email'] ?? null) {
            return NotificationLog::where('recipient_email', $email)
                ->whereNotNull('sent_at')
                ->where('sent_at', '>=', now()->subDays(7))
                ->latest('sent_at')
                ->first();
        }

        return null;
    }

    /**
     * Resolve the event timestamp from Brevo's various time fields.
     */
    private function eventTime(array $event): \DateTimeInterface
    {
        try {
            if (! empty($event['ts_epoch'])) {
                // ts_epoch is in milliseconds
                return \Carbon\Carbon::createFromTimestampMs((int) $event['ts_epoch']);
            }
            if (! empty($event['ts'])) {
                return \Carbon\Carbon::createFromTimestamp((int) $event['ts']);
            }
            if (! empty($event['date'])) {
                return \Carbon\Carbon::parse($event['date']);
            }
        } catch (\Throwable $e) {
            // fall through to now()
        }

        return now();
    }
}
