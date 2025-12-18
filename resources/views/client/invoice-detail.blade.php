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
                <flux:button variant="outline" href="{{ route('invoices') }}">
                    <flux:icon.arrow-left class="size-4" />
                    Back to Invoices
                </flux:button>
            </div>
        </div>

        <!-- Invoice Info Card -->
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <div class="grid gap-6 md:grid-cols-2">
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
                        @endif
                    </div>
                </div>
            </div>
        </flux:card>

        <!-- Detailed Breakdown File -->
        @if($invoice->hasBreakdownFile())
            <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Detailed Worker Salary Breakdown</h3>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                Download the complete breakdown file with detailed calculations for all {{ $invoice->total_workers }} {{ Str::plural('worker', $invoice->total_workers) }}
                            </p>
                        </div>
                    </div>
                    <flux:button href="{{ route('payroll.breakdown.download', $invoice->id) }}">
                        <flux:icon.arrow-down-tray class="size-4 inline me-1" />
                        Download Breakdown
                    </flux:button>
                </div>
            </flux:card>
        @endif

        <!-- Invoice Summary -->
        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Invoice Summary</h3>

            <div class="space-y-3">
                <div class="flex justify-between py-2 border-b border-zinc-200 dark:border-zinc-700">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Payroll Amount ({{ $invoice->total_workers }} {{ Str::plural('worker', $invoice->total_workers) }}):</span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($invoice->admin_final_amount ?? $invoice->total_amount, 2) }}</span>
                </div>

                <div class="flex justify-between py-2">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Service Charge (RM 200 × {{ $invoice->billable_workers_count }} {{ Str::plural('worker', $invoice->billable_workers_count) }}):</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($invoice->calculated_service_charge, 2) }}</span>
                </div>

                <div class="flex justify-between py-2 border-b border-zinc-200 dark:border-zinc-700">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">SST (8%):</span>
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($invoice->calculated_sst, 2) }}</span>
                </div>

                @if($invoice->has_penalty)
                    <div class="flex justify-between py-2 bg-zinc-50 dark:bg-zinc-800 px-4 rounded">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Subtotal:</span>
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($invoice->client_total, 2) }}</span>
                    </div>

                    <div class="flex justify-between py-2">
                        <span class="text-sm text-red-600 dark:text-red-400">Late Payment Penalty (8%):</span>
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($invoice->penalty_amount, 2) }}</span>
                    </div>
                @endif

                <div class="flex justify-between py-2">
                    <span class="text-base font-bold text-zinc-900 dark:text-zinc-100">Total Amount Due:</span>
                    <span class="text-base font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($invoice->total_due, 2) }}</span>
                </div>
            </div>
        </flux:card>
        
        <!-- Payment Action -->
        @if($invoice->status === 'pending_payment' || $invoice->status === 'overdue')
            <flux:card class="p-6 dark:bg-zinc-900 border border-blue-200 dark:border-blue-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Ready to Pay?</h3>                      
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Complete your payment securely via Billplz using Online Banking (FPX)
                        </p>
                    </div>
                    <form method="POST" action="{{ route('client.payment.create', $invoice->id) }}">
                        @csrf
                        <flux:button type="submit" variant="primary">
                            <flux:icon.credit-card class="size-5 inline me-1" />
                            Pay with Online Banking
                        </flux:button>
                    </form>
                </div>
            </flux:card>
        @elseif($invoice->status === 'paid')
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

        <!-- Payment Method Notice -->
        @if($invoice->status === 'pending_payment' || $invoice->status === 'overdue')
            {{-- <flux:card class="p-4 dark:bg-zinc-900 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <div class="flex gap-3">
                    <flux:icon.information-circle class="size-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="text-sm text-amber-900 dark:text-amber-100">
                        <p class="font-medium">Payment Method Information</p>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                            Payment can only be made using <span class="font-semibold">Online Banking (FPX)</span> through our secure <span class="font-semibold">Billplz payment gateway</span>.
                            Credit/debit card payments are not accepted. Please ensure your online banking is activated before proceeding.
                        </p>
                    </div>
                </div>
            </flux:card> --}}
            <flux:callout icon="check-circle" color="amber">
                <flux:callout.heading>Payment Method Information</flux:callout.heading>
                <flux:callout.text>
                    <p>
                        Payment can only be made using <span class="font-semibold">Online Banking (FPX)</span> through our secure <span class="font-semibold">Billplz payment gateway</span>.
                        Credit/debit card payments are not accepted. Please ensure your online banking is activated before proceeding.
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
                    The overtime amount shown above are for <strong>{{ $previousMonthName }}</strong> and are being paid in this <strong>{{ $invoice->month_year }}</strong> payroll.
                    This payment includes basic salary plus {{ $previousMonthName }}'s overtime.
                </p>
            </flux:callout.text>
        </flux:callout>         

    </div>
</x-layouts.app>
