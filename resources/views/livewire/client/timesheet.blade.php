<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Timesheet Management</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Submit monthly payroll with overtime hours</p>
        </div>
    </div>


    <!-- Sequential Payroll Queue Notice -->
    @if($isBlocked && count($blockReasons) > 0)
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg border-2 border-orange-200 dark:border-orange-800">
            <div class="flex items-start gap-3">
                <flux:icon.queue-list class="size-6 text-orange-600 dark:text-orange-400 flex-shrink-0 mt-0.5" />
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Complete Payroll in Order</h3>
                        <flux:badge color="zinc" size="sm">{{ $totalOutstandingCount }} {{ \Str::plural('month', $totalOutstandingCount) }} remaining</flux:badge>
                    </div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        {{ $blockReasons[0]['message'] }}
                    </p>

                    <div class="mt-4">
                        <flux:button
                            variant="primary"
                            size="sm"
                            href="{{ $blockReasons[0]['redirect_url'] }}"
                            wire:navigate
                            class="w-full sm:w-auto"
                        >
                            {{ $blockReasons[0]['action_text'] }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Statistics Cards (only show for current month and when not blocked) -->
    @if(!$targetMonth && !$targetYear && !$isBlocked)
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Submissions</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_submissions'] }}</p>
                </div>
                <flux:icon.document-text class="size-8 text-blue-600 dark:text-blue-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Paid</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['paid_submissions'] }}</p>
                </div>
                <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Pending Payment</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['pending_submissions'] }}</p>
                </div>
                <flux:icon.clock class="size-8 text-orange-600 dark:text-orange-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Unsubmitted Workers</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['unsubmitted_workers'] }}</p>
                </div>
                <flux:icon.users class="size-8 text-purple-600 dark:text-purple-400" />
            </div>
        </flux:card>
    </div>
    @endif

    @if(!$errorMessage && !$isBlocked && !$targetMonth && !$targetYear)
    <!-- Current Month Info -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $period['month_name'] }} {{ $period['year'] }} Payroll</h3>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                    Payment deadline: <span class="font-semibold {{ $period['days_until_deadline'] < 7 ? 'text-red-600 dark:text-red-400' : 'text-orange-600 dark:text-orange-400' }}">
                        {{ $period['deadline']->format('F d, Y') }} ({{ floor($period['days_until_deadline']) }} days remaining)
                    </span>
                </p>
            </div>
            @if($currentSubmission->status === 'draft')
                <flux:badge color="zinc" size="lg">Draft</flux:badge>
            @elseif($currentSubmission->status === 'pending_payment')
                <flux:badge color="orange" size="lg">Pending Payment</flux:badge>
            @elseif($currentSubmission->status === 'paid')
                <flux:badge color="green" size="lg">Paid</flux:badge>
            @elseif($currentSubmission->status === 'overdue')
                <flux:badge color="red" size="lg">Overdue</flux:badge>
            @endif
        </div>
    </flux:card>
    @endif

    <!-- Payroll Entry Form -->
    @if(!$errorMessage && !$isBlocked)
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $period['month_name'] }} {{ $period['year'] }} - Worker Hours & Overtime</h2>
        </div>

        @if(count($workers) > 0)
            <!-- Selection Controls -->
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ count($selectedWorkers) }} of {{ count($workers) }} workers selected
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 w-10">
                                <input
                                    type="checkbox"
                                    wire:click="toggleAllWorkers"
                                    @if(count($selectedWorkers) === count($workers) && count($workers) > 0) checked @endif
                                    class="size-4 rounded border-zinc-300 dark:border-zinc-700"
                                />
                            </th>
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 w-[20%]">Worker</th>
                            <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400 w-[120px]" title="Basic Salary">Basic Salary</th>
                            <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400 w-[100px]" title="OT Normal Hours">OT Normal (hrs)</th>
                            <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400 w-[100px]" title="OT Rest Day Hours">OT Rest (hrs)</th>
                            <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400 w-[100px]" title="OT Public Holiday Hours">OT Public (hrs)</th>
                            <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400 w-[140px]" title="Advances & Deductions">Advances/Deductions</th>
                            <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400 w-[100px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($workers as $index => $worker)
                        <tr wire:key="worker-{{ $worker['worker_id'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ !in_array($worker['worker_id'], $selectedWorkers) ? 'opacity-50' : '' }}">
                            <td class="py-3">
                                <input
                                    type="checkbox"
                                    value="{{ $worker['worker_id'] }}"
                                    wire:model.live="selectedWorkers"
                                    {{ $isBlocked ? 'disabled' : '' }}
                                    class="size-4 rounded border-zinc-300 dark:border-zinc-700"
                                />
                            </td>
                            <td class="py-3 pr-4">
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="sm" name="{{ $worker['worker_name'] }}" />
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $worker['worker_name'] }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $worker['worker_id'] }} · {{ $worker['worker_passport'] }}</div>
                                        @if($worker['contract_ended'] ?? false)
                                            <div class="text-xs text-orange-600 dark:text-orange-400">Contract Ended</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <!-- Basic Salary -->
                            <td class="py-3 text-center">
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($worker['basic_salary'], 2) }}</span>
                            </td>

                            <!-- OT Normal Hours -->
                            <td class="py-3 text-center">
                                <span class="text-sm {{ ($worker['ot_normal_hours'] ?? 0) > 0 ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-600' }}">
                                    {{ ($worker['ot_normal_hours'] ?? 0) > 0 ? number_format($worker['ot_normal_hours'], 1) . ' hrs' : '-' }}
                                </span>
                            </td>

                            <!-- OT Rest Day Hours -->
                            <td class="py-3 text-center">
                                <span class="text-sm {{ ($worker['ot_rest_hours'] ?? 0) > 0 ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-600' }}">
                                    {{ ($worker['ot_rest_hours'] ?? 0) > 0 ? number_format($worker['ot_rest_hours'], 1) . ' hrs' : '-' }}
                                </span>
                            </td>

                            <!-- OT Public Holiday Hours -->
                            <td class="py-3 text-center">
                                <span class="text-sm {{ ($worker['ot_public_hours'] ?? 0) > 0 ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-600' }}">
                                    {{ ($worker['ot_public_hours'] ?? 0) > 0 ? number_format($worker['ot_public_hours'], 1) . ' hrs' : '-' }}
                                </span>
                            </td>

                            <!-- Advances/Deductions -->
                            <td class="py-3 text-center">
                                @php
                                    $transactions = $worker['transactions'] ?? [];
                                    $totalTransactions = collect($transactions)->sum('amount');
                                @endphp
                                <div class="flex items-center justify-center gap-1">
                                    @if($totalTransactions > 0)
                                        <span class="text-sm text-red-600 dark:text-red-400">-RM {{ number_format($totalTransactions, 2) }}</span>
                                    @else
                                        <span class="text-sm text-zinc-400 dark:text-zinc-600">-</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-2">
                                <div class="flex items-center justify-center gap-2">
                                    <flux:button
                                        wire:click="openOTModal({{ $index }})"
                                        variant="filled"
                                        size="sm"
                                        :disabled="$isBlocked"
                                    >
                                        Overtime
                                    </flux:button>
                                    <flux:button
                                        wire:click="openTransactionModal({{ $index }})"
                                        variant="filled"
                                        size="sm"
                                        :disabled="$isBlocked"
                                    >
                                        Manage
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-end items-center gap-2">
                <flux:button wire:click="saveDraft" variant="filled" :disabled="$isBlocked">
                    Save as Draft
                </flux:button>
                <flux:button wire:click="submitForPayment" variant="primary" :disabled="$isBlocked">
                    Submit for Admin Review
                </flux:button>
            </div>
        @else
            <!-- No Workers Available Message -->
            <div class="py-12 text-center">
                <flux:icon.users class="mx-auto size-7 text-zinc-400 dark:text-zinc-600 mb-4" />
                <p class="text-md font-medium text-zinc-900 dark:text-zinc-100 mb-2">No Workers Available</p>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    All workers have already been submitted for this month's payroll.
                </p>
            </div>
        @endif
    </flux:card>

    <!-- Submission History -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Recent Submissions</h2>
            <div class="flex gap-2 hidden">
                <flux:button variant="filled" size="sm">
                    <flux:icon.arrow-down-tray class="size-4" />
                    Export
                </flux:button>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Period</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Submitted Date</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Grand Total</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Penalty</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($recentSubmissions as $submission)
                    <flux:table.rows :key="$submission->id">
                        <flux:table.cell>{{ $loop->iteration }}</flux:table.cell>

                        <flux:table.cell variant="strong">{{ $submission->month_year }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $submission->submitted_at ? $submission->submitted_at->format('M d, Y') : 'Not submitted' }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">{{ $submission->total_workers }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            @if($submission->status === 'draft')
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">Draft</span>
                            @elseif($submission->status === 'submitted')
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">Pending Admin Review</span>
                            @elseif($submission->hasAdminReview())
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    RM {{ number_format($submission->admin_final_amount, 2) }}
                                </div>
                            @else
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            @if($submission->has_penalty)
                                <span class="text-red-600 dark:text-red-400">+ RM {{ number_format($submission->penalty_amount, 2) }}</span>
                            @else
                                -
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($submission->status === 'draft')
                                <flux:badge color="zinc" size="sm" inset="top bottom">Draft</flux:badge>
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
                                    @if($submission->status !== 'draft')
                                        <flux:menu.item icon="eye" icon:variant="outline" href="{{ route('timesheet.show', $submission->id) }}">View Details</flux:menu.item>
                                    @endif
                                    @if($submission->hasAdminReview())
                                        <flux:menu.item icon="document-text" icon:variant="outline" href="{{ route('invoices.show', $submission->id) }}">View Invoice</flux:menu.item>
                                    @endif

                                    @if($submission->status === 'draft')
                                        <flux:menu.separator />
                                        <flux:menu.item icon="pencil" icon:variant="outline" href="{{ route('timesheet.edit', $submission->id) }}">Edit Draft</flux:menu.item>
                                        <flux:menu.item icon="paper-airplane" icon:variant="outline" wire:click="submitDraftForPayment({{ $submission->id }})">Submit for Admin Review</flux:menu.item>
                                    @endif
                                    @if($submission->status === 'pending_payment' || $submission->status === 'overdue')
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
            </flux:table.rows>
        </flux:table>
    </flux:card>
    @endif

    <!-- Transaction Management Modal -->
    @if($showTransactionModal && $currentWorkerIndex !== null)
        <flux:modal wire:model="showTransactionModal" class="min-w-[600px]">
            <div class="space-y-6">
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Manage Transactions
                    </h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        Worker: {{ $workers[$currentWorkerIndex]['worker_name'] ?? 'Unknown' }}
                    </p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-0">
                        Passport: {{ $workers[$currentWorkerIndex]['worker_passport'] ?? 'Unknown' }}
                    </p>                    
                </div>

                <!-- Add New Transaction Form -->
                <flux:card class="p-4 bg-zinc-50 dark:bg-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Add New Transaction</h3>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <flux:select wire:model.live="newTransactionType" variant="listbox" label="Type">
                                <flux:select.option value="advance_payment">Advance Payment</flux:select.option>
                                <flux:select.option value="deduction">Deduction</flux:select.option>
                            </flux:select>
                            @error('newTransactionType') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <flux:input wire:model.live="newTransactionAmount" type="number" step="0.01" min="0" label="Amount (RM)" placeholder="0.00" />
                            @error('newTransactionAmount') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <flux:textarea wire:model.live="newTransactionRemarks" label="Remarks" placeholder="Enter reason for this transaction..." rows="2" />
                            @error('newTransactionRemarks') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <flux:button wire:click="addTransaction" variant="primary" size="sm">
                                Add Transaction
                            </flux:button>
                        </div>
                    </div>
                </flux:card>

                <!-- Transaction List -->
                @php
                    $currentTransactions = $currentWorkerIndex !== null ? ($workers[$currentWorkerIndex]['transactions'] ?? []) : [];
                @endphp
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Transactions ({{ count($currentTransactions) }})</h3>

                    @if(count($currentTransactions) > 0)
                        <div class="space-y-2 max-h-64 overflow-y-auto" wire:key="transaction-list-{{ md5(json_encode($currentTransactions)) }}">
                            @foreach($currentTransactions as $index => $transaction)
                                <div class="flex items-start justify-between p-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg" wire:key="transaction-{{ $index }}-{{ $transaction['amount'] }}">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-zinc-900 dark:text-zinc-100">-RM {{ number_format($transaction['amount'], 2) }}</span>
                                            @if($transaction['type'] === 'advance_payment')
                                                <flux:badge color="orange" size="xs">Advance Payment</flux:badge>
                                            @else
                                                <flux:badge color="red" size="xs">Deduction</flux:badge>
                                            @endif
                                        </div>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ $transaction['remarks'] }}</p>
                                    </div>
                                    <flux:button wire:click="removeTransaction({{ $index }})" variant="ghost" size="sm" class="text-red-600 dark:text-red-400">
                                        <flux:icon.trash class="size-4" />
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>

                        <!-- Summary -->
                        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Impact on Worker's Salary</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-zinc-600 dark:text-zinc-400">Total Advance Payment (Deducted):</p>
                                    <p class="text-lg font-bold text-orange-600 dark:text-orange-400">
                                        -RM {{ number_format(collect($currentTransactions)->where('type', 'advance_payment')->sum('amount'), 2) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-zinc-600 dark:text-zinc-400">Total Deduction:</p>
                                    <p class="text-lg font-bold text-red-600 dark:text-red-400">
                                        -RM {{ number_format(collect($currentTransactions)->where('type', 'deduction')->sum('amount'), 2) }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-red-200 dark:border-red-700">
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                    <strong>Note:</strong> Both advance payments and deductions will be subtracted from the worker's basic salary.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            <flux:icon.banknotes class="size-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                            <p class="text-sm">No transactions added yet</p>
                        </div>
                    @endif
                </div>

                <!-- Modal Actions -->
                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closeTransactionModal" variant="ghost">Cancel</flux:button>
                    <flux:button wire:click="saveTransactions" variant="primary">Save Transactions</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <!-- OT Management Modal -->
    @if($showOTModal && $currentWorkerIndex !== null)
        <flux:modal wire:model="showOTModal" class="min-w-[600px]">
            <div class="space-y-6">
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Manage Overtime Hours
                    </h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        Worker: {{ $workers[$currentWorkerIndex]['worker_name'] ?? 'Unknown' }}
                    </p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-0">
                        Passport: {{ $workers[$currentWorkerIndex]['worker_passport'] ?? 'Unknown' }}
                    </p>
                </div>

                <!-- OT Input Form -->
                <flux:card class="p-4 bg-zinc-50 dark:bg-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Enter Overtime Hours</h3>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <flux:input
                                wire:model.live="otNormalHours"
                                type="number"
                                step="0.5"
                                min="0"
                                label="Normal Day OT (hours)"
                                placeholder="0.0"
                            />
                        </div>

                        <div>
                            <flux:input
                                wire:model.live="otRestHours"
                                type="number"
                                step="0.5"
                                min="0"
                                label="Rest Day OT (hours)"
                                placeholder="0.0"
                            />
                        </div>

                        <div>
                            <flux:input
                                wire:model.live="otPublicHours"
                                type="number"
                                step="0.5"
                                min="0"
                                label="Public Holiday OT (hours)"
                                placeholder="0.0"
                            />
                        </div>
                    </div>
                </flux:card>

                <!-- OT Summary -->
                <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">OT Calculation Summary</h4>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-zinc-600 dark:text-zinc-400">Normal Day OT:</p>
                            <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                RM {{ number_format(($otNormalHours ?? 0) * 12.26, 2) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-zinc-600 dark:text-zinc-400">Rest Day OT:</p>
                            <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                RM {{ number_format(($otRestHours ?? 0) * 16.34, 2) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-zinc-600 dark:text-zinc-400">Public Holiday OT:</p>
                            <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                RM {{ number_format(($otPublicHours ?? 0) * 24.51, 2) }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-green-200 dark:border-green-700">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Total OT Pay:</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                RM {{ number_format((($otNormalHours ?? 0) * 12.26) + (($otRestHours ?? 0) * 16.34) + (($otPublicHours ?? 0) * 24.51), 2) }}
                            </p>
                        </div>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-2">
                            <strong>Note:</strong> This OT amount will be added to the worker's salary for this period.
                        </p>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closeOTModal" variant="ghost">Cancel</flux:button>
                    <flux:button wire:click="saveOT" variant="primary">Save</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
