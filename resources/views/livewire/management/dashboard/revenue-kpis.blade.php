<div class="space-y-4">
    <!-- Primary KPIs -->
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <!-- Company revenue: the money the business actually earns -->
        <flux:card class="space-y-3 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Company Revenue</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($kpis['revenue'], 0) }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $kpis['period_label'] }}</p>
                </div>
                <div class="rounded-full bg-indigo-100 dark:bg-indigo-900/30 p-3 hidden xl:block">
                    <flux:icon.banknotes class="size-6 text-indigo-600 dark:text-indigo-400" />
                </div>
            </div>

            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-indigo-500"></span>
                    Service charge RM {{ number_format($kpis['service_charge'], 0) }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-sky-400"></span>
                    SST RM {{ number_format($kpis['sst'], 0) }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-amber-500"></span>
                    Penalty RM {{ number_format($kpis['penalty'], 0) }}
                </span>
            </div>

            @include('livewire.management.dashboard.partials.delta', ['change' => $kpis['revenue_change']])
        </flux:card>

        <!-- Payroll volume: collected and passed through, not income -->
        <flux:card class="space-y-3 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Payroll Volume</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($kpis['payroll_volume'], 0) }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Pass-through &middot; {{ $kpis['period_label'] }}</p>
                </div>
                <div class="rounded-full bg-zinc-100 dark:bg-zinc-800 p-3 hidden xl:block">
                    <flux:icon.arrows-right-left class="size-6 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Wages and statutory contributions forwarded to workers. Not company income.
            </p>

            @include('livewire.management.dashboard.partials.delta', ['change' => $kpis['payroll_volume_change']])
        </flux:card>

        <!-- Cash collected: keyed on when payment landed, so calendar month -->
        <flux:card class="space-y-3 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Cash Collected</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($kpis['cash_collected'], 0) }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        {{ $kpis['cash_month_label'] }}{{ $kpis['cash_is_partial_month'] ? ' to date' : '' }}
                    </p>
                </div>
                <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3 hidden xl:block">
                    <flux:icon.wallet class="size-6 text-green-600 dark:text-green-400" />
                </div>
            </div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ $kpis['cash_last_month_label'] }} closed at RM {{ number_format($kpis['cash_last_month_full'], 0) }}.
            </p>

            @include('livewire.management.dashboard.partials.delta', [
                'change' => $kpis['cash_collected_change'],
                'label' => $kpis['cash_is_partial_month'] ? 'vs same point last month' : 'vs last month',
            ])
        </flux:card>

        <!-- Receivables: everything billed and still unsettled -->
        <flux:card class="space-y-3 p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Receivables</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">RM {{ number_format($kpis['receivables'], 0) }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Across {{ $kpis['receivables_clients'] }} {{ Str::plural('client', $kpis['receivables_clients']) }}</p>
                </div>
                <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3 hidden xl:block">
                    <flux:icon.exclamation-circle class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>

            @if($kpis['receivables_overdue'] > 0)
                <p class="text-xs">
                    <span class="text-red-600 dark:text-red-400 font-medium">RM {{ number_format($kpis['receivables_overdue'], 0) }}</span>
                    <span class="text-zinc-600 dark:text-zinc-400">is already past its deadline</span>
                </p>
            @else
                <p class="text-xs text-green-600 dark:text-green-400">Nothing past its deadline.</p>
            @endif
        </flux:card>
    </div>

    <!-- Unit economics -->
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <flux:card class="space-y-1 p-4 bg-white dark:bg-zinc-900 rounded-lg">
            <p class="text-xs text-zinc-600 dark:text-zinc-400">Clients Billed</p>
            <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($kpis['clients_billed']) }}</p>
            <p class="text-xs {{ $kpis['clients_billed_change'] > 0 ? 'text-green-600 dark:text-green-400' : ($kpis['clients_billed_change'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400') }}">
                {{ $kpis['clients_billed_change'] > 0 ? '+' : '' }}{{ $kpis['clients_billed_change'] }} vs last period
            </p>
        </flux:card>

        <flux:card class="space-y-1 p-4 bg-white dark:bg-zinc-900 rounded-lg">
            <p class="text-xs text-zinc-600 dark:text-zinc-400">Billable Workers</p>
            <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($kpis['billable_workers']) }}</p>
            <p class="text-xs {{ $kpis['billable_workers_change'] > 0 ? 'text-green-600 dark:text-green-400' : ($kpis['billable_workers_change'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400') }}">
                {{ $kpis['billable_workers_change'] > 0 ? '+' : '' }}{{ $kpis['billable_workers_change'] }} vs last period
            </p>
        </flux:card>

        <flux:card class="space-y-1 p-4 bg-white dark:bg-zinc-900 rounded-lg">
            <p class="text-xs text-zinc-600 dark:text-zinc-400">Revenue per Client</p>
            <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($kpis['revenue_per_client'], 0) }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Average this period</p>
        </flux:card>

        <flux:card class="space-y-1 p-4 bg-white dark:bg-zinc-900 rounded-lg">
            <p class="text-xs text-zinc-600 dark:text-zinc-400">Revenue per Worker</p>
            <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">RM {{ number_format($kpis['revenue_per_worker'], 2) }}</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">RM 216 at full rate</p>
        </flux:card>
    </div>

    <p class="text-xs text-zinc-400 dark:text-zinc-500">
        Figures cached &middot; generated {{ \Carbon\Carbon::parse($kpis['generated_at'])->diffForHumans() }}
    </p>
</div>
