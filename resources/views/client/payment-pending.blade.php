<x-layouts.app :title="__('Payment Pending')">
    <div class="flex h-full w-full flex-1 flex-col items-center justify-center gap-6 py-12">
        <div class="text-center">
            <div class="mb-4 flex justify-center">
                <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-6">
                    <flux:icon.clock class="size-16 text-orange-600 dark:text-orange-400" />
                </div>
            </div>

            <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 mb-2">Payment Pending</h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-400 mb-8">
                Your payment is being processed.
            </p>
        </div>

        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Please Wait</h2>

            <div class="space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                <p>We're waiting for confirmation from the payment gateway.</p>
                <p>This usually takes a few moments. You can:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Wait a few minutes and refresh this page</li>
                    <li>Check your email for payment confirmation</li>
                    <li>Contact support if the issue persists</li>
                </ul>
            </div>

            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-sm text-blue-900 dark:text-blue-100">
                    <strong>Period:</strong> {{ $submission->month_year }}<br>
                    <strong>Client Total:</strong> RM {{ number_format($submission->client_total, 2) }}<br>
                    @if($submission->has_penalty)
                        <strong>Penalty (8%):</strong> + RM {{ number_format($submission->penalty_amount, 2) }}<br>
                        <strong class="text-lg">Total Due:</strong> RM {{ number_format($submission->total_due, 2) }}
                    @else
                        <strong class="text-lg">Total Due:</strong> RM {{ number_format($submission->client_total, 2) }}
                    @endif
                </p>
            </div>
        </flux:card>

        <div class="flex gap-3">
            <flux:button variant="outline" href="{{ route('timesheet') }}">
                <flux:icon.arrow-left class="size-4" />
                Back to Timesheet
            </flux:button>
            <flux:button variant="primary" onclick="window.location.reload()">
                <flux:icon.arrow-path class="size-4" />
                Refresh Status
            </flux:button>
        </div>
    </div>
</x-layouts.app>
