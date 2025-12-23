<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {email?}';

    protected $description = 'Reset admin user password';

    public function handle()
    {
        $email = $this->argument('email') ?: $this->ask('Enter admin email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found!");

            return 1;
        }

        if (! in_array($user->role, ['super_admin', 'admin'])) {
            $this->error("User is not an admin! Role: {$user->role}");

            return 1;
        }

        $this->info("Resetting password for: {$user->name} ({$user->email})");

        $newPassword = $this->secret('Enter new password');
        $confirmPassword = $this->secret('Confirm new password');

        if ($newPassword !== $confirmPassword) {
            $this->error('Passwords do not match!');

            return 1;
        }

        if (strlen($newPassword) < 6) {
            $this->error('Password must be at least 6 characters!');

            return 1;
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        $this->info('✓ Password reset successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', $user->role],
            ]
        );

        return 0;
    }
}
