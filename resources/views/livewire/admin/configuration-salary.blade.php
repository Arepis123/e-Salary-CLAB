<!-- Statistics Cards -->
<div class="grid gap-4 md:grid-cols-4">
    <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Contracted Workers</p>
                <p class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total_workers']) }}</p>
            </div>
            <flux:icon.users class="size-8 text-blue-600 dark:text-blue-400" />
        </div>
    </flux:card>

    <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Active Contracts</p>
                <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['active_workers']) }}</p>
            </div>
            <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
        </div>
    </flux:card>

    <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Average Salary</p>
                <p class="text-xl font-bold text-orange-600 dark:text-orange-400">RM {{ number_format($stats['avg_salary'], 2) }}</p>
            </div>
            <flux:icon.currency-dollar class="size-8 text-orange-600 dark:text-orange-400" />
        </div>
    </flux:card>

    <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Salary Cost</p>
                <p class="text-xl font-bold text-purple-600 dark:text-purple-400">RM {{ number_format($stats['total_salary_cost'], 2) }}</p>
            </div>
            <flux:icon.banknotes class="size-8 text-purple-600 dark:text-purple-400" />
        </div>
    </flux:card>
</div>

<!-- Workers Table -->
<flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Contracted Worker Salaries</h2>
            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">Only showing workers with active or expired contracts</p>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="mb-6">
        <div class="grid gap-4 md:grid-cols-5">
            <div class="md:col-span-2">
                <flux:input
                    wire:model.live="search"
                    placeholder="Search by name, passport, or ID"
                    icon="magnifying-glass"
                    size="sm"
                />
            </div>
            <div>
                <flux:select wire:model.live="countryFilter" variant="listbox" searchable placeholder="Filter by Country" size="sm">
                    <flux:select.option value="">All Countries</flux:select.option>
                    @foreach($countries as $code => $countryName)
                        <flux:select.option value="{{ $code }}">{{ $countryName }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="positionFilter" variant="listbox" searchable placeholder="Filter by Position" size="sm">
                    <flux:select.option value="">All Positions</flux:select.option>
                    @foreach($positions as $code => $positionName)
                        @php
                            // Add spaces around & symbol for better readability
                            $formattedPosition = preg_replace('/\s*&\s*/', ' & ', $positionName);
                        @endphp
                        <flux:select.option value="{{ $code }}">{{ $formattedPosition }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:button variant="ghost" size="sm" wire:click="clearFilters" icon="x-mark" icon-variant="outline">
                    Clear Filters
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sortByColumn('name')">
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'country'" :direction="$sortDirection" wire:click="sortByColumn('country')">
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Country</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Position</span>
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'salary'" :direction="$sortDirection" wire:click="sortByColumn('salary')">
                    <span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</span>
                </flux:table.column>
                <flux:table.column>
                    <span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span>
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($workers as $worker)
                    <flux:table.row :key="$worker->wkr_id">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :name="$worker->wkr_name" color="auto"/>
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->wkr_name }}</p>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">ID: {{ $worker->wkr_id }}</p>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell variant="strong">{{ $worker->wkr_passno }}</flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $worker->country?->cty_desc ?? $worker->wkr_country ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            @php
                                $position = $worker->workTrade?->trade_desc ?? $worker->wkr_wtrade ?? '-';
                                // Add spaces around & symbol for better readability
                                $position = preg_replace('/\s*&\s*/', ' & ', $position);
                            @endphp
                            {{ $position }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            <span class="font-semibold text-blue-600 dark:text-blue-400">
                                RM {{ number_format($worker->wkr_salary, 2) }}
                            </span>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex justify-center">
                                <flux:button variant="filled" size="sm" wire:click="openEditModal({{ $worker->wkr_id }})" icon="pencil" icon-variant="outline">
                                    Edit Salary
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="py-12 text-center">
                                <flux:icon.users class="mx-auto size-7 text-zinc-400 dark:text-zinc-600 mb-4" />
                                <p class="text-md font-medium text-zinc-900 dark:text-zinc-100 mb-2">No Workers Found</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                    Try adjusting your search or filters.
                                </p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $workers->links() }}
    </div>
</flux:card>

<!-- Salary Adjustment History -->
<flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Salary Adjustment History</h2>
            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">Recent salary changes (last 50 adjustments)</p>
        </div>
        <flux:button variant="ghost" size="sm" wire:click="toggleHistory" icon="{{ $showHistory ? 'chevron-up' : 'chevron-down' }}" icon-variant="outline">
            {{ $showHistory ? 'Hide' : 'Show' }} History
        </flux:button>
    </div>

    @if($showHistory)
        <div class="overflow-x-auto" x-data x-transition>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Date</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Old Salary</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">New Salary</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Change</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Adjusted By</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Remarks</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($salaryHistory as $history)
                        <flux:table.row :key="$history->id">
                            <flux:table.cell variant="strong">
                                <div class="text-xs">
                                    {{ $history->created_at->format('d M Y') }}<br>
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ $history->created_at->format('H:i') }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $history->worker_name }}</p>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $history->worker_passport }}</p>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <span class="text-zinc-600 dark:text-zinc-400">RM {{ number_format($history->old_salary, 2) }}</span>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <span class="text-blue-600 dark:text-blue-400 font-semibold">RM {{ number_format($history->new_salary, 2) }}</span>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                @php
                                    $difference = $history->new_salary - $history->old_salary;
                                    $isIncrease = $difference > 0;
                                @endphp
                                <div class="text-xs">
                                    <span class="{{ $isIncrease ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $isIncrease ? '+' : '' }}RM {{ number_format(abs($difference), 2) }}
                                    </span>
                                    <br>
                                    <span class="text-zinc-500 dark:text-zinc-400">
                                        ({{ $isIncrease ? '+' : '' }}{{ number_format($history->percentage_change, 1) }}%)
                                    </span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                {{ $history->adjustedBy->name ?? 'Unknown' }}
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ $history->remarks ?: '-' }}</span>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">
                                <div class="py-8 text-center">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">No History Yet</p>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                        Salary adjustments will appear here after you make changes.
                                    </p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</flux:card>

<!-- Edit Salary Modal -->
@if($showEditModal)
    <flux:modal name="edit-salary" class="md:w-96 space-y-6" wire:model="showEditModal">
        <div>
            <flux:heading size="lg">Edit Basic Salary</flux:heading>
            <flux:subheading>Update basic salary for {{ $editingWorkerName }}</flux:subheading>
        </div>

        <flux:input
            wire:model="editingBasicSalary"
            label="Basic Salary (RM)"
            type="number"
            step="0.01"
            min="0"
            max="99999.99"
            placeholder="0.00"
        />

        <flux:textarea
            wire:model="remarks"
            label="Remarks (Optional)"
            rows="3"
            placeholder="Reason for salary adjustment..."
        />

        <div class="flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="closeEditModal">Cancel</flux:button>
            <flux:button variant="primary" wire:click="updateBasicSalary">
                <flux:icon.check class="size-4" />
                Save Changes
            </flux:button>
        </div>
    </flux:modal>
@endif
