<?php

namespace App\Livewire\Management\Dashboard;

use App\Services\ManagementMetricsService;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class RevenueKpis extends Component
{
    public array $kpis = [];

    public function mount(): void
    {
        $this->kpis = app(ManagementMetricsService::class)->revenueKpis();
    }

    public function placeholder()
    {
        return view('livewire.management.dashboard.placeholders.revenue-kpis');
    }

    public function render()
    {
        return view('livewire.management.dashboard.revenue-kpis');
    }
}
