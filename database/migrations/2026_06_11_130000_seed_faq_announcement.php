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
            'title'         => 'New: Frequently Asked Questions (FAQ)',
            'description'   => 'A new FAQ menu is now available in your sidebar. Click "FAQ" to open our answers to common questions about timesheets, OT entry, payments and invoices in a new tab. We keep it updated, so check back whenever you need a quick guide.',
            'type'          => 'announcement',
            'icon'          => 'question-mark-circle',
            'gradient_from' => 'blue-500',
            'gradient_to'   => 'indigo-600',
            'button_text'   => 'Open FAQ',
            'button_url'    => '/faq',
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
            ->where('title', 'New: Frequently Asked Questions (FAQ)')
            ->delete();

        // Restore original order
        DB::table('news')->decrement('order');
    }
};
