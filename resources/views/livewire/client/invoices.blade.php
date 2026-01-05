<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Invoices</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">View and manage your payroll invoices</p>
        </div>
        <x-tutorial-button page="invoices" />
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-4 border border-green-200 dark:border-green-800">
            <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800">
            <p class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</p>
        </div>
    @endif

    @if(isset($error))
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800">
            <p class="text-sm text-red-800 dark:text-red-200">{{ $error }}</p>
        </div>
    @endif

    @if(!isset($error))
    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-3">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Pending Invoices</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['pending_invoices'] }}</p>
                </div>
                <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3">
                    <flux:icon.clock class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Paid Invoices</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['paid_invoices'] }}</p>
                </div>
                <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                    <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Invoiced</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['total_invoiced'], 2) }}</p>
                </div>
                <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                    <flux:icon.document-text class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Search and Filters -->
    <div>
        <button type="button" onclick="toggleInvoiceFilters()" class="w-full flex items-center justify-between p-1 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
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
            <flux:icon.chevron-down id="invoice-filter-chevron" class="size-5 text-zinc-600 dark:text-zinc-400 transition-transform duration-200" />
        </button>

        <div id="invoice-filter-content" class="border-t border-zinc-200 dark:border-zinc-700 mt-2" style="display: none;">
            <div class="p-6">

                <!-- Filters Row -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <flux:input
                                wire:model.live.debounce.500ms="search"
                                placeholder="Search by invoice # or period"
                                icon="magnifying-glass"
                                size="sm"
                                label="Search bar"
                            />
                        </div>
                        <div>
                            <flux:select variant="listbox" wire:model.live="statusFilter" size="sm" label="Status">
                                <flux:select.option value="all">All Status</flux:select.option>
                                <flux:select.option value="draft">Draft</flux:select.option>
                                <flux:select.option value="pending_payment">Pending</flux:select.option>
                                <flux:select.option value="paid">Paid</flux:select.option>
                                <flux:select.option value="overdue">Overdue</flux:select.option>
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
        function toggleInvoiceFilters() {
            const content = document.getElementById('invoice-filter-content');
            const chevron = document.getElementById('invoice-filter-chevron');

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
                toggleInvoiceFilters();
            }
        });
    </script>

    <!-- Invoices Table -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Invoices</h2>
            <!-- <div class="flex gap-2">
                <flux:button variant="ghost" size="sm">
                    <flux:icon.arrow-down-tray class="size-4" />
                    Export
                </flux:button>
            </div> -->
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'invoice_number'" :direction="$sortDirection" wire:click="sortByColumn('invoice_number')" align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'invoice_number'" :direction="$sortDirection" wire:click="sortByColumn('invoice_number')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Invoice #</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'period'" :direction="$sortDirection" wire:click="sortByColumn('period')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'workers'" :direction="$sortDirection" wire:click="sortByColumn('workers')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'grand_total'" :direction="$sortDirection" wire:click="sortByColumn('grand_total')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Grand Total</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'issue_date'" :direction="$sortDirection" wire:click="sortByColumn('issue_date')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Issue Date</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'due_date'" :direction="$sortDirection" wire:click="sortByColumn('due_date')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Due Date</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sortByColumn('status')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($invoices as $invoice)
                    <flux:table.rows
                        :key="$invoice->id"
                        id="invoice-{{ $invoice->id }}"
                        class="{{ $highlightId == $invoice->id ? 'animate-pulse bg-green-100 dark:bg-green-900/30' : '' }}"
                    >
                        <flux:table.cell>{{ $loop->iteration }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            INV-{{ str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">{{ $invoice->month_year }}</flux:table.cell>

                        <flux:table.cell variant="strong">{{ $invoice->total_workers }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            @if(in_array($invoice->status, ['draft', 'submitted']))
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">In Process</span>
                            @elseif($invoice->hasAdminReview())
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    RM {{ number_format($invoice->total_due, 2) }}
                                </div>
                            @else
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $invoice->submitted_at ? $invoice->submitted_at->format('M d, Y') : '-' }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            <span class="{{ now()->gt($invoice->payment_deadline) && $invoice->status !== 'paid' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                {{ $invoice->payment_deadline->format('M d, Y') }}
                            </span>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($invoice->status === 'draft')
                                <flux:badge color="zinc" size="sm" inset="top bottom">Draft</flux:badge>
                            @elseif($invoice->status === 'submitted')
                                <flux:badge color="blue" size="sm" inset="top bottom">In Process</flux:badge>
                            @elseif($invoice->status === 'approved')
                                <flux:badge color="purple" size="sm" inset="top bottom">Approved</flux:badge>
                            @elseif($invoice->status === 'pending_payment')
                                <flux:badge color="orange" size="sm" inset="top bottom">Pending Payment</flux:badge>
                            @elseif($invoice->status === 'paid')
                                <flux:badge color="green" size="sm" inset="top bottom">Paid</flux:badge>
                            @elseif($invoice->status === 'overdue')
                                <flux:badge color="red" size="sm" inset="top bottom">Overdue</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    @if($invoice->hasAdminReview() && $invoice->status !== 'draft')
                                        <flux:menu.item icon="eye" href="{{ route('invoices.show', $invoice->id) }}">View Invoice</flux:menu.item>
                                        <flux:menu.item icon="document-arrow-down" href="{{ route('invoices.download', $invoice->id) }}">Download Invoice</flux:menu.item>
                                    @endif
                                    @if($invoice->hasBreakdownFile())
                                        <flux:menu.item icon="document-arrow-down" wire:click="downloadBreakdown({{ $invoice->id }})">Download Breakdown</flux:menu.item>
                                    @endif
                                    @if($invoice->status === 'draft')
                                        <flux:menu.separator />
                                        <flux:menu.item icon="paper-airplane" wire:click="finalizeDraft({{ $invoice->id }})">Finalize & Submit</flux:menu.item>
                                    @endif
                                    @if($invoice->status === 'approved' || $invoice->status === 'pending_payment' || $invoice->status === 'overdue')
                                        <flux:menu.separator />
                                        <form method="POST" action="{{ route('client.payment.create', $invoice->id) }}" class="contents">
                                            @csrf
                                            <flux:menu.item icon="credit-card" type="submit">Pay Now</flux:menu.item>
                                        </form>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.rows>
                @empty
                    <flux:table.rows>
                        <flux:table.cell variant="strong" colspan="9" class="text-center">
                            No invoices found for {{ $year }}.
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

    @if($highlightId)
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    const highlightedRow = document.getElementById('invoice-{{ $highlightId }}');
                    if (highlightedRow) {
                        highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

                        // Remove highlight after 3 seconds
                        setTimeout(function() {
                            highlightedRow.classList.remove('animate-pulse', 'bg-green-100', 'dark:bg-green-900/30');
                        }, 3000);
                    }
                }, 100);
            });
        </script>
    @endif
</div>
