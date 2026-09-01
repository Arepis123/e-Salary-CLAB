<!-- PIC (Person In Charge) Assignment Tab -->
<div class="space-y-6">

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Admin Users</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $picStats['total'] ?? 0 }}</p>
                </div>
                <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-3">
                    <flux:icon.user-group class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">With Assignments</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $picStats['assigned'] ?? 0 }}</p>
                </div>
                <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-3">
                    <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Contractors Available</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $picStats['contractors'] ?? 0 }}</p>
                </div>
                <div class="rounded-full bg-purple-100 dark:bg-purple-900/30 p-3">
                    <flux:icon.building-office class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Unassigned Contractors</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $picStats['unassigned'] ?? 0 }}</p>
                </div>
                <div class="rounded-full bg-amber-100 dark:bg-amber-900/30 p-3">
                    <flux:icon.exclamation-triangle class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- PIC Table -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Person In Charge (PIC)</h2>
                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                    Assign the contractors each admin manages — reports can then be filtered by PIC
                </p>
            </div>
        </div>

        <!-- Filters -->
        <div class="grid gap-4 md:grid-cols-3 mb-4">
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="picSearch"
                    placeholder="Search by name or email..."
                    icon="magnifying-glass"
                    size="sm"
                />
            </div>
            <div>
                <flux:select wire:model.live="picRoleFilter" variant="listbox" placeholder="Filter by Role" size="sm">
                    <flux:select.option value="">All Roles</flux:select.option>
                    <flux:select.option value="super_admin">Super Admin</flux:select.option>
                    <flux:select.option value="admin">Admin</flux:select.option>
                </flux:select>
            </div>
            <div>
                <flux:button wire:click="clearPicFilters" variant="filled" size="sm" icon="x-mark" icon-variant="outline">
                    Clear Filters
                </flux:button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">User</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Role</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Status</span></flux:table.column>
                    <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">Managed Contractors</span></flux:table.column>
                    <flux:table.column><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">Actions</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($picUsers as $picUser)
                        <flux:table.row :key="$picUser['id']">
                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" :name="$picUser['name']" color="auto" />
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $picUser['name'] }}</p>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $picUser['email'] }}</p>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge color="{{ $picUser['role'] === 'super_admin' ? 'purple' : 'blue' }}" size="sm">
                                    {{ $picUser['role'] === 'super_admin' ? 'Super Admin' : 'Admin' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($picUser['is_active'])
                                    <flux:badge color="green" size="sm">Active</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Inactive</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if(! $picUser['is_active'])
                                    <flux:text>Released (inactive)</flux:text>
                                @elseif($picUser['assigned_count'] === 0)
                                    <flux:text>None assigned</flux:text>
                                @else
                                    <div class="flex flex-wrap items-center gap-1">                                       
                                        <flux:text variant="strong">{{ $picUser['assigned_count'] }} contractor(s)</flux:text>
                                    </div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-center">
                                    <flux:dropdown align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                        <flux:menu>
                                            <flux:menu.item
                                                icon="building-office"
                                                :disabled="! $picUser['is_active']"
                                                wire:click="openPicAssignmentModal({{ $picUser['id'] }})"
                                            >
                                                Assign Contractors
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">
                                <div class="py-12 text-center">
                                    <flux:icon.user-group class="mx-auto size-7 text-zinc-400 dark:text-zinc-600 mb-2" />
                                     <flux:heading>Not Found</flux:heading>
                                     <flux:text>
                                        @if($picSearch !== '' || $picRoleFilter !== '')
                                            No admins match your filters.
                                        @else
                                            Admin and super admin accounts will appear here.
                                        @endif
                                     </flux:text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <flux:pagination :paginator="$picUsers" class="mt-4" />
    </flux:card>
</div>

<!-- Assign Contractors Modal -->
<flux:modal name="pic-assignment" class="md:w-2xl space-y-6" wire:model="showPicAssignmentModal">
    <div>
        <flux:heading size="lg">Assign Contractors</flux:heading>
        <flux:subheading>
            Pick the contractors <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $assigningUserName }}</span> will manage.
            Contractors already managed by another PIC are locked.
        </flux:subheading>
    </div>

    @php
        $picTerm = mb_strtolower($picContractorSearch);

        $picContractors = collect($contractorConfigs)
            ->filter(function ($contractor) use ($picTerm) {
                if ($picTerm === '') {
                    return true;
                }

                return str_contains(mb_strtolower((string) $contractor->contractor_name), $picTerm)
                    || str_contains(mb_strtolower((string) $contractor->contractor_clab_no), $picTerm);
            })
            ->sortBy('contractor_name');
    @endphp

    <div class="space-y-3">
        <flux:input
            wire:model.live.debounce.300ms="picContractorSearch"
            placeholder="Search contractor..."
            icon="magnifying-glass"
            size="sm"
        />

        <div class="flex items-center justify-between rounded-lg bg-zinc-50 dark:bg-zinc-800 px-3 py-2">
            <flux:checkbox wire:model.live="picSelectAll" label="Select All Available" />
            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ count($picSelectedClabs) }} selected
            </span>
        </div>

        <div class="max-h-80 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($picContractors as $contractor)
                @php
                    // A contractor has exactly one PIC — if someone else already has it, it is locked here
                    $takenBy = $picTakenClabs[$contractor->contractor_clab_no] ?? null;
                @endphp

                <label class="flex items-center justify-between gap-3 px-3 py-2 {{ $takenBy ? 'opacity-60 cursor-not-allowed' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer' }}">
                    <div class="flex items-center gap-3">
                        <flux:checkbox
                            wire:model.live="picSelectedClabs"
                            value="{{ $contractor->contractor_clab_no }}"
                            :disabled="(bool) $takenBy"
                        />
                        <div>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $contractor->contractor_name }}</p>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $contractor->contractor_clab_no }}</p>
                        </div>
                    </div>

                    @if($takenBy)
                        <flux:badge color="zinc" size="sm">PIC: {{ $takenBy }}</flux:badge>
                    @endif
                </label>
            @empty
                <p class="px-3 py-6 text-center text-sm text-zinc-600 dark:text-zinc-400">
                    No contractors match your search.
                </p>
            @endforelse
        </div>
    </div>

    <div class="flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="closePicAssignmentModal">Cancel</flux:button>
        <flux:button variant="primary" wire:click="savePicAssignments">
            Save Assignments
        </flux:button>
    </div>
</flux:modal>
