@php
    $aging = $health['aging'];
    $behaviour = $health['payment_behaviour'];
    $rate = $health['collection_rate'];
    $mix = $health['payment_mix'];

    $hasCollection = collect($rate['billed'] ?? [])->sum() > 0;

    // Built here rather than inline: Blade's @json directive cannot parse a
    // multi-line array literal written inside an HTML attribute.
    $collectionPayload = [
        'labels' => $rate['labels'],
        'billed' => $rate['billed'],
        'collected' => $rate['collected'],
        'rate' => $rate['rate'],
    ];

    // Ageing severity runs cool to hot as the debt gets older.
    $bucketTone = [
        'current' => 'bg-zinc-400 dark:bg-zinc-500',
        '1_30' => 'bg-yellow-400',
        '31_60' => 'bg-amber-500',
        '61_90' => 'bg-orange-500',
        'over_90' => 'bg-red-500',
    ];
@endphp

<div class="space-y-4">
    <div class="grid gap-4 xl:grid-cols-3">
        <!-- Receivables ageing -->
        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Receivables Ageing</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">Unsettled payroll by days past deadline</p>
            </div>

            @if($aging['total'] > 0)
                <div class="space-y-3">
                    @foreach($aging['buckets'] as $key => $bucket)
                        <div>
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $bucket['label'] }}</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100 tabular-nums">
                                    RM {{ number_format($bucket['amount'], 0) }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-full rounded-full {{ $bucketTone[$key] }}" style="width: {{ $bucket['share'] }}%"></div>
                                </div>
                                <span class="w-20 shrink-0 text-right text-xs text-zinc-500 dark:text-zinc-400 tabular-nums">
                                    {{ $bucket['share'] }}% &middot; {{ $bucket['count'] }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex items-baseline justify-between border-t border-zinc-200 dark:border-zinc-700 pt-3">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Total outstanding</span>
                    <span class="text-lg font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($aging['total'], 0) }}</span>
                </div>
            @else
                <div class="flex h-56 flex-col items-center justify-center gap-2 text-center">
                    <flux:icon.check-circle class="size-10 text-green-500" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No outstanding receivables.</p>
                </div>
            @endif
        </flux:card>

        <!-- Billed vs collected -->
        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg xl:col-span-2">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Billed vs Collected</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                    Collections attributed to the payroll period they settle, over 6 periods
                </p>
            </div>

            @if($hasCollection)
                <div id="mgmtCollectionData" data-payload='@json($collectionPayload)' style="display: none;"></div>
                <div class="relative h-72">
                    <canvas id="mgmtCollectionChart" wire:ignore></canvas>
                </div>
            @else
                <div class="flex h-72 flex-col items-center justify-center gap-2 text-center">
                    <flux:icon.chart-bar class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Nothing billed in the last 6 periods yet.</p>
                </div>
            @endif
        </flux:card>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <!-- How long clients take to pay -->
        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Behaviour</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">Settled payroll over the last 6 periods</p>
            </div>

            @if($behaviour['paid_count'] > 0)
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $behaviour['avg_days_to_pay'] }}</p>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">Avg days to pay</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ $behaviour['on_time_rate'] >= 80 ? 'text-green-600 dark:text-green-400' : ($behaviour['on_time_rate'] >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                            {{ $behaviour['on_time_rate'] }}%
                        </p>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">Paid on time</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($behaviour['paid_count']) }}</p>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">Payrolls settled</p>
                    </div>
                </div>

                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ number_format($behaviour['on_time_count']) }} of {{ number_format($behaviour['paid_count']) }}
                    settled on or before the deadline.
                </p>
            @else
                <div class="flex h-32 items-center justify-center text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No settled payroll in the last 6 periods.</p>
                </div>
            @endif
        </flux:card>

        <!-- Payment method mix -->
        <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg xl:col-span-2">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Method Mix</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                    Completed payments over the last 6 months — manually recorded ones cost reconciliation time
                </p>
            </div>

            @if(count($mix) > 0)
                <div class="space-y-3">
                    @foreach($mix as $method)
                        <div>
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="text-zinc-700 dark:text-zinc-300 capitalize">{{ str_replace('_', ' ', $method['method']) }}</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100 tabular-nums">
                                    RM {{ number_format($method['amount'], 0) }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ $method['share'] }}%"></div>
                                </div>
                                <span class="w-28 shrink-0 text-right text-xs text-zinc-500 dark:text-zinc-400 tabular-nums">
                                    {{ $method['share'] }}% &middot; {{ number_format($method['count']) }} {{ Str::plural('payment', $method['count']) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex h-32 items-center justify-center text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No completed payments in the last 6 months.</p>
                </div>
            @endif
        </flux:card>
    </div>
</div>
