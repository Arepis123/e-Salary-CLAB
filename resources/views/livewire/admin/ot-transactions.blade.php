<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">OT & Transactions</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Track client OT and transaction submissions</p>
        </div>
    </div>

    <!-- Statistics Row -->
    <div class="grid gap-4 md:grid-cols-4">
        <!-- OT Card -->
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total OT Hours</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format(($stats['total_weekday_ot_hours'] ?? 0) + ($stats['total_rest_ot_hours'] ?? 0) + ($stats['total_public_ot_hours'] ?? 0), 1) }} hrs</p>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 space-y-0.5">
                        <div>W: {{ number_format($stats['total_weekday_ot_hours'] ?? 0, 1) }}h | R: {{ number_format($stats['total_rest_ot_hours'] ?? 0, 1) }}h | P: {{ number_format($stats['total_public_ot_hours'] ?? 0, 1) }}h</div>
                    </div>
                </div>
                <flux:icon.clock class="size-8 text-blue-600 dark:text-blue-400" />
            </div>
        </flux:card>

        <!-- Deduction Card -->
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Deductions</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">RM {{ number_format(($stats['total_accommodation'] ?? 0) + ($stats['total_advance_payment'] ?? 0) + ($stats['total_deduction'] ?? 0), 2) }}</p>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        @if(($stats['total_npl_days'] ?? 0) > 0)
                            <span>NPL: {{ number_format($stats['total_npl_days'] ?? 0, 1) }} days</span>
                        @endif
                    </div>
                </div>
                <flux:icon.minus-circle class="size-8 text-red-600 dark:text-red-400" />
            </div>
        </flux:card>

        <!-- Earning Card -->
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Earnings</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">RM {{ number_format($stats['total_allowance'] ?? 0, 2) }}</p>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        Allowances
                    </div>
                </div>
                <flux:icon.plus-circle class="size-8 text-green-600 dark:text-green-400" />
            </div>
        </flux:card>

        <!-- Client Submitted Card -->
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Clients Submitted</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['contractors_submitted'] ?? 0 }}</p>
                    @if(($stats['contractors_draft'] ?? 0) > 0)
                        <p class="text-xs text-amber-600 dark:text-amber-400">{{ $stats['contractors_draft'] }} draft</p>
                    @endif
                </div>
                <flux:icon.check-circle class="size-8 text-purple-600 dark:text-purple-400" />
            </div>
        </flux:card>
    </div>

    <!-- Submissions Table -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Client OT & Transaction Submissions</h2>
            <div class="flex gap-2">
                <flux:button variant="ghost" size="sm" icon="funnel" icon-variant="outline" wire:click="toggleFilters">
                    Filter
                </flux:button>
            </div>
        </div>

        <!-- Filters and Search -->
        @if($showFilters)
        <div class="mb-6" x-data x-transition>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                <div>
                    <flux:input
                        wire:model.live="search"
                        placeholder="Search by contractor..."
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
                    <flux:select wire:model.live="selectedPeriod" variant="listbox" placeholder="Select Period" size="sm">
                        <flux:select.option value="">All Periods</flux:select.option>
                        @foreach($availableMonths as $month)
                            <flux:select.option value="{{ $month['value'] }}">{{ $month['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Status" size="sm">
                        <flux:select.option value="">All Statuses</flux:select.option>
                        <flux:select.option value="draft">Draft</flux:select.option>
                        <flux:select.option value="submitted">Submitted</flux:select.option>
                        <flux:select.option value="locked">Locked</flux:select.option>
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model.live="transactionType" variant="listbox" placeholder="Transaction Type" size="sm">
                        <flux:select.option value="">All Types</flux:select.option>
                        <flux:select.option value="accommodation">Accommodation</flux:select.option>
                        <flux:select.option value="advance_payment">Advance Payment</flux:select.option>
                        <flux:select.option value="deduction">Deduction</flux:select.option>
                        <flux:select.option value="npl">NPL (No-Pay Leave)</flux:select.option>
                        <flux:select.option value="allowance">Allowance</flux:select.option>
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
                <flux:table.column sortable :sorted="$sortBy === 'contractor_name'" :direction="$sortDirection" wire:click="sortByColumn('contractor_name')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'entry_month'" :direction="$sortDirection" wire:click="sortByColumn('entry_month')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Entry Period</span></flux:table.column>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Summary</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Transactions</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sortByColumn('status')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'submitted_at'" :direction="$sortDirection" wire:click="sortByColumn('submitted_at')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Submitted</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($submissions as $index => $submission)
                    @php
                        $hasOt = $submission->total_ot_normal > 0 || $submission->total_ot_rest > 0 || $submission->total_ot_public > 0;
                        $hasTransactions = $submission->has_transactions;
                    @endphp
                    <flux:table.row :key="$submission->contractor_clab_no . '-' . $submission->entry_month . '-' . $submission->entry_year">
                        <flux:table.cell align="center">{{ $pagination['from'] + $index }}</flux:table.cell>

                        <flux:table.cell variant="strong" class="max-w-xs truncate">
                            {{ $submission->contractor_name }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ \Carbon\Carbon::create($submission->entry_year, $submission->entry_month)->format('M Y') }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong" align="center">
                            {{ $submission->worker_count }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($hasOt)
                                <div class="text-xs space-y-0.5">
                                    @if($submission->total_ot_normal > 0)
                                        <div><span class="text-blue-600 dark:text-blue-400">W:</span> {{ number_format($submission->total_ot_normal, 1) }}h</div>
                                    @endif
                                    @if($submission->total_ot_rest > 0)
                                        <div><span class="text-purple-600 dark:text-purple-400">R:</span> {{ number_format($submission->total_ot_rest, 1) }}h</div>
                                    @endif
                                    @if($submission->total_ot_public > 0)
                                        <div><span class="text-orange-600 dark:text-orange-400">P:</span> {{ number_format($submission->total_ot_public, 1) }}h</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-zinc-400 dark:text-zinc-500 text-xs">No OT</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($hasTransactions)
                                <div class="text-xs space-y-0.5">
                                    @if($submission->total_accommodation > 0)
                                        <div class="text-amber-600 dark:text-amber-400">Acc: -RM {{ number_format($submission->total_accommodation, 2) }}</div>
                                    @endif
                                    @if($submission->total_advance > 0)
                                        <div class="text-red-600 dark:text-red-400">Adv: -RM {{ number_format($submission->total_advance, 2) }}</div>
                                    @endif
                                    @if($submission->total_deduction > 0)
                                        <div class="text-red-600 dark:text-red-400">Ded: -RM {{ number_format($submission->total_deduction, 2) }}</div>
                                    @endif
                                    @if($submission->total_npl > 0)
                                        <div class="text-purple-600 dark:text-purple-400">NPL: {{ number_format($submission->total_npl, 1) }} days</div>
                                    @endif
                                    @if($submission->total_allowance > 0)
                                        <div class="text-green-600 dark:text-green-400">Alw: +RM {{ number_format($submission->total_allowance, 2) }}</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-zinc-400 dark:text-zinc-500 text-xs">None</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($submission->status === 'locked')
                                <flux:badge color="green" size="sm">Locked</flux:badge>
                            @elseif($submission->status === 'submitted')
                                <flux:badge color="blue" size="sm">Submitted</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">Draft</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            @if($submission->submitted_at)
                                {{ \Carbon\Carbon::parse($submission->submitted_at)->format('d M Y') }}
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" icon="eye" wire:click="openDetail('{{ $submission->contractor_clab_no }}', {{ $submission->entry_month }}, {{ $submission->entry_year }})">
                                View
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell variant="strong" colspan="9" class="text-center">
                            @if($search || $contractor || $transactionType || $selectedPeriod || $statusFilter)
                                No submissions found matching your filters.
                            @else
                                No OT entries have been submitted yet.
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
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

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedContractor)
        <flux:modal wire:model="showDetailModal" class="w-full max-w-5xl">
            <div class="space-y-4 p-4 sm:p-6">
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        OT & Transactions Details
                    </h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        {{ $selectedContractor->name }}
                        @if($selectedEntries->isNotEmpty())
                            - {{ \Carbon\Carbon::create($selectedEntries->first()->entry_year, $selectedEntries->first()->entry_month)->format('F Y') }}
                        @endif
                    </p>
                </div>

                <!-- Contractor Info Card -->
                <flux:card class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <flux:icon.building-office class="size-8 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $selectedContractor->name }}
                            </p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                CLAB: {{ $selectedContractor->contractor_clab_no }} | Workers: {{ $selectedEntries->count() }}
                            </p>
                        </div>
                    </div>
                </flux:card>

                <!-- OT Breakdown -->
                <div class="space-y-3">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Overtime Entries</h3>

                    @php
                        $entriesWithOt = $selectedEntries->filter(function($entry) {
                            return $entry->ot_normal_hours > 0 || $entry->ot_rest_hours > 0 || $entry->ot_public_hours > 0;
                        });
                    @endphp

                    @if($entriesWithOt->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Worker</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Passport</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">Weekday OT</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">Rest Day OT</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">Public Holiday OT</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach($entriesWithOt as $entry)
                                        <tr>
                                            <td class="px-3 py-2 text-zinc-900 dark:text-zinc-100">{{ $entry->worker_name }}</td>
                                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ $entry->worker_passport ?? '-' }}</td>
                                            <td class="px-3 py-2 text-center">
                                                @if($entry->ot_normal_hours > 0)
                                                    <span class="text-blue-600 dark:text-blue-400">{{ number_format($entry->ot_normal_hours, 1) }}h</span>
                                                @else
                                                    <span class="text-zinc-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                @if($entry->ot_rest_hours > 0)
                                                    <span class="text-purple-600 dark:text-purple-400">{{ number_format($entry->ot_rest_hours, 1) }}h</span>
                                                @else
                                                    <span class="text-zinc-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                @if($entry->ot_public_hours > 0)
                                                    <span class="text-orange-600 dark:text-orange-400">{{ number_format($entry->ot_public_hours, 1) }}h</span>
                                                @else
                                                    <span class="text-zinc-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ number_format($entry->ot_normal_hours + $entry->ot_rest_hours + $entry->ot_public_hours, 1) }}h
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-zinc-100 dark:bg-zinc-800/50">
                                    <tr class="font-semibold">
                                        <td class="px-3 py-2 text-zinc-900 dark:text-zinc-100" colspan="2">Total</td>
                                        <td class="px-3 py-2 text-center text-blue-600 dark:text-blue-400">{{ number_format($entriesWithOt->sum('ot_normal_hours'), 1) }}h</td>
                                        <td class="px-3 py-2 text-center text-purple-600 dark:text-purple-400">{{ number_format($entriesWithOt->sum('ot_rest_hours'), 1) }}h</td>
                                        <td class="px-3 py-2 text-center text-orange-600 dark:text-orange-400">{{ number_format($entriesWithOt->sum('ot_public_hours'), 1) }}h</td>
                                        <td class="px-3 py-2 text-center text-zinc-900 dark:text-zinc-100">{{ number_format($entriesWithOt->sum('ot_normal_hours') + $entriesWithOt->sum('ot_rest_hours') + $entriesWithOt->sum('ot_public_hours'), 1) }}h</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <flux:card class="p-4 text-center bg-zinc-50 dark:bg-zinc-800/50">
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No overtime recorded for this submission.</p>
                        </flux:card>
                    @endif
                </div>

                <!-- Transactions Breakdown -->
                <div class="space-y-3">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Transactions</h3>

                    @php
                        $allTransactions = $selectedEntries->flatMap(function($entry) {
                            return $entry->transactions->map(function($t) use ($entry) {
                                $t->worker_name = $entry->worker_name;
                                return $t;
                            });
                        });
                    @endphp

                    @if($allTransactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                                <thead class="bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Worker</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Type</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400">Amount</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach($allTransactions as $transaction)
                                        <tr>
                                            <td class="px-3 py-2 text-zinc-900 dark:text-zinc-100">{{ $transaction->worker_name }}</td>
                                            <td class="px-3 py-2">
                                                @if($transaction->type === 'accommodation')
                                                    <flux:badge color="amber" size="sm">Accommodation</flux:badge>
                                                @elseif($transaction->type === 'advance_payment')
                                                    <flux:badge color="red" size="sm">Advance</flux:badge>
                                                @elseif($transaction->type === 'deduction')
                                                    <flux:badge color="orange" size="sm">Deduction</flux:badge>
                                                @elseif($transaction->type === 'npl')
                                                    <flux:badge color="purple" size="sm">NPL</flux:badge>
                                                @elseif($transaction->type === 'allowance')
                                                    <flux:badge color="green" size="sm">Allowance</flux:badge>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium">
                                                @if($transaction->type === 'npl')
                                                    <span class="text-amber-600 dark:text-amber-400">{{ number_format($transaction->amount, 1) }} days</span>
                                                @elseif($transaction->type === 'allowance')
                                                    <span class="text-green-600 dark:text-green-400">+RM {{ number_format($transaction->amount, 2) }}</span>
                                                @else
                                                    <span class="text-red-600 dark:text-red-400">-RM {{ number_format($transaction->amount, 2) }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400 max-w-xs truncate">{{ $transaction->remarks ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Transaction Summary -->
                        <div class="grid gap-3 sm:grid-cols-5 mt-4">
                            <flux:card class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                                <p class="text-xs text-red-600 dark:text-red-400">Total Advance</p>
                                <p class="text-lg font-bold text-red-600 dark:text-red-400">RM {{ number_format($allTransactions->where('type', 'advance_payment')->sum('amount'), 2) }}</p>
                            </flux:card>
                            <flux:card class="p-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                                <p class="text-xs text-orange-600 dark:text-orange-400">Total Deductions</p>
                                <p class="text-lg font-bold text-orange-600 dark:text-orange-400">RM {{ number_format($allTransactions->where('type', 'deduction')->sum('amount'), 2) }}</p>
                            </flux:card>
                            <flux:card class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                                <p class="text-xs text-amber-600 dark:text-amber-400">Total NPL</p>
                                <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ number_format($allTransactions->where('type', 'npl')->sum('amount'), 1) }} days</p>
                            </flux:card>
                            <flux:card class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-amber-800">
                                <p class="text-xs text-yellow-600 dark:text-yellow-400">Total Accommodations</p>
                                <p class="text-lg font-bold text-yellow-600 dark:text-yellow-400">RM {{ number_format($allTransactions->where('type', 'accommodation')->sum('amount'), 2) }}</p>
                            </flux:card>
                            <flux:card class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                <p class="text-xs text-green-600 dark:text-green-400">Total Allowances</p>
                                <p class="text-lg font-bold text-green-600 dark:text-green-400">RM {{ number_format($allTransactions->where('type', 'allowance')->sum('amount'), 2) }}</p>
                            </flux:card>
                        </div>
                    @else
                        <flux:card class="p-4 text-center bg-zinc-50 dark:bg-zinc-800/50">
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No transactions recorded for this submission.</p>
                        </flux:card>
                    @endif
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closeDetail" variant="filled">Close</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
