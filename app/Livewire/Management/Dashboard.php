<?php

namespace App\Livewire\Management;

use App\Services\ManagementMetricsService;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.management.dashboard', [
            'period' => app(ManagementMetricsService::class)->periodContext(),
        ]);
    }
}
