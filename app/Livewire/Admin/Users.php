<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class Users extends Component
{
    public $users;
    public $userIdToDelete;

    public function mount()
    {
        // Authorization check
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized access. Only Super Admin can access this page.');
        }

        $this->loadUsers();
    }

    public function render()
    {
        return view('livewire.admin.users')
            ->layout('components.layouts.app', ['title' => __('User Management')]);
    }

    #[On('reloadUsers')]
    public function reloadUsers()
    {
        $this->loadUsers();
    }

    private function loadUsers()
    {
        // Load all users except client users
        $this->users = User::where('role', '!=', 'client')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function edit($id)
    {
        $this->dispatch('editUser', $id);
    }

    public function changePassword($id)
    {
        $this->dispatch('changePasswordUser', $id);
    }

    public function delete($id)
    {
        $this->userIdToDelete = $id;
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

        // Reload users and close modal
        $this->loadUsers();
        Flux::modal('delete-user')->close();

        Flux::toast(
            variant: 'success',
            heading: 'User Deleted',
            text: 'User has been deleted successfully.'
        );
    }
}
