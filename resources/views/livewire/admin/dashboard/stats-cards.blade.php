<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
    <!-- Clients Without Submission -->
    <a href="{{ route('missing-submissions') }}" wire:navigate>
        <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg cursor-pointer transition-[transform,box-shadow] duration-300 ease-in-out hover:scale-103 hover:shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Pending Submissions</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['clients_without_submission'] }}</p>
                </div>
                <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3 hidden lg:hidden xl:block">
                    <flux:icon.exclamation-triangle class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-zinc-600 dark:text-zinc-400">{{ $stats['clients_with_submission_count'] }} of {{ $stats['total_clients'] }} submitted</span>
            </div>
        </flux:card>
    </a>

    <!-- Active Workers -->
    <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg cursor-pointer transition-[transform,box-shadow] duration-300 ease-in-out hover:scale-103 hover:shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Active Workers</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['active_workers'] }}</p>
            </div>
            <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3 hidden lg:hidden xl:block">
                <flux:icon.users class="size-6 text-green-600 dark:text-green-400" />
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs">
            @if($stats['workers_growth'] > 0)
                <span class="text-green-600 dark:text-green-400">+{{ $stats['workers_growth'] }} workers</span>
            @elseif($stats['workers_growth'] < 0)
                <span class="text-red-600 dark:text-red-400">{{ $stats['workers_growth'] }} workers</span>
            @else
                <span class="text-zinc-500 dark:text-zinc-400">no change</span>
            @endif
            <span class="text-zinc-600 dark:text-zinc-400">from last month</span>
        </div>
    </flux:card>

    <!-- This Month / Last Month Payments (switches on the 16th) -->
    <a href="{{ route('payroll') }}" wire:navigate>
        <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg cursor-pointer transition-[transform,box-shadow] duration-300 ease-in-out hover:scale-103 hover:shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    @if(now()->day >= 16)
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">This Month Payments</p>
                    @else
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Last Month Payments</p>
                    @endif
                    @if(now()->day >= 16)
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['this_month_payments']) }}</p>
                    @else
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['last_month_payments']) }}</p>
                    @endif
                </div>
                <div class="rounded-full bg-purple-100 dark:bg-purple-900/30 p-3 hidden lg:hidden xl:block">
                    <flux:icon.wallet class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-green-600 dark:text-green-400">+{{ $stats['payments_growth'] }}%</span>
                <span class="text-zinc-600 dark:text-zinc-400">from last month</span>
            </div>
        </flux:card>
    </a>

    <!-- Outstanding Balance -->
    <flux:card class="space-y-2 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg cursor-pointer transition-[transform,box-shadow] duration-300 ease-in-out hover:scale-103 hover:shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Outstanding Balance</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($stats['outstanding_balance']) }}</p>
            </div>
            <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3 hidden lg:hidden xl:block">
                <flux:icon.exclamation-circle class="size-6 text-orange-600 dark:text-orange-400" />
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="text-orange-600 dark:text-orange-400">Unpaid invoices</span>
        </div>
    </flux:card>
</div>
