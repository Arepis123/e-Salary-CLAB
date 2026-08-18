@php
    $hasRevenue = collect($trends['revenue_total'] ?? [])->sum() > 0;
    $hasHeadcount = collect($trends['workers'] ?? [])->sum() > 0;

    // Built here rather than inline: Blade's @json directive cannot parse a
    // multi-line array literal written inside an HTML attribute.
    $revenuePayload = [
        'labels' => $trends['labels'],
        'service_charge' => $trends['service_charge'],
        'sst' => $trends['sst'],
        'penalty' => $trends['penalty'],
        'payroll_volume' => $trends['payroll_volume'],
    ];

    $headcountPayload = [
        'labels' => $trends['labels'],
        'workers' => $trends['workers'],
        'clients' => $trends['clients'],
    ];
@endphp

<div class="grid gap-4 xl:grid-cols-2">
    <!-- Revenue composition over 12 periods -->
    <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Revenue Composition</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                Service charge, SST and penalties over 12 periods, with payroll volume for scale
            </p>
        </div>

        @if($hasRevenue)
            <div id="mgmtRevenueData" data-payload='@json($revenuePayload)' style="display: none;"></div>
            <div class="relative h-72">
                <canvas id="mgmtRevenueChart" wire:ignore></canvas>
            </div>
        @else
            <div class="flex h-72 flex-col items-center justify-center gap-2 text-center">
                <flux:icon.chart-bar class="size-10 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">No billed payroll in the last 12 periods yet.</p>
            </div>
        @endif
    </flux:card>

    <!-- Headcount, the driver behind the revenue line -->
    <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Workers &amp; Clients on Payroll</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                Headcount drives revenue at RM 200 per billable worker
            </p>
        </div>

        @if($hasHeadcount)
            <div id="mgmtHeadcountData" data-payload='@json($headcountPayload)' style="display: none;"></div>
            <div class="relative h-72">
                <canvas id="mgmtHeadcountChart" wire:ignore></canvas>
            </div>
        @else
            <div class="flex h-72 flex-col items-center justify-center gap-2 text-center">
                <flux:icon.users class="size-10 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">No workers on payroll in the last 12 periods yet.</p>
            </div>
        @endif
    </flux:card>
</div>
