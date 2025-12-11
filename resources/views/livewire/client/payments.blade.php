<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Payment History</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">View your payment records and transaction history</p>
        </div>
    </div>

    @if(isset($error))
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800">
            <p class="text-sm text-red-800 dark:text-red-200">{{ $error }}</p>
        </div>
    @endif

    @if(!isset($error))
    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">This Month</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        RM {{ number_format($stats['this_month_amount'], 2) }}
                    </p>
                </div>
                <div class="rounded-full bg-purple-100 dark:bg-purple-900/30 p-3">
                    <flux:icon.wallet class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
            @if($stats['this_month_status'] === 'paid')
                <flux:badge color="green" size="sm">Paid</flux:badge>
            @elseif($stats['this_month_status'] === 'pending_payment')
                <flux:badge color="orange" size="sm">Pending</flux:badge>
            @elseif($stats['this_month_status'] === 'overdue')
                <flux:badge color="red" size="sm">Overdue</flux:badge>
            @else
                <flux:badge color="zinc" size="sm">No Data</flux:badge>
            @endif
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Last Month</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        RM {{ number_format($stats['last_month_amount'], 2) }}
                    </p>
                </div>
                <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                    <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
            @if($stats['last_month_amount'] > 0)
                <flux:badge color="green" size="sm">Paid</flux:badge>
            @else
                <flux:badge color="zinc" size="sm">No Data</flux:badge>
            @endif
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">This Year</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        RM {{ number_format($stats['this_year_amount'], 2) }}
                    </p>
                </div>
                <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                    <flux:icon.chart-bar class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $stats['this_year_count'] }} payments</p>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Avg Monthly</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        RM {{ number_format($stats['avg_monthly'], 2) }}
                    </p>
                </div>
                <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3">
                    <flux:icon.calculator class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
            <p class="text-xs text-zinc-600 dark:text-zinc-400">Based on {{ now()->year }}</p>
        </flux:card>
    </div>

    <!-- Search and Filters -->
    <div>
        <button type="button" onclick="togglePaymentFilters()" class="w-full flex items-center justify-between p-1 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-3">
                    <flux:icon.funnel class="size-5 text-zinc-600 dark:text-zinc-400" />
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Search & Filters</h3>
                </div>
                @if($search)
                    <flux:badge color="zinc" size="sm">Search: "{{ Str::limit($search, 20) }}"</flux:badge>
                @endif
                @if($statusFilter !== 'all')
                    <flux:badge color="zinc" size="sm">{{ ucfirst($statusFilter) }}</flux:badge>
                @endif
            </div>
            <flux:icon.chevron-down id="payment-filter-chevron" class="size-5 text-zinc-600 dark:text-zinc-400 transition-transform duration-200" />
        </button>

        <div id="payment-filter-content" class="border-t border-zinc-200 dark:border-zinc-700 mt-2" style="display: none;">
            <div class="p-6">

                <!-- Filters Row -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <flux:input
                                wire:model.live.debounce.500ms="search"
                                placeholder="Search by transaction ID or period"
                                icon="magnifying-glass"
                                size="sm"
                                label="Search bar"
                            />
                        </div>
                        <div>
                            <flux:select variant="listbox" wire:model.live="statusFilter" size="sm" label="Status">
                                <flux:select.option value="all">All Status</flux:select.option>
                                <flux:select.option value="completed">Paid</flux:select.option>
                                <flux:select.option value="pending">Pending</flux:select.option>
                                <flux:select.option value="failed">Failed</flux:select.option>
                            </flux:select>
                        </div>
                        <div>
                            <flux:select variant="listbox" wire:model.live="year" size="sm" label="Year">
                                @foreach($availableYears as $yearOption)
                                    <flux:select.option value="{{ $yearOption }}">{{ $yearOption }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>

                    <div class="flex gap-2 sm:flex-shrink-0">
                        <flux:button wire:click="resetFilters" type="button" variant="filled" size="sm">
                            Clear
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePaymentFilters() {
            const content = document.getElementById('payment-filter-content');
            const chevron = document.getElementById('payment-filter-chevron');

            if (content.style.display === 'none') {
                content.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                content.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        // Auto-expand if filters are active
        document.addEventListener('DOMContentLoaded', function() {
            const hasActiveFilters = {{ ($search || $statusFilter !== 'all') ? 'true' : 'false' }};

            if (hasActiveFilters) {
                togglePaymentFilters();
            }
        });
    </script>

    <!-- Payment History Table -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Payments</h2>
            <!-- <div class="flex gap-2">
                <flux:button variant="filled" size="sm">
                    <flux:icon.arrow-down-tray class="size-4" />
                    Export
                </flux:button>
            </div> -->
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'transaction_id'" :direction="$sortDirection" wire:click="sortByColumn('transaction_id')" align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'transaction_id'" :direction="$sortDirection" wire:click="sortByColumn('transaction_id')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Transaction ID</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'period'" :direction="$sortDirection" wire:click="sortByColumn('period')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'amount'" :direction="$sortDirection" wire:click="sortByColumn('amount')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Amount</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'workers'" :direction="$sortDirection" wire:click="sortByColumn('workers')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'payment_date'" :direction="$sortDirection" wire:click="sortByColumn('payment_date')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Payment Date</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'method'" :direction="$sortDirection" wire:click="sortByColumn('method')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Method</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sortByColumn('status')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($payments as $payment)
                    <flux:table.rows :key="$payment->id">
                        <flux:table.cell>{{ $loop->iteration }}</flux:table.cell>

                        <flux:table.cell variant="strong" class="font-mono">
                            {{ $payment->transaction_id ?? $payment->billplz_bill_id ?? 'N/A' }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">{{ $payment->submission->month_year }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            RM {{ number_format($payment->amount, 2) }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $payment->submission->total_workers }} workers
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $payment->completed_at ? $payment->completed_at->format('M d, Y') : 'Pending' }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ ucfirst($payment->payment_method) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($payment->status === 'completed')
                                <flux:badge color="green" size="sm" inset="top bottom">Paid</flux:badge>
                            @elseif($payment->status === 'pending')
                                <flux:badge color="orange" size="sm" inset="top bottom">Pending</flux:badge>
                            @elseif($payment->status === 'failed')
                                <flux:badge color="red" size="sm" inset="top bottom">Failed</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" href="{{ route('client.timesheet.show', $payment->submission->id) }}">View Details</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.rows>
                @empty
                    <flux:table.rows>
                        <flux:table.cell variant="strong" colspan="9" class="text-center">
                            <div class="py-5">
                                No payment records found for {{ $year }}
                            </div>
                        </flux:table.cell>
                    </flux:table.rows>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <!-- Pagination -->
        @if($pagination['total'] > 0)
            <div class="mt-4 flex items-center justify-between border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Showing {{ $pagination['from'] }} to {{ $pagination['to'] }} of {{ $pagination['total'] }} results
                </p>
                <div class="flex gap-2">
                    @if($pagination['current_page'] > 1)
                        <flux:button variant="ghost" size="sm" wire:click="$set('page', {{ $pagination['current_page'] - 1 }})">Previous</flux:button>
                    @else
                        <flux:button variant="ghost" size="sm" disabled>Previous</flux:button>
                    @endif

                    @for($i = 1; $i <= $pagination['last_page']; $i++)
                        @if($i == $pagination['current_page'])
                            <flux:button variant="primary" size="sm">{{ $i }}</flux:button>
                        @else
                            <flux:button variant="ghost" size="sm" wire:click="$set('page', {{ $i }})">{{ $i }}</flux:button>
                        @endif
                    @endfor

                    @if($pagination['current_page'] < $pagination['last_page'])
                        <flux:button variant="ghost" size="sm" wire:click="$set('page', {{ $pagination['current_page'] + 1 }})">Next</flux:button>
                    @else
                        <flux:button variant="ghost" size="sm" disabled>Next</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:card>
    @endif
</div>
