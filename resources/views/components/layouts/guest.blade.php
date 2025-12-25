<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
        <nav class="bg-white dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('home') }}" class="flex items-center gap-3">
                            <img src="{{ asset('logo-clab.png') }}" alt="CLAB Logo" class="h-10 w-auto">
                            <span class="text-xl font-semibold text-gray-900 dark:text-white">e-Salary CLAB</span>
                        </a>
                    </div>
                    <div class="flex items-center gap-4">
                        @auth
                            <flux:button :href="route('dashboard')" wire:navigate variant="primary">
                                {{ __('Dashboard') }}
                            </flux:button>
                        @else
                            <flux:button :href="route('login')" wire:navigate variant="primary">
                                {{ __('Login') }}
                            </flux:button>
                            <!-- <flux:button :href="route('register')" wire:navigate variant="primary">
                                {{ __('Register') }}
                            </flux:button> -->
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        {{ $slot }}

        <flux:toast />
        @fluxScripts
    </body>
</html>
