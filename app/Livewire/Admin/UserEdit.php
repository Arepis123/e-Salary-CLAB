<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class UserEdit extends Component
{
    public $userId;

    public $username;

    public $name;

    public $email;

    public $phone;

    public $person_in_charge;

    public $role;

    public function render()
    {
        return view('livewire.admin.user-edit');
    }

    #[On('editUser')]
    public function editUser($id)
    {
        $user = User::find($id);

        // Security check
        if (! $user || $user->role === 'client') {
            Flux::toast(
                variant: 'danger',
                heading: 'Error',
                text: 'User not found or unauthorized.'
            );

            return;
        }

        // Load user data
        $this->userId = $user->id;
        $this->username = $user->username;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->person_in_charge = $user->person_in_charge;
        $this->role = $user->role;

        // Show modal
        Flux::modal('edit-user')->show();
    }

    public function update()
    {
        $this->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($this->userId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'in:admin,super_admin,finance'],
        ]);

        $user = User::find($this->userId);

        // Security checks
        if ($user->role === 'client') {
            Flux::toast(
                variant: 'danger',
                heading: 'Unauthorized',
                text: 'Cannot edit client users.'
            );

            return;
        }

        // Prevent changing own role
        if ($user->id === auth()->id() && $user->role !== $this->role) {
            Flux::toast(
                variant: 'danger',
                heading: 'Cannot Change Role',
                text: 'You cannot change your own role.'
            );

            return;
        }

        // Update the user
        $user->update([
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'person_in_charge' => $this->person_in_charge,
            'role' => $this->role,
        ]);

        // Close modal
        Flux::modal('edit-user')->close();

        // Reload users list
        $this->dispatch('reloadUsers');

        // Show success message
        Flux::toast(
            variant: 'success',
            heading: 'User Updated',
            text: 'User has been updated successfully.'
        );
    }
}
