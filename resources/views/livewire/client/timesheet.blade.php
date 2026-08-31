<div id="timesheet-scroll-container" class="flex h-full w-full flex-1 flex-col gap-6 overflow-y-auto" wire:init="loadData">
    <!-- Page Header -->
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Timesheet</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Review this month's payroll before it submits automatically</p>
        </div>
        <x-tutorial-button page="timesheet" />
    </div>

    <!-- Sequential Payroll Queue Notice -->
    @if(!$isLoading && $isBlocked && count($blockReasons) > 0)
        <flux:callout icon="queue-list" color="amber">
            <flux:callout.heading>Finish {{ $totalOutstandingCount }} earlier {{ \Str::plural('month', $totalOutstandingCount) }} first</flux:callout.heading>
            <flux:callout.text>{{ $blockReasons[0]['message'] }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" href="{{ $blockReasons[0]['redirect_url'] }}" wire:navigate>
                    {{ $blockReasons[0]['action_text'] }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <!-- Period & Schedule -->
    @if(!$targetMonth && !$targetYear && ($isLoading || (!$errorMessage && !$isBlocked)))
    @php
        // The system submits on the 16th; payment falls due at month end.
        $submitsOn = !$isLoading && isset($period['year'], $period['month'])
            ? \Carbon\Carbon::create($period['year'], $period['month'], 16)
            : null;
        $daysLeft = !$isLoading ? (int) floor($period['days_until_deadline'] ?? 0) : 0;
        $deadlineTone = $daysLeft < 7
            ? 'text-red-600 dark:text-red-400'
            : 'text-zinc-900 dark:text-zinc-100';
    @endphp
    <flux:card id="current-period-info" class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <!-- The period this page is about -->
            <div>
                @if($isLoading)
                    <flux:skeleton animate="shimmer" class="h-8 w-44 rounded" />
                    <flux:skeleton animate="shimmer" class="h-4 w-32 rounded mt-2" />
                @else
                    <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $period['month_name'] }} {{ $period['year'] }}
                    </div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        {{ count($workers) }} {{ \Str::plural('worker', count($workers)) }} awaiting submission
                        @if($stats['overdue_submissions'] > 0)
                            · <span class="text-red-600 dark:text-red-400 font-medium">{{ $stats['overdue_submissions'] }} overdue</span>
                        @endif
                    </p>
                @endif
            </div>

            <!-- Signature: the two dates this page runs on -->
            <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:text-right">
                <dt class="text-zinc-600 dark:text-zinc-400 text-left">Submits</dt>
                <dd class="text-zinc-900 dark:text-zinc-100 tabular-nums">
                    @if($isLoading)
                        <flux:skeleton animate="shimmer" class="h-4 w-24 rounded ml-auto" />
                    @else
                        {{ $submitsOn?->format('d M Y') ?? '—' }}
                    @endif
                </dd>

                <dt class="text-zinc-600 dark:text-zinc-400 text-left">Payment due</dt>
                <dd class="tabular-nums {{ $deadlineTone }}">
                    @if($isLoading)
                        <flux:skeleton animate="shimmer" class="h-4 w-24 rounded ml-auto" />
                    @else
                        {{ $period['deadline']->format('d M Y') }}
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">({{ $daysLeft }}d)</span>
                    @endif
                </dd>
            </dl>
        </div>
    </flux:card>
    @endif

    <!-- Deductions applied this month -->
    @php
        $activeDeductions = !$isLoading ? array_filter($applicableDeductions, fn($d) => $d['status'] === 'active') : [];
    @endphp
    @if(!$isLoading && !$errorMessage && !$isBlocked && count($activeDeductions) > 0 && !$targetMonth && !$targetYear)
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Deductions this month</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">Applied automatically when payroll submits.</p>

        <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach($activeDeductions as $deduction)
                <li class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $deduction['name'] }}</span>
                            <flux:badge color="zinc" size="sm">
                                @if($deduction['type'] === 'contractor')
                                    All workers
                                @else
                                    {{ $deduction['active_worker_count'] }} {{ \Str::plural('worker', $deduction['active_worker_count']) }}
                                @endif
                            </flux:badge>
                        </div>
                        @if($deduction['description'])
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ $deduction['description'] }}</p>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-sm font-medium tabular-nums text-zinc-900 dark:text-zinc-100">
                            −RM {{ number_format($deduction['amount'], 2) }}
                        </span>
                        @if($deduction['type'] === 'contractor')
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">per worker</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </flux:card>
    @endif

    <!-- Worker rows for this period -->
    @if($isLoading || (!$errorMessage && !$isBlocked))
    <flux:card id="worker-verification-table" class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Workers</h2>
            @if(!$isLoading && count($workers) > 0)
                <span class="text-sm text-zinc-500 dark:text-zinc-400 tabular-nums">{{ count($workers) }}</span>
            @endif
        </div>

        @if(!$isLoading && !$canSubmitPayroll && $submissionWindowMessage)
            <div class="mb-4">
                <flux:callout icon="information-circle" color="amber" inline>
                    <flux:callout.text>{{ $submissionWindowMessage }}</flux:callout.text>
                </flux:callout>
            </div>
        @endif

        @if($isLoading)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">#</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</span></flux:table.column>
                    <flux:table.column align="end"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic</span></flux:table.column>
                    <flux:table.column align="end"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Normal</span></flux:table.column>
                    <flux:table.column align="end"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Rest</span></flux:table.column>
                    <flux:table.column align="end"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Public</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Adjustments</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <flux:skeleton.group>
                        @for($i = 0; $i < 5; $i++)
                        <flux:table.rows>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-4 rounded mx-auto" /></flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:skeleton animate="shimmer" class="size-8 rounded-full shrink-0" />
                                    <div class="space-y-1">
                                        <flux:skeleton animate="shimmer" class="h-4 w-28 rounded" />
                                        <flux:skeleton animate="shimmer" class="h-3 w-20 rounded" />
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-16 rounded ml-auto" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-10 rounded ml-auto" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-10 rounded ml-auto" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-10 rounded ml-auto" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-20 rounded" /></flux:table.cell>
                        </flux:table.rows>
                        @endfor
                    </flux:skeleton.group>
                </flux:table.rows>
            </flux:table>
        @elseif(count($workers) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column align="center"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">#</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</span></flux:table.column>
                    <flux:table.column align="end"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic</span></flux:table.column>
                    <flux:table.column align="end"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Normal</span></flux:table.column>
                    <flux:table.column align="end"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Rest</span></flux:table.column>
                    <flux:table.column align="end"><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Public</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Adjustments</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($workers as $index => $worker)
                        <flux:table.rows wire:key="worker-{{ $worker['worker_id'] }}">
                            <flux:table.cell class="text-center tabular-nums text-zinc-500 dark:text-zinc-400">{{ $index + 1 }}</flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="sm" name="{{ $worker['worker_name'] }}" color="auto" />
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $worker['worker_name'] }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 tabular-nums">{{ $worker['worker_id'] }} · {{ $worker['worker_passport'] }}</div>
                                        @if($worker['contract_ended'] ?? false)
                                            <flux:badge color="amber" size="sm" class="mt-1">Contract ended</flux:badge>
                                        @endif
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="text-right tabular-nums text-zinc-900 dark:text-zinc-100">
                                {{ number_format($worker['basic_salary'], 2) }}
                            </flux:table.cell>

                            @foreach(['ot_normal_hours', 'ot_rest_hours', 'ot_public_hours'] as $otField)
                                <flux:table.cell class="text-right tabular-nums {{ ($worker[$otField] ?? 0) > 0 ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-600' }}">
                                    {{ ($worker[$otField] ?? 0) > 0 ? number_format($worker[$otField], 2) : '—' }}
                                </flux:table.cell>
                            @endforeach

                            <flux:table.cell>
                                @php
                                    $transactions = $worker['transactions'] ?? [];
                                    $labels = [
                                        'allowance' => 'Allowance',
                                        'backpay' => 'Backpay',
                                        'medical_claim' => 'Medical claim',
                                        'accommodation' => 'Accommodation',
                                        'advance_payment' => 'Advance',
                                        'npl' => 'NPL',
                                    ];
                                    $earnings = ['allowance', 'backpay', 'medical_claim'];
                                @endphp
                                @if(count($transactions) > 0)
                                    <div class="space-y-0.5">
                                        @foreach($transactions as $txn)
                                            @php
                                                $label = $labels[$txn['type']] ?? 'Deduction';
                                                $isEarning = in_array($txn['type'], $earnings, true);
                                            @endphp
                                            <div class="text-xs text-zinc-600 dark:text-zinc-400">
                                                <span class="tabular-nums {{ $isEarning ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                                    @if($txn['type'] === 'npl')
                                                        {{ $txn['amount'] }} {{ \Str::plural('day', $txn['amount']) }}
                                                    @else
                                                        {{ $isEarning ? '+' : '−' }}RM {{ number_format($txn['amount'], 2) }}
                                                    @endif
                                                </span>
                                                {{ $label }}
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-sm text-zinc-400 dark:text-zinc-600">—</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.rows>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <div class="py-12 text-center">
                <flux:icon.users class="mx-auto size-7 text-zinc-400 dark:text-zinc-600 mb-3" />
                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Nothing left to submit</p>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                    Every worker is already on this month's payroll.
                </p>
            </div>
        @endif
    </flux:card>
    <!-- Submission History -->
    <flux:card id="submission-history" class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Recent timesheets</h2>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Submitted Date</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Grand Total</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Invoice No.</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @if($isLoading)
                    <flux:skeleton.group>
                        @for($i = 0; $i < 5; $i++)
                        <flux:table.rows>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-4 rounded mx-auto" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-20 rounded" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-24 rounded" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-8 rounded" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-20 rounded" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-4 w-16 rounded" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-5 w-20 rounded-full" /></flux:table.cell>
                            <flux:table.cell><flux:skeleton animate="shimmer" class="h-7 w-7 rounded" /></flux:table.cell>
                        </flux:table.rows>
                        @endfor
                    </flux:skeleton.group>
                @else
                @forelse($recentSubmissions as $submission)
                    <flux:table.rows :key="$submission->id">
                        <flux:table.cell>{{ $loop->iteration }}</flux:table.cell>

                        <flux:table.cell variant="strong">{{ $submission->month_year }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $submission->submitted_at ? $submission->submitted_at->format('M d, Y') : 'Not submitted' }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="tabular-nums">{{ $submission->total_workers }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            @if(in_array($submission->status, ['draft', 'submitted']))
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">-</span>
                            @elseif($submission->hasAdminReview())
                                <div class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">
                                    RM {{ number_format($submission->total_due, 2) }}
                                </div>
                            @else
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="tabular-nums">
                            INV-{{ str_pad($submission->id, 4, '0', STR_PAD_LEFT) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if(in_array($submission->status, ['draft', 'submitted']))
                                <flux:badge color="blue" size="sm" inset="top bottom">In Progress</flux:badge>
                            @elseif($submission->status === 'approved')
                                <flux:badge color="purple" size="sm" inset="top bottom">Approved</flux:badge>
                            @elseif($submission->status === 'pending_payment')
                                <flux:badge color="orange" size="sm" inset="top bottom">Pending Payment</flux:badge>
                            @elseif($submission->status === 'paid')
                                <flux:badge color="green" size="sm" inset="top bottom">Paid</flux:badge>
                            @elseif($submission->status === 'overdue')
                                <flux:badge color="red" size="sm" inset="top bottom">Overdue</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" icon:variant="outline" href="{{ route('timesheet.show', $submission->id) }}">View Details</flux:menu.item>
                                    @if($submission->hasAdminReview())
                                        <flux:menu.item icon="document-text" icon:variant="outline" href="{{ route('invoices.show', $submission->id) }}">View Invoice</flux:menu.item>
                                    @endif
                                    @if($submission->status === 'approved' || $submission->status === 'pending_payment' || $submission->status === 'overdue')
                                        <flux:menu.separator />
                                        <form method="POST" action="{{ route('client.payment.create', $submission->id) }}" class="contents">
                                            @csrf
                                            <flux:menu.item icon="credit-card" icon:variant="outline" type="submit">Pay Now</flux:menu.item>
                                        </form>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.rows>
                @empty
                    <flux:table.rows>
                        <flux:table.cell variant="strong" colspan="8" class="text-center">
                            No submissions yet.
                        </flux:table.cell>
                    </flux:table.rows>
                @endforelse
                @endif
            </flux:table.rows>
        </flux:table>
    </flux:card>
    @endif

</div>
