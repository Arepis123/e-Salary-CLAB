<div>
    <flux:modal name="create-user" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create User') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Add a new admin or super admin user') }}</flux:text>
            </div>

            <flux:input wire:model="username" label="{{ __('Username') }}" placeholder="admin123" required />

            <flux:input wire:model="name" label="{{ __('Full Name') }}" placeholder="John Doe" required />

            <flux:input wire:model="email" label="{{ __('Email') }}" type="email" placeholder="admin@example.com" required />

            <flux:input wire:model="phone" label="{{ __('Phone') }}" placeholder="012-3456789" />

            <flux:input wire:model="person_in_charge" label="{{ __('Person in Charge') }}" placeholder="Manager Name" />

            <flux:select wire:model="role" label="Role" variant="listbox" placeholder="{{ __('Select Role') }}">
                <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
                <flux:select.option value="super_admin">{{ __('Super Admin') }}</flux:select.option>
                <flux:select.option value="admin">{{ __('Admin') }}</flux:select.option>
                <flux:select.option value="finance">{{ __('Finance') }}</flux:select.option>
            </flux:select>            

            <flux:input wire:model="password" label="{{ __('Password') }}" type="password" viewable required />

            <flux:input wire:model="password_confirmation" label="{{ __('Confirm Password') }}" type="password" viewable required />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:click="submit">{{ __('Create User') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
