<div class="flex h-full w-full flex-1 flex-col gap-6 overflow-y-auto">
    <!-- Page Header -->
    <div>
        <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 sm:text-2xl">Email Logs</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Delivery tracking for all outgoing emails (sent, delivered, opened, bounced).
        </p>
    </div>

    <!-- Stats -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="p-4 dark:bg-zinc-900">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Sent</p>
            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total']) }}</p>
        </flux:card>
        <flux:card class="p-4 dark:bg-zinc-900">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Delivered</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['delivered']) }}</p>
        </flux:card>
        <flux:card class="p-4 dark:bg-zinc-900">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Opened</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($stats['opened']) }}</p>
        </flux:card>
        <flux:card class="p-4 dark:bg-zinc-900">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Bounced / Failed</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($stats['failed']) }}</p>
        </flux:card>
    </div>

    <!-- Filters -->
    <flux:card class="p-4 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.400ms="search" placeholder="Search recipient or subject..." icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="statusFilter" placeholder="All statuses" class="sm:max-w-44">
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="sent">Sent</flux:select.option>
                <flux:select.option value="delivered">Delivered</flux:select.option>
                <flux:select.option value="opened">Opened</flux:select.option>
                <flux:select.option value="clicked">Clicked</flux:select.option>
                <flux:select.option value="bounced">Bounced</flux:select.option>
                <flux:select.option value="spam">Spam</flux:select.option>
                <flux:select.option value="blocked">Blocked</flux:select.option>
                <flux:select.option value="deferred">Deferred</flux:select.option>
                <flux:select.option value="failed">Failed</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="dateFilter" placeholder="All time" class="sm:max-w-40">
                <flux:select.option value="">All time</flux:select.option>
                <flux:select.option value="today">Today</flux:select.option>
                <flux:select.option value="7days">Last 7 days</flux:select.option>
                <flux:select.option value="30days">Last 30 days</flux:select.option>
            </flux:select>
            @if($search !== '' || $statusFilter !== '' || $dateFilter !== '')
                <flux:button variant="ghost" wire:click="clearFilters" icon="x-mark">Clear</flux:button>
            @endif
        </div>
    </flux:card>

    <!-- Table -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Sent</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Recipient</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Subject</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</th>
                        <th class="pb-3 text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Opened</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($logs as $log)
                        <tr>
                            <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $log->sent_at?->format('M d, Y H:i') ?? $log->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                <div>{{ $log->to_name ?: $log->to_email }}</div>
                                @if($log->to_name)
                                    <div class="text-xs text-zinc-500">{{ $log->to_email }}</div>
                                @endif
                            </td>
                            <td class="py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $log->subject }}</td>
                            <td class="py-3">
                                @include('livewire.admin.partials.notification-status-badge', ['log' => $log])
                            </td>
                            <td class="py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $log->opened_at?->format('M d, H:i') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-sm text-zinc-500">No emails found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </flux:card>
</div>
