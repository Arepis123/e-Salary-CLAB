<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\PayrollSubmission;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class TopOverdueChart extends Component
{
    public $topOverdueChartData = [
        'labels' => [],
        'data' => [],
    ];

    public function mount(): void
    {
        $this->loadTopOverdueClients();
    }

    public function placeholder()
    {
        return view('livewire.admin.dashboard.placeholders.top-overdue-chart');
    }

    /**
     * Top 5 clients who most frequently miss their payroll payment deadline.
     *
     * A submission counts as overdue when it is either:
     *  - still unsettled past its deadline (currently overdue), or
     *  - paid, but settled after the deadline (historically late).
     */
    protected function loadTopOverdueClients(): void
    {
        $overdueSubmissions = PayrollSubmission::query()
            ->with('user')
            ->whereNotNull('payment_deadline')
            ->where(function ($q) {
                // Currently overdue: deadline passed and not yet settled
                $q->where(function ($q2) {
                    $q2->whereNotIn('status', ['paid', 'draft'])
                        ->whereDate('payment_deadline', '<', now()->startOfDay());
                })
                    // Or paid, but settled after the deadline (late payment)
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'paid')
                            ->whereColumn('paid_at', '>', 'payment_deadline');
                    });
            })
            ->get();

        $topClients = $overdueSubmissions
            ->groupBy('contractor_clab_no')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'name' => $first->user->name ?? ('Client '.$first->contractor_clab_no),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values();

        $this->topOverdueChartData = [
            'labels' => $topClients->pluck('name')->toArray(),
            'data' => $topClients->pluck('count')->toArray(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.dashboard.top-overdue-chart');
    }
}
