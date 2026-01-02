<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class UserChangePassword extends Component
{
    public $userId;
    public $userName;
    public $password = '';
    public $password_confirmation = '';

    public function render()
    {
        return view('livewire.admin.user-change-password');
    }

    #[On('changePasswordUser')]
    public function changePasswordUser($id)
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
        $this->userName = $user->name;
        $this->password = '';
        $this->password_confirmation = '';

        // Show modal
        Flux::modal('change-password-user')->show();
    }

    public function updatePassword()
    {
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::find($this->userId);

        // Security check
        if ($user->role === 'client') {
            Flux::toast(
                variant: 'danger',
                heading: 'Unauthorized',
                text: 'Cannot change password for client users.'
            );

            return;
        }

        // Update password
        $user->update([
            'password' => $this->password, // Let the model cast handle hashing
        ]);

        // Reset password fields
        $this->reset(['password', 'password_confirmation']);

        // Close modal
        Flux::modal('change-password-user')->close();

        // Show success message
        Flux::toast(
            variant: 'success',
            heading: 'Password Updated',
            text: "Password for {$this->userName} has been updated successfully."
        );
    }
}
