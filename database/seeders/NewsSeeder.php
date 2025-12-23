<?php

namespace Database\Seeders;

use App\Models\News;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        News::create([
            'title' => 'Welcome to e-Salary CLAB!',
            'description' => 'Thank you for using our payroll management system. We\'re here to help you manage your worker salaries efficiently and accurately.',
            'type' => 'welcome',
            'icon' => 'hand-raised',
            'gradient_from' => 'blue-500',
            'gradient_to' => 'purple-600',
            'order' => 1,
            'is_active' => true,
        ]);

        News::create([
            'title' => 'System Maintenance Notice',
            'description' => 'Please be informed that system maintenance will be conducted on the last Sunday of every month from 2:00 AM to 6:00 AM. Services may be temporarily unavailable during this period.',
            'type' => 'alert',
            'icon' => 'exclamation-triangle',
            'gradient_from' => 'orange-500',
            'gradient_to' => 'red-600',
            'order' => 2,
            'is_active' => true,
        ]);

        News::create([
            'title' => 'New Feature: Bulk Import',
            'description' => 'We\'ve added a new bulk import feature! You can now import multiple worker records at once using CSV files. This will save you time when managing large teams.',
            'type' => 'announcement',
            'icon' => 'sparkles',
            'gradient_from' => 'green-500',
            'gradient_to' => 'teal-600',
            'button_text' => 'Learn More',
            'button_url' => '/admin/worker',
            'order' => 3,
            'is_active' => true,
        ]);
    }
}
