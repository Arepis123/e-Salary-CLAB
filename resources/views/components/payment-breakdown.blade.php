@props([
    'submission',
    // Pass the already-built array where the caller has one (the admin screen
    // keeps it as a Livewire computed property); otherwise it is built here.
    'breakdown' => null,
    // Admin context. Turns on the reconciliation lines that explain how the
    // billed figure was arrived at — the file it came from, and how it compares
    // with what the contractor submitted.
    'internal' => false,
    'heading' => null,
])

@php
    $b = $breakdown ?? (new \App\Services\ClientBreakdownBuilder)->build($submission);

    // Without the approved file's itemisation every line below would be derived
    // from the worker rows rather than taken from what the client is billed, so
    // the payroll figure is shown as a single line instead.
    $itemised = $submission->hasBreakdownItemisation();

    $file = $b['file'];
    $fileTotal = $file ? (float) ($file['total'] ?? 0) : 0.0;
    $fileVariance = $file ? $b['payroll_amount'] - $fileTotal : 0.0;
    $submittedVariance = $b['payroll_amount'] - $b['computed_payroll'];
    $fmt = fn ($value) => number_format($value, 2);

    // SKBBK is mandatory for foreign workers and comes out of the worker's
    // gross like their EPF and SOCSO shares, so it belongs in the memo below
    // and never on the client's bill.
    $skbbk = (float) ($file['skbbk'] ?? 0);
    // Withheld from the worker but kept by CLAB, so it sits in the memo
    // alongside statutory rather than reducing the client's bill.
    $withheld = $b['worker_statutory'] + $skbbk + $b['retained_deductions'];

    // The breakdown file only carries a single gross figure, so its make-up is
    // taken from the contractor's worker rows. Anything the two do not account
    // for is shown as its own line rather than silently folded into one of the
    // components.
    $earningLines = array_values(array_filter([
        ['Basic salary ('.$b['workers_counted'].' '.Str::plural('worker', $b['workers_counted']).')', $b['basic_salary']],
        ['Overtime', $b['overtime']],
        ['Allowances / claims / backpay', $b['additional_earnings']],
    ], fn ($line) => (float) $line[1] > 0));

    $earningsTotal = $file ? (float) ($file['gross_salary'] ?? 0) : $b['earnings'];
    $earningsBalance = $earningsTotal - array_sum(array_column($earningLines, 1));

    if (round($earningsBalance, 2) != 0.00) {
        $earningLines[] = ['Other earnings in the file', $earningsBalance];
    }

    $contributionLines = array_values(array_filter($file ? [
        ['EPF (employer)', $file['epf'] ?? 0],
        ['SOCSO (employer)', $file['socso'] ?? 0],
        ['EIS (employer)', $file['eis'] ?? 0],
        ['HRDF levy', $file['hrdf'] ?? 0],
    ] : [
        ['EPF (employer)', $b['employer_epf']],
        ['SOCSO (employer)', $b['employer_socso']],
    ], fn ($line) => (float) $line[1] > 0));

    $deductionLines = array_values(array_filter($file ? [
        ['Advance payment', $file['custom_advance_salary'] ?? 0],
        ['Accommodation', $file['custom_accomodation'] ?? 0],
        ['No-pay leave', $file['custom_npl'] ?? 0],
    ] : [
        ['Advance payment', $b['advance_payment']],
        ['Accommodation', $b['accommodation']],
        ['No-pay leave', $b['npl']],
    ], fn ($line) => (float) $line[1] > 0));

    $groups = [
        ['key' => 'earn', 'label' => 'Earnings', 'negative' => false, 'color' => 'text-green-600 dark:text-green-400', 'lines' => $earningLines, 'total' => $earningsTotal],
        ['key' => 'contrib', 'label' => 'Employer contributions', 'negative' => false, 'color' => 'text-zinc-800 dark:text-zinc-100', 'lines' => $contributionLines, 'total' => array_sum(array_column($contributionLines, 1))],
        ['key' => 'ded', 'label' => 'Deductions', 'negative' => true, 'color' => 'text-red-600 dark:text-red-400', 'lines' => $deductionLines, 'total' => array_sum(array_column($deductionLines, 1))],
    ];

    $payrollAdjustment = $file ? $fileVariance : ($b['is_reviewed'] ? $b['adjustment'] : 0.0);
@endphp

