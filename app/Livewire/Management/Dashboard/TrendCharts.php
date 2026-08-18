<?php

namespace App\Livewire\Management\Dashboard;

use App\Services\ManagementMetricsService;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class TrendCharts extends Component
{
    public array $trends = [];

    public function mount(): void
    {
        $this->trends = app(ManagementMetricsService::class)->trends(12);
    }

    public function placeholder()
    {
        return view('livewire.management.dashboard.placeholders.trend-charts');
    }

    public function render()
    {
        return view('livewire.management.dashboard.trend-charts');
    }
}
