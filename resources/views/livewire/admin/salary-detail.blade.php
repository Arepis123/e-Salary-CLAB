<div class="flex h-full w-full flex-1 flex-col gap-6 overflow-y-auto">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    Payroll Details #PAY{{ str_pad($submission->id, 6, '0', STR_PAD_LEFT) }}
                </h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Submission for {{ $submission->month_year }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="filled" size="sm" wire:click="exportWorkerList" icon="arrow-down-tray" icon-variant="outline">
                Export
            </flux:button>
            @if($submission->hasAdminReview())
                <flux:button variant="filled" size="sm" wire:click="openEditAmountModal" icon="pencil" icon-variant="outline">
                    Edit
                </flux:button>
            @endif
            @if($submission->payment && $submission->payment->status === 'completed')
                <flux:button variant="filled" size="sm" wire:click="downloadReceipt" icon="document" icon-variant="outline">
                    Receipt
                </flux:button>
                <flux:button variant="filled" size="sm" wire:click="printPayslip" icon="printer" icon-variant="outline">
                    Payslip
                </flux:button>
                <flux:button variant="filled" size="sm" wire:click="viewPaymentProof" icon="eye" icon-variant="outline">
                    Payment Proof
                </flux:button>
            @endif            
            <flux:button variant="filled" size="sm" href="{{ route('payroll') }}" icon="arrow-left" icon-variant="outline">
                Back to Submissions
            </flux:button>            
        </div>
    </div>

    <!-- Status Badges -->
    <div class="flex items-center gap-3">
        @if($submission->status === 'paid')
            <flux:badge color="green" size="sm" icon="check-circle" inset="top bottom">Completed</flux:badge>
        @elseif($submission->status === 'pending_payment')
            <flux:badge color="yellow" size="sm" icon="clock" inset="top bottom">Pending Payment</flux:badge>
        @elseif($submission->status === 'overdue')
            <flux:badge color="red" size="sm" icon="exclamation-triangle" inset="top bottom">Overdue</flux:badge>
        @elseif($submission->status === 'approved')
            <flux:badge color="blue" size="sm" icon="check-circle" inset="top bottom">Approved</flux:badge>
        @elseif($submission->status === 'submitted')
            <flux:badge color="orange" size="sm" icon="document-text" inset="top bottom">Under Review</flux:badge>
        @else
            <flux:badge color="zinc" size="sm" inset="top bottom">Draft</flux:badge>
        @endif

        @if($submission->payment && $submission->payment->status === 'completed')
            <flux:badge color="green" size="sm" icon="check" inset="top bottom">Payment Received</flux:badge>
        @elseif($submission->status === 'paid' || $submission->status === 'pending_payment' || $submission->status === 'overdue')
            <flux:badge color="orange" size="sm" icon="clock" inset="top bottom">Awaiting Payment</flux:badge>
        @endif
    </div>

    <!-- Awaiting Review Banner -->
    @if($submission->canBeReviewed())
    <flux:callout icon="exclamation-triangle" color="amber" inline>
        <flux:callout.heading>Awaiting Admin Review</flux:callout.heading>
        <flux:callout.text>
            <p>This submission requires admin review before payment can be processed.</p>
        </flux:callout.text>
        <x-slot name="actions">
            <flux:button wire:click="openReviewModal">Review & Approve</flux:button>
        </x-slot>
    </flux:callout>    
    @endif

    <!-- Approved Info -->
    @if($submission->hasAdminReview())
    <flux:card class="p-6 bg-green-50 dark:bg-green-900/20">
        <h3 class="text-lg font-semibold text-green-900 dark:text-green-100 mb-4">
            Admin Review Completed
            <flux:icon.check-circle class="inline size-5" />
        </h3>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <p class="text-sm text-green-700 dark:text-green-300">Reviewed By:</p>
                <p class="font-medium">{{ $submission->adminReviewer->name ?? 'Unknown' }}</p>
            </div>
            <div>
                <p class="text-sm text-green-700 dark:text-green-300">Reviewed At:</p>
                <p class="font-medium">{{ $submission->admin_reviewed_at->format('d M Y, H:i') }}</p>
            </div>
            <div>
                <p class="text-sm text-green-700 dark:text-green-300">Breakdown File:</p>
                @if($submission->hasBreakdownFile())
                    <flux:button size="sm" variant="ghost" wire:click="downloadBreakdown" icon="arrow-down-tray">
                        {{ $submission->breakdown_file_name }}
                    </flux:button>
                @else
                    <p class="text-sm text-zinc-500">No file uploaded</p>
                @endif
            </div>
        </div>

        <!-- Amount Breakdown for Client -->
        <div class="mt-6 bg-white dark:bg-zinc-800 p-4 rounded-lg border border-green-200 dark:border-green-700">
            <h4 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Client Payment Breakdown:</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-zinc-600 dark:text-zinc-400">Payroll Amount ({{ $submission->total_workers }} workers):</span>
                    <span class="font-medium">RM {{ number_format($submission->admin_final_amount, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-600 dark:text-zinc-400">Service Charge (RM 200 × {{ $submission->billable_workers_count }} {{ Str::plural('worker', $submission->billable_workers_count) }}):</span>
                    <span class="font-medium">RM {{ number_format($submission->calculated_service_charge, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-600 dark:text-zinc-400">SST (8% of service charge):</span>
                    <span class="font-medium">RM {{ number_format($submission->calculated_sst, 2) }}</span>
                </div>
                <div class="border-t border-green-200 dark:border-green-700 pt-2 mt-2 flex justify-between">
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">Client Total:</span>
                    <span class="text-lg font-bold text-green-600">RM {{ number_format($submission->client_total, 2) }}</span>
                </div>
                @if($submission->has_penalty || $submission->isOverdue())
                <div class="flex justify-between mt-2">
                    <span class="text-red-600 dark:text-red-400">Late Payment Penalty (8%):</span>
                    <span class="font-medium text-red-600 dark:text-red-400">+ RM {{ number_format($submission->penalty_amount ?? ($submission->client_total * 0.08), 2) }}</span>
                </div>
                <div class="border-t border-red-200 dark:border-red-700 pt-2 mt-2 flex justify-between">
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">Total Amount Due:</span>
                    <span class="text-xl font-bold text-red-600 dark:text-red-400">RM {{ number_format($submission->total_due, 2) }}</span>
                </div>
                @endif
            </div>
        </div>

        @if($submission->admin_notes)
        <div class="mt-4">
            <p class="text-sm text-green-700 dark:text-green-300">Admin Notes:</p>
            <p class="text-sm bg-white dark:bg-zinc-800 p-3 rounded mt-1">{{ $submission->admin_notes }}</p>
        </div>
        @endif
    </flux:card>
    @endif

    <!-- Summary Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="space-y-2">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Workers</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_workers'] }}</p>
            </div>
        </flux:card>

        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="space-y-2">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Last Month OT Hours</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total_ot_hours'], 2) }}</p>
                <p class="text-xs text-zinc-600 dark:text-zinc-400">Paid in current month</p>
            </div>
        </flux:card>

        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="space-y-2">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Payment Deadline</p>
                <p class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ $submission->payment_deadline->format('d M Y') }}
                </p>
                @if(!$submission->isOverdue() && $submission->status !== 'paid')
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">
                        {{ abs($submission->daysUntilDeadline()) }} days remaining
                    </p>
                @elseif($submission->isOverdue())
                    <p class="text-xs text-red-600 dark:text-red-400">
                        {{ abs($submission->daysUntilDeadline()) }} days overdue
                    </p>
                @endif
            </div>
        </flux:card>
    </div>


    <!-- Submission Information -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Submission Information</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Submission ID:</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        #PAY{{ str_pad($submission->id, 6, '0', STR_PAD_LEFT) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Contractor:</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $submission->user ? $submission->user->name : 'Client ' . $submission->contractor_clab_no }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">CLAB No:</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $submission->contractor_clab_no }}
                    </span>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Period:</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $submission->month_year }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Submitted At:</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $submission->submitted_at ? $submission->submitted_at->format('d M Y, H:i') : '-' }}
                    </span>
                </div>
                @if($submission->paid_at)
                <div class="flex justify-between">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Paid At:</span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">
                        {{ $submission->paid_at->format('d M Y, H:i') }}
                    </span>
                </div>
                @endif
            </div>
        </div>
    </flux:card>

    <!-- Payment Information -->
    @php
        // Get actual payment (exclude redirect logs)
        $actualPayment = $submission->payments()
            ->whereNotIn('status', ['redirected'])
            ->latest()
            ->first();
    @endphp
    @if($actualPayment)
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Information</h2>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Payment Method:</span>
                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ strtoupper($actualPayment->payment_method ?? 'N/A') }}
                </p>
            </div>
            <div>
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Transaction ID:</span>
                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ $actualPayment->transaction_id ?? 'N/A' }}
                </p>
            </div>
            <div>
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Status:</span>
                <p class="mt-1">
                    @if($actualPayment->status === 'completed')
                        <flux:badge color="green" size="sm" icon="check">Completed</flux:badge>
                    @elseif($actualPayment->status === 'pending')
                        <flux:badge color="yellow" size="sm" icon="clock">Pending</flux:badge>
                    @elseif($actualPayment->status === 'failed')
                        <flux:badge color="red" size="sm" icon="x-mark">Failed</flux:badge>
                    @elseif($actualPayment->status === 'cancelled')
                        <flux:badge color="zinc" size="sm" icon="x-mark">Cancelled</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">{{ ucfirst($actualPayment->status) }}</flux:badge>
                    @endif
                </p>
            </div>
        </div>
    </flux:card>
    @endif

    <!-- Workers List - RAW DATA ONLY (No Calculations) -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Client Submission</h2>
            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $stats['total_workers'] }} {{ Str::plural('worker', $stats['total_workers']) }}</span>
        </div>

        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column align="center">
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker ID</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker Name</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</span>
                    </flux:table.column>
                    <flux:table.column align="right">
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</span>
                    </flux:table.column>
                    <flux:table.column align="end">
                        <span class="text-xs text-right font-medium text-zinc-600 dark:text-zinc-400">Weekday OT<br>(Hours)</span>
                    </flux:table.column>
                    <flux:table.column align="end">
                        <span class="text-xs text-right font-medium text-zinc-600 dark:text-zinc-400">Rest Day OT<br>(Hours)</span>
                    </flux:table.column>
                    <flux:table.column align="end">
                        <span class="text-xs text-right font-medium text-zinc-600 dark:text-zinc-400">Public Holiday OT<br>(Hours)</span>
                    </flux:table.column>
                    <flux:table.column align="right">
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Transactions</span>
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($workers as $index => $worker)
                        <flux:table.rows :key="$worker->id">
                            <flux:table.cell align="center">{{ $index + 1 }}</flux:table.cell>

                            <flux:table.cell variant="strong">
                                {{ $worker->worker_id }}
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                {{ $worker->worker_name }}
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                {{ $worker->worker_passport }}
                            </flux:table.cell>

                            <flux:table.cell align="end" variant="strong">
                                @if($worker->basic_salary > 0)
                                    RM {{ number_format($worker->basic_salary, 2) }}
                                @else
                                    <span class="text-zinc-500">-</span>
                                @endif
                            </flux:table.cell>

                            <!-- Weekday OT Hours Only -->
                            <flux:table.cell align="end" variant="strong">
                                @if($worker->ot_normal_hours > 0)
                                    <span class="font-medium">{{ number_format($worker->ot_normal_hours, 2) }}</span>
                                @else
                                    <span class="text-zinc-500">-</span>
                                @endif
                            </flux:table.cell>

                            <!-- Rest Day OT Hours Only -->
                            <flux:table.cell align="end" variant="strong">
                                @if($worker->ot_rest_hours > 0)
                                    <span class="font-medium">{{ number_format($worker->ot_rest_hours, 2) }}</span>
                                @else
                                    <span class="text-zinc-500">-</span>
                                @endif
                            </flux:table.cell>

                            <!-- Public Holiday OT Hours Only -->
                            <flux:table.cell align="end" variant="strong">
                                @if($worker->ot_public_hours > 0)
                                    <span class="font-medium">{{ number_format($worker->ot_public_hours, 2) }}</span>
                                @else
                                    <span class="text-zinc-500">-</span>
                                @endif
                            </flux:table.cell>

                            <!-- Transactions -->
                            <flux:table.cell align="end" variant="strong">
                                @php
                                    $workerTransactions = $worker->transactions ?? collect([]);
                                @endphp
                                @if($workerTransactions->count() > 0)
                                    <div class="text-xs space-y-1">
                                        @foreach($workerTransactions as $txn)
                                            <div class="text-zinc-900 dark:text-zinc-100">
                                                @if($txn->type === 'allowance')
                                                    +RM {{ number_format($txn->amount, 2) }} (Allowance)
                                                @elseif($txn->type === 'npl')
                                                    {{ $txn->amount }} {{ $txn->amount == 1 ? 'day' : 'days' }} (NPL)
                                                @elseif($txn->type === 'advance_payment')
                                                    -RM {{ number_format($txn->amount, 2) }} (Advance)
                                                @else
                                                    -RM {{ number_format($txn->amount, 2) }} (Deduction)
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-zinc-500">-</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.rows>
                    @endforeach

                    <!-- Summary Row -->
                    <flux:table.rows class="border-t-2 border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-800 hidden">
                        <flux:table.cell colspan="5" variant="strong" class="font-bold">
                            <span class="flex justify-center">TOTALS (Reference Only)</span>
                        </flux:table.cell>
                        <!-- Weekday OT Total -->
                        <flux:table.cell align="center" variant="strong" class="font-bold">
                            {{ number_format($workers->sum('ot_normal_hours'), 2) }}h
                        </flux:table.cell>
                        <!-- Rest Day OT Total -->
                        <flux:table.cell align="center" variant="strong" class="font-bold">
                            {{ number_format($workers->sum('ot_rest_hours'), 2) }}h
                        </flux:table.cell>
                        <!-- Public Holiday OT Total -->
                        <flux:table.cell align="center" variant="strong" class="font-bold">
                            {{ number_format($workers->sum('ot_public_hours'), 2) }}h
                        </flux:table.cell>
                        <flux:table.cell align="right" variant="strong" class="font-bold">
                            <div class="text-xs space-y-1">
                                @php
                                    $totalAdvances = $workers->sum('total_advance_payment');
                                    $totalDeductions = $workers->sum('total_deduction');
                                @endphp
                                @if($totalAdvances > 0)
                                    <div class="text-orange-600 dark:text-orange-400">
                                        Adv: -RM {{ number_format($totalAdvances, 2) }}
                                    </div>
                                @endif
                                @if($totalDeductions > 0)
                                    <div class="text-red-600 dark:text-red-400">
                                        Ded: -RM {{ number_format($totalDeductions, 2) }}
                                    </div>
                                @endif
                                @if($totalAdvances == 0 && $totalDeductions == 0)
                                    -
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.rows>
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>


    <!-- Review Modal -->
    <flux:modal wire:model="showReviewModal" size="lg">
        <form wire:submit.prevent="approveSubmission">
            <flux:heading size="lg">Review & Approve Submission</flux:heading>
            <flux:subheading class="mb-4">
                Review #{{ $submission->id }} for {{ $submission->month_year }}
            </flux:subheading>

            <!-- Final Amount Input -->
            <flux:field>
                <flux:label required>Final Amount (RM)</flux:label>
                {{-- <flux:description>Enter final amount from external payroll system</flux:description> --}}
                <flux:input type="number" step="0.01" wire:model="reviewFinalAmount" placeholder="0.00" />
                <flux:error name="reviewFinalAmount" />
            </flux:field>

            <!-- File Upload -->
            <flux:field class="mt-3">
                <flux:label required>Breakdown File (Excel)</flux:label>
                <flux:description>Upload Excel file with columns: Gross Salary, EPF, SOCSO, EIS, HRDF. Amount will be calculated automatically.</flux:description>
                <input type="file" wire:model="breakdownFile" accept=".xlsx,.xls"
                    class="block w-full text-sm text-zinc-500 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-200 dark:hover:file:bg-zinc-600" />
                <flux:error name="breakdownFile" />
                <div wire:loading wire:target="breakdownFile" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                    <flux:icon.arrow-path class="size-3 inline animate-spin" /> Processing Excel file...
                </div>
                @if($breakdownFile)
                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                        <flux:icon.check-circle class="size-3 inline" /> Ready: {{ $breakdownFile->getClientOriginalName() }}
                    </p>
                @endif
            </flux:field>

            <!-- Calculated Breakdown from Excel -->
            @if($calculatedBreakdown)
                <div class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
                    <h4 class="text-sm font-semibold text-green-900 dark:text-green-100 mb-3 flex items-center gap-2">
                        <flux:icon.check-circle class="size-4" />
                        Calculated from Excel
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">Gross Salary:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['gross_salary'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">EPF:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['epf'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">SOCSO:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['socso'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">EIS:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['eis'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">HRDF:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['hrdf'], 2) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-green-300 dark:border-green-600 pt-1">
                            <span class="font-bold text-green-900 dark:text-green-100">Total Payroll:</span>
                            <span class="font-bold text-lg text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['total'], 2) }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Notes -->
            <flux:field class="mt-3">
                <flux:label>Internal Notes (Optional)</flux:label>
                <flux:textarea wire:model="reviewNotes" rows="3" placeholder="Internal notes..." />
                <flux:error name="reviewNotes" />
            </flux:field>

            <div class="flex gap-2 mt-6">
                <flux:button type="submit" variant="filled" icon="check-circle" :disabled="$isReviewing" wire:loading.attr="disabled" wire:target="breakdownFile">
                    <span wire:loading.remove wire:target="breakdownFile">
                        @if($isReviewing)
                            Approving...
                        @else
                            Approve Submission
                        @endif
                    </span>
                    <span wire:loading wire:target="breakdownFile">
                        Uploading file...
                    </span>
                </flux:button>
                <flux:button type="button" wire:click="closeReviewModal" variant="ghost">
                    Cancel
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Payroll Submission Modal -->
    <flux:modal wire:model="showEditAmountModal" size="lg">
        <form wire:submit.prevent="updatePayrollAmount">
            <flux:heading size="lg">Edit Payroll Submission</flux:heading>
            <flux:subheading class="mb-4">
                Update the payroll amount and/or breakdown file for {{ $submission->month_year }}
            </flux:subheading>

            <!-- Warning Callout -->
            <flux:callout icon="exclamation-triangle" color="amber" class="mb-4">
                <flux:callout.text>
                    <p class="text-sm">Changes will update the client's payment amount. Service charge, SST, and penalties will be recalculated automatically.</p>
                </flux:callout.text>
            </flux:callout>

            <div class="grid gap-4 md:grid-cols-2">
                <!-- Current Amount -->
                <div class="p-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">Current Amount:</p>
                    <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->admin_final_amount, 2) }}</p>
                </div>

                <!-- Current File -->
                <div class="p-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">Current File:</p>
                    @if($submission->hasBreakdownFile())
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $submission->breakdown_file_name }}</p>
                    @else
                        <p class="text-sm text-zinc-500">No file</p>
                    @endif
                </div>
            </div>

            <!-- New Amount -->
            <flux:field class="mt-4">
                <flux:label>New Payroll Amount</flux:label>
                <flux:description>Leave blank to keep current amount</flux:description>
                <flux:input wire:model="editPayrollAmount" type="number" step="0.01" min="0.01" placeholder="{{ number_format($submission->admin_final_amount, 2) }}" />
                <flux:error name="editPayrollAmount" />
            </flux:field>

            <!-- New Breakdown File -->
            <flux:field class="mt-3">
                <flux:label>Replace Breakdown File (Excel)</flux:label>
                <flux:description>Upload Excel with columns: Gross Salary, EPF, SOCSO, EIS, HRDF. Amount calculated automatically. Leave blank to keep existing.</flux:description>
                <input type="file" wire:model="newBreakdownFile" accept=".xlsx,.xls"
                    class="block w-full text-sm text-zinc-500 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-200 dark:hover:file:bg-zinc-600" />
                <flux:error name="newBreakdownFile" />
                <div wire:loading wire:target="newBreakdownFile" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                    <flux:icon.arrow-path class="size-3 inline animate-spin" /> Processing Excel file...
                </div>
                @if($newBreakdownFile)
                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                        <flux:icon.check-circle class="size-3 inline" /> Ready: {{ $newBreakdownFile->getClientOriginalName() }}
                    </p>
                @endif
            </flux:field>

            <!-- Calculated Breakdown from Excel -->
            @if($calculatedBreakdown)
                <div class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
                    <h4 class="text-sm font-semibold text-green-900 dark:text-green-100 mb-3 flex items-center gap-2">
                        <flux:icon.check-circle class="size-4" />
                        Calculated from Excel
                    </h4>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">Gross Salary:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['gross_salary'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">EPF:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['epf'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">SOCSO:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['socso'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">EIS:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['eis'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-700 dark:text-green-300">HRDF:</span>
                            <span class="font-medium text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['hrdf'], 2) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-green-300 dark:border-green-600 pt-1">
                            <span class="font-bold text-green-900 dark:text-green-100">Total Payroll:</span>
                            <span class="font-bold text-lg text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['total'], 2) }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Reason for Update -->
            <flux:field class="mt-3">
                <flux:label required>Reason for Changes</flux:label>
                <flux:description>Explain what you're changing and why (required for audit trail)</flux:description>
                <flux:textarea wire:model="editAmountNotes" rows="3" placeholder="e.g., Corrected calculation error, replaced file with updated breakdown..." />
                <flux:error name="editAmountNotes" />
            </flux:field>

            <div class="flex gap-2 mt-6">
                <flux:button type="submit" variant="filled" icon="check" :disabled="$isUpdatingAmount" wire:loading.attr="disabled" wire:target="newBreakdownFile">
                    <span wire:loading.remove wire:target="newBreakdownFile">
                        @if($isUpdatingAmount)
                            Updating...
                        @else
                            Save Changes
                        @endif
                    </span>
                    <span wire:loading wire:target="newBreakdownFile">
                        Uploading file...
                    </span>
                </flux:button>
                <flux:button type="button" wire:click="closeEditAmountModal" variant="ghost">
                    Cancel
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