<div {{ $attributes }} x-data="{ details: true, memo: true, open: { earn: true, contrib: true, ded: true } }">
    @if($heading)
        <p class="mb-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $heading }}</p>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="divide-y divide-zinc-200 bg-white text-sm dark:divide-zinc-700 dark:bg-zinc-800">
            @unless($itemised)
                {{-- No stored itemisation to open up: the figures below would be
                     derived rather than taken from the approved file, so the
                     payroll line stands on its own. --}}
                <div class="flex items-start justify-between gap-3 px-3 py-2.5 sm:px-4">
                    <span class="text-zinc-900 dark:text-zinc-100">
                        Payroll
                        @if($internal)
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">(itemisation not stored for this submission)</span>
                        @endif
                    </span>
                    <span class="shrink-0 text-zinc-900 dark:text-zinc-100">RM {{ $fmt($b['payroll_amount']) }}</span>
                </div>
            @else
            <!-- Payroll -->
            <div class="flex items-start justify-between gap-3 px-3 py-2.5 sm:px-4">
                <button type="button" class="inline-flex items-center gap-1.5 text-zinc-900 dark:text-zinc-100" @click="details = !details" x-bind:aria-expanded="details.toString()">
                    Payroll
                    <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform" x-bind:class="details ? 'rotate-180' : ''" />
                </button>
                <span class="shrink-0 text-zinc-900 dark:text-zinc-100">RM {{ $fmt($b['payroll_amount']) }}</span>
            </div>

            <!-- Payroll detail -->
            <div class="bg-white px-3 py-2 dark:bg-zinc-900/40 sm:px-4" x-show="details">
                @foreach($groups as $group)
                    @continue($group['lines'] === [] && round($group['total'], 2) == 0.00)
                    <div class="py-1">
                        <button type="button" class="flex w-full items-start justify-between gap-3 text-left" @click="open['{{ $group['key'] }}'] = ! open['{{ $group['key'] }}']" x-bind:aria-expanded="(!! open['{{ $group['key'] }}']).toString()">
                            <span class="inline-flex min-w-0 items-center gap-1.5 text-left text-zinc-600 dark:text-zinc-300">
                                {{ $group['label'] }}
                                <flux:icon.chevron-down class="size-3.5 text-zinc-400 transition-transform" x-bind:class="open['{{ $group['key'] }}'] ? 'rotate-180' : ''" />
                            </span>
                            <span class="shrink-0 {{ $group['color'] }}">{{ $group['negative'] ? '−' : '+' }}{{ $fmt($group['total']) }}</span>
                        </button>
                        <div class="mt-0.5 pl-3 text-xs text-zinc-500 dark:text-zinc-400" x-show="open['{{ $group['key'] }}']">
                            @foreach($group['lines'] as $line)
                                <div class="flex items-start justify-between gap-3 py-0.5">
                                    <span>{{ $line[0] }}</span>
                                    <span class="shrink-0 tabular-nums">{{ $line[1] < 0 ? '−' : '' }}{{ $fmt(abs($line[1])) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if(round($payrollAdjustment, 2) != 0.00)
                    <div class="flex items-start justify-between gap-3 py-1">
                        <span class="text-zinc-600 dark:text-zinc-300">{{ $internal ? 'Admin adjustment' : 'Adjustment' }}</span>
                        <span class="shrink-0 text-amber-600 dark:text-amber-400">{{ $payrollAdjustment < 0 ? '−' : '+' }}{{ $fmt(abs($payrollAdjustment)) }}</span>
                    </div>
                @endif

                @if($file)
                    {{-- Where the figures came from, and how they compare with the
                         submitted timesheet. The contractor is entitled to both:
                         they can download the same file from their invoice. --}}
                    <p class="mt-1 border-t border-zinc-200 pt-1.5 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        From {{ $submission->breakdown_file_name ?: 'the approved breakdown file' }}.
                        {{ $internal ? 'Contractor submitted' : 'Your submitted figures totalled' }}
                        {{ $fmt($b['computed_payroll']) }}@if(round($submittedVariance, 2) != 0.00) ({{ $submittedVariance < 0 ? '−' : '+' }}{{ $fmt(abs($submittedVariance)) }} difference)@endif.
                        @if(($file['backpay'] ?? 0) > 0)
                            Backpay {{ $fmt($file['backpay']) }} excluded by design.
                        @endif
                    </p>
                @elseif($b['overtime_derived'] || $b['statutory_derived'])
                    <p class="mt-1 border-t border-zinc-200 pt-1.5 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        Some figures calculated at the standard rates.
                    </p>
                @endif

                <!-- Memo: withheld from workers, never billed to the client -->
                <div class="mt-2 border-t border-zinc-200 pt-1.5 dark:border-zinc-700">
                    <button type="button" class="flex w-full items-start justify-between gap-3 text-left text-xs text-zinc-500 dark:text-zinc-400" @click="memo = !memo" x-bind:aria-expanded="memo.toString()">
                        <span class="inline-flex items-center gap-1.5">
                            Withheld from workers (not billed)
                            <flux:icon.chevron-down class="size-3.5 transition-transform" x-bind:class="memo ? 'rotate-180' : ''" />
                        </span>
                        <span class="shrink-0 tabular-nums">({{ $fmt($withheld) }})</span>
                    </button>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-show="memo">
                        <div class="flex items-start justify-between gap-3 py-0.5">
                            <span>EPF (worker)</span>
                            <span class="shrink-0 tabular-nums">({{ $fmt($b['worker_epf']) }})</span>
                        </div>
                        <div class="flex items-start justify-between gap-3 py-0.5">
                            <span>SOCSO (worker)</span>
                            <span class="shrink-0 tabular-nums">({{ $fmt($b['worker_socso']) }})</span>
                        </div>
                        @if($skbbk > 0)
                            <div class="flex items-start justify-between gap-3 py-0.5">
                                <span>SKBBK (worker)</span>
                                <span class="shrink-0 tabular-nums">({{ $fmt($skbbk) }})</span>
                            </div>
                        @elseif($internal && $file)
                            <div class="flex items-start justify-between gap-3 py-0.5 text-zinc-400 dark:text-zinc-500">
                                <span>SKBBK (worker)</span>
                                <span class="shrink-0 tabular-nums">not in this breakdown file</span>
                            </div>
                        @endif
                        @if($b['retained_deductions'] > 0)
                            <div class="flex items-start justify-between gap-3 py-0.5">
                                <span>Deductions retained by CLAB <span class="text-zinc-400 dark:text-zinc-500">(phone topup, rental)</span></span>
                                <span class="shrink-0 tabular-nums">({{ $fmt($b['retained_deductions']) }})</span>
                            </div>
                        @endif
                        <p class="mt-1">Deducted from the workers&rsquo; salary &mdash; already inside the gross above.</p>
                    </div>
                </div>
            </div>
            @endunless

            <!-- Charges -->
            <div class="flex items-start justify-between gap-3 px-3 py-2.5 sm:px-4">
                <span class="text-zinc-900 dark:text-zinc-100">
                    Service charge
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        @if($b['service_charge'] > 0)
                            (RM 200 &times; {{ $submission->billable_workers_count }})
                        @else
                            (exempt)
                        @endif
                    </span>
                </span>
                <span class="shrink-0 text-zinc-900 dark:text-zinc-100">RM {{ $fmt($b['service_charge']) }}</span>
            </div>
            <div class="flex items-start justify-between gap-3 px-3 py-2.5 sm:px-4">
                <span class="text-zinc-900 dark:text-zinc-100">SST <span class="text-xs text-zinc-500 dark:text-zinc-400">(8%)</span></span>
                <span class="shrink-0 text-zinc-900 dark:text-zinc-100">RM {{ $fmt($b['sst']) }}</span>
            </div>
            @if($b['penalty'] > 0)
                <div class="flex items-start justify-between gap-3 px-3 py-2.5 sm:px-4">
                    <span class="text-zinc-600 dark:text-zinc-300">Late payment penalty <span class="text-xs text-zinc-500 dark:text-zinc-400">(8%)</span></span>
                    <span class="shrink-0 text-zinc-900 dark:text-zinc-100">RM {{ $fmt($b['penalty']) }}</span>
                </div>
            @endif
        </div>

        <!-- Total -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-200 px-3 py-3 sm:px-4 dark:border-zinc-700 bg-white dark:bg-zinc-800">
            <div>
                <p class="text-sm font-medium dark:text-zinc-200">Total Amount Due</p>
                @unless($b['is_reviewed'])
                    <flux:badge color="amber" size="sm" class="mt-1">Estimated &mdash; pending review</flux:badge>
                @endunless
            </div>
            <span class="font-medium text-sm dark:text-zinc-100">RM {{ $fmt($b['total']) }}</span>
        </div>
    </div>
</div>
