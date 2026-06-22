<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    // Outstanding balance shown in the Alerts box. Fed by the StatsCards child
    // (via the 'stats-loaded' event) so the heavy outstanding-balance query is
    // run only once across the whole dashboard.
    public $outstandingBalance = null;

    #[On('stats-loaded')]
    public function setOutstandingBalance($outstandingBalance): void
    {
        $this->outstandingBalance = $outstandingBalance;
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
