<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Payroll Management</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">View and manage payroll submissions and payment status</p>
        </div>
    </div>

    <!-- Statistics Summary -->
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Submissions</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_submissions'] ?? 0 }}</p>
                </div>
                <flux:icon.document-text class="size-8 text-blue-600 dark:text-blue-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Grand Total (incl. Service & SST)</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['grand_total'] ?? 0, 2) }}</p>
                </div>
                <flux:icon.wallet class="size-8 text-purple-600 dark:text-purple-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Completed</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['completed'] ?? 0 }}</p>
                </div>
                <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Pending</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['pending'] ?? 0 }}</p>
                </div>
                <flux:icon.clock class="size-8 text-orange-600 dark:text-orange-400" />
            </div>
        </flux:card>
    </div>

    <!-- Submissions Table -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Submissions</h2>
            <div class="flex gap-2">
                <flux:button variant="ghost" size="sm" icon="arrow-down-tray" icon-variant="outline" wire:click="export">
                    Export
                </flux:button>
                <flux:button variant="ghost" size="sm" icon="funnel" icon-variant="outline" wire:click="toggleFilters">
                    Filter
                </flux:button>
            </div>
        </div>

        <!-- Filters and Search -->
        @if($showFilters)
        <div class="mb-6" x-data x-transition>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                <div>
                    <flux:input
                        wire:model.live="search"
                        placeholder="Search by ID or contractor..."
                        icon="magnifying-glass"
                        size="sm"
                    />
                </div>
                <div>
                    <flux:select wire:model.live="contractor" variant="listbox" placeholder="Filter by Contractor" size="sm">
                        <flux:select.option value="">All Contractors</flux:select.option>
                        @foreach($contractors as $clabNo => $name)
                            <flux:select.option value="{{ $clabNo }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Filter by Status" size="sm">
                        <flux:select.option value="">All Statuses</flux:select.option>
                        <flux:select.option value="draft">Draft</flux:select.option>
                        <flux:select.option value="submitted">Submitted</flux:select.option>
                        <flux:select.option value="approved">Approved</flux:select.option>
                        <flux:select.option value="pending_payment">Pending Payment</flux:select.option>
                        <flux:select.option value="paid">Paid</flux:select.option>
                        <flux:select.option value="overdue">Overdue</flux:select.option>
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model.live="paymentStatusFilter" variant="listbox" placeholder="Filter by Payment" size="sm">
                        <flux:select.option value="">All Payment Statuses</flux:select.option>
                        <flux:select.option value="paid">Paid</flux:select.option>
                        <flux:select.option value="awaiting">Awaiting Payment</flux:select.option>
                    </flux:select>
                </div>
                <div>
                    <flux:button variant="filled" size="sm" wire:click="clearFilters">
                        <flux:icon.x-mark class="size-4 inline" />
                        Clear
                    </flux:button>
                </div>
            </div>
        </div>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sortByColumn('id')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Submission ID</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'contractor_clab_no'" :direction="$sortDirection" wire:click="sortByColumn('contractor_clab_no')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span></flux:table.column>
                <flux:table.column align="center" sortable :sorted="$sortBy === 'total_workers'" :direction="$sortDirection" wire:click="sortByColumn('total_workers')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'month'" :direction="$sortDirection" wire:click="sortByColumn('month')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'grand_total'" :direction="$sortDirection" wire:click="sortByColumn('grand_total')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Grand Total</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sortByColumn('status')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Payment</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'submitted_at'" :direction="$sortDirection" wire:click="sortByColumn('submitted_at')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Submitted</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($submissions as $index => $submission)
                    <flux:table.rows :key="$submission->id">
                        <flux:table.cell>{{ $pagination['from'] + $index }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            #PAY{{ str_pad($submission->id, 6, '0', STR_PAD_LEFT) }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="max-w-xs truncate">
                            {{ $submission->user ? $submission->user->name : 'Client ' . $submission->contractor_clab_no }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong" align="center">
                            {{ $submission->total_workers }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ \Carbon\Carbon::parse($submission->month_year)->format('M Y') }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            @if($submission->hasAdminReview())
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    RM {{ number_format($submission->client_total, 2) }}
                                </div>
                            @else
                                <span class="text-sm"></span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($submission->status === 'paid')
                                <flux:badge color="green" size="sm">Completed</flux:badge>
                            @elseif($submission->status === 'pending_payment')
                                <flux:badge color="yellow" size="sm">Pending Payment</flux:badge>
                            @elseif($submission->status === 'overdue')
                                <flux:badge color="red" size="sm">Overdue</flux:badge>
                            @elseif($submission->status === 'approved')
                                <flux:badge color="blue" size="sm">Approved</flux:badge>
                            @elseif($submission->status === 'submitted')
                                <flux:badge color="orange" size="sm">Under Review</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Draft</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($submission->status === 'paid')
                                <flux:badge color="green" size="sm" class="w-12 justify-center">Paid</flux:badge>
                            @else
                                <flux:badge color="red" size="sm" class="w-12 justify-center">No</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $submission->submitted_at ? $submission->submitted_at->format('d M Y') : '' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" href="{{ route('payroll.detail', $submission->id) }}">View Details</flux:menu.item>
                                    <flux:menu.item icon="clipboard-document-list" wire:click="openPaymentLog({{ $submission->id }})">Payment Log</flux:menu.item>
                                    {{-- @if($submission->payment && $submission->payment->status === 'completed')
                                        <flux:menu.item icon="document">Download Receipt</flux:menu.item>
                                    @endif --}}
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.rows>
                @empty
                    <flux:table.rows>
                        <flux:table.cell variant="strong" colspan="10" class="text-center">
                            @if($search || $contractor || $statusFilter || $paymentStatusFilter)
                                No submissions found matching your filters.
                            @else
                                No payroll submissions have been created yet.
                            @endif
                        </flux:table.cell>
                    </flux:table.rows>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <!-- Pagination -->
        @if($pagination['total'] > $perPage)
        <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                Showing {{ $pagination['from'] }} to {{ $pagination['to'] }} of {{ $pagination['total'] }} submissions
            </div>
            <div class="flex gap-2">
                <flux:button
                    wire:click="$set('page', {{ $pagination['current_page'] - 1 }})"
                    variant="ghost"
                    size="sm"
                    icon="chevron-left"
                    icon-variant="micro"
                    :disabled="$pagination['current_page'] === 1"
                >
                    Previous
                </flux:button>
                <flux:button
                    wire:click="$set('page', {{ $pagination['current_page'] + 1 }})"
                    variant="ghost"
                    size="sm"
                    icon="chevron-right"
                    icon-trailing
                    icon-variant="micro"
                    :disabled="$pagination['current_page'] >= $pagination['last_page']"
                >
                    Next
                </flux:button>
            </div>
        </div>
        @endif
    </flux:card>

    <!-- Payment Log Modal -->
    @if($showPaymentLog && $selectedSubmission)
        <flux:modal wire:model="showPaymentLog" class="w-full max-w-3xl">
            <div class="space-y-4 p-4 sm:p-6">
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Payment Log
                    </h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        Payment details for submission #PAY{{ str_pad($selectedSubmission->id, 6, '0', STR_PAD_LEFT) }}
                    </p>
                </div>

                <!-- Submission Info Card -->
                <flux:card class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <flux:icon.document-text class="size-8 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $selectedSubmission->user ? $selectedSubmission->user->name : 'Client ' . $selectedSubmission->contractor_clab_no }}
                            </p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                Period: {{ \Carbon\Carbon::parse($selectedSubmission->month_year)->format('M Y') }} | Workers: {{ $selectedSubmission->total_workers }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2 items-center hidden">
                                <flux:badge color="blue" size="sm">
                                    RM {{ number_format($selectedSubmission->client_total, 2) }}
                                </flux:badge>
                                @if($selectedSubmission->payment && $selectedSubmission->payment->status === 'completed')
                                    <flux:badge color="green" size="sm" icon="check">Paid</flux:badge>
                                @else
                                    <flux:badge color="orange" size="sm" icon="clock">Awaiting Payment</flux:badge>
                                @endif
                            </div>
                        </div>
                    </div>
                </flux:card>

                @if($selectedSubmission->payments && $selectedSubmission->payments->count() > 0)
                    <!-- Payment Attempts -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Payment Attempts</h3>
                            <flux:badge color="zinc" size="sm">
                                {{ $selectedSubmission->payments->count() }} {{ Str::plural('attempt', $selectedSubmission->payments->count()) }}
                            </flux:badge>
                        </div>

                        <!-- Latest Payment Status -->
                        @php
                            $latestPayment = $selectedSubmission->payments->first();
                            $statusColors = [
                                'completed' => 'green',
                                'pending' => 'orange',
                                'processing' => 'blue',
                                'failed' => 'red',
                                'cancelled' => 'yellow',
                                'redirected' => 'cyan',
                            ];
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">Current Status:</span>
                            <flux:badge color="{{ $statusColors[$latestPayment->status] ?? 'zinc' }}" size="md" inset="top bottom">
                                {{ ucfirst($latestPayment->status) }}
                            </flux:badge>
                        </div>

                        <!-- Info Note -->
                        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3">
                            <div class="flex gap-2">
                                <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                                <div class="text-xs text-blue-800 dark:text-blue-200">
                                    <p class="font-medium mb-1">Payment Attempt Tracking</p>
                                    <p>Every payment attempt is logged, including when clients are redirected to existing pending payments within the 2-hour window. This helps you track all client payment interactions and assist them if they encounter errors.</p>
                                </div>
                            </div>
                        </div>

                        <!-- All Payment Attempts List -->
                        <div class="space-y-3">
                            @foreach($selectedSubmission->payments as $index => $payment)
                                @php
                                    $borderColor = match($payment->status) {
                                        'completed' => 'border-green-500',
                                        'failed' => 'border-red-500',
                                        'cancelled' => 'border-yellow-500',
                                        'pending' => 'border-orange-500',
                                        'processing' => 'border-blue-500',
                                        'redirected' => 'border-cyan-500',
                                        default => 'border-zinc-300 dark:border-zinc-700',
                                    };
                                @endphp
                                <flux:card class="p-4 {{ $borderColor }}">
                                    <div class="space-y-3">
                                        <!-- Header -->
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                                    Attempt #{{ $selectedSubmission->payments->count() - $index }}
                                                </span>
                                                <flux:badge color="{{ $statusColors[$payment->status] ?? 'zinc' }}" size="sm" inset="top bottom">
                                                    {{ ucfirst($payment->status) }}
                                                </flux:badge>
                                                @if($index === 0)
                                                    <flux:badge color="zinc" size="sm">Latest</flux:badge>
                                                @endif
                                            </div>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $payment->created_at->format('d M Y, h:i A') }}
                                            </span>
                                        </div>

                                        <!-- Payment Details Grid -->
                                        <div class="grid gap-2 sm:grid-cols-2 text-sm">
                                            <div>
                                                <span class="text-zinc-500 dark:text-zinc-400">Method:</span>
                                                <span class="font-medium text-zinc-900 dark:text-zinc-100 ml-1">{{ strtoupper($payment->payment_method) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-zinc-500 dark:text-zinc-400">Amount:</span>
                                                <span class="font-medium text-zinc-900 dark:text-zinc-100 ml-1">RM {{ number_format($payment->amount, 2) }}</span>
                                            </div>

                                            @if($payment->payment_type)
                                                <div>
                                                    <span class="text-zinc-500 dark:text-zinc-400">Payment Type:</span>
                                                    <flux:badge
                                                        color="{{ $payment->payment_type === 'B2B' ? 'purple' : 'blue' }}"
                                                        size="sm"
                                                        inset="top bottom"
                                                        class="ml-1"
                                                    >
                                                        {{ $payment->payment_type }}
                                                    </flux:badge>
                                                </div>
                                            @endif

                                            @if($payment->bank_name)
                                                <div>
                                                    <span class="text-zinc-500 dark:text-zinc-400">Bank:</span>
                                                    <span class="font-medium text-zinc-900 dark:text-zinc-100 ml-1">{{ $payment->bank_name }}</span>
                                                </div>
                                            @endif

                                            @if($payment->transaction_id)
                                                <div class="sm:col-span-2">
                                                    <span class="text-zinc-500 dark:text-zinc-400">Transaction ID:</span>
                                                    <span class="font-mono text-xs text-zinc-900 dark:text-zinc-100 ml-1">{{ $payment->transaction_id }}</span>
                                                </div>
                                            @endif

                                            @if($payment->billplz_bill_id)
                                                <div class="sm:col-span-2">
                                                    <span class="text-zinc-500 dark:text-zinc-400">Billplz Bill ID:</span>
                                                    <span class="font-mono text-xs text-zinc-900 dark:text-zinc-100 ml-1">{{ $payment->billplz_bill_id }}</span>
                                                </div>
                                            @endif

                                            @if($payment->completed_at)
                                                <div class="sm:col-span-2">
                                                    <span class="text-zinc-500 dark:text-zinc-400">Completed At:</span>
                                                    <span class="font-medium text-zinc-900 dark:text-zinc-100 ml-1">{{ $payment->completed_at->format('d M Y, h:i A') }}</span>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Billplz URL -->
                                        @if($payment->billplz_url)
                                            <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Payment URL:</p>
                                                <a href="{{ $payment->billplz_url }}" target="_blank" class="text-xs text-blue-600 dark:text-blue-400 hover:underline break-all">
                                                    {{ $payment->billplz_url }}
                                                </a>
                                            </div>
                                        @endif

                                        <!-- Payment Response (Collapsible) -->
                                        @if($payment->payment_response && is_array($payment->payment_response) && count($payment->payment_response) > 0)
                                            <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                                                <details class="group">
                                                    <summary class="text-xs font-medium text-zinc-700 dark:text-zinc-300 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 flex items-center gap-1">
                                                        <flux:icon.chevron-right class="size-3 group-open:rotate-90 transition-transform" />
                                                        View Payment Response
                                                    </summary>
                                                    <div class="mt-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-2 bg-zinc-50 dark:bg-zinc-800/50 max-h-40 overflow-y-auto">
                                                        <pre class="text-xs text-zinc-700 dark:text-zinc-300 font-mono whitespace-pre-wrap break-all">{{ json_encode($payment->payment_response, JSON_PRETTY_PRINT) }}</pre>
                                                    </div>
                                                </details>
                                            </div>
                                        @endif
                                    </div>
                                </flux:card>
                            @endforeach
                        </div>
                    </div>
                @else
                    <!-- No Payment Record -->
                    <flux:card class="p-8 text-center bg-zinc-50 dark:bg-zinc-800/50">
                        <div class="flex flex-col items-center gap-3">
                            <div class="rounded-full bg-zinc-200 dark:bg-zinc-700 p-4">
                                <flux:icon.x-circle class="size-8 text-zinc-500 dark:text-zinc-400" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">No Payment Record</h3>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    This submission does not have a payment record yet.
                                </p>
                            </div>
                        </div>
                    </flux:card>
                @endif

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closePaymentLog" variant="ghost">Close</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
