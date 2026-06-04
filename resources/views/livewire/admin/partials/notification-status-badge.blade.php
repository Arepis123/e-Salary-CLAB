@php
    $statusMap = [
        'pending'   => ['yellow', 'Pending'],
        'sent'      => ['blue', 'Sent'],
        'delivered' => ['green', 'Delivered'],
        'opened'    => ['emerald', 'Opened'],
        'clicked'   => ['teal', 'Clicked'],
        'bounced'   => ['red', 'Bounced'],
        'spam'      => ['orange', 'Spam'],
        'blocked'   => ['orange', 'Blocked'],
        'deferred'  => ['amber', 'Deferred'],
        'failed'    => ['red', 'Failed'],
    ];
    [$badgeColor, $badgeLabel] = $statusMap[$log->status] ?? ['zinc', ucfirst($log->status)];

    $statusTip = match (true) {
        (bool) $log->opened_at    => 'Opened '.$log->opened_at->format('M d, Y H:i'),
        (bool) $log->bounced_at   => $log->bounce_reason ? 'Bounced: '.$log->bounce_reason : 'Bounced '.$log->bounced_at->format('M d, Y H:i'),
        (bool) $log->delivered_at => 'Delivered '.$log->delivered_at->format('M d, Y H:i'),
        $log->status === 'failed' && $log->error_message => $log->error_message,
        default => null,
    };
@endphp
<flux:badge color="{{ $badgeColor }}" size="sm" :title="$statusTip">{{ $badgeLabel }}</flux:badge>
