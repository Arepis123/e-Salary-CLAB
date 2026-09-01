<!-- Worker Settings Tab -->
<div class="space-y-6">

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
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

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Average Salary</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">RM {{ number_format($stats['avg_salary'] ?? 0, 2) }}</p>
                </div>
                <div class="rounded-full bg-orange-100 dark:bg-orange-900/30 p-3">
                    <flux:icon.currency-dollar class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Salary Cost</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">RM {{ number_format($stats['total_salary_cost'] ?? 0, 2) }}</p>
                </div>
                <div class="rounded-full bg-purple-100 dark:bg-purple-900/30 p-3">
                    <flux:icon.banknotes class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Manage Workers</h3>
                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">Basic salary and active status for workers with contracts</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5 mb-4">
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="workerSearch"
                    placeholder="Search by name or passport..."
                    icon="magnifying-glass"
                    size="sm"
                />
            </div>
            <div>
                <flux:select wire:model.live="workerContractorFilter" variant="listbox" searchable placeholder="Filter by Contractor" size="sm">
                    <flux:select.option value="">All Contractors</flux:select.option>
                    @foreach($workerContractors as $contractor)
                        <flux:select.option value="{{ $contractor['clab_no'] }}">{{ $contractor['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
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
            <div class="flex gap-2">
                <flux:select wire:model.live="workerStatusFilter" variant="listbox" placeholder="Filter by Status" size="sm">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="active">Active Only</flux:select.option>
                    <flux:select.option value="inactive">Inactive Only</flux:select.option>
                </flux:select>
                <flux:button wire:click="clearWorkerFilters" variant="filled" size="sm" icon="x-mark" icon-variant="outline">
                    Clear
                </flux:button>
            </div>
        </div>

        <!-- Workers Table -->
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">#</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sortByColumn('name')">
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Worker</span>
                    </flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'country'" :direction="$sortDirection" wire:click="sortByColumn('country')">
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Country</span>
                    </flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Position</span></flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'salary'" :direction="$sortDirection" wire:click="sortByColumn('salary')">
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</span>
                    </flux:table.column>
                    <flux:table.column><span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                    <flux:table.column><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($workersList as $worker)
                        <flux:table.row :key="$worker['id']">
                            <flux:table.cell>
                                {{ $workersList->firstItem() + $loop->index }}
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" :name="$worker['name']" color="auto"/>
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $worker['name'] }}</p>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">ID: {{ $worker['id'] }}</p>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $worker['passport'] }}
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $worker['country'] }}
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $worker['position'] }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="truncate max-w-xs">{{ $worker['contractor_name'] }}</span>
                                    <span class="text-xs text-zinc-500">{{ $worker['contractor_clab'] }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <span class="font-semibold text-blue-600 dark:text-blue-400">
                                    RM {{ number_format($worker['salary'], 2) }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($worker['is_inactive'])
                                    <flux:badge color="red" size="sm">Inactive</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">Active</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-center">
                                    <flux:dropdown align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                        <flux:menu>
                                            <flux:menu.item
                                                icon="pencil"
                                                wire:click="openEditModal('{{ $worker['id'] }}')"
                                            >
                                                Edit Salary
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            @if($worker['is_inactive'])
                                                <flux:menu.item
                                                    icon="check-circle"
                                                    wire:click="reactivateWorker('{{ $worker['id'] }}')"
                                                >
                                                    Reactivate
                                                </flux:menu.item>
                                            @else
                                                <flux:menu.item
                                                    variant="danger"
                                                    icon="x-circle"
                                                    wire:click="openDeactivateModal('{{ $worker['id'] }}', '{{ addslashes($worker['name']) }}', '{{ $worker['passport'] }}', '{{ $worker['contractor_clab'] }}')"
                                                >
                                                    Deactivate
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9" class="text-center py-8">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.users class="size-8 text-zinc-400" />
                                    <p class="text-zinc-600 dark:text-zinc-400">No workers found matching your filters.</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <!-- Pagination -->
        <flux:pagination :paginator="$workersList" class="mt-4" />
    </flux:card>

    <!-- Salary Adjustment History -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Salary Adjustment History</h3>
                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ number_format($salaryHistory->total()) }} salary change(s), newest first</p>
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

                <!-- Pagination -->
                <flux:pagination :paginator="$salaryHistory" class="mt-4" />
            </div>
        @endif
    </flux:card>

    <!-- Inactive Workers History -->
    @if($inactiveWorkersList->total() > 0)
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Deactivated Workers</h3>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ number_format($inactiveWorkersList->total()) }} worker(s) currently deactivated</p>
                </div>
                <flux:button variant="ghost" size="sm" wire:click="toggleDeactivatedWorkers" icon="{{ $showDeactivatedWorkers ? 'chevron-up' : 'chevron-down' }}" icon-variant="outline">
                    {{ $showDeactivatedWorkers ? 'Hide' : 'Show' }} List
                </flux:button>
            </div>

            @if($showDeactivatedWorkers)
            <div class="overflow-x-auto" x-data x-transition>
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
                        <flux:table.row :key="$inactive->id">
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
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <!-- Pagination -->
            <flux:pagination :paginator="$inactiveWorkersList" class="mt-4" />
            </div>
            @endif
        </flux:card>
    @endif
</div>

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
                Save Changes
            </flux:button>
        </div>
    </flux:modal>
@endif

<!-- Remove from Payroll Modal -->
<flux:modal name="remove-from-payroll" class="md:w-96 space-y-6" wire:model="showRemoveFromPayrollModal">
    <div>
        <flux:heading size="lg">Remove from Current Payroll?</flux:heading>
        <flux:subheading>{{ $deactivatingWorkerName }}</flux:subheading>
    </div>

    @if($payrollSubmissionToRemove)
    <div class="rounded-lg bg-blue-50 dark:bg-blue-950 p-4 border border-blue-200 dark:border-blue-800">
        <div class="flex gap-3">
            <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
            <div class="text-sm text-blue-900 dark:text-blue-100">
                <p>This worker is currently included in the <strong>{{ $payrollSubmissionToRemove['month_year'] }}</strong> payroll (status: <strong>{{ $payrollSubmissionToRemove['status'] }}</strong>).</p>
                <p class="mt-2">Do you want to remove <strong>{{ $deactivatingWorkerName }}</strong> from this payroll?</p>
            </div>
        </div>
    </div>
    @endif

    <div class="flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="skipRemoveFromPayroll">No, Keep in Payroll</flux:button>
        <flux:button variant="danger" wire:click="confirmRemoveFromPayroll">
            Yes, Remove from Payroll
        </flux:button>
    </div>
</flux:modal>

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
