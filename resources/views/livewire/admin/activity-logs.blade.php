<div class="flex w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Activity Logs</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Track all contractor activities in the system</p>
        </div>
    </div>

    <!-- Main Content Card -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <!-- Header with Filter Toggle -->
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Activity History</h2>
            <div class="flex gap-2">
                <flux:button variant="ghost" size="sm" icon="funnel" icon-variant="outline" wire:click="toggleFilters">
                    Filter
                </flux:button>
            </div>
        </div>

        <!-- Filters -->
        @if($showFilters)
        <div class="mb-6" x-data x-transition>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                <!-- Date From -->
                <div>
                    <flux:input
                        wire:model.live="startDate"
                        label="From Date"
                        type="date"
                        size="sm"
                    />
                </div>

                <!-- Date To -->
                <div>
                    <flux:input
                        wire:model.live="endDate"
                        label="To Date"
                        type="date"
                        size="sm"
                    />
                </div>

                <!-- Module Filter -->
                <div>
                    <flux:select wire:model.live="moduleFilter" variant="listbox" placeholder="Filter by Module" size="sm" label="Module">
                        <flux:select.option value="">All Modules</flux:select.option>
                        @foreach($modules as $module)
                            <flux:select.option value="{{ $module }}">{{ ucfirst($module) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Action Filter -->
                <div>
                    <flux:select wire:model.live="actionFilter" variant="listbox" placeholder="Filter by Action" size="sm" label="Action">
                        <flux:select.option value="">All Actions</flux:select.option>
                        @foreach($actions as $action)
                            <flux:select.option value="{{ $action }}">{{ ucfirst(str_replace('_', ' ', $action)) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Contractor Filter -->
                <div>
                    <flux:select wire:model.live="contractorFilter" variant="listbox" placeholder="Filter by Contractor" size="sm" label="Contractor">
                        <flux:select.option value="">All Contractors</flux:select.option>
                        @foreach($contractors as $clabNo => $name)
                            <flux:select.option value="{{ $clabNo }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <!-- Search and Clear -->
            <div class="grid gap-4 md:grid-cols-2 mt-4">
                <div>
                    <flux:input
                        wire:model.live="search"
                        placeholder="Search description, user name, or email..."
                        icon="magnifying-glass"
                        size="sm"
                    />
                </div>
                <div>
                    <flux:button variant="filled" size="sm" wire:click="clearFilters">
                        <flux:icon.x-mark class="size-4 inline" />
                        Clear Filters
                    </flux:button>
                </div>
            </div>
        </div>
        @endif

        <!-- Activity Table -->
        <flux:table>
            <flux:table.columns>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Date & Time</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Contractor</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Module</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Action</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Description</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">IP Address</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($logs as $log)
                    <flux:table.rows :key="$log->id">
                        <flux:table.cell variant="strong">
                            <div class="text-xs">
                                <div>{{ $log->created_at->format('d M Y') }}</div>
                                <div class="text-zinc-500 dark:text-zinc-400">{{ $log->created_at->format('h:i:s A') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="max-w-xs truncate">
                            <div class="text-xs">
                                <div>{{ strtoupper($log->user_name) ?? 'Unknown' }}</div>
                                @if($log->contractor_clab_no)
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ $log->contractor_clab_no }}</div>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @php
                                $moduleColors = [
                                    'payment' => 'zinc',
                                    'timesheet' => 'zinc',
                                    'worker' => 'zinc',
                                    'invoice' => 'zinc',
                                    'authentication' => 'zinc',
                                ];
                                $moduleColor = $moduleColors[$log->module] ?? 'zinc';
                                $moduleIcons = [
                                    'payment' => 'wallet',
                                    'timesheet' => 'calendar',
                                    'worker' => 'users',
                                    'invoice' => 'document-text',
                                    'authentication' => 'fingerprint',
                                ];
                                $moduleIcon = $moduleIcons[$log->module] ?? 'question-mark-circle';                                
                            @endphp
                            <flux:badge color="{{ $moduleColor }}" size="sm" inset="top bottom" icon="{{ $moduleIcon }}">
                                {{ ucfirst($log->module) }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            @php
                                $actionColors = [
                                    'created' => 'green',
                                    'updated' => 'blue',
                                    'deleted' => 'red',
                                    'submitted' => 'purple',
                                    'completed' => 'green',
                                    'failed' => 'red',
                                    'initiated' => 'blue',
                                    'draft_saved' => 'yellow',
                                    'login' => 'lime',
                                    'logout' => 'emerald',                                    
                                ];
                                $actionColor = $actionColors[$log->action] ?? 'zinc';
                            @endphp
                            <flux:badge color="{{ $actionColor }}" size="sm" inset="top bottom">
                                {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="max-w-md">
                            <div class="text-xs text-zinc-700 dark:text-zinc-300 truncate">
                                {{ $log->description }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400 font-mono">
                                {{ $log->ip_address ?? '-' }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" icon="eye" wire:click="viewDetails({{ $log->id }})">
                                View
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.rows>
                @empty
                    <flux:table.rows>
                        <flux:table.cell colspan="7" class="text-center">
                            @if($search || $moduleFilter || $actionFilter || $contractorFilter || $startDate || $endDate)
                                <div class="py-8">
                                    <flux:icon.magnifying-glass class="size-12 mx-auto text-zinc-400 dark:text-zinc-600 mb-2" />
                                    <p class="text-zinc-600 dark:text-zinc-400">No activity logs found matching your filters.</p>
                                </div>
                            @else
                                <div class="py-8">
                                    <flux:icon.document-text class="size-12 mx-auto text-zinc-400 dark:text-zinc-600 mb-2" />
                                    <p class="text-zinc-600 dark:text-zinc-400">No activity logs recorded yet.</p>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.rows>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <!-- Pagination -->
        @if($pagination['total'] > 0)
            <div class="mt-4 flex items-center justify-between border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Showing {{ $pagination['from'] }} to {{ $pagination['to'] }} of {{ $pagination['total'] }} results
                </p>
                <div class="flex items-center gap-2">
                    @if($pagination['current_page'] > 1)
                        <flux:button variant="ghost" size="sm" wire:click="$set('page', {{ $pagination['current_page'] - 1 }})">Previous</flux:button>
                    @else
                        <flux:button variant="ghost" size="sm" disabled>Previous</flux:button>
                    @endif

                    @for($i = 1; $i <= min(5, $pagination['last_page']); $i++)
                        @if($i == $pagination['current_page'])
                            <flux:button variant="primary" size="xs">{{ $i }}</flux:button>
                        @else
                            <flux:button variant="ghost" size="xs" wire:click="$set('page', {{ $i }})">{{ $i }}</flux:button>
                        @endif
                    @endfor

                    @if($pagination['last_page'] > 5)
                        <span class="text-zinc-500">...</span>
                    @endif

                    @if($pagination['current_page'] < $pagination['last_page'])
                        <flux:button variant="ghost" size="sm" wire:click="$set('page', {{ $pagination['current_page'] + 1 }})">Next</flux:button>
                    @else
                        <flux:button variant="ghost" size="sm" disabled>Next</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:card>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedLog)
        <flux:modal wire:model="showDetailModal" class="w-full max-w-3xl">
            <div class="space-y-4 p-4 sm:p-6">
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                        Activity Log Details
                    </h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        {{ $selectedLog->created_at->format('d M Y, h:i:s A') }}
                    </p>
                </div>

                <!-- User Info -->
                <flux:card class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <flux:icon.user class="size-8 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedLog->user_name ?? 'Unknown User' }}</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $selectedLog->user_email ?? 'No email' }}</p>
                            @if($selectedLog->contractor_clab_no)
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">CLAB No: {{ $selectedLog->contractor_clab_no }}</p>
                            @endif
                        </div>
                    </div>
                </flux:card>

                <!-- Activity Details -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Activity Details</h3>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Module</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                                {{ ucfirst($selectedLog->module) }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Action</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                                {{ ucfirst(str_replace('_', ' ', $selectedLog->action)) }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 sm:col-span-2">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Description</p>
                            <p class="text-sm text-zinc-900 dark:text-zinc-100 mt-1">
                                {{ $selectedLog->description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Request Information -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Request Information</h3>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">IP Address</p>
                            <p class="text-sm font-mono text-zinc-900 dark:text-zinc-100 mt-1">
                                {{ $selectedLog->ip_address ?? 'N/A' }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Method</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                                {{ $selectedLog->method ?? 'N/A' }}
                            </p>
                        </div>

                        @if($selectedLog->url)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 sm:col-span-2">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">URL</p>
                                <p class="text-xs font-mono text-zinc-700 dark:text-zinc-300 mt-1 break-all">
                                    {{ $selectedLog->url }}
                                </p>
                            </div>
                        @endif

                        @if($selectedLog->user_agent)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 sm:col-span-2">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">User Agent</p>
                                <p class="text-xs font-mono text-zinc-700 dark:text-zinc-300 mt-1 break-all">
                                    {{ $selectedLog->user_agent }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Properties/Metadata -->
                @if($selectedLog->properties && is_array($selectedLog->properties) && count($selectedLog->properties) > 0)
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Additional Information</h3>
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-800/50 max-h-60 overflow-y-auto">
                            <pre class="text-xs text-zinc-700 dark:text-zinc-300 font-mono whitespace-pre-wrap break-all">{{ json_encode($selectedLog->properties, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                @endif

                <!-- Changes -->
                @if(method_exists($selectedLog, 'getChangesAttribute') && count($selectedLog->changes) > 0)
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Changes Made</h3>
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-800/50">
                            @foreach($selectedLog->changes as $field => $change)
                                <div class="mb-3 last:mb-0">
                                    <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ ucfirst(str_replace('_', ' ', $field)) }}</p>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div class="rounded bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-2">
                                            <p class="text-red-600 dark:text-red-400 font-medium mb-1">Old:</p>
                                            <p class="text-zinc-700 dark:text-zinc-300">{{ is_array($change['old']) ? json_encode($change['old']) : ($change['old'] ?? 'null') }}</p>
                                        </div>
                                        <div class="rounded bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-2">
                                            <p class="text-green-600 dark:text-green-400 font-medium mb-1">New:</p>
                                            <p class="text-zinc-700 dark:text-zinc-300">{{ is_array($change['new']) ? json_encode($change['new']) : ($change['new'] ?? 'null') }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="closeDetailModal" variant="ghost">Close</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
