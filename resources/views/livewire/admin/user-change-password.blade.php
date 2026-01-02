<div>
    <flux:modal name="change-password-user" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Change Password') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Change password for:') }} <strong>{{ $userName }}</strong>
                </flux:text>
            </div>

            <flux:input wire:model="password" label="{{ __('New Password') }}" type="password" required />

            <flux:input wire:model="password_confirmation" label="{{ __('Confirm New Password') }}" type="password" required />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:click="updatePassword">{{ __('Update Password') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
