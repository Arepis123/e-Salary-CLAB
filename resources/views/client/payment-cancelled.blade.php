<x-layouts.app :title="__('Payment Cancelled')">
    <div class="flex h-full w-full flex-1 flex-col items-center justify-center gap-6 py-12">
        <div class="text-center">
            <div class="mb-4 flex justify-center">
                <div class="rounded-full bg-zinc-100 dark:bg-zinc-800 p-6">
                    <flux:icon.x-mark class="size-16 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>

            <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 mb-2">Payment Cancelled</h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-400 mb-8">
                You have returned without completing the payment.
            </p>
        </div>

        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">What's Next?</h2>

            <div class="space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                <p>No payment has been made for this submission. You can:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Try making the payment again</li>
                    <li>Review your submission details</li>
                    <li>Contact support if you need assistance</li>
                </ul>
            </div>

            <!-- <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-sm text-blue-900 dark:text-blue-100">
                    <strong>Period:</strong> {{ $submission->month_year }}<br>
                    <strong>Grand Total:</strong> RM {{ number_format($submission->grand_total, 2) }}<br>
                    @if($submission->has_penalty)
                        <strong>Penalty (8%):</strong> + RM {{ number_format($submission->penalty_amount, 2) }}<br>
                        <strong class="text-lg">Total Due:</strong> RM {{ number_format($submission->grand_total + $submission->penalty_amount, 2) }}
                    @else
                        <strong class="text-lg">Total Due:</strong> RM {{ number_format($submission->grand_total, 2) }}
                    @endif
                </p>
            </div> -->

            <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <p class="text-xs text-yellow-900 dark:text-yellow-100">
                    <flux:icon.information-circle class="inline size-4 mr-1" />
                    <strong>Note:</strong> Payment is required to complete your submission. Late payments may incur an 8% penalty.
                </p>
            </div>
        </flux:card>

        <div class="flex gap-3">
            <flux:button variant="outline" href="{{ route('timesheet') }}">
                <flux:icon.arrow-left class="size-4" />
                Back to Timesheet
            </flux:button>
            <form method="POST" action="{{ route('client.payment.create', $submission->id) }}">
                @csrf
                <flux:button type="submit" variant="primary">
                    <flux:icon.credit-card class="size-4 inline me-1" />
                    Make Payment
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts.app>
