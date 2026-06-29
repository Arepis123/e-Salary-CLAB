<?php

namespace App\Livewire\Admin;

use App\Mail\WelcomeNewUser;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // Keep the plaintext password to email to the user before the model hashes it
        $plainPassword = $this->password;

        // Create the user
        $user = User::create([
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'person_in_charge' => $this->person_in_charge,
            'role' => $this->role,
            'password' => $plainPassword, // Let the model cast handle hashing
            'contractor_clab_no' => null, // Admin users don't have CLAB numbers
            'email_verified_at' => now(), // Auto-verify admin users
        ]);

        // Email the new user their login credentials. A mail failure must not
        // block account creation, so failures are logged and surfaced as a warning.
        $emailSent = true;
        try {
            Mail::to($user->email)->send(new WelcomeNewUser($user, $plainPassword));
        } catch (\Throwable $e) {
            $emailSent = false;
            Log::error('Failed to send welcome email to new user.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        // Reset form
        $this->resetForm();

        // Close modal
        Flux::modal('create-user')->close();

        // Reload users list
        $this->dispatch('reloadUsers');

        // Show success message
        if ($emailSent) {
            Flux::toast(
                variant: 'success',
                heading: 'User Created',
                text: 'User created successfully. Login credentials have been emailed to '.$user->email.'.'
            );
        } else {
            Flux::toast(
                variant: 'warning',
                heading: 'User Created',
                text: 'User was created, but the credentials email could not be sent. Please share the login details manually.'
            );
        }
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
