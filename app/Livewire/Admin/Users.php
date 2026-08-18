<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $roleFilter = '';

    #[Url]
    public $sortBy = 'name';

    #[Url]
    public $sortDirection = 'asc';

    public $showFilters = true;

    public $perPage = 10;

    public $userIdToDelete;

    /**
     * Columns that may be sorted at the database level.
     */
    protected array $sortableColumns = ['username', 'name', 'email', 'role'];

    public function mount()
    {
        // Authorization check
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized access. Only Super Admin can access this page.');
        }
    }

    public function toggleFilters()
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->resetPage();
    }

    public function sortByColumn($column)
    {
        if (! in_array($column, $this->sortableColumns)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        // All non-client users (admin / super admin / finance)
        $baseQuery = User::where('role', '!=', 'client');

        // Search across username, name and email
        if ($this->search !== '') {
            $baseQuery->where(function ($q) {
                $q->where('username', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        // Role filter
        if ($this->roleFilter !== '') {
            $baseQuery->where('role', $this->roleFilter);
        }

        // Sorting (guarded against invalid columns)
        $sortColumn = in_array($this->sortBy, $this->sortableColumns) ? $this->sortBy : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $users = (clone $baseQuery)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($this->perPage);

        $stats = [
            'total' => User::where('role', '!=', 'client')->count(),
            'super_admin' => User::where('role', 'super_admin')->count(),
            'admin' => User::where('role', 'admin')->count(),
            'finance' => User::where('role', 'finance')->count(),
            'management' => User::where('role', 'management')->count(),
        ];

        return view('livewire.admin.users', compact('users', 'stats'))
            ->layout('components.layouts.app', ['title' => __('User Management')]);
    }

    #[On('reloadUsers')]
    public function reloadUsers()
    {
        // Re-render with fresh data; reset to the first page so new users are visible.
        $this->resetPage();
    }

    public function edit($id)
    {
        $this->dispatch('editUser', $id);
    }

    public function changePassword($id)
    {
        $this->dispatch('changePasswordUser', $id);
    }

    public function toggleStatus($id)
    {
        $user = User::find($id);

        if (! $user) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'User not found.'
            );

            return;
        }

        // Prevent disabling self (would lock yourself out)
        if ($user->id === auth()->id()) {
            Flux::toast(
                variant: 'danger',
                heading: 'Cannot Disable',
                text: 'You cannot disable your own account.'
            );

            return;
        }

        // Prevent toggling client users from this screen
        if ($user->role === 'client') {
            Flux::toast(
                variant: 'danger',
                heading: 'Unauthorized',
                text: 'Cannot change status of client users.'
            );

            return;
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        Flux::toast(
            variant: 'success',
            heading: $user->is_active ? 'User Enabled' : 'User Disabled',
            text: $user->is_active
                ? 'User can now log in to the system.'
                : 'User has been disabled and can no longer log in.'
        );
    }

    public function delete($id)
    {
        $this->userIdToDelete = $id;
        // Clear any previously typed confirmation so the button starts disabled
        $this->dispatch('reset-delete-confirmation');
        Flux::modal('delete-user')->show();
    }

    public function destroy()
    {
        $user = User::find($this->userIdToDelete);

        // Security checks
        if (! $user) {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'User not found.'
            );

            return;
        }

        // Prevent deleting self
        if ($user->id === auth()->id()) {
            Flux::toast(
                variant: 'danger',
                heading: 'Cannot Delete',
                text: 'You cannot delete yourself.'
            );
            Flux::modal('delete-user')->close();

            return;
        }

        // Prevent deleting client users
        if ($user->role === 'client') {
            Flux::toast(
                variant: 'danger',
                heading: 'Unauthorized',
                text: 'Cannot delete client users.'
            );
            Flux::modal('delete-user')->close();

            return;
        }

        // Delete the user
        $user->delete();

        // Close modal; render() reloads the list with fresh data
        Flux::modal('delete-user')->close();

        Flux::toast(
            variant: 'success',
            heading: 'User Deleted',
            text: 'User has been deleted successfully.'
        );
    }
}
