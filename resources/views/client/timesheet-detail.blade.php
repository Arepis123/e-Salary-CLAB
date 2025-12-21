<x-layouts.app :title="__('Payroll Details')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Payroll Details</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $submission->month_year }}</p>
            </div>
            <div class="flex gap-2">
                @if($submission->status === 'draft')
                    <flux:button variant="primary" icon="pencil" href="{{ route('client.timesheet.edit', $submission->id) }}">
                        Edit Draft
                    </flux:button>
                @endif
                <flux:button variant="outline" href="{{ route('client.timesheet') }}">
                    <flux:icon.arrow-left class="size-4 inline" />
                    Back to Timesheet
                </flux:button>
            </div>
        </div>

        <!-- Submission Info Card -->
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Period</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $submission->month_year }}</p>
                </div>
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Submitted Date</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $submission->submitted_at ? $submission->submitted_at->format('F d, Y h:i A') : 'Not submitted yet' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Status</p>
                    <div class="mt-1">
                        @if($submission->status === 'draft')
                            <flux:badge color="zinc" >Draft</flux:badge>
                        @elseif($submission->status === 'submitted')
                            <flux:badge color="blue" >In Process</flux:badge>
                        @elseif($submission->status === 'approved')
                            <flux:badge color="purple" >Approved</flux:badge>
                        @elseif($submission->status === 'pending_payment')
                            <flux:badge color="orange" >Pending Payment</flux:badge>
                        @elseif($submission->status === 'paid')
                            <flux:badge color="green" >Paid</flux:badge>
                        @elseif($submission->status === 'overdue')
                            <flux:badge color="red" >Overdue</flux:badge>
                        @endif
                    </div>
                </div>
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Workers</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $submission->total_workers }}</p>
                </div>
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Payment Deadline</p>
                    <p class="text-lg font-semibold {{ now()->gt($submission->payment_deadline) ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ $submission->payment_deadline->format('F d, Y') }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Amount Due</p>
                    @if($submission->hasAdminReview())
                        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->total_due, 2) }}</p>
                    @else
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                            Amount will be available after processing
                        </p>
                    @endif
                </div>
            </div>

            <!-- Payment Action -->
            @if($submission->hasAdminReview() && $submission->status !== 'paid')
                <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                    <form method="POST" action="{{ route('client.payment.create', $submission->id) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" >
                            <flux:icon.credit-card class="size-5 inline me-1" />
                            Pay Now - RM {{ number_format($submission->total_due, 2) }}
                        </flux:button>
                    </form>
                </div>
            @elseif($submission->payment && $submission->status === 'paid')
                <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-4 border border-green-200 dark:border-green-800">
                        <div class="flex gap-3">
                            <flux:icon.check-circle class="size-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                            <div class="text-sm text-green-900 dark:text-green-100">
                                <p class="font-medium">Payment Completed</p>
                                <p class="text-xs text-green-700 dark:text-green-300 mt-1">
                                    Paid on {{ $submission->payment->completed_at?->format('F d, Y h:i A') }}
                                    @if($submission->payment->transaction_id)
                                        | Transaction ID: {{ $submission->payment->transaction_id }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </flux:card>

        @if(!$submission->hasAdminReview())
            <!-- In Process Notice -->
            <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800">
                    <div class="flex gap-3">
                        <flux:icon.information-circle class="size-5 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                        <div class="text-sm text-blue-900 dark:text-blue-100">
                            <p class="font-medium">Payroll Processing in Progress</p>
                            <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                Your submission is being processed by our certified payroll system. The salary breakdown and invoice will be available after admin approval.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Client Input Summary -->
                <div class="mt-6">
                    <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Your Submission Summary</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                    <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</th>
                                    <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</th>
                                    <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Normal</th>
                                    <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Rest</th>
                                    <th class="pb-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Public</th>
                                    <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Transactions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($submission->workers as $worker)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="py-3">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar size="sm" name="{{ $worker->worker_name }}" />
                                            <div>
                                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->worker_name }}</p>
                                                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $worker->worker_id }} · {{ $worker->worker_passport }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        RM {{ number_format($worker->basic_salary, 2) }}
                                    </td>
                                    <td class="py-3 text-center text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        @if(($worker->ot_normal_hours ?? 0) > 0)
                                            {{ number_format($worker->ot_normal_hours, 1) }}h
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-center text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        @if(($worker->ot_rest_hours ?? 0) > 0)
                                            {{ number_format($worker->ot_rest_hours, 1) }}h
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-center text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        @if(($worker->ot_public_hours ?? 0) > 0)
                                            {{ number_format($worker->ot_public_hours, 1) }}h
                                        @else
                                            <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-2">
                                        @php
                                            $workerTransactions = $worker->transactions ?? collect([]);
                                        @endphp
                                        @if($workerTransactions->count() > 0)
                                            <div class="space-y-1">
                                                @foreach($workerTransactions as $transaction)
                                                    <div class="text-xs text-zinc-900 dark:text-zinc-100">
                                                        @if($transaction->type === 'allowance')
                                                            +RM {{ number_format($transaction->amount, 2) }} (Allowance)
                                                        @elseif($transaction->type === 'npl')
                                                            {{ $transaction->amount }} {{ $transaction->amount == 1 ? 'day' : 'days' }} (NPL)
                                                        @elseif($transaction->type === 'advance_payment')
                                                            -RM {{ number_format($transaction->amount, 2) }} (Advance)
                                                        @else
                                                            -RM {{ number_format($transaction->amount, 2) }} (Deduction)
                                                        @endif
                                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 italic">({{ $transaction->remarks }})</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-sm text-zinc-400 dark:text-zinc-500">-</div>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </flux:card>
        @else
            <!-- After Admin Review: Show Breakdown File and Invoice -->
            <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Salary Breakdown</h2>

                @if($submission->hasBreakdownFile())
                    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-4 border border-green-200 dark:border-green-800 mb-6">
                        <div class="flex items-center justify-between">
                            <div class="flex gap-3">
                                <flux:icon.document-check class="size-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                                <div class="text-sm text-green-900 dark:text-green-100">
                                    <p class="font-medium">Salary breakdown has been processed</p>
                                    <p class="text-xs text-green-700 dark:text-green-300 mt-1">
                                        Download the detailed breakdown file to view complete salary calculations for all workers.
                                    </p>
                                </div>
                            </div>
                            <flux:button size="sm" href="{{ route('payroll.breakdown.download', $submission->id) }}">
                                <flux:icon.arrow-down-tray class="size-4 inline me-1" />
                                Download Breakdown
                            </flux:button>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/50 p-4 border border-zinc-200 dark:border-zinc-700 mb-6">
                        <div class="flex gap-3">
                            <flux:icon.information-circle class="size-5 flex-shrink-0 text-zinc-600 dark:text-zinc-400" />
                            <div class="text-sm text-zinc-900 dark:text-zinc-100">
                                <p class="font-medium">No breakdown file uploaded</p>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                    The admin has not uploaded a detailed breakdown file yet.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Invoice Summary -->
                <div class="mt-6">
                    <h3 class="mb-3 text-base font-semibold text-zinc-900 dark:text-zinc-100">Invoice Summary</h3>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-600 dark:text-zinc-400">Total Amount (Payroll)</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->admin_final_amount ?? $submission->total_amount, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-600 dark:text-zinc-400">Service Charge (RM 200 × {{ $submission->billable_workers_count }} {{ Str::plural('worker', $submission->billable_workers_count) }})</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->service_charge, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-600 dark:text-zinc-400">SST (8%)</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->sst, 2) }}</span>
                            </div>
                            @if($submission->has_penalty)
                                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-2 flex justify-between">
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">Grand Total</span>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->client_total, 2) }}</span>
                                </div>
                                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-2">
                                    <div class="flex justify-between text-sm text-red-600 dark:text-red-400">
                                        <span>Late Payment Penalty (8%)</span>
                                        <span class="font-medium">RM {{ number_format($submission->penalty_amount, 2) }}</span>
                                    </div>
                                </div>
                            @endif
                            <div class="border-t-2 border-zinc-300 dark:border-zinc-600 pt-2 flex justify-between">
                                <span class="text-base font-bold text-zinc-900 dark:text-zinc-100">Total Amount Due</span>
                                <span class="text-base font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->total_due, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Worker Breakdown (Your Submitted Data) - Separate Card -->
            <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
                <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Your Submitted Worker Data</h3>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                    This shows the data you submitted for {{ $submission->month_year }} payroll
                </p>

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
                            <flux:table.column align="end">
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
                            <flux:table.column align="end">
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Transactions</span>
                            </flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($submission->workers as $index => $worker)
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

                                    <!-- Weekday OT Hours -->
                                    <flux:table.cell align="end" variant="strong">
                                        @if($worker->ot_normal_hours > 0)
                                            <span class="font-medium">{{ number_format($worker->ot_normal_hours, 2) }}</span>
                                        @else
                                            <span class="text-zinc-500">-</span>
                                        @endif
                                    </flux:table.cell>

                                    <!-- Rest Day OT Hours -->
                                    <flux:table.cell align="end" variant="strong">
                                        @if($worker->ot_rest_hours > 0)
                                            <span class="font-medium">{{ number_format($worker->ot_rest_hours, 2) }}</span>
                                        @else
                                            <span class="text-zinc-500">-</span>
                                        @endif
                                    </flux:table.cell>

                                    <!-- Public Holiday OT Hours -->
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
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        @endif
    </div>
</x-layouts.app>
