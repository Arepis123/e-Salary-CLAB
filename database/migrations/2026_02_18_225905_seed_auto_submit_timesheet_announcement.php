<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Shift existing news orders down to make room at position 1
        DB::table('news')->increment('order');

        DB::table('news')->insert([
            'title'         => 'Important: Timesheet Now Auto-Submitted on the 16th',
            'description'   => 'Starting this month, timesheets are automatically submitted by the system on the 16th of every month. You no longer need to manually submit. Please ensure all overtime hours and transactions are entered before the 16th to avoid missing data in your payroll.',
            'type'          => 'announcement',
            'icon'          => 'calendar-days',
            'gradient_from' => 'purple-500',
            'gradient_to'   => 'indigo-600',
            'button_text'   => 'Go to Timesheet',
            'button_url'    => '/timesheet',
            'order'         => 1,
            'is_active'     => true,
            'published_at'  => null,
            'expires_at'    => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('news')
            ->where('title', 'Important: Timesheet Now Auto-Submitted on the 16th')
            ->delete();

        // Restore original order
        DB::table('news')->decrement('order');
    }
};
