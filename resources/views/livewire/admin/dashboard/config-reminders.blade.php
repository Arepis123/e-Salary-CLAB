<div>
    <!-- Configuration reminder: single-panel dismissable popup (same look & feel as the
         client dashboard carousel). Opens on dashboard load when any OT entry window is open
         or any contractor-specific override (service charge / penalty exemption) is active. -->
    <div id="configReminderModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-[2px] opacity-0 invisible transition-all duration-300">
        <div id="configReminderPanel" class="relative w-full max-w-lg mx-4 max-h-[90vh] flex flex-col bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
            <button onclick="closeConfigReminder()" class="absolute top-4 right-4 z-10 p-2 rounded-full bg-white/90 dark:bg-zinc-800/90 hover:bg-white dark:hover:bg-zinc-800 transition-colors">
                <flux:icon.x-mark class="size-5 text-zinc-600 dark:text-zinc-400" />
            </button>

            <!-- Header -->
            <div class="shrink-0 px-6 pt-6 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Active Configuration Reminder</h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    The following settings are currently active. Review them on the Configuration page.
                </p>
            </div>

            <!-- Body: both sections as accordion items in one panel -->
            <div class="flex-1 min-h-0 overflow-y-auto p-6">
                <flux:accordion transition>
                    @if(count($configReminderWindows) > 0)
                        <flux:accordion.item expanded>
                            <flux:accordion.heading>
                                Open OT Entry Windows
                            </flux:accordion.heading>
                            <flux:accordion.content>
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-800">
                                    @foreach($configReminderWindows as $contractor)
                                        <li class="flex items-center justify-between px-3 py-2">
                                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $contractor['name'] }}</span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $contractor['clab_no'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </flux:accordion.content>
                        </flux:accordion.item>
                    @endif

                    @if(count($configReminderSettings) > 0)
                        <flux:accordion.item expanded>
                            <flux:accordion.heading>
                                Contractor-Specific Settings
                            </flux:accordion.heading>
                            <flux:accordion.content>
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800 rounded-lg border border-zinc-100 dark:border-zinc-800">
                                    @foreach($configReminderSettings as $contractor)
                                        <li class="px-3 py-2">
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $contractor['name'] }}</span>
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $contractor['clab_no'] }}</span>
                                            </div>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @if($contractor['service_charge_exempt'])
                                                    <flux:badge size="sm" color="purple">Service charge exempt</flux:badge>
                                                @endif
                                                @if($contractor['penalty_exempt'])
                                                    <flux:badge size="sm" color="orange">Penalty exempt</flux:badge>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </flux:accordion.content>
                        </flux:accordion.item>
                    @endif

                    @if(count($configReminderPaymentLocks) > 0)
                        <flux:accordion.item expanded>
                            <flux:accordion.heading>
                                Payments Disabled
                            </flux:accordion.heading>
                            <flux:accordion.content>
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800 rounded-lg border border-zinc-100 dark:border-zinc-800">
                                    @foreach($configReminderPaymentLocks as $contractor)
                                        <li class="px-3 py-2">
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $contractor['name'] }}</span>
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $contractor['clab_no'] }}</span>
                                            </div>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <flux:badge size="sm" color="red">Payments disabled</flux:badge>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </flux:accordion.content>
                        </flux:accordion.item>
                    @endif
                </flux:accordion>
            </div>

            <!-- Footer -->
            <div class="shrink-0 flex justify-end gap-2 border-t border-zinc-100 dark:border-zinc-800 p-4">
                <flux:button onclick="closeConfigReminder()" variant="filled">Dismiss</flux:button>
                {{-- Configuration is super_admin-only (see routes/web.php), so only show
                     the link to users who can actually open that page. --}}
                @if(auth()->user()->isSuperAdmin())
                    <flux:button :href="route('configuration')" wire:navigate variant="primary">
                        Go to Configuration
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Single-panel configuration reminder (dismissable; same look & feel as the client carousel).
        function cfgShowConfigReminder() {
            const modal = document.getElementById('configReminderModal');
            if (!modal) return;
            modal.classList.remove('opacity-0', 'invisible');
            const panel = modal.querySelector('.transform');
            if (panel) { panel.classList.remove('scale-95'); panel.classList.add('scale-100'); }
        }

        function closeConfigReminder() {
            const modal = document.getElementById('configReminderModal');
            if (!modal) return;
            modal.classList.add('opacity-0', 'invisible');
            const panel = modal.querySelector('.transform');
            if (panel) { panel.classList.remove('scale-100'); panel.classList.add('scale-95'); }
        }

        // Bind global listeners once (survives wire:navigate; uses delegation so it
        // keeps working after the modal element is re-rendered).
        if (!window.__cfgReminderBound) {
            window.__cfgReminderBound = true;

            // Dismiss when clicking outside the panel (anywhere on the backdrop)
            document.addEventListener('click', function (e) {
                const modal = document.getElementById('configReminderModal');
                if (!modal || modal.classList.contains('invisible')) return;
                const panel = document.getElementById('configReminderPanel');
                if (panel && !panel.contains(e.target)) closeConfigReminder();
            });

            // Dismiss on Escape
            document.addEventListener('keydown', function (e) {
                const modal = document.getElementById('configReminderModal');
                if (modal && !modal.classList.contains('invisible') && e.key === 'Escape') {
                    closeConfigReminder();
                }
            });

            // Opened by the Livewire component once the content is in the DOM.
            window.addEventListener('config-reminder-loaded', () => {
                setTimeout(cfgShowConfigReminder, 100);
            });
        }
    </script>
</div>
