<!-- Window Statistics Cards -->
<div class="grid gap-4 md:grid-cols-4 mb-6">
    <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Contractors</p>
                <p class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ $windowStats['total_contractors'] ?? 0 }}
                </p>
            </div>
            <flux:icon.building-office class="size-8 text-blue-600 dark:text-blue-400" />
        </div>
    </flux:card>

    <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Windows Open</p>
                <p class="text-xl font-bold text-green-600 dark:text-green-400">
                    {{ $windowStats['windows_open'] ?? 0 }}
                </p>
            </div>
            <flux:icon.lock-open class="size-8 text-green-600 dark:text-green-400" />
        </div>
    </flux:card>

    <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Windows Closed</p>
                <p class="text-xl font-bold text-red-600 dark:text-red-400">
                    {{ $windowStats['windows_closed'] ?? 0 }}
                </p>
            </div>
            <flux:icon.lock-closed class="size-8 text-red-600 dark:text-red-400" />
        </div>
    </flux:card>

    <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Using Default</p>
                <p class="text-xl font-bold text-orange-600 dark:text-orange-400">
                    {{ $windowStats['using_default'] ?? 0 }}
                </p>
            </div>
            <flux:icon.calendar class="size-8 text-orange-600 dark:text-orange-400" />
        </div>
    </flux:card>
</div>

<!-- Contractor Window Settings Table -->
<flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                Contractor OT Entry Windows
            </h2>
            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                Control OT entry and transaction submission windows per contractor
            </p>
        </div>
    </div>

    <div class="rounded-lg bg-blue-50 dark:bg-blue-950 p-4 border border-blue-200 dark:border-blue-800 mb-6">
        <div class="flex gap-3">
            <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
            <div class="text-sm text-blue-900 dark:text-blue-100">
                <strong>Window Control:</strong>
                By default, all contractors follow the 1st-15th window rule.
                Use manual controls below to override this and open/close windows at any time.
                Opening a window automatically unlocks any locked entries.
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        Contractor
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        CLAB Number
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        Window Status
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        Last Changed
                    </th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        Changed By
                    </th>
                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($contractors as $contractor)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $contractor['contractor_name'] }}
                        </td>

                        <td class="px-4 py-4 whitespace-nowrap">
                            <code class="text-xs bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded">
                                {{ $contractor['contractor_clab_no'] }}
                            </code>
                        </td>

                        <td class="px-4 py-4 whitespace-nowrap">
                            <flux:badge
                                size="sm"
                                color="{{ $contractor['is_window_open'] ? 'green' : 'red' }}"
                            >
                                {{ $contractor['is_window_open'] ? 'Open' : 'Closed' }}
                            </flux:badge>
                        </td>

                        <td class="px-4 py-4 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                            @if($contractor['last_changed_at'])
                                <div class="text-xs">
                                    {{ $contractor['last_changed_at']->format('d M Y') }}<br>
                                    <span class="text-zinc-500">
                                        {{ $contractor['last_changed_at']->format('H:i') }}
                                    </span>
                                </div>
                            @else
                                <span class="text-xs text-zinc-500">-</span>
                            @endif
                        </td>

                        <td class="px-4 py-4 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $contractor['last_changed_by']->name ?? '-' }}
                        </td>

                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex justify-center gap-2">
                                @if($contractor['is_window_open'])
                                    <flux:button
                                        variant="danger"
                                        size="sm"
                                        icon="lock-closed"
                                        wire:click="openWindowModal('{{ $contractor['contractor_clab_no'] }}', '{{ $contractor['contractor_name'] }}', 'close')"
                                    >
                                        Close
                                    </flux:button>
                                @else
                                    <flux:button
                                        variant="primary"
                                        size="sm"
                                        icon="lock-open"
                                        wire:click="openWindowModal('{{ $contractor['contractor_clab_no'] }}', '{{ $contractor['contractor_name'] }}', 'open')"
                                    >
                                        Open
                                    </flux:button>
                                @endif

                                <flux:button
                                    variant="filled"
                                    size="sm"
                                    icon="clock"
                                    wire:click="viewContractorHistory('{{ $contractor['contractor_clab_no'] }}', '{{ $contractor['contractor_name'] }}')"
                                >
                                    History
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center">
                            <flux:icon.building-office class="mx-auto size-7 text-zinc-400 mb-4" />
                            <p class="text-md font-medium text-zinc-900 dark:text-zinc-100 mb-2">
                                No Contractors Found
                            </p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                No client users with contractor CLAB numbers found in system.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</flux:card>

<!-- Recent Changes -->
@if(!empty($windowStats['recent_changes']) && count($windowStats['recent_changes']) > 0)
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg mt-6">
        <h3 class="text-md font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
            Recent Window Changes
        </h3>

        <div class="space-y-3">
            @foreach($windowStats['recent_changes'] as $change)
                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                    <div class="flex items-center gap-3">
                        <flux:badge
                            size="sm"
                            color="{{ $change->action === 'opened' ? 'green' : 'red' }}"
                        >
                            {{ ucfirst($change->action) }}
                        </flux:badge>
                        <div>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $change->contractor_clab_no }}
                            </p>
                            @if($change->remarks)
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $change->remarks }}
                                </p>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">
                            {{ $change->changedBy->name ?? 'Unknown User' }}
                        </p>
                        <p class="text-xs text-zinc-500">
                            {{ $change->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>
@endif
