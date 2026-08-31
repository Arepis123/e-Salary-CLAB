<div class="flex h-full w-full flex-1 flex-col gap-6 overflow-y-auto">
    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 sm:text-2xl">
                Payroll Details #PAY{{ str_pad($submission->id, 6, '0', STR_PAD_LEFT) }}
            </h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Submission for {{ $submission->month_year }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button variant="filled" size="sm" wire:click="exportWorkerList" icon="arrow-down-tray" icon-variant="outline">
                Export
            </flux:button>
            @if($submission->hasAdminReview() && (!$submission->payment || $submission->payment->status !== 'completed') && !auth()->user()->isFinance())
                <flux:button variant="filled" size="sm" wire:click="openEditAmountModal" icon="pencil" icon-variant="outline">
                    Edit
                </flux:button>
            @endif
            @if($submission->hasAdminReview() && (!$submission->payment || $submission->payment->status !== 'completed'))
                <flux:button variant="filled" size="sm" wire:click="openManualPaymentModal" icon="banknotes" icon-variant="outline">
                    Record Payment
                </flux:button>
            @endif
            @if($submission->payment && $submission->payment->status === 'completed')
                <flux:button variant="filled" size="sm" wire:click="downloadReceipt" icon="document" icon-variant="outline">
                    Receipt
                </flux:button>
            @endif
            <flux:button variant="filled" size="sm" href="{{ route('payroll') }}" icon="arrow-left" icon-variant="outline">
                Back
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

        @if($submission->payment && $submission->payment->status === 'completed' && $submission->payment->payment_method === 'bank_transfer')
            <flux:badge color="pink" size="sm" icon="building-library" inset="top bottom">Manual Transfer</flux:badge>
        @endif
    </div>

    <!-- Awaiting Review Banner -->
    @if($submission->canBeReviewed())
    <flux:callout icon="exclamation-triangle" color="amber" inline>
        <flux:callout.heading>Awaiting Admin Review</flux:callout.heading>
        <flux:callout.text>
            <p>This submission requires admin review before payment can be processed.</p>
        </flux:callout.text>
        @if(!auth()->user()->isFinance())
        <x-slot name="actions">
            <flux:button wire:click="openReviewModal">Review & Approve</flux:button>
        </x-slot>
        @endif
    </flux:callout>
    @endif

    <!-- Approved Info -->
    @if($submission->hasAdminReview())
    <flux:card class="p-4 sm:p-6 bg-green-50 dark:bg-zinc-900 rounded-lg">
        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Admin Review Completed</h2>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Reviewed By:</span>
                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $submission->adminReviewer ? $submission->adminReviewer->name : 'N/A' }}</p>
            </div>
            <div>              
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Reviewed At:</span>
                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $submission->admin_reviewed_at ? $submission->admin_reviewed_at->format('d M Y, H:i') : 'N/A' }}</p>                
            </div>
            <div>               
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Breakdown File:</p>
                @if($submission->hasBreakdownFile())
                    <flux:button size="sm" class="mt-2" variant="filled" wire:click="downloadBreakdown" icon="arrow-down-tray" title="{{ $submission->breakdown_file_name }}">
                        <span class="sm:hidden">{{ Str::limit($submission->breakdown_file_name, 25) }}</span>
                        <span class="hidden sm:inline">{{ $submission->breakdown_file_name }}</span>
                    </flux:button>
                @else
                    <p class="text-sm text-zinc-500">No file uploaded</p>
                @endif
            </div>
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Payslip File (ZIP):</p>
                @if($submission->hasPayslipFile())
                    <flux:button size="sm" class="mt-2" variant="filled" wire:click="downloadPayslip" icon="arrow-down-tray">
                        {{ $submission->payslip_file_name }}
                    </flux:button>
                @elseif(!auth()->user()->isFinance())
                    <flux:button size="sm" class="mt-2" variant="filled" wire:click="openUploadPayslipModal" icon="arrow-up-tray">
                        Upload Payslip
                    </flux:button>
                @else
                    <p class="text-sm text-zinc-500">Not uploaded yet</p>
                @endif
            </div>
        </div>

        <!-- Client Payment Breakdown -->
        <x-payment-breakdown
            :submission="$submission"
            :breakdown="$clientBreakdown"
            heading="Client Payment Breakdown:"
            internal
            class="mt-5"
        />

        @php
            // Newest first: the last thing an admin did is what a reviewer
            // opening this page needs to see.
            $noteEntries = array_reverse($submission->adminNoteEntries());
        @endphp

        @if($noteEntries !== [])
        @php
            // How many fit across depends on the screen: one at a time on a
            // phone, two on a tablet, four on a desktop. Entries are sized as a
            // fraction of the visible strip and the rest are reached by
            // scrolling, so a quarter-width card is never forced onto a phone.
            $notesHorizontal = count($noteEntries) > 1;
            $notesOverflow = count($noteEntries) > 4;
        @endphp

        <p class="mb-2 mt-5 text-sm text-zinc-600 dark:text-zinc-300">
            Admin Notes:
            @if($notesHorizontal)
                <span class="text-xs text-zinc-500 dark:text-zinc-400">({{ count($noteEntries) }} entries &mdash; scroll for older)</span>
            @endif
        </p>
        <div class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800 sm:p-4">
            <div @class(['@container', 'overflow-x-auto pb-2' => $notesHorizontal])>
            <flux:timeline :horizontal="count($noteEntries) > 1">
                @foreach($noteEntries as $entry)
                    {{-- The newest entry carries the accent indicator; older ones stay muted. --}}
                    <flux:timeline.item @class([
                        'w-[82cqw] sm:w-[46cqw]' => $notesHorizontal,
                        'lg:w-[25cqw]' => $notesOverflow,
                        'lg:w-auto' => $notesHorizontal && ! $notesOverflow,
                    ])>
                        <flux:timeline.indicator :color="$loop->first ? 'green' : null">
                            @if($entry['type'] === 'review')
                                <flux:icon.check class="size-4" variant="micro"/>
                            @else
                                <flux:icon.pencil class="size-3.5" variant="micro" />
                            @endif
                        </flux:timeline.indicator>

                        {{-- Flux centres horizontal content at its natural width, so a long
                             note spills over the neighbouring entries once the item has a
                             fixed width. Stretching it to the column, with min-w-0 so the
                             track may shrink, keeps each note inside its own card. --}}
                        <flux:timeline.content class="min-w-0 justify-self-stretch! break-words">
                            {{-- Narrow columns: let the author, time and badge wrap onto their own lines. --}}
                            <flux:heading class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $entry['author'] ?: 'Admin' }}</span>
                                {{-- A review note predating admin_reviewed_at has no timestamp to show. --}}
                                @if($entry['at'])
                                    <flux:text inline class="whitespace-nowrap">{{ $entry['at']->format('d M Y, H:i') }}</flux:text>
                                @endif
                                <flux:badge size="sm" color="zinc">{{ $entry['type'] === 'review' ? 'Review' : 'Update' }}</flux:badge>
                            </flux:heading>
                            

                            {{-- whitespace-pre-line keeps the line breaks the reviewer typed. --}}
                            {{-- break-words keeps long file names inside the column. --}}
                            <p class="mt-0.5 whitespace-pre-line break-words text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $entry['body'] }}</p>
                        </flux:timeline.content>
                    </flux:timeline.item>
                @endforeach
            </flux:timeline>
            </div>
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

    <!-- Salary Deduction Form (signed by contractor + worker) -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Salary Deduction Form</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Declaration signed by the contractor's officer and the worker, covering deductions recorded for
                    <strong>{{ $deductionForm['entry_period'] ?? \Carbon\Carbon::create($submission->year, $submission->month, 1)->subMonth()->format('F Y') }}</strong>.
                </p>
            </div>
            <flux:dropdown position="bottom" align="end">
                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" inset="top right" />

                <flux:menu>
                    <flux:menu.item icon="envelope" wire:click="openDeductionEmailModal">
                        Send email
                    </flux:menu.item>
                    <flux:menu.item icon="clock" wire:click="openDeductionHistoryModal">
                        History
                    </flux:menu.item>
                    <flux:menu.item icon="arrow-up-tray" wire:click="$set('showDeductionUploadModal', true)">
                        Upload
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        <div class="mt-4">
            @if($deductionForm)
                <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 flex flex-wrap items-center gap-3">
                    <flux:icon.check-circle class="size-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $deductionForm['file_name'] }}
                        </p>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">
                            {{ $deductionForm['file_size'] }}
                            @if($deductionForm['uploaded_at'])
                                &middot; uploaded {{ $deductionForm['uploaded_at'] }}
                            @endif
                            @if($deductionWorkersCount > 0)
                                &middot; {{ $deductionWorkersCount }} {{ \Str::plural('worker', $deductionWorkersCount) }} with deductions
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-shrink-0 gap-2">
                        {{-- Livewire turns any file response into a download, so viewing goes through a route. --}}
                        <flux:button
                            size="sm"
                            variant="filled"
                            icon="eye"
                            :href="route('salary-deduction-forms.view', [$deductionForm['id'], $deductionForm['file_name']])"
                            target="_blank"
                        >
                            View
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="filled"
                            icon="arrow-down-tray"
                            :href="route('salary-deduction-forms.download', $deductionForm['id'])"
                        >
                            Download
                        </flux:button>
                    </div>
                </div>
            @elseif($deductionWorkersCount > 0)
                <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 flex flex-wrap items-center gap-3">
                    <flux:icon.exclamation-triangle class="size-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                        Not uploaded. {{ $deductionWorkersCount }} {{ \Str::plural('worker', $deductionWorkersCount) }}
                        had deductions this period, so a signed form is outstanding.
                    </p>
                </div>
            @else
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    No deductions were recorded for this period, so no form is required.
                </p>
            @endif
        </div>
    </flux:card>

    <!-- Payment Information -->
    @php
        // Get actual payment (exclude redirect logs)
        $actualPayment = $submission->payments()
            ->whereNotIn('status', ['redirected'])
            ->latest()
            ->first();

        // Decode the manual-entry metadata (payment_response is stored as a JSON string)
        $manualMeta = [];
        if ($actualPayment && $actualPayment->payment_response) {
            $manualMeta = is_array($actualPayment->payment_response)
                ? $actualPayment->payment_response
                : (json_decode($actualPayment->payment_response, true) ?: []);
        }
        $manualNotes = $manualMeta['notes'] ?? null;
    @endphp
    @if($actualPayment)
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Information</h2>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Payment Method:</span>
                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    @if($actualPayment->payment_method === 'bank_transfer')
                        Bank Transfer
                        @if($actualPayment->bank_name)
                            <span class="text-zinc-500">({{ $actualPayment->bank_name }})</span>
                        @endif
                    @else
                        {{ strtoupper($actualPayment->payment_method ?? 'N/A') }}
                    @endif
                </p>
            </div>
            <div>
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $actualPayment->payment_method === 'bank_transfer' ? 'Bank Reference:' : 'Transaction ID:' }}
                </span>
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

        @if($actualPayment->payment_method === 'bank_transfer')
            <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Amount Received:</span>
                    <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        RM {{ number_format((float) $actualPayment->amount, 2) }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Date Received:</span>
                    <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $actualPayment->completed_at?->format('d M Y') ?? 'N/A' }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Paid From (Bank):</span>
                    <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $actualPayment->bank_name ?? 'N/A' }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Bank Reference:</span>
                    <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $actualPayment->transaction_id ?? 'N/A' }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Recorded By:</span>
                    <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $actualPayment->recordedBy?->name ?? 'N/A' }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Recorded At:</span>
                    <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $actualPayment->created_at?->format('d M Y, H:i') ?? 'N/A' }}
                    </p>
                </div>
                <div>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Proof of Payment:</span>
                    <div class="mt-1">
                        @if($actualPayment->hasProof())
                            <flux:button size="xs" variant="filled" wire:click="downloadPaymentProof" icon="arrow-down-tray">
                                Download
                            </flux:button>
                        @else
                            <span class="text-sm text-zinc-500">Not attached</span>
                        @endif
                    </div>
                </div>
            </div>

            @if(!empty($manualNotes))
                <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Notes:</span>
                    <p class="mt-1 text-sm text-zinc-900 dark:text-zinc-100 whitespace-pre-line">{{ $manualNotes }}</p>
                </div>
            @endif
        @endif
    </flux:card>
    @endif

    <!-- Workers List - RAW DATA ONLY (No Calculations) -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Client Submission</h2>
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
                    <flux:table.column>
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">SOCSO No.</span>
                    </flux:table.column>
                    <flux:table.column>
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">KWSP No.</span>
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

                            <flux:table.cell variant="strong">
                                {{ $worker->worker?->wkr_sosco_id ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                {{ $worker->worker?->wkr_kwsp ?? '-' }}
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
                                                @elseif($txn->type === 'backpay')
                                                    +RM {{ number_format($txn->amount, 2) }} (Backpay)
                                                @elseif($txn->type === 'medical_claim')
                                                    +RM {{ number_format($txn->amount, 2) }} (Medical Claim)
                                                @elseif($txn->type === 'npl')
                                                    {{ $txn->amount }} {{ $txn->amount == 1 ? 'day' : 'days' }} (NPL)
                                                    @if($txn->nplDetails->isNotEmpty())
                                                        <div class="pl-2 mt-0.5 space-y-0.5">
                                                            @foreach($txn->nplDetails as $detail)
                                                                <div class="text-zinc-500 dark:text-zinc-400">
                                                                    {{ $detail->month_label }} ({{ $detail->days_in_month }} days) &mdash;
                                                                    {{ rtrim(rtrim(number_format($detail->npl_days, 1), '0'), '.') }} days &times;
                                                                    RM{{ number_format($detail->daily_rate, 2) }} =
                                                                    <span class="font-medium text-red-600 dark:text-red-400">RM{{ number_format($detail->amount, 2) }}</span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
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
                        <flux:table.cell colspan="7" variant="strong" class="font-bold">
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


    <!-- Send email: composed here so the admin sees what goes out before it does -->
    <flux:modal wire:model="showDeductionEmailModal" class="w-full max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Send Reminder</flux:heading>
                <flux:subheading>
                    @if($submission->user)
                        To {{ $submission->user->name }} &middot; {{ $submission->user->email }}
                    @else
                        This submission has no user account to email.
                    @endif
                </flux:subheading>
            </div>

            <flux:input label="Subject" wire:model="deductionEmailSubject" />

            <flux:textarea label="Message" wire:model="deductionEmailMessage" rows="10" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" size="sm" wire:click="$set('showDeductionEmailModal', false)">
                    Cancel
                </flux:button>
                <flux:button
                    variant="primary"
                    size="sm"
                    icon="paper-airplane"
                    wire:click="sendDeductionFormReminder"
                    wire:loading.attr="disabled"
                    wire:target="sendDeductionFormReminder"
                    :disabled="!$submission->user"
                >
                    <span wire:loading.remove wire:target="sendDeductionFormReminder">Send Email</span>
                    <span wire:loading wire:target="sendDeductionFormReminder">Sending...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- History of reminders sent for this form -->
    <flux:modal wire:model="showDeductionHistoryModal" class="w-full max-w-2xl">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Email History</flux:heading>
                <flux:subheading>
                    Reminders sent about the Salary Deduction Form for this payroll.
                </flux:subheading>
            </div>

            @forelse($deductionEmailHistory as $entry)
                <div class="rounded-md border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $entry['subject'] }}
                        </p>
                        <flux:badge
                            size="sm"
                            :color="match($entry['status']) {
                                'sent' => 'green',
                                'failed' => 'red',
                                default => 'zinc',
                            }"
                        >
                            {{ ucfirst($entry['status']) }}
                        </flux:badge>
                    </div>
                    <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                        {{ $entry['recipient'] }} &middot; {{ $entry['sent_at'] }}
                        @if($entry['sent_by'])
                            &middot; by {{ $entry['sent_by'] }}
                        @endif
                    </p>
                    @if($entry['opened_at'])
                        <p class="mt-1 text-xs text-green-700 dark:text-green-400">
                            Opened {{ $entry['opened_at'] }}
                        </p>
                    @elseif($entry['bounced_at'])
                        <p class="mt-1 text-xs text-red-700 dark:text-red-400">
                            Bounced {{ $entry['bounced_at'] }}
                        </p>
                    @endif
                    @if($entry['error'])
                        <p class="mt-1 text-xs text-red-700 dark:text-red-400">{{ $entry['error'] }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    No reminder has been sent for this form yet.
                </p>
            @endforelse

            <div class="flex justify-end">
                <flux:button variant="ghost" size="sm" wire:click="$set('showDeductionHistoryModal', false)">
                    Close
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Upload on the contractor's behalf -->
    <flux:modal wire:model="showDeductionUploadModal" class="w-full max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Upload on Behalf</flux:heading>
                <flux:subheading>
                    Record the signed form for
                    <strong>{{ \Carbon\Carbon::create($deductionEntryYear, $deductionEntryMonth, 1)->format('F Y') }}</strong>
                    when the contractor sends it outside the system.
                    @if($deductionForm)
                        This replaces the copy already on file.
                    @endif
                </flux:subheading>
            </div>

            <flux:file-upload wire:model="adminDeductionFormFile" accept=".pdf,.jpg,.jpeg,.png">
                <flux:file-upload.dropzone
                    heading="Drop the signed form or click to browse"
                    text="PDF, JPG or PNG up to 10 MB"
                    with-progress
                    inline
                />
            </flux:file-upload>

            @if($adminDeductionFormFile)
                <flux:file-item
                    heading="{{ $adminDeductionFormFile->getClientOriginalName() }}"
                    :size="$adminDeductionFormFile->getSize()"
                    class="my-2"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="$set('adminDeductionFormFile', null)" />
                    </x-slot>
                </flux:file-item>
            @endif

            <flux:error name="adminDeductionFormFile" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" size="sm" wire:click="$set('showDeductionUploadModal', false)">
                    Cancel
                </flux:button>
                @if($adminDeductionFormFile)
                    <flux:button
                        variant="primary"
                        size="sm"
                        icon="arrow-up-tray"
                        wire:click="uploadDeductionFormOnBehalf"
                        wire:loading.attr="disabled"
                        wire:target="uploadDeductionFormOnBehalf"
                    >
                        Upload Signed Form
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

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
                        @if(isset($calculatedBreakdown['custom_advance_salary']) && $calculatedBreakdown['custom_advance_salary'] > 0)
                        <div class="flex justify-between">
                            <span class="text-red-600 dark:text-red-400">Custom Advance Salary:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">- RM {{ number_format($calculatedBreakdown['custom_advance_salary'], 2) }}</span>
                        </div>
                        @endif
                        @if(isset($calculatedBreakdown['custom_accomodation']) && $calculatedBreakdown['custom_accomodation'] > 0)
                        <div class="flex justify-between">
                            <span class="text-red-600 dark:text-red-400">Custom Accomodation:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">- RM {{ number_format($calculatedBreakdown['custom_accomodation'], 2) }}</span>
                        </div>
                        @endif
                        @if(isset($calculatedBreakdown['custom_npl']) && $calculatedBreakdown['custom_npl'] > 0)
                        <div class="flex justify-between">
                            <span class="text-red-600 dark:text-red-400">Custom Npl:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">- RM {{ number_format($calculatedBreakdown['custom_npl'], 2) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between border-t border-green-300 dark:border-green-600 pt-1">
                            <span class="font-bold text-green-900 dark:text-green-100">Total Payroll:</span>
                            <span class="font-bold text-lg text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['total'], 2) }}</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- The file and the contractor's submission disagree, or the amount
                 has been edited away from the file. Approving is still allowed;
                 it just has to be deliberate. --}}
            @if($this->reviewVariance()['has_difference'])
                @php($v = $this->reviewVariance())
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                    <h4 class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-100">
                        <flux:icon.exclamation-triangle class="size-4" />
                        Figures Do Not Match
                    </h4>

                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-amber-800 dark:text-amber-200">Uploaded file total:</span>
                            <span class="font-medium tabular-nums text-amber-900 dark:text-amber-100">RM {{ number_format($v['file_total'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-amber-800 dark:text-amber-200">Contractor's submission:</span>
                            <span class="font-medium tabular-nums text-amber-900 dark:text-amber-100">RM {{ number_format($v['submitted'], 2) }}</span>
                        </div>
                        @if($v['against_submission'] != 0.0)
                            <div class="flex justify-between border-t border-amber-300 pt-1 dark:border-amber-700">
                                <span class="font-semibold text-amber-900 dark:text-amber-100">File vs submission:</span>
                                <span class="font-semibold tabular-nums text-amber-900 dark:text-amber-100">{{ $v['against_submission'] < 0 ? '−' : '+' }}RM {{ number_format(abs($v['against_submission']), 2) }}</span>
                            </div>
                        @endif
                        @if($v['against_file'] != 0.0)
                            <div class="flex justify-between border-t border-amber-300 pt-1 dark:border-amber-700">
                                <span class="font-semibold text-amber-900 dark:text-amber-100">Amount you entered vs file:</span>
                                <span class="font-semibold tabular-nums text-amber-900 dark:text-amber-100">{{ $v['against_file'] < 0 ? '−' : '+' }}RM {{ number_format(abs($v['against_file']), 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <label class="mt-3 flex items-start gap-2 text-xs text-amber-900 dark:text-amber-100">
                        <input type="checkbox" wire:model.live="varianceAcknowledged" class="mt-0.5 rounded border-amber-400 text-amber-600 focus:ring-amber-500" />
                        <span>I have checked the difference and want to approve <span class="font-semibold">RM {{ number_format($v['entered'], 2) }}</span>.</span>
                    </label>
                    <flux:error name="varianceAcknowledged" />
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
            <flux:field class="mt-4">
                <flux:label>Replace Breakdown File (Excel)</flux:label>
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
                        @if(isset($calculatedBreakdown['custom_advance_salary']) && $calculatedBreakdown['custom_advance_salary'] > 0)
                        <div class="flex justify-between">
                            <span class="text-red-600 dark:text-red-400">Custom Advance Salary:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">- RM {{ number_format($calculatedBreakdown['custom_advance_salary'], 2) }}</span>
                        </div>
                        @endif
                        @if(isset($calculatedBreakdown['custom_accomodation']) && $calculatedBreakdown['custom_accomodation'] > 0)
                        <div class="flex justify-between">
                            <span class="text-red-600 dark:text-red-400">Custom Accomodation:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">- RM {{ number_format($calculatedBreakdown['custom_accomodation'], 2) }}</span>
                        </div>
                        @endif
                        @if(isset($calculatedBreakdown['custom_npl']) && $calculatedBreakdown['custom_npl'] > 0)
                        <div class="flex justify-between">
                            <span class="text-red-600 dark:text-red-400">Custom Npl:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">- RM {{ number_format($calculatedBreakdown['custom_npl'], 2) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between border-t border-green-300 dark:border-green-600 pt-1">
                            <span class="font-bold text-green-900 dark:text-green-100">Total Payroll:</span>
                            <span class="font-bold text-lg text-green-900 dark:text-green-100">RM {{ number_format($calculatedBreakdown['total'], 2) }}</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Same guard as the review modal: a saved figure that disagrees
                 with the file or the submission has to be confirmed. --}}
            @if($this->editVariance()['has_difference'])
                @php($ev = $this->editVariance())
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                    <h4 class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-100">
                        <flux:icon.exclamation-triangle class="size-4" />
                        Figures Do Not Match
                    </h4>

                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-amber-800 dark:text-amber-200">{{ $calculatedBreakdown ? 'Uploaded file total:' : 'Stored breakdown total:' }}</span>
                            <span class="font-medium tabular-nums text-amber-900 dark:text-amber-100">RM {{ number_format($ev['file_total'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-amber-800 dark:text-amber-200">Contractor's submission:</span>
                            <span class="font-medium tabular-nums text-amber-900 dark:text-amber-100">RM {{ number_format($ev['submitted'], 2) }}</span>
                        </div>
                        @if($ev['against_submission'] != 0.0)
                            <div class="flex justify-between border-t border-amber-300 pt-1 dark:border-amber-700">
                                <span class="font-semibold text-amber-900 dark:text-amber-100">Breakdown vs submission:</span>
                                <span class="font-semibold tabular-nums text-amber-900 dark:text-amber-100">{{ $ev['against_submission'] < 0 ? '−' : '+' }}RM {{ number_format(abs($ev['against_submission']), 2) }}</span>
                            </div>
                        @endif
                        @if($ev['against_file'] != 0.0)
                            <div class="flex justify-between border-t border-amber-300 pt-1 dark:border-amber-700">
                                <span class="font-semibold text-amber-900 dark:text-amber-100">Amount you entered vs breakdown:</span>
                                <span class="font-semibold tabular-nums text-amber-900 dark:text-amber-100">{{ $ev['against_file'] < 0 ? '−' : '+' }}RM {{ number_format(abs($ev['against_file']), 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <label class="mt-3 flex items-start gap-2 text-xs text-amber-900 dark:text-amber-100">
                        <input type="checkbox" wire:model.live="editVarianceAcknowledged" class="mt-0.5 rounded border-amber-400 text-amber-600 focus:ring-amber-500" />
                        <span>I have checked the difference and want to save <span class="font-semibold">RM {{ number_format($ev['entered'], 2) }}</span>.</span>
                    </label>
                    <flux:error name="editVarianceAcknowledged" />
                </div>
            @endif

            <!-- Reason for Update -->
            <flux:field class="mt-4">
                <flux:label required>Reason for Changes</flux:label>
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

    <!-- Upload Payslip Modal -->
    <flux:modal wire:model="showUploadPayslipModal" size="lg">
        <form wire:submit.prevent="uploadPayslip">
            <flux:heading size="lg">Upload Payslip File</flux:heading>
            <flux:subheading class="mb-4">
                Upload a ZIP file containing all workers' payslip PDFs for {{ $submission->month_year }}.
            </flux:subheading>

            <flux:file-upload wire:model="payslipFile" accept=".zip,.rar,.pdf" label="Payslip File" :disabled="(bool) $payslipFile">
                <flux:file-upload.dropzone
                    heading="Drop file here or click to browse"
                    text="ZIP, RAR, or PDF up to 10MB"
                    with-progress
                    inline
                />
            </flux:file-upload>

            @if($payslipFile)
            <div class="mt-3">
                <flux:file-item heading="{{ $payslipFile->getClientOriginalName() }}" :description="number_format($payslipFile->getSize() / 1024, 2) . ' KB'">
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="$set('payslipFile', null)" />
                    </x-slot>
                </flux:file-item>
            </div>
            @endif

            <div class="flex gap-2 mt-6">
                <flux:button type="submit" variant="filled" icon="arrow-up-tray" :disabled="!$payslipFile" wire:loading.attr="disabled" wire:target="payslipFile, uploadPayslip">
                    <span wire:loading.remove wire:target="uploadPayslip">
                        Upload Payslip
                    </span>
                    <span wire:loading wire:target="uploadPayslip">
                        Uploading...
                    </span>
                </flux:button>
                <flux:button type="button" wire:click="closeUploadPayslipModal" variant="ghost">
                    Cancel
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Record Manual Payment Modal -->
    <flux:modal wire:model="showManualPaymentModal" size="lg">
        <form wire:submit.prevent="recordManualPayment">
            <flux:heading size="lg">Record Manual Payment</flux:heading>
            <flux:subheading class="mb-4">
                Record a payment made directly into the company bank account for {{ $submission->month_year }}.
            </flux:subheading>

            <!-- Amount Due reference -->
            <div class="mb-4 p-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex justify-between items-center">
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Total Amount Due:</span>
                <span class="text-lg font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->total_due, 2) }}</span>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <!-- Amount Received -->
                <flux:field>
                    <flux:label required>Amount Received (RM)</flux:label>
                    <flux:input type="number" step="0.01" min="0.01" wire:model="manualPaymentAmount" placeholder="0.00" />
                    <flux:error name="manualPaymentAmount" />
                </flux:field>

                <!-- Payment Date -->
                <flux:field>
                    <flux:label required>Date Received</flux:label>
                    <flux:date-picker wire:model="manualPaymentDate" max="today" placeholder="Select date" />
                    <flux:error name="manualPaymentDate" />
                </flux:field>

                <!-- Bank -->
                <flux:field>
                    <flux:label required>Paid From (Bank)</flux:label>
                    <flux:input wire:model="manualPaymentBank" placeholder="e.g. Maybank" />
                    <flux:error name="manualPaymentBank" />
                </flux:field>

                <!-- Reference -->
                <flux:field>
                    <flux:label required>Bank Reference No.</flux:label>
                    <flux:input wire:model="manualPaymentReference" placeholder="Transaction / reference number" />
                    <flux:error name="manualPaymentReference" />
                </flux:field>
            </div>

            <!-- Proof of Payment -->
            <flux:field class="mt-3">
                <flux:label badge="Required">Proof of Payment</flux:label>
                <input type="file" wire:model="manualPaymentProof" accept=".pdf,.jpg,.jpeg,.png"
                    class="block w-full text-sm text-zinc-500 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-200 dark:hover:file:bg-zinc-600" />
                <flux:error name="manualPaymentProof" />
                <div wire:loading wire:target="manualPaymentProof" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                    <flux:icon.arrow-path class="size-3 inline animate-spin" /> Uploading file...
                </div>
                @if($manualPaymentProof)
                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                        <flux:icon.check-circle class="size-3 inline" /> Ready: {{ $manualPaymentProof->getClientOriginalName() }}
                    </p>
                @endif
            </flux:field>

            <!-- Notes -->
            <flux:field class="mt-3">
                <flux:label>Notes <span class="ms-1 text-zinc-500 dark:text-zinc-400">(Optional)</span></flux:label>
                <flux:textarea wire:model="manualPaymentNotes" rows="2" placeholder="Any additional notes for the audit trail..." />
                <flux:error name="manualPaymentNotes" />
            </flux:field>

            <div class="flex gap-2 mt-6">
                <flux:button type="submit" variant="primary" icon="check-circle" :disabled="$isRecordingPayment" wire:loading.attr="disabled" wire:target="manualPaymentProof, recordManualPayment">
                    <span wire:loading.remove wire:target="manualPaymentProof, recordManualPayment">
                        Record Payment
                    </span>
                    <span wire:loading wire:target="manualPaymentProof">
                        Uploading file...
                    </span>
                    <span wire:loading wire:target="recordManualPayment">
                        Recording...
                    </span>
                </flux:button>
                <flux:button type="button" wire:click="closeManualPaymentModal" variant="ghost">
                    Cancel
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
