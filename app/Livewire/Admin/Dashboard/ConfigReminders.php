<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\ContractorConfiguration;
use App\Models\ContractorWindowSetting;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

class ConfigReminders extends Component
{
    // Configuration reminder popup: contractors with an open OT window and/or
    // an active contractor-specific override (service charge / penalty exemption).
    // Surfaced so admins don't forget these are active.
    public $configReminderWindows = [];

    public $configReminderSettings = [];

    // Contractors currently blocked from making payments (a temporary lock the
    // admin should remember to lift once the payroll is fixed).
    public $configReminderPaymentLocks = [];

    /**
     * Build the configuration-reminder data and pop the modal when there are any
     * open OT windows or active contractor-specific settings.
     *
     * Listens for 'dashboard-data-loaded' (dispatched once the dashboard's data
     * sections have loaded), so it runs as its own final round-trip and never
     * competes with the primary dashboard data.
     */
    #[On('dashboard-data-loaded')]
    public function load(): void
    {
        // Contractors with an open OT entry window. Filter open windows in SQL and
        // pull only the contractor name/CLAB — avoids loading every window setting
        // and lazy-loading the lastChangedBy relation per row.
        $openWindowClabs = ContractorWindowSetting::where('is_window_open', true)
            ->pluck('contractor_clab_no');

        $this->configReminderWindows = User::where('role', 'client')
            ->whereIn('contractor_clab_no', $openWindowClabs)
            ->orderBy('name')
            ->get(['contractor_clab_no', 'name'])
            ->map(fn ($u) => [
                'name' => $u->name,
                'clab_no' => $u->contractor_clab_no,
            ])
            ->values()
            ->toArray();

        // Contractors with an active exemption override: service charge or penalty.
        $this->configReminderSettings = ContractorConfiguration::where(function ($q) {
            $q->where('service_charge_exempt', true)
                ->orWhere('penalty_exempt', true);
        })
            ->orderBy('contractor_name')
            ->get()
            ->map(fn ($config) => [
                'name' => $config->contractor_name,
                'clab_no' => $config->contractor_clab_no,
                'service_charge_exempt' => (bool) $config->service_charge_exempt,
                'penalty_exempt' => (bool) $config->penalty_exempt,
            ])
            ->values()
            ->toArray();

        // Contractors whose payments are currently locked by the admin.
        $this->configReminderPaymentLocks = ContractorConfiguration::where('payment_enabled', false)
            ->orderBy('contractor_name')
            ->get(['contractor_clab_no', 'contractor_name'])
            ->map(fn ($config) => [
                'name' => $config->contractor_name,
                'clab_no' => $config->contractor_clab_no,
            ])
            ->values()
            ->toArray();

        // Show the carousel popup when any area has something active.
        // JS (window 'config-reminder-loaded' listener) opens it once the slides
        // have been rendered into the DOM.
        if (! empty($this->configReminderWindows)
            || ! empty($this->configReminderSettings)
            || ! empty($this->configReminderPaymentLocks)) {
            $this->dispatch('config-reminder-loaded');
        }
    }

    public function render()
    {
        return view('livewire.admin.dashboard.config-reminders');
    }
}
