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

    <!-- Filters -->
    <div class="grid gap-4 md:grid-cols-3 mb-4">
        <div>
            <flux:input
                wire:model.live.debounce.300ms="windowSearch"
                placeholder="Search by name or CLAB no..."
                icon="magnifying-glass"
                size="sm"
            />
        </div>
        <div>
            <flux:select wire:model.live="windowContractorFilter" variant="listbox" searchable placeholder="Filter by Contractor" size="sm">
                <flux:select.option value="">All Contractors</flux:select.option>
                @foreach($windowContractors as $contractor)
                    <flux:select.option value="{{ $contractor['clab_no'] }}">
                        {{ $contractor['name'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div>
            <flux:button wire:click="clearWindowFilters" variant="filled" size="sm">
                <flux:icon.x-mark class="size-4 inline" />
                Clear Filters
            </flux:button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">CLAB No</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Window Status</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Last Changed</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Changed By</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span>
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($contractors as $contractor)
                    <flux:table.row :key="$contractor['contractor_clab_no']">
                        <flux:table.cell variant="strong">
                            {{ $contractor['contractor_name'] }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $contractor['contractor_clab_no'] }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                color="{{ $contractor['is_window_open'] ? 'green' : 'red' }}"
                            >
                                {{ $contractor['is_window_open'] ? 'Open' : 'Closed' }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
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
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $contractor['last_changed_by']->name ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell>
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
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="py-12 text-center">
                                <flux:icon.building-office class="mx-auto size-7 text-zinc-400 dark:text-zinc-600 mb-4" />
                                <p class="text-md font-medium text-zinc-900 dark:text-zinc-100 mb-2">No Contractors Found</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                    @if($windowSearch !== '' || $windowContractorFilter !== '')
                                        No contractors match your filters.
                                    @else
                                        No client users with contractor CLAB numbers found in system.
                                    @endif
                                </p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:pagination :paginator="$contractors" class="mt-4" />
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
