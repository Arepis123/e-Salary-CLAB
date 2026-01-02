<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Livewire\Component;

class UserCreate extends Component
{
    public $username = '';
    public $name = '';
    public $email = '';
    public $phone = '';
    public $person_in_charge = '';
    public $role = '';
    public $password = '';
    public $password_confirmation = '';

    public function render()
    {
        return view('livewire.admin.user-create');
    }

    public function submit()
    {
        $this->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'in:admin,super_admin,finance'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Create the user
        User::create([
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'person_in_charge' => $this->person_in_charge,
            'role' => $this->role,
            'password' => $this->password, // Let the model cast handle hashing
            'contractor_clab_no' => null, // Admin users don't have CLAB numbers
            'email_verified_at' => now(), // Auto-verify admin users
        ]);

        // Reset form
        $this->resetForm();

        // Close modal
        Flux::modal('create-user')->close();

        // Reload users list
        $this->dispatch('reloadUsers');

        // Show success message
        Flux::toast(
            variant: 'success',
            heading: 'User Created',
            text: 'User has been created successfully.'
        );
    }

    public function resetForm()
    {
        $this->username = '';
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->person_in_charge = '';
        $this->role = '';
        $this->password = '';
        $this->password_confirmation = '';
    }
}
