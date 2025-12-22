<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-800">
        <flux:sidebar sticky collapsible class="bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
            <flux:sidebar.header>
                <flux:sidebar.brand
                    href="{{ route('dashboard') }}"
                    wire:navigate
                    logo="{{ asset('logo-clab.png') }}"
                    logo:dark="{{ asset('logo-clab.png') }}"
                    name="e-Salary CLAB"
                />

                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <div class="px-3 py-2 mt-4 in-data-flux-sidebar-collapsed-desktop:hidden">
                    <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-400 uppercase tracking-wider">{{ __('MAIN') }}</h3>
                </div>
                <flux:sidebar.item icon="house" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:sidebar.item>

                @if(auth()->user()->role === 'client')
                    <flux:sidebar.item icon="clock" :href="route('ot-entry')" :current="request()->routeIs('ot-entry')" wire:navigate>{{ __('OT Entry') }}</flux:sidebar.item>
                @endif

                <flux:sidebar.item icon="users" :href="route('workers')" :current="request()->routeIs('workers')" wire:navigate>{{ __('Worker') }}</flux:sidebar.item>
                <flux:sidebar.item icon="building-office" :href="route('contractors')" :current="request()->routeIs('contractors')" wire:navigate>{{ __('Contractors') }}</flux:sidebar.item>
                {{-- <flux:sidebar.item icon="wallet" :href="route('payroll')" :current="request()->routeIs('payroll')" wire:navigate>{{ __('Payroll') }}</flux:sidebar.item> --}}
                <flux:sidebar.group expandable icon="wallet" heading="Payroll" class="grid">
                    <flux:sidebar.item :href="route('payroll')" :current="request()->routeIs('payroll')" wire:navigate>All submissions</flux:sidebar.item>
                    <flux:sidebar.item :href="route('missing-submissions')" :current="request()->routeIs('missing-submissions')" wire:navigate>No submissions</flux:sidebar.item>
                </flux:sidebar.group>
                <flux:sidebar.item icon="document-text" :href="route('invoices')" :current="request()->routeIs('invoices')" wire:navigate>{{ __('Invoices') }}</flux:sidebar.item>
                <flux:sidebar.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" wire:navigate>{{ __('Reminders') }}</flux:sidebar.item>
                <flux:sidebar.item icon="newspaper" :href="route('news')" :current="request()->routeIs('news')" wire:navigate>{{ __('News') }}</flux:sidebar.item>
                @if(auth()->user()->isSuperAdmin())
                    <div class="px-3 py-2 mt-4 in-data-flux-sidebar-collapsed-desktop:hidden">
                        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-400 uppercase tracking-wider">{{ __('SUPER ADMIN') }}</h3>
                    </div>
                    <flux:sidebar.item icon="file-text" :href="route('report')" :current="request()->routeIs('report')" wire:navigate>{{ __('Report') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="cog" :href="route('configuration')" :current="request()->routeIs('configuration')" wire:navigate>{{ __('Configuration') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('activity-logs')" :current="request()->routeIs('activity-logs')" wire:navigate>{{ __('Activity Logs') }}</flux:sidebar.item>
                @endif
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="book-open" :href="route('user-manual')" wire:navigate>
                    {{ __('User Manual') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="settings" :href="route('settings.profile')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <!-- Desktop User Menu -->
            <flux:dropdown position="top" align="start" class="max-lg:hidden">
                <flux:sidebar.profile
                    :name="auth()->user()->name ? preg_replace('/\s+(BIN|BINTI)\b.*/i', '', auth()->user()->name) : 'N/A'"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar name="{{ auth()->user() ? preg_replace('/\s+(BIN|BINTI)\b.*/i', '', auth()->user()->name) : 'N/A' }}" />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name ? preg_replace('/\s+(BIN|BINTI)\b.*/i', '', auth()->user()->name) : 'N/A' }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="start">
                <flux:profile
                    :initials="auth()->user()
                        ? collect(explode(' ', preg_replace('/\s+(BIN|BINTI)\b.*/i', '', auth()->user()->name)))
                            ->map(fn($part) => strtoupper(substr($part, 0, 1)))
                            ->implode('')
                        : 'NA'"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                        <flux:avatar class="flex h-full w-full items-center justify-center" name="{{ auth()->user() ? preg_replace('/\s+(BIN|BINTI)\b.*/i', '', auth()->user()->name) : 'N/A' }}" />
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <flux:toast />

        @fluxScripts
    </body>
</html>
