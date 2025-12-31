<div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Worker Management</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Manage all construction workers</p>
            </div>
            <flux:button variant="primary" href="#" wire:navigate>
                <flux:icon.plus class="size-4" />
                Add New Worker
            </flux:button>
        </div>

        <!-- Statistics Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Workers</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total'] }}</p>
                    </div>
                    <flux:icon.users class="size-8 text-blue-600 dark:text-blue-400" />
                </div>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Active</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['active'] }}</p>
                    </div>
                    <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
                </div>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">On Leave</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['on_leave'] }}</p>
                    </div>
                    <flux:icon.clock class="size-8 text-orange-600 dark:text-orange-400" />
                </div>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Inactive</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $stats['inactive'] }}</p>
                    </div>
                    <flux:icon.x-circle class="size-8 text-red-600 dark:text-red-400" />
                </div>
            </flux:card>
        </div>

        <!-- Workers Table -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Workers</h2>
                <div class="flex">
                    <flux:button variant="ghost" size="sm" wire:click="export">
                        <flux:icon.arrow-down-tray class="size-4 inline" />
                        Export
                    </flux:button>
                    <flux:button variant="ghost" size="sm" wire:click="toggleFilters">
                        <flux:icon.funnel class="size-4 inline" />
                        Filter
                    </flux:button>
                </div>
            </div>

        <!-- Filters and Search -->
        @if($showFilters)
        <div class="mb-6" x-data x-transition>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                <div class="md:col-span-2">
                    <flux:input
                        wire:model.live="search"
                        placeholder="Search by name or passport number"
                        icon="magnifying-glass"
                        size="sm"
                    />
                </div>
                <div>
                    <flux:select wire:model.live="clientFilter" variant="listbox" searchable placeholder="Filter by Client" size="sm">
                        <flux:select.option value="">All Clients</flux:select.option>
                        @foreach($clients as $clientId => $clientName)
                            <flux:select.option value="{{ $clientId }}">{{ $clientName }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model.live="positionFilter" variant="listbox" placeholder="Filter by Position" size="sm">
                        <flux:select.option value="">All Positions</flux:select.option>
                        @foreach($positions as $position)
                            <flux:select.option value="{{ $position }}">{{ $position }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Filter by Status" size="sm">
                        <flux:select.option value="">All Status</flux:select.option>
                        <flux:select.option value="Active">Active</flux:select.option>
                        <flux:select.option value="Inactive">Inactive</flux:select.option>
                    </flux:select>
                </div>
                <div>
                    <flux:button variant="filled" size="sm" wire:click="clearFilters">
                        <flux:icon.x-mark class="size-4 inline" />
                        Clear
                    </flux:button>
                </div>
            </div>
        </div>
        @endif

            <flux:table>
                <flux:table.columns>
                    <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sortByColumn('name')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Name</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'passport'" :direction="$sortDirection" wire:click="sortByColumn('passport')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport Number</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'position'" :direction="$sortDirection" wire:click="sortByColumn('position')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Position</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'country'" :direction="$sortDirection" wire:click="sortByColumn('country')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Country</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'client'" :direction="$sortDirection" wire:click="sortByColumn('client')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400 max-md:hidden">Current Client</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'passport_expiry'" :direction="$sortDirection" wire:click="sortByColumn('passport_expiry')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport Expiry</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'permit_expiry'" :direction="$sortDirection" wire:click="sortByColumn('permit_expiry')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Permit Expiry</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sortByColumn('status')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($workers as $index => $worker)
                        @php
                            $passportExpiryTime = $worker['passport_expiry'] !== 'N/A' ? strtotime($worker['passport_expiry']) : null;
                            $permitExpiryTime = $worker['permit_expiry'] !== 'N/A' ? strtotime($worker['permit_expiry']) : null;
                            $oneMonthAway = strtotime('+1 month');
                            $threeMonthsAway = strtotime('+3 months');

                            $passportClass = '';
                            $permitClass = '';

                            if ($passportExpiryTime) {
                                if ($passportExpiryTime < $oneMonthAway) {
                                    $passportClass = 'text-red-600 dark:text-red-400 font-medium';
                                } elseif ($passportExpiryTime < $threeMonthsAway) {
                                    $passportClass = 'text-orange-600 dark:text-orange-400 font-medium';
                                }
                            }

                            if ($permitExpiryTime) {
                                if ($permitExpiryTime < $oneMonthAway) {
                                    $permitClass = 'text-red-600 dark:text-red-400 font-medium';
                                } elseif ($permitExpiryTime < $threeMonthsAway) {
                                    $permitClass = 'text-orange-600 dark:text-orange-400 font-medium';
                                }
                            }
                        @endphp
                        <flux:table.rows :key="$worker['id']">
                            <flux:table.cell>{{ $pagination['from'] + $index }}</flux:table.cell>

                            <flux:table.cell variant="strong" class="flex items-center gap-3">
                                <flux:avatar size="xs" color="auto" name="{{ $worker['name'] }}" />
                                {{ $worker['name'] }}
                            </flux:table.cell>

                            <flux:table.cell variant="strong">{{ $worker['passport'] }}</flux:table.cell>

                            <flux:table.cell variant="strong">{{ $worker['position'] }}</flux:table.cell>

                            <flux:table.cell variant="strong">{{ $worker['country'] }}</flux:table.cell>

                            <flux:table.cell variant="strong" class="max-w-xs truncate">{{ $worker['client'] }}</flux:table.cell>

                            <flux:table.cell variant="strong">
                                <span class="{{ $passportClass }}">
                                    {{ $worker['passport_expiry'] }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                <span class="{{ $permitClass }}">
                                    {{ $worker['permit_expiry'] }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($worker['status'] === 'Active')
                                    <flux:badge color="green" size="sm" inset="top bottom">Active</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" inset="top bottom">Inactive</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" href="{{ route('workers.show', $worker['id']) }}">View Details</flux:menu.item>
                                        {{-- <flux:menu.item icon="pencil">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger">Delete</flux:menu.item> --}}
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.rows>
                    @empty
                        <flux:table.rows>
                            <flux:table.cell variant="strong" colspan="10" class="text-center">
                                No workers found.
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

                        @php
                            $currentPage = $pagination['current_page'];
                            $lastPage = $pagination['last_page'];
                            $delta = 2; // Number of pages to show on each side of current page
                            $left = $currentPage - $delta;
                            $right = $currentPage + $delta + 1;
                            $pages = [];
                            $l = 0;

                            for ($i = 1; $i <= $lastPage; $i++) {
                                if ($i == 1 || $i == $lastPage || ($i >= $left && $i < $right)) {
                                    if ($l) {
                                        if ($i - $l === 2) {
                                            $pages[] = $l + 1;
                                        } else if ($i - $l !== 1) {
                                            $pages[] = '...';
                                        }
                                    }
                                    $pages[] = $i;
                                    $l = $i;
                                }
                            }
                        @endphp

                        @foreach($pages as $page)
                            @if($page === '...')
                                <span class="px-2 text-zinc-400">...</span>
                            @elseif($page == $currentPage)
                                <flux:button variant="primary" size="xs">{{ $page }}</flux:button>
                            @else
                                <flux:button variant="ghost" size="xs" wire:click="$set('page', {{ $page }})">{{ $page }}</flux:button>
                            @endif
                        @endforeach

                        @if($pagination['current_page'] < $pagination['last_page'])
                            <flux:button variant="ghost" size="sm" wire:click="$set('page', {{ $pagination['current_page'] + 1 }})">Next</flux:button>
                        @else
                            <flux:button variant="ghost" size="sm" disabled>Next</flux:button>
                        @endif
                    </div>
                </div>
            @endif
        </flux:card>
</div>
