@php
    // $change is null when there is no prior-period baseline to compare against.
    // $invert flips the colour semantics for metrics where growth is bad
    // (receivables, for example).
    $change = $change ?? null;
    $label = $label ?? 'vs last period';
    $invert = $invert ?? false;

    $tone = 'text-zinc-500 dark:text-zinc-400';
    if (! is_null($change) && $change != 0) {
        $good = $invert ? $change < 0 : $change > 0;
        $tone = $good ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
    }
@endphp

<div class="flex items-center gap-1.5 text-xs">
    @if(is_null($change))
        <span class="text-zinc-500 dark:text-zinc-400">No prior-period data</span>
    @else
        <span class="{{ $tone }}">{{ $change > 0 ? '+' : '' }}{{ number_format($change, 1) }}%</span>
        <span class="text-zinc-600 dark:text-zinc-400">{{ $label }}</span>
    @endif
</div>
