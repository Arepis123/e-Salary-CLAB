<!-- Worker Settings Tab -->
<div class="space-y-6">
    <!-- Info Card -->
    <flux:card class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
        <div class="flex gap-3">
            <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
            <div class="text-sm text-blue-800 dark:text-blue-200">
                <p class="font-medium mb-1">Worker Status Management</p>
                <p>Set workers as inactive to hide them from contractor timesheets. Inactive workers will not appear in OT entry forms or payroll submissions. This does not affect the external worker database.</p>
            </div>
        </div>
    </flux:card>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-3">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Workers</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $workerStats['total'] ?? 0 }}</p>
                </div>
                <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                    <flux:icon.users class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Active Workers</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $workerStats['active'] ?? 0 }}</p>
                </div>
                <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                    <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Inactive Workers</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $workerStats['inactive'] ?? 0 }}</p>
                </div>
                <div class="rounded-full bg-red-100 dark:bg-red-900/30 p-3">
                    <flux:icon.x-circle class="size-6 text-red-600 dark:text-red-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-4">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Manage Worker Status</h3>
        </div>

        <div class="grid gap-4 md:grid-cols-4 mb-4">
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="workerSearch"
                    placeholder="Search by name or passport..."
                    icon="magnifying-glass"
                    size="sm"
                />
            </div>
            <div>
                <flux:select wire:model.live="workerContractorFilter" variant="listbox" placeholder="Filter by Contractor" size="sm">
                    <flux:select.option value="">All Contractors</flux:select.option>
                    @foreach($workerContractors as $contractor)
                        <flux:select.option value="{{ $contractor['clab_no'] }}">{{ $contractor['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="workerStatusFilter" variant="listbox" placeholder="Filter by Status" size="sm">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="active">Active Only</flux:select.option>
                    <flux:select.option value="inactive">Inactive Only</flux:select.option>
                </flux:select>
            </div>
            <div>
                <flux:button wire:click="clearWorkerFilters" variant="filled" size="sm">
                    <flux:icon.x-mark class="size-4 inline" />
                    Clear Filters
                </flux:button>             
            </div>
        </div>

        <!-- Workers Table -->
        <flux:table>
            <flux:table.columns>
                <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker ID</span></flux:table.column>
                <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Name</span></flux:table.column>
                <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</span></flux:table.column>
                <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span></flux:table.column>
                <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($workersList as $worker)
                    <flux:table.rows :key="$worker['id']">
                        <flux:table.cell variant="strong">
                            {{ $worker['id'] }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $worker['name'] }}
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $worker['passport'] }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="truncate max-w-xs">{{ $worker['contractor_name'] }}</span>
                                <span class="text-xs text-zinc-500">{{ $worker['contractor_clab'] }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($worker['is_inactive'])
                                <flux:badge color="red" size="sm">Inactive</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">Active</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($worker['is_inactive'])
                                <flux:button
                                    wire:click="reactivateWorker('{{ $worker['id'] }}')"
                                    variant="filled"
                                    size="sm"
                                >
                                    <flux:icon.check-circle class="size-4 mr-1" />
                                    Reactivate
                                </flux:button>
                            @else
                                <flux:button
                                    wire:click="openDeactivateModal('{{ $worker['id'] }}', '{{ addslashes($worker['name']) }}', '{{ $worker['passport'] }}', '{{ $worker['contractor_clab'] }}')"
                                    variant="filled"
                                    size="sm"
                                >
                                    <flux:icon.x-circle class="size-4 mr-1 inline" />
                                    Deactivate
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.rows>
                @empty
                    <flux:table.rows>
                        <flux:table.cell colspan="6" class="text-center py-8">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.users class="size-8 text-zinc-400" />
                                <p class="text-zinc-600 dark:text-zinc-400">No workers found matching your filters.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.rows>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <!-- Pagination -->
        @if($workersPagination['total'] > $workersPagination['per_page'])
            <div class="mt-4 flex items-center justify-between border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Showing {{ $workersPagination['from'] }} to {{ $workersPagination['to'] }} of {{ $workersPagination['total'] }} workers
                </p>
                <div class="flex gap-2">
                    <flux:button
                        wire:click="$set('workersPage', {{ $workersPagination['current_page'] - 1 }})"
                        variant="ghost"
                        size="sm"
                        :disabled="$workersPagination['current_page'] <= 1"
                    >
                        Previous
                    </flux:button>
                    <flux:button
                        wire:click="$set('workersPage', {{ $workersPagination['current_page'] + 1 }})"
                        variant="ghost"
                        size="sm"
                        :disabled="$workersPagination['current_page'] >= $workersPagination['last_page']"
                    >
                        Next
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:card>

    <!-- Inactive Workers History -->
    @if(count($inactiveWorkersList) > 0)
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Recently Deactivated Workers</h3>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Reason</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Deactivated By</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Date</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($inactiveWorkersList as $inactive)
                        <flux:table.rows :key="$inactive->id">
                            <flux:table.cell variant="strong">
                                <div class="flex flex-col">
                                    <span>{{ $inactive->worker_name }}</span>
                                    <span class="text-xs text-zinc-500">{{ $inactive->worker_passport }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $inactive->contractor_clab_no }}</flux:table.cell>
                            <flux:table.cell>{{ $inactive->reason ?: '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $inactive->deactivatedBy->name ?? 'Unknown' }}</flux:table.cell>
                            <flux:table.cell>{{ $inactive->deactivated_at->format('d M Y H:i') }}</flux:table.cell>
                        </flux:table.rows>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>

<!-- Deactivate Worker Modal -->
<flux:modal name="deactivate-worker" class="md:w-96 space-y-6" wire:model="showDeactivateModal">
    <div>
        <flux:heading size="lg">Deactivate Worker</flux:heading>
        <flux:subheading>{{ $deactivatingWorkerName }}</flux:subheading>
    </div>

    <div class="rounded-lg bg-yellow-50 dark:bg-yellow-950 p-4 border border-yellow-200 dark:border-yellow-800">
        <div class="flex gap-3">
            <flux:icon.exclamation-triangle class="size-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
            <div class="text-sm text-yellow-900 dark:text-yellow-100">
                <strong>This will:</strong>
                <ul class="list-disc ml-5 mt-2">
                    <li>Hide this worker from contractor timesheets</li>
                    <li>Prevent OT entry for this worker</li>
                    <li>Exclude from payroll submissions</li>
                </ul>
            </div>
        </div>
    </div>

    <flux:textarea
        wire:model="deactivateReason"
        label="Reason (Optional)"
        rows="3"
        placeholder="Why is this worker being deactivated?"
    />

    <div class="flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="closeDeactivateModal">Cancel</flux:button>
        <flux:button variant="danger" wire:click="confirmDeactivate">
            Deactivate Worker
        </flux:button>
    </div>
</flux:modal>
