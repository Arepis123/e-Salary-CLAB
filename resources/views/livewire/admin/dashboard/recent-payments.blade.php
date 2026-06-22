<flux:card class="order-last lg:order-first lg:col-span-2 min-w-0 overflow-hidden p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Recent Payments</h2>
        <flux:button variant="ghost" size="sm" href="{{ route('payroll') }}" wire:navigate>View all</flux:button>
    </div>

    @if(count($recentPayments) > 0)
        <!-- Mobile: card list -->
        <div class="sm:hidden space-y-3">
            @foreach($recentPayments as $payment)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate mr-2">{{ $payment['client'] }}</span>
                        <flux:badge color="{{ $payment['status'] === 'completed' ? 'green' : 'yellow' }}" size="sm">
                            {{ ucfirst($payment['status']) }}
                        </flux:badge>
                    </div>
                    <div class="flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-400">
                        <span class="font-medium">RM {{ number_format($payment['amount']) }}</span>
                        <span>{{ $payment['workers'] }} workers · {{ $payment['date'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Desktop: table -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Client</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Amount</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Workers</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Date</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($recentPayments as $payment)
                    <tr>
                        <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100 max-w-[150px] truncate">{{ $payment['client'] }}</td>
                        <td class="py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100 whitespace-nowrap">RM {{ number_format($payment['amount']) }}</td>
                        <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">{{ $payment['workers'] }} workers</td>
                        <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">{{ $payment['date'] }}</td>
                        <td class="py-3">
                            <flux:badge color="{{ $payment['status'] === 'completed' ? 'green' : 'yellow' }}" size="sm">
                                {{ ucfirst($payment['status']) }}
                            </flux:badge>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="py-12 text-center">
            <flux:icon.banknotes class="mx-auto size-12 text-zinc-300 dark:text-zinc-600 mb-3" />
            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-1">No Recent Payments</p>
            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                Payment records will appear here once contractors complete their payments.
            </p>
        </div>
    @endif
</flux:card>
