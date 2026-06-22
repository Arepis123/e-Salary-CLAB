<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\PayrollSubmission;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class RecentPayments extends Component
{
    public $recentPayments = [];

    public function mount(): void
    {
        $this->loadRecentPayments();
    }

    public function placeholder()
    {
        return view('livewire.admin.dashboard.placeholders.recent-payments');
    }

    protected function loadRecentPayments(): void
    {
        $recentSubmissions = PayrollSubmission::with(['user', 'payments'])
            ->where('status', 'paid')
            ->whereHas('payments', function ($query) {
                // Check if submission has ANY completed payment (not just latest)
                $query->where('status', 'completed');
            })
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        $this->recentPayments = $recentSubmissions->map(function ($submission) {
            $clientName = $submission->user
                ? $submission->user->name
                : 'Client '.$submission->contractor_clab_no;

            // Get actual completed payment (exclude redirect logs)
            $actualPayment = $submission->payments()
                ->where('status', 'completed')
                ->latest()
                ->first();

            $date = $actualPayment && $actualPayment->completed_at
                ? $actualPayment->completed_at->format('M d, Y')
                : $submission->paid_at->format('M d, Y');

            return [
                'client' => $clientName,
                'amount' => $submission->client_total,
                'workers' => $submission->total_workers,
                'date' => $date,
                'status' => 'completed',
            ];
        })->toArray();
    }

    public function render()
    {
        return view('livewire.admin.dashboard.recent-payments');
    }
}
