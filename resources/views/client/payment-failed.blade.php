<x-layouts.app :title="__('Payment Failed')">
    <div class="flex h-full w-full flex-1 flex-col items-center justify-center gap-6 py-12">
        <div class="text-center">
            <div class="mb-4 flex justify-center">
                <div class="rounded-full bg-red-100 dark:bg-red-900/30 p-6">
                    <flux:icon.x-circle class="size-16 text-red-600 dark:text-red-400" />
                </div>
            </div>

            <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 mb-2">Payment Failed</h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-400 mb-8">
                Unfortunately, your payment could not be processed.
            </p>
        </div>

        <flux:card class="p-6 dark:bg-zinc-900 rounded-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">What happened?</h2>

            <div class="space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                <p>Your payment was not successful. This could be due to:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Insufficient funds</li>
                    <li>Payment gateway timeout</li>
                    <li>Cancelled transaction</li>
                    <li>Invalid payment details</li>
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
            <form method="POST" action="{{ route('client.payment.create', $submission->id) }}">
                @csrf
                <flux:button type="submit" variant="primary">
                    <flux:icon.arrow-path class="size-4" />
                    Try Again
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts.app>
