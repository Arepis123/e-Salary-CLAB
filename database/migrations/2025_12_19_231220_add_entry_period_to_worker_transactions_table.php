<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_worker_transactions', function (Blueprint $table) {
            // Month/Year these transactions are FOR (e.g., November)
            $table->integer('entry_month')->nullable()->after('payroll_worker_id');
            $table->integer('entry_year')->nullable()->after('entry_month');

            // Month/Year when submitted (e.g., December 1-15)
            $table->integer('submission_month')->nullable()->after('entry_year');
            $table->integer('submission_year')->nullable()->after('submission_month');

            // Entry window dates
            $table->date('submission_window_start')->nullable()->after('submission_year');
            $table->date('submission_window_end')->nullable()->after('submission_window_start');

            // Status for transaction entry
            $table->enum('entry_status', ['draft', 'submitted', 'locked'])->default('draft')->after('submission_window_end');

            // Index for querying by entry period
            $table->index(['entry_month', 'entry_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_worker_transactions', function (Blueprint $table) {
            $table->dropIndex(['entry_month', 'entry_year']);
            $table->dropColumn([
                'entry_month',
                'entry_year',
                'submission_month',
                'submission_year',
                'submission_window_start',
                'submission_window_end',
                'entry_status',
            ]);
        });
    }
};
