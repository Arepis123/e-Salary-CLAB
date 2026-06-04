<?php

namespace App\Livewire\Admin;

use App\Models\EmailLog;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EmailLogs extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $statusFilter = '';

    #[Url]
    public $dateFilter = '';

    public function updating($name): void
    {
        if (in_array($name, ['search', 'statusFilter', 'dateFilter'])) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'dateFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $query = EmailLog::query()->latest('id');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('to_email', 'like', $term)
                    ->orWhere('to_name', 'like', $term)
                    ->orWhere('subject', 'like', $term);
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->dateFilter !== '') {
            $since = match ($this->dateFilter) {
                'today' => now()->startOfDay(),
                '7days' => now()->subDays(7),
                '30days' => now()->subDays(30),
                default => null,
            };
            if ($since) {
                $query->where('sent_at', '>=', $since);
            }
        }

        $logs = $query->paginate(25);

        $base = EmailLog::query();
        $stats = [
            'total' => (clone $base)->count(),
            'delivered' => (clone $base)->whereIn('status', ['delivered', 'opened', 'clicked'])->count(),
            'opened' => (clone $base)->whereIn('status', ['opened', 'clicked'])->count(),
            'failed' => (clone $base)->whereIn('status', ['bounced', 'spam', 'blocked', 'failed'])->count(),
        ];

        return view('livewire.admin.email-logs', compact('logs', 'stats'))
            ->layout('components.layouts.app', ['title' => __('Email Logs')]);
    }
}
