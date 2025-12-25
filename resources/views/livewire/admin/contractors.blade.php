<div class="flex h-full w-full flex-1 flex-col gap-6">
    <style>
        .scrollbar-hide {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;  /* Chrome, Safari and Opera */
        }
    </style>

    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Contractors</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Manage and view all contractor accounts</p>
        </div>
    </div>

    <!-- Statistics Summary -->
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Contractors</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_contractors'] ?? 0 }}</p>
                </div>
                <flux:icon.users class="size-8 text-blue-600 dark:text-blue-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Active Contractors</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['active_contractors'] ?? 0 }}</p>
                </div>
                <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">With Pending Payments</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['contractors_with_pending'] ?? 0 }}</p>
                </div>
                <flux:icon.exclamation-triangle class="size-8 text-orange-600 dark:text-orange-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Outstanding</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">RM {{ number_format($stats['total_outstanding'] ?? 0, 2) }}</p>
                </div>
                <flux:icon.exclamation-circle class="size-8 text-red-600 dark:text-red-400" />
            </div>
        </flux:card>
    </div>

    <!-- Contractors Table -->
    <flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Contractors</h2>
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
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <flux:input
                        wire:model.live="search"
                        placeholder="Search by name, CLAB, email, phone..."
                        icon="magnifying-glass"
                        size="sm"
                    />
                </div>
                <div>
                    <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Filter by Status" size="sm">
                        <flux:select.option value="">All Contractors</flux:select.option>
                        <flux:select.option value="active">Active (Last 3 months)</flux:select.option>
                        <flux:select.option value="inactive">Inactive</flux:select.option>
                        <flux:select.option value="with_pending">With Pending Payments</flux:select.option>
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

        <div class="overflow-x-hidden">
            <div class="overflow-x-auto scrollbar-hide">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'contractor_clab_no'" :direction="$sortDirection" wire:click="sortByColumn('contractor_clab_no')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">CLAB No</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sortByColumn('name')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Company Name</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Contact Info</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'person_in_charge'" :direction="$sortDirection" wire:click="sortByColumn('person_in_charge')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Person in Charge</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Submissions</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Pending</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Total Paid</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">Outstanding</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                </flux:table.columns>

            <flux:table.rows>
                @forelse($contractors as $index => $contractor)
                    <flux:table.rows :key="$contractor->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $pagination['from'] + $index }}</flux:table.cell>

                        <flux:table.cell variant="strong" class="whitespace-nowrap">
                            {{ $contractor->contractor_clab_no }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            <div class="max-w-[200px] truncate">{{ $contractor->name }}</div>
                        </flux:table.cell>

                        <flux:table.cell class="min-w-[200px]">
                            <div class="space-y-1">
                                <div class="flex items-center gap-1 text-xs text-zinc-600 dark:text-zinc-400">
                                    <flux:icon.envelope class="size-3 flex-shrink-0" />
                                    @php
                                        // Extract first email if multiple emails separated by comma or semicolon
                                        $firstEmail = trim(preg_split('/[,;]/', $contractor->email)[0]);
                                    @endphp
                                    <span class="truncate">{{ $firstEmail }}</span>
                                </div>
                                @if($contractor->phone)
                                <div class="flex items-center gap-1 text-xs text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                    <flux:icon.phone class="size-3 flex-shrink-0" />
                                    <span>{{ $contractor->phone }}</span>
                                </div>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            <div class="max-w-[150px] truncate">{{ $contractor->person_in_charge ?? '-' }}</div>
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="whitespace-nowrap">
                            {{ $contractor->total_submissions }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="whitespace-nowrap">
                            {{ $contractor->pending_payments }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="whitespace-nowrap">
                            <span class="{{ $contractor->total_paid > 0 ? 'text-red-600 dark:text-green-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                RM {{ number_format($contractor->total_paid, 2) }}
                            </span>
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="whitespace-nowrap">
                            <span class="{{ $contractor->total_outstanding > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                RM {{ number_format($contractor->total_outstanding, 2) }}
                            </span>
                        </flux:table.cell>

                        <flux:table.cell class="whitespace-nowrap">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" href="{{ route('contractors.detail', $contractor->contractor_clab_no) }}">View Details</flux:menu.item>
                                    <flux:menu.item icon="document-text" href="{{ route('payroll') }}?contractor={{ $contractor->contractor_clab_no }}">View Submissions</flux:menu.item>
                                    <flux:menu.item icon="document" href="{{ route('invoices') }}?contractor={{ $contractor->contractor_clab_no }}">View Invoices</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.rows>
                @empty
                    <flux:table.rows>
                        <flux:table.cell variant="strong" colspan="10" class="text-center">
                            @if($search || $statusFilter)
                                No contractors found matching your filters.
                            @else
                                No contractors found.
                            @endif
                        </flux:table.cell>
                    </flux:table.rows>
                @endforelse
            </flux:table.rows>
        </flux:table>
            </div>
        </div>

        <!-- Pagination -->
        @if($pagination['total'] > 0)
            <div class="mt-4 flex items-center justify-between border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Showing {{ $pagination['from'] }} to {{ $pagination['to'] }} of {{ $pagination['total'] }} results
                </p>
                <div class="flex items-center gap-2">
                    @if($pagination['current_page'] > 1)
                        <flux:button variant="ghost" size="sm" wire:click="$set('page', {{ $pagination['current_page'] - 1 }})">Previous</flux:button>
                    @else
                        <flux:button variant="ghost" size="sm" disabled>Previous</flux:button>
                    @endif

                    @for($i = 1; $i <= $pagination['last_page']; $i++)
                        @if($i == $pagination['current_page'])
                            <flux:button variant="primary" size="xs">{{ $i }}</flux:button>
                        @else
                            <flux:button variant="ghost" size="xs" wire:click="$set('page', {{ $i }})">{{ $i }}</flux:button>
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
</div>
