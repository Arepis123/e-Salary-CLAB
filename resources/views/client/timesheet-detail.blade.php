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
                    <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->total_due, 2) }}</p>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1 hidden">
                        + Service Charge: RM {{ number_format($submission->service_charge, 2) }}
                    </p>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400 hidden">
                        + SST 8%: RM {{ number_format($submission->sst, 2) }}
                    </p>
                    <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mt-1 hidden">
                        Grand Total: RM {{ number_format($submission->grand_total, 2) }}
                    </p>
                    @if($submission->has_penalty)
                        <p class="text-xs text-red-600 dark:text-red-400 mt-2 hidden">
                            + Late Penalty: RM {{ number_format($submission->penalty_amount, 2) }}
                        </p>
                        <p class="text-sm font-bold text-red-600 dark:text-red-400 hidden">
                            Total Due: RM {{ number_format($submission->total_due, 2) }}
                        </p>
                    @endif
                </div>
            </div>

            <!-- Payment Action -->
            @if($submission->status === 'pending_payment' || $submission->status === 'overdue')
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

        <!-- Workers Details -->
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Worker Breakdown</h2>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Overtime (OT)</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Gross Salary</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker EPF</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker SOCSO</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Deduction</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Net Salary</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Employer EPF</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Employer SOCSO</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Total Payment</th>
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
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $worker->worker_id }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->basic_salary, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                @if($worker->total_ot_pay > 0)
                                    RM {{ number_format($worker->total_ot_pay, 2) }}
                                @else
                                    <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                @endif
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->gross_salary, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->epf_employee, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->socso_employee, 2) }}
                            </td>
                            <td class="py-3">
                                @php
                                    $workerTransactions = $worker->transactions ?? collect([]);
                                    $advancePayments = $workerTransactions->where('type', 'advance_payment');
                                    $deductions = $workerTransactions->where('type', 'deduction');
                                @endphp
                                @if($workerTransactions->count() > 0)
                                    <div class="space-y-1 text-right">
                                        @if($advancePayments->count() > 0)
                                            <div class="text-sm font-medium text-orange-600 dark:text-orange-400">Advance:</div>
                                            @foreach($advancePayments as $transaction)
                                                <div class="text-xs text-zinc-600 dark:text-zinc-400">
                                                    RM {{ number_format($transaction->amount, 2) }}
                                                    <div class="italic">({{ $transaction->remarks }})</div>
                                                </div>
                                            @endforeach
                                        @endif
                                        @if($deductions->count() > 0)
                                            <div class="text-sm font-medium text-red-600 dark:text-red-400">Deduction:</div>
                                            @foreach($deductions as $transaction)
                                                <div class="text-xs text-zinc-600 dark:text-zinc-400">
                                                    RM {{ number_format($transaction->amount, 2) }}
                                                    <div class="italic">({{ $transaction->remarks }})</div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @else
                                    <div class="text-sm text-center text-zinc-400 dark:text-zinc-500">-</div>
                                @endif
                            </td>
                            <td class="py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->net_salary, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->epf_employer, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->socso_employer, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->total_payment, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-zinc-300 dark:border-zinc-600">
                            <td colspan="10" class="py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                Total Amount:
                            </td>
                            <td class="py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($submission->total_amount, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="10" class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                Service Charge (RM200 × {{ $submission->total_workers }} {{ Str::plural('worker', $submission->total_workers) }}):
                            </td>
                            <td class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                RM {{ number_format($submission->service_charge, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="10" class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                SST 8%:
                            </td>
                            <td class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                RM {{ number_format($submission->sst, 2) }}
                            </td>
                        </tr>
                        <tr class="border-t border-zinc-300 dark:border-zinc-600">
                            <td colspan="10" class="py-3 text-right text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                Grand Total:
                            </td>
                            <td class="py-3 text-right text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($submission->grand_total, 2) }}
                            </td>
                        </tr>
                        @if($submission->has_penalty)
                        <tr>
                            <td colspan="10" class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                Late Payment Penalty (8%):
                            </td>
                            <td class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                RM {{ number_format($submission->penalty_amount, 2) }}
                            </td>
                        </tr>
                        @endif
                        <tr class="border-t border-zinc-300 dark:border-zinc-600">
                            <td colspan="10" class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                Total Amount Due:
                            </td>
                            <td class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($submission->total_due, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </flux:card>
    </div>
</x-layouts.app>
