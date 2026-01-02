<div>
    {{-- Page Header --}}
    <div class="mb-6">
        <flux:heading size="xl">{{ __('User Management') }}</flux:heading>
        <flux:text>{{ __('Manage admin and super admin users') }}</flux:text>
    </div>

        {{-- Create User Button --}}
        <div class="mb-4">
            <flux:modal.trigger name="create-user">
                <flux:button icon="user-plus">{{ __('Create User') }}</flux:button>
            </flux:modal.trigger>
        </div>

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

        {{-- Users Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3">{{ __('ID') }}</th>
                        <th class="px-6 py-3">{{ __('Username') }}</th>
                        <th class="px-6 py-3">{{ __('Name') }}</th>
                        <th class="px-6 py-3">{{ __('Email') }}</th>
                        <th class="px-6 py-3">{{ __('Role') }}</th>
                        <th class="px-6 py-3">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                    <tr class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                        <td class="px-6 py-2 font-medium text-gray-900 dark:text-white">{{ $user->id }}</td>
                        <td class="px-6 py-2 text-gray-600 dark:text-gray-300">{{ $user->username }}</td>
                        <td class="px-6 py-2 text-gray-600 dark:text-gray-300">{{ $user->name }}</td>
                        <td class="px-6 py-2 text-gray-600 dark:text-gray-300">{{ $user->email }}</td>
                        <td class="px-6 py-2">
                            <flux:badge :variant="$user->role === 'super_admin' ? 'success' : 'info'">
                                {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                            </flux:badge>
                        </td>
                        <td class="px-6 py-2 space-x-2">
                            <flux:button size="sm" wire:click="edit({{ $user->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" wire:click="changePassword({{ $user->id }})">{{ __('Change Password') }}</flux:button>
                            @if($user->id !== auth()->id())
                                <flux:button size="sm" variant="danger" wire:click="delete({{ $user->id }})">{{ __('Delete') }}</flux:button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">{{ __('No users found.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
</div>
