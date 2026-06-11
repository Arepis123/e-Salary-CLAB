<div>
<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('User Management') }}</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Manage admin and super admin users') }}</p>
        </div>
        <flux:modal.trigger name="create-user">
            <flux:button variant="primary" icon="user-plus">{{ __('Create User') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Total Users') }}</p>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['total'] }}</p>
                </div>
                <flux:icon.users class="size-8 text-blue-600 dark:text-blue-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Super Admins') }}</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['super_admin'] }}</p>
                </div>
                <flux:icon.shield-check class="size-8 text-purple-600 dark:text-purple-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Admins') }}</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['admin'] }}</p>
                </div>
                <flux:icon.user class="size-8 text-green-600 dark:text-green-400" />
            </div>
        </flux:card>

        <flux:card class="space-y-2 p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Finance') }}</p>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['finance'] }}</p>
                </div>
                <flux:icon.banknotes class="size-8 text-orange-600 dark:text-orange-400" />
            </div>
        </flux:card>
    </div>

    <!-- Users Table -->
    <flux:card class="p-4 sm:p-6 dark:bg-zinc-900 rounded-lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('All Users') }}</h2>
            <div class="flex">
                <flux:button variant="ghost" size="sm" wire:click="toggleFilters">
                    <flux:icon.funnel class="size-4 inline" />
                    {{ __('Filter') }}
                </flux:button>
            </div>
        </div>

        <!-- Filters and Search -->
        @if($showFilters)
        <div class="mb-6" x-data x-transition>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                <div class="md:col-span-2" x-data x-on:focusin="$el.querySelector('input')?.removeAttribute('readonly')">
                    <flux:input
                        type="search"
                        wire:model.live="search"
                        placeholder="{{ __('Search by login ID, name or email') }}"
                        icon="magnifying-glass"
                        size="sm"
                        autocomplete="off"
                        name="q"
                        readonly
                    />
                </div>
                <div>
                    <flux:select wire:model.live="roleFilter" variant="listbox" placeholder="{{ __('Filter by Role') }}" size="sm">
                        <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
                        <flux:select.option value="super_admin">{{ __('Super Admin') }}</flux:select.option>
                        <flux:select.option value="admin">{{ __('Admin') }}</flux:select.option>
                        <flux:select.option value="finance">{{ __('Finance') }}</flux:select.option>
                    </flux:select>
                </div>
                <div>
                    <flux:button variant="filled" size="sm" wire:click="clearFilters">
                        <flux:icon.x-mark class="size-4 inline" />
                        {{ __('Clear') }}
                    </flux:button>
                </div>
            </div>
        </div>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column align="center"><span class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('No') }}</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'username'" :direction="$sortDirection" wire:click="sortByColumn('username')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Username') }}</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sortByColumn('name')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Name') }}</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sortByColumn('email')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Email') }}</span></flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'role'" :direction="$sortDirection" wire:click="sortByColumn('role')"><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Role') }}</span></flux:table.column>
                <flux:table.column><span class="text-left text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Actions') }}</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.rows :key="$user->id">
                        <flux:table.cell>{{ $users->firstItem() + $loop->index }}</flux:table.cell>

                        <flux:table.cell variant="strong" class="flex items-center gap-3">
                            <flux:avatar size="xs" color="auto" name="{{ $user->name }}" />
                            {{ $user->username }}
                        </flux:table.cell>

                        <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>

                        <flux:table.cell variant="strong">{{ $user->email }}</flux:table.cell>

                        <flux:table.cell>
                            @php
                                $roleColor = match($user->role) {
                                    'super_admin' => 'purple',
                                    'admin' => 'green',
                                    'finance' => 'orange',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$roleColor" size="sm" inset="top bottom">
                                {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $user->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="key" wire:click="changePassword({{ $user->id }})">{{ __('Change Password') }}</flux:menu.item>
                                    @if($user->id !== auth()->id())
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $user->id }})">{{ __('Delete') }}</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.rows>
                @empty
                    <flux:table.rows>
                        <flux:table.cell variant="strong" colspan="6" class="text-center">
                            {{ __('No users found.') }}
                        </flux:table.cell>
                    </flux:table.rows>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:pagination :paginator="$users" class="mt-5"/>
    </flux:card>
</div>

{{-- Modals: kept outside the gap-6 flex flow so their empty wrappers don't add vertical space --}}
{{-- Include Sub-component Modals --}}
<livewire:admin.user-create/>
<livewire:admin.user-edit/>
<livewire:admin.user-change-password/>

{{-- Delete Confirmation Modal --}}
<flux:modal name="delete-user" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete user?') }}</flux:heading>
                <flux:text class="mt-2">
                    <p>{{ __("You're about to delete this user.") }}</p>
                    <p>{{ __('This action cannot be reversed.') }}</p>
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" wire:click="destroy()">{{ __('Delete user') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
