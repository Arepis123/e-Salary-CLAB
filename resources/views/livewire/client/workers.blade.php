<div class="flex h-full w-full flex-1 flex-col gap-6 overflow-y-auto">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">My Workers</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Manage workers assigned to your company</p>
            </div>
            <x-tutorial-button page="workers" />
        </div>

        <!-- Statistics Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Workers</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total_workers'] }}</p>
                    </div>
                    <flux:icon.users class="size-8 text-blue-600 dark:text-blue-400" />
                </div>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Active</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['active_workers'] }}</p>
                    </div>
                    <flux:icon.check-circle class="size-8 text-green-600 dark:text-green-400" />
                </div>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Inactive</p>
                        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['inactive_workers'] }}</p>
                    </div>
                    <flux:icon.clock class="size-8 text-orange-600 dark:text-orange-400" />
                </div>
            </flux:card>

            <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Avg Salary</p>
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            @if($stats['average_salary'] > 0)
                                RM {{ number_format($stats['average_salary'], 2) }}
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <flux:icon.wallet class="size-8 text-purple-600 dark:text-purple-400" />
                </div>
            </flux:card>
        </div>

        <!-- Filters and Search (Accordion) -->
        <div id="filter-section">
            <button type="button" onclick="toggleFilters()" class="w-full flex items-center justify-between p-1 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="flex items-center gap-3">
                        <flux:icon.funnel class="size-5 text-zinc-600 dark:text-zinc-400" />
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Search & Filters</h3>
                    </div>
                    @if($search)
                        <flux:badge color="zinc" size="sm">Search: "{{ Str::limit($search, 20) }}"</flux:badge>
                    @endif
                    @if($status !== 'all')
                        <flux:badge color="zinc" size="sm">{{ ucfirst($status) }}</flux:badge>
                    @endif
                    @if($country !== 'all')
                        @php
                            $currentCountry = $country;
                            $selectedCountry = $countries->firstWhere('cty_code', $currentCountry);
                            $countryName = $selectedCountry ? $selectedCountry->cty_desc : $country;
                        @endphp
                        <flux:badge color="zinc" size="sm">{{ $countryName }}</flux:badge>
                    @endif
                    @if($position !== 'all')
                        @php
                            $currentPosition = $position;
                            $selectedPosition = $positions->firstWhere('trade_code', $currentPosition);
                            $positionName = $selectedPosition ? $selectedPosition->trade_desc : $position;
                        @endphp
                        <flux:badge color="zinc" size="sm">{{ $positionName }}</flux:badge>
                    @endif
                </div>
                <flux:icon.chevron-down id="filter-chevron" class="size-5 text-zinc-600 dark:text-zinc-400 transition-transform duration-200" />
            </button>

            <div id="filter-content" class="border-t border-zinc-200 dark:border-zinc-700 mt-2" style="display: none;">
                <div class="p-6">

                    <!-- Filters Row -->
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <flux:input
                                    wire:model.live.debounce.500ms="search"
                                    placeholder="Search by name or passport number"
                                    icon="magnifying-glass"
                                    size="sm"
                                    label="Search bar"
                                />
                            </div>
                            <div>
                                <flux:select variant="listbox" wire:model.live="status" size="sm" label="Status">
                                    <flux:select.option value="all">All Status</flux:select.option>
                                    <flux:select.option value="active">Active</flux:select.option>
                                    <flux:select.option value="inactive">Inactive</flux:select.option>
                                </flux:select>
                            </div>
                            <div>
                                <flux:select variant="listbox" wire:model.live="country" size="sm" label="Country">
                                    <flux:select.option value="all">All Country</flux:select.option>
                                    @foreach($countries as $countryItem)
                                        <flux:select.option value="{{ $countryItem->cty_code }}">{{ $countryItem->cty_desc }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:select variant="listbox" wire:model.live="position" size="sm" label="Position">
                                    <flux:select.option value="all">All Position</flux:select.option>
                                    @foreach($positions as $positionItem)
                                        <flux:select.option value="{{ $positionItem->trade_code }}">{{ $positionItem->trade_desc }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>

                        <div class="flex gap-2 sm:flex-shrink-0">
                            <flux:button wire:click="resetFilters" type="button" variant="filled" size="sm">
                                Clear
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>    
        </div>    
        
        <script>
            function toggleFilters() {
                const content = document.getElementById('filter-content');
                const chevron = document.getElementById('filter-chevron');

                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    chevron.style.transform = 'rotate(180deg)';
                } else {
                    content.style.display = 'none';
                    chevron.style.transform = 'rotate(0deg)';
                }
            }

            // Auto-expand if filters are active
            document.addEventListener('DOMContentLoaded', function() {
                const hasActiveFilters = {{ ($search || $status !== 'all' || $country !== 'all' || $position !== 'all' || $expiryStatus !== 'all') ? 'true' : 'false' }};

                if (hasActiveFilters) {
                    toggleFilters();
                }
            });
        </script>

        <!-- Workers List -->
        <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg" id="workers-table-section">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Workers</h2>
                <div class="flex gap-2">
                    <flux:button wire:click="export" variant="filled" size="sm">
                        <flux:icon.arrow-down-tray class="size-4 inline" />
                        Export
                    </flux:button>
                </div>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'wkr_id'" :direction="$sortDirection" wire:click="sortByColumn('wkr_id')" align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">No</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sortByColumn('name')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Name</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'ic_number'" :direction="$sortDirection" wire:click="sortByColumn('ic_number')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport Number</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'passport_expiry'" :direction="$sortDirection" wire:click="sortByColumn('passport_expiry')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Passport Expiry</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'permit_expiry'" :direction="$sortDirection" wire:click="sortByColumn('permit_expiry')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Permit Expiry</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'country'" :direction="$sortDirection" wire:click="sortByColumn('country')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Country</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'position'" :direction="$sortDirection" wire:click="sortByColumn('position')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Position</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'basic_salary'" :direction="$sortDirection" wire:click="sortByColumn('basic_salary')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Basic Salary</span></flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sortByColumn('status')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                    <flux:table.column><span id="actions-column-header" class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($workers as $worker)
                        @php
                            $passportExpired = $worker->wkr_passexp && $worker->wkr_passexp->isPast();
                            $passportExpiringSoon = $worker->wkr_passexp && $worker->wkr_passexp->isFuture() && now()->diffInDays($worker->wkr_passexp, false) <= 60;
                            $permitExpired = $worker->wkr_permitexp && $worker->wkr_permitexp->isPast();
                            $permitExpiringSoon = $worker->wkr_permitexp && $worker->wkr_permitexp->isFuture() && now()->diffInDays($worker->wkr_permitexp, false) <= 30;
                        @endphp
                        <flux:table.rows :key="$worker->wkr_id">
                            <flux:table.cell>{{ $loop->iteration }}</flux:table.cell>

                            <flux:table.cell variant="strong" class="flex items-center gap-2">
                                <flux:avatar size="xs" color="auto" name="{{ $worker->name }}" />
                                {{ $worker->name }}
                            </flux:table.cell>

                            <flux:table.cell variant="strong">{{ $worker->ic_number }}</flux:table.cell>

                            <flux:table.cell variant="strong">
                                @if($worker->wkr_passexp)
                                    <span>
                                        {{ $worker->wkr_passexp->format('M d, Y') }}
                                        @if($passportExpired)
                                            <span class="text-xs {{ $passportExpired ? 'text-red-600 dark:text-red-400' : ($passportExpiringSoon ? 'text-orange-600 dark:text-orange-400' : '') }}">(Expired)</span>
                                        @elseif($passportExpiringSoon)
                                            <span class="text-xs {{ $passportExpired ? 'text-red-600 dark:text-red-400' : ($passportExpiringSoon ? 'text-orange-600 dark:text-orange-400' : '') }}">(Soon)</span>
                                        @endif
                                    </span>
                                @else
                                    -
                                @endif
                            </flux:table.cell>

                            <flux:table.cell variant="strong">
                                @if($worker->wkr_permitexp)
                                    <span>
                                        {{ $worker->wkr_permitexp->format('M d, Y') }}
                                        @if($permitExpired)
                                            <span class="text-xs {{ $permitExpired ? 'text-red-600 dark:text-red-400' : ($permitExpiringSoon ? 'text-orange-600 dark:text-orange-400' : '') }}">(Expired)</span>
                                        @elseif($permitExpiringSoon)
                                            <span class="text-xs {{ $permitExpired ? 'text-red-600 dark:text-red-400' : ($permitExpiringSoon ? 'text-orange-600 dark:text-orange-400' : '') }}">(Soon)</span>
                                        @endif
                                    </span>
                                @else
                                    -
                                @endif
                            </flux:table.cell>

                            <flux:table.cell variant="strong">{{ $worker->country->cty_desc ?? '-' }}</flux:table.cell>

                            <flux:table.cell variant="strong">{{ $worker->workTrade->trade_desc ?? '-' }}</flux:table.cell>

                            <flux:table.cell variant="strong">
                                @if($worker->basic_salary)
                                    RM {{ number_format($worker->basic_salary, 2) }}
                                @else
                                    -
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($worker->contract_info && $worker->contract_info->isActive())
                                    <flux:badge color="green" size="sm" inset="top bottom">Active</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" inset="top bottom">Inactive</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" href="{{ route('workers.show', $worker->wkr_id) }}">View Details</flux:menu.item>
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
                    <div class="flex gap-2">
                        @if($pagination['current_page'] > 1)
                            <flux:button variant="ghost" size="sm" href="{{ route('client.workers', array_merge(request()->query(), ['page' => $pagination['current_page'] - 1])) }}">Previous</flux:button>
                        @else
                            <flux:button variant="ghost" size="sm" disabled>Previous</flux:button>
                        @endif

                        @for($i = 1; $i <= $pagination['last_page']; $i++)
                            @if($i == $pagination['current_page'])
                                <flux:button variant="primary" size="sm">{{ $i }}</flux:button>
                            @else
                                <flux:button variant="ghost" size="sm" href="{{ route('client.workers', array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</flux:button>
                            @endif
                        @endfor

                        @if($pagination['current_page'] < $pagination['last_page'])
                            <flux:button variant="ghost" size="sm" href="{{ route('client.workers', array_merge(request()->query(), ['page' => $pagination['current_page'] + 1])) }}">Next</flux:button>
                        @else
                            <flux:button variant="ghost" size="sm" disabled>Next</flux:button>
                        @endif
                    </div>
                </div>
            @endif
        </flux:card>
</div>
