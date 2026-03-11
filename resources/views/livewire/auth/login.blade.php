<div class="flex flex-col gap-6">
    {{-- <x-auth-header :title="__('Log in to your account')" :description="__('Enter your username and password below to log in')" /> --}}

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <!-- Username -->
        <!-- <flux:input
            wire:model="username"
            :label="__('Username')"
            type="text"
            required
            autofocus
            autocomplete="username"
            placeholder="CLABID"
        /> -->
        <flux:input
            wire:model="username"
            type="text"
            placeholder="CLABID"
            icon="envelope"
            required
            autofocus
            clearable
            autocomplete="username"
            :label="''"
        />        

        <!-- Password -->
        <div class="relative">
            <!-- <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            /> -->
            <flux:input
                wire:model="password"
                type="password"
                placeholder="Password"
                icon="lock-closed"
                required
                viewable
                autocomplete="current-password"
            /> 


        </div>

        <!-- Remember Me -->
        <!-- <flux:checkbox wire:model="remember" :label="__('Remember me')" /> -->

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</flux:button>
        </div>
    </form>

    <div class="text-center text-sm text-white dark:text-zinc-400">
        {{ __('Please use your credentials from the e-CLAB Portal to log in.') }}
    </div>
</div>
