<x-layouts.app :title="__('Invoice Details')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Invoice #INV-{{ str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $invoice->month_year }} Payroll Invoice</p>
            </div>
            <div class="flex gap-2">
                @if($invoice->status === 'paid')
                    <!-- Tax Invoice (for paid invoices) -->
                    <flux:button variant="primary" href="{{ route('invoices.download-tax', $invoice->id) }}">
                        <flux:icon.arrow-down-tray class="size-4" />
                        Download Tax Invoice
                    </flux:button>
                    <!-- Pro Forma Invoice (still available) -->
                    <flux:button variant="outline" href="{{ route('invoices.download', $invoice->id) }}">
                        <flux:icon.arrow-down-tray class="size-4" />
                        Download Pro Forma
                    </flux:button>
                @else
                    <!-- Pro Forma Invoice only (for unpaid invoices) -->
                    <flux:button variant="outline" href="{{ route('invoices.download', $invoice->id) }}">
                        <flux:icon.arrow-down-tray class="size-4" />
                        Download Pro Forma Invoice
                    </flux:button>
                @endif
                <flux:button variant="outline" href="{{ route('invoices') }}" wire:navigate>
                    <flux:icon.arrow-left class="size-4" />
                    Back to Invoices
                </flux:button>
            </div>
        </div>

        <!-- Invoice Info Card -->
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <div class="grid gap-6 md:grid-cols-3">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Contractor Information</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Name:</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->user ? $invoice->user->name : 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">CLAB No:</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->contractor_clab_no }}</span>
                        </div>
                        @if($invoice->user && $invoice->user->email)
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Email:</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->user->email }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Invoice Information</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Invoice Number:</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">INV-{{ str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Period:</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->month_year }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Issue Date:</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->submitted_at ? $invoice->submitted_at->format('F d, Y') : 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Due Date:</span>
                            <span class="font-medium {{ now()->gt($invoice->payment_deadline) && $invoice->status !== 'paid' ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $invoice->payment_deadline->format('F d, Y') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Payment Information</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Status:</span>
                            <div>
                                @if($invoice->status === 'draft')
                                    <flux:badge color="zinc" size="sm">Draft</flux:badge>
                                @elseif($invoice->status === 'pending_payment')
                                    <flux:badge color="yellow" size="sm">Pending Payment</flux:badge>
                                @elseif($invoice->status === 'paid')
                                    <flux:badge color="green" size="sm">Paid</flux:badge>
                                @elseif($invoice->status === 'overdue')
                                    <flux:badge color="red" size="sm">Overdue</flux:badge>
                                @endif
                            </div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Total Workers:</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->total_workers }}</span>
                        </div>
                        @if($invoice->payment)
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Payment Method:</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ ucfirst($invoice->payment->payment_method) }}</span>
                        </div>
                        @if($invoice->payment->transaction_id)
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Transaction ID:</span>
                            <span class="font-mono text-xs text-zinc-900 dark:text-zinc-100">{{ $invoice->payment->transaction_id }}</span>
                        </div>
                        @endif
                        @if($invoice->payment->completed_at)
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">Paid On:</span>
                            <span class="font-medium text-green-600 dark:text-green-400">{{ $invoice->payment->completed_at->format('F d, Y h:i A') }}</span>
                        </div>
                        @endif
                        @endif
                    </div>
                </div>
            </div>
        </flux:card>

        <!-- Workers Breakdown -->
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Worker Salary Breakdown</h3>

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
                        @foreach($invoice->workers as $worker)
                        @php
                            $totalOtHours = $worker->ot_normal_hours + $worker->ot_rest_hours + $worker->ot_public_hours;
                        @endphp
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
                                RM {{ number_format($invoice->total_amount, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="10" class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                Service Charge (RM200 × {{ $invoice->total_workers }} {{ Str::plural('worker', $invoice->total_workers) }}):
                            </td>
                            <td class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                RM {{ number_format($invoice->service_charge, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="10" class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                SST 8%:
                            </td>
                            <td class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                RM {{ number_format($invoice->sst, 2) }}
                            </td>
                        </tr>
                        <tr class="border-t border-zinc-300 dark:border-zinc-600">
                            <td colspan="10" class="py-3 text-right text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                Grand Total:
                            </td>
                            <td class="py-3 text-right text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($invoice->grand_total, 2) }}
                            </td>
                        </tr>
                        @if($invoice->has_penalty)
                        <tr>
                            <td colspan="10" class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                Late Payment Penalty (8%):
                            </td>
                            <td class="py-2 text-right text-sm text-zinc-600 dark:text-zinc-400">
                                RM {{ number_format($invoice->penalty_amount, 2) }}
                            </td>
                        </tr>
                        @endif
                        <tr class="border-t border-zinc-300 dark:border-zinc-600">
                            <td colspan="10" class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                Total Amount Due:
                            </td>
                            <td class="py-3 text-right text-base font-bold text-zinc-900 dark:text-zinc-100">
                                RM {{ number_format($invoice->total_due, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </flux:card>

        @if($invoice->status === 'paid')
            {{-- <flux:card class="p-6 dark:bg-zinc-900 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <div class="flex gap-3">
                    <flux:icon.check-circle class="size-6 flex-shrink-0 text-green-600 dark:text-green-400" />
                    <div>
                        <h3 class="text-lg font-semibold text-green-900 dark:text-green-100">Invoice Paid</h3>
                        <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                            This invoice was paid on {{ $invoice->payment->completed_at?->format('F d, Y h:i A') }}
                            @if($invoice->payment->transaction_id)
                                <br>Transaction ID: {{ $invoice->payment->transaction_id }}
                            @endif
                        </p>
                    </div>
                </div>
            </flux:card> --}}
            <flux:callout icon="check-circle" color="emerald">
                <flux:callout.heading>Invoice Paid</flux:callout.heading>
                <flux:callout.text>
                    <p>
                        This invoice was paid on {{ $invoice->payment->completed_at?->format('F d, Y h:i A') }}
                        @if($invoice->payment->transaction_id)
                            <br>Transaction ID: {{ $invoice->payment->transaction_id }}
                        @endif
                    </p>
                </flux:callout.text>
            </flux:callout>            
        @endif
       
        <!-- OT Information Notice -->
        @php
            // Calculate previous month name
            $previousMonth = $invoice->month - 1;
            $previousYear = $invoice->year;
            if ($previousMonth < 1) {
                $previousMonth = 12;
                $previousYear--;
            }
            $previousMonthName = \Carbon\Carbon::create($previousYear, $previousMonth, 1)->format('F Y');
        @endphp
        <flux:callout icon="information-circle" color="blue">
            <flux:callout.heading>Previous Month OT Payment</flux:callout.heading>
            <flux:callout.text>
                <p>
                    The overtime amount shown above are for <strong>{{ $invoice->month_year }}</strong> and are being paid in this <strong>{{ $invoice->month_year }}</strong> payroll.
                    This payment includes basic salary plus {{ $previousMonthName }}'s overtime.
                </p>
            </flux:callout.text>
        </flux:callout>           

    </div>
</x-layouts.app>
