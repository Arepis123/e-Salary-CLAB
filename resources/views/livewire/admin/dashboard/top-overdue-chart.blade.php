<flux:card class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-lg">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Top 5 Overdue Clients</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">Clients who most frequently miss their payment deadline</p>
    </div>
    @if(count($topOverdueChartData['labels'] ?? []) > 0)
        <!-- Data container for Top Overdue Clients chart -->
        <div id="topOverdueChartDataContainer"
            data-labels='@json($topOverdueChartData["labels"])'
            data-counts='@json($topOverdueChartData["data"])'
            style="display: none;"></div>
        <div class="relative h-64">
            <canvas id="topOverdueChart" wire:ignore></canvas>
        </div>
    @else
        <div class="flex h-64 flex-col items-center justify-center gap-2 text-center">
            <flux:icon.check-circle class="size-10 text-green-500" />
            <p class="text-sm text-zinc-500 dark:text-zinc-400">No overdue payrolls. Every client is on time.</p>
        </div>
    @endif
</flux:card>
