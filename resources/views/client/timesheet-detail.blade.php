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
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Amount</p>
                    <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($submission->total_amount, 2) }}</p>
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
                            Total Due: RM {{ number_format($submission->total_with_penalty, 2) }}
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
                            Pay Now - RM {{ number_format($submission->total_with_penalty, 2) }}
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

        <!-- OT Payment Flow Information -->
        <div class="grid gap-4 md:grid-cols-2">
            <!-- Previous Month OT (Paid This Month) -->
            <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-start gap-3">
                    <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400 mt-0.5" />
                    <div class="flex-1">
                        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Previous Month OT (Included in This Payment)</h3>
                        @if($previousSubmission)
                            <div class="space-y-2 text-sm">
                                <p class="text-zinc-600 dark:text-zinc-400">
                                    OT from <span class="font-medium text-green-600 dark:text-green-400">{{ $previousSubmission->month_year }}</span> is included in this month's payment
                                </p>
                                <div class="grid grid-cols-2 gap-2 mt-3">
                                    <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded">
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">Total Hours</p>
                                        <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ number_format($previousOtStats['total_ot_hours'], 2) }}h</p>
                                    </div>
                                    <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded">
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">Total Amount</p>
                                        <p class="text-xl font-bold text-green-600 dark:text-green-400">RM {{ number_format($previousOtStats['total_ot_pay'], 2) }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-t border-green-200 dark:border-green-800">
                                    <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-2">Breakdown:</p>
                                    <div class="space-y-1 text-xs">
                                        <div class="flex justify-between">
                                            <span class="text-zinc-600 dark:text-zinc-400">Weekday OT (1.5x):</span>
                                            <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($previousOtStats['total_weekday_ot_hours'], 2) }}h | RM {{ number_format($previousOtStats['total_weekday_ot_pay'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-zinc-600 dark:text-zinc-400">Rest Day OT (2.0x):</span>
                                            <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($previousOtStats['total_rest_ot_hours'], 2) }}h | RM {{ number_format($previousOtStats['total_rest_ot_pay'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-zinc-600 dark:text-zinc-400">Public Holiday OT (3.0x):</span>
                                            <span class="font-medium text-green-600 dark:text-green-400">{{ number_format($previousOtStats['total_public_ot_hours'], 2) }}h | RM {{ number_format($previousOtStats['total_public_ot_pay'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">No previous month OT data. This is likely your first submission.</p>
                        @endif
                    </div>
                </div>
            </flux:card>

            <!-- Current Month OT (To be Paid Next Month) -->
            <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-start gap-3">
                    <flux:icon.clock class="size-6 text-orange-600 dark:text-orange-400 mt-0.5" />
                    <div class="flex-1">
                        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Current Month OT (To be Paid Next Month)</h3>
                        @php
                            $currentOtHours = $submission->workers->sum(function ($worker) {
                                return $worker->ot_normal_hours + $worker->ot_rest_hours + $worker->ot_public_hours;
                            });
                            $currentOtPay = $submission->workers->sum('total_ot_pay');
                            $currentWeekdayOtHours = $submission->workers->sum('ot_normal_hours');
                            $currentWeekdayOtPay = $submission->workers->sum('ot_normal_pay');
                            $currentRestOtHours = $submission->workers->sum('ot_rest_hours');
                            $currentRestOtPay = $submission->workers->sum('ot_rest_pay');
                            $currentPublicOtHours = $submission->workers->sum('ot_public_hours');
                            $currentPublicOtPay = $submission->workers->sum('ot_public_pay');
                        @endphp
                        <div class="space-y-2 text-sm">
                            <p class="text-zinc-600 dark:text-zinc-400">
                                OT from <span class="font-medium text-orange-600 dark:text-orange-400">{{ $submission->month_year }}</span> will be charged next month
                            </p>
                            <div class="grid grid-cols-2 gap-2 mt-3">
                                <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded">
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">Total Hours</p>
                                    <p class="text-xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($currentOtHours, 2) }}h</p>
                                </div>
                                <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded">
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">Total Amount</p>
                                    <p class="text-xl font-bold text-orange-600 dark:text-orange-400">RM {{ number_format($currentOtPay, 2) }}</p>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-orange-200 dark:border-orange-800">
                                <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-2">Breakdown:</p>
                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-zinc-600 dark:text-zinc-400">Weekday OT (1.5x):</span>
                                        <span class="font-medium text-zinc-600 dark:text-zinc-400">{{ number_format($currentWeekdayOtHours, 2) }}h | RM {{ number_format($currentWeekdayOtPay, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-zinc-600 dark:text-zinc-400">Rest Day OT (2.0x):</span>
                                        <span class="font-medium text-zinc-600 dark:text-zinc-400">{{ number_format($currentRestOtHours, 2) }}h | RM {{ number_format($currentRestOtPay, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-zinc-600 dark:text-zinc-400">Public Holiday OT (3.0x):</span>
                                        <span class="font-medium text-zinc-600 dark:text-zinc-400">{{ number_format($currentPublicOtHours, 2) }}h | RM {{ number_format($currentPublicOtPay, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Workers Details -->
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Worker Breakdown</h2>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Normal<br><span class="text-xs">(Deferred)</span></th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Rest<br><span class="text-xs">(Deferred)</span></th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">OT Public<br><span class="text-xs">(Deferred)</span></th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Transactions</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Gross Salary</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Deductions</th>
                            <th class="pb-3 text-right text-xs font-medium text-zinc-600 dark:text-zinc-400">Net Salary</th>
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
                            <td class="py-3 text-right text-sm text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->basic_salary, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $worker->ot_normal_hours }}h<br>
                                <span class="text-xs">RM {{ number_format($worker->ot_normal_pay, 2) }}</span>
                            </td>
                            <td class="py-3 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $worker->ot_rest_hours }}h<br>
                                <span class="text-xs">RM {{ number_format($worker->ot_rest_pay, 2) }}</span>
                            </td>
                            <td class="py-3 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $worker->ot_public_hours }}h<br>
                                <span class="text-xs">RM {{ number_format($worker->ot_public_pay, 2) }}</span>
                            </td>
                            <td class="py-3">
                                @php
                                    $workerTransactions = $worker->transactions ?? collect([]);
                                    $advancePayments = $workerTransactions->where('type', 'advance_payment');
                                    $deductions = $workerTransactions->where('type', 'deduction');
                                @endphp
                                @if($workerTransactions->count() > 0)
                                    <div class="space-y-1">
                                        @if($advancePayments->count() > 0)
                                            <div class="text-xs text-right">
                                                <span class="font-medium text-orange-600 dark:text-orange-400">Advance Payment:</span>
                                                @foreach($advancePayments as $transaction)
                                                    <div class="ml-2 text-zinc-600 dark:text-zinc-400">
                                                        • -RM {{ number_format($transaction->amount, 2) }}
                                                        <span class="text-xs italic">({{ $transaction->remarks }})</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($deductions->count() > 0)
                                            <div class="text-xs text-right">
                                                <span class="font-medium text-red-600 dark:text-red-400">Deduction:</span>
                                                @foreach($deductions as $transaction)
                                                    <div class="ml-2 text-zinc-600 dark:text-zinc-400">
                                                        • -RM {{ number_format($transaction->amount, 2) }}
                                                        <span class="text-xs italic">({{ $transaction->remarks }})</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">-</span>
                                @endif
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->gross_salary, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm text-red-600 dark:text-red-400">
                                -RM {{ number_format($worker->total_deductions, 2) }}<br>
                                <span class="text-xs">(EPF+SOCSO)</span>
                            </td>
                            <td class="py-3 text-right text-sm font-medium text-green-600 dark:text-green-400">
                                RM {{ number_format($worker->net_salary, 2) }}
                            </td>
                            <td class="py-3 text-right text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($worker->total_payment, 2) }}<br>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">
                                    (+RM {{ number_format($worker->total_employer_contribution, 2) }} contrib.)
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-zinc-300 dark:border-zinc-600">
                            <td colspan="9" class="py-3 text-right text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                Total Amount:
                            </td>
                            <td class="py-3 text-right text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($submission->total_amount, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="9" class="py-1 text-right text-sm text-zinc-900 dark:text-zinc-300">
                                Service Charge (RM200 × {{ $submission->total_workers }} {{ Str::plural('worker', $submission->total_workers) }}):
                            </td>
                            <td class="py-1 text-right text-sm text-zinc-900 dark:text-zinc-300">
                                +RM {{ number_format($submission->service_charge, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="9" class="py-1 text-right text-sm text-zinc-900 dark:text-zinc-300">
                                SST 8%:
                            </td>
                            <td class="py-1 text-right text-sm text-zinc-900 dark:text-zinc-300">
                                +RM {{ number_format($submission->sst, 2) }}
                            </td>
                        </tr>
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td colspan="9" class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                Grand Total:
                            </td>
                            <td class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($submission->grand_total, 2) }}
                            </td>
                        </tr>
                        @if($submission->has_penalty)
                        <tr>
                            <td colspan="9" class="py-2 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-300">
                                Late Payment Penalty (8%):
                            </td>
                            <td class="py-2 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-300">
                                +RM {{ number_format($submission->penalty_amount, 2) }}
                            </td>
                        </tr>
                        <tr class="border-t-2 border-zinc-300 dark:border-zinc-600">
                            <td colspan="9" class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                Total Amount Due:
                            </td>
                            <td class="py-3 text-right text-base font-bold text-red-600 dark:text-red-400">
                                RM {{ number_format($submission->grand_total + $submission->penalty_amount, 2) }}
                            </td>
                        </tr>
                        @else
                        <tr class="border-t-2 border-zinc-300 dark:border-zinc-600">
                            <td colspan="9" class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                Total Amount Due:
                            </td>
                            <td class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($submission->grand_total, 2) }}
                            </td>
                        </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </flux:card>
    </div>
</x-layouts.app>
