<div>
    <flux:modal name="edit-user" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit User') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Update user information') }}</flux:text>
            </div>

            <flux:input wire:model="username" label="{{ __('Username') }}" required />

            <flux:input wire:model="name" label="{{ __('Full Name') }}" required />

            <flux:input wire:model="email" label="{{ __('Email') }}" type="email" required />

            <flux:input wire:model="phone" label="{{ __('Phone') }}" />

            <flux:input wire:model="person_in_charge" label="{{ __('Person in Charge') }}" />

            <flux:select wire:model="role" label="{{ __('Role') }}" required>
                <option value="admin">{{ __('Admin') }}</option>
                <option value="finance">{{ __('Finance') }}</option>
                <option value="super_admin">{{ __('Super Admin') }}</option>
            </flux:select>

            <flux:text class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Note: Password cannot be changed here. Use the "Change Password" action.') }}
            </flux:text>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:click="update">{{ __('Save Changes') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
