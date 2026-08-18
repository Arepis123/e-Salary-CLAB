<?php

namespace App\Livewire\Management\Dashboard;

use App\Services\ManagementMetricsService;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class CollectionHealth extends Component
{
    public array $health = [];

    public function mount(): void
    {
        $this->health = app(ManagementMetricsService::class)->collectionHealth(6);
    }

    public function placeholder()
    {
        return view('livewire.management.dashboard.placeholders.collection-health');
    }

    public function render()
    {
        return view('livewire.management.dashboard.collection-health');
    }
}
