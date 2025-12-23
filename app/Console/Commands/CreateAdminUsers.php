<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Admin and Super Admin users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating Admin and Super Admin users...');
        $this->newLine();

        // Create Super Admin
        $superAdminEmail = $this->ask('Enter Super Admin email', 'superadmin@epayroll.com');
        $superAdminPassword = $this->secret('Enter Super Admin password');
        $superAdminPasswordConfirm = $this->secret('Confirm Super Admin password');

        if ($superAdminPassword !== $superAdminPasswordConfirm) {
            $this->error('Passwords do not match for Super Admin!');

            return 1;
        }

        $superAdmin = User::updateOrCreate(
            ['email' => $superAdminEmail],
            [
                'username' => 'superadmin',
                'name' => 'Super Administrator',
                'email' => $superAdminEmail,
                'password' => Hash::make($superAdminPassword),
                'role' => 'super_admin',
                'phone' => '000-0000000',
                'person_in_charge' => 'Super Admin',
                'contractor_clab_no' => null,
                'email_verified_at' => now(),
            ]
        );

        $this->info('✓ Super Admin created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Email', $superAdmin->email],
                ['Username', $superAdmin->username],
                ['Name', $superAdmin->name],
                ['Role', $superAdmin->role],
            ]
        );
        $this->newLine();

        // Create Regular Admin
        if ($this->confirm('Do you want to create a regular Admin user?', true)) {
            $adminEmail = $this->ask('Enter Admin email', 'admin@epayroll.com');
            $adminPassword = $this->secret('Enter Admin password');
            $adminPasswordConfirm = $this->secret('Confirm Admin password');

            if ($adminPassword !== $adminPasswordConfirm) {
                $this->error('Passwords do not match for Admin!');

                return 1;
            }

            $admin = User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'username' => 'admin',
                    'name' => 'Administrator',
                    'email' => $adminEmail,
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                    'phone' => '000-0000000',
                    'person_in_charge' => 'Admin',
                    'contractor_clab_no' => null,
                    'email_verified_at' => now(),
                ]
            );

            $this->info('✓ Admin created successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Email', $admin->email],
                    ['Username', $admin->username],
                    ['Name', $admin->name],
                    ['Role', $admin->role],
                ]
            );
        }

        $this->newLine();
        $this->info('All admin users have been created successfully! 🎉');
        $this->info('You can now login with the credentials you provided.');

        return 0;
    }
}
