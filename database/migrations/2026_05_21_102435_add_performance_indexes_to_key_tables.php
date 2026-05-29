<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->index('status', 'idx_ps_status');
            $table->index('year', 'idx_ps_year');
            $table->index(['month', 'year'], 'idx_ps_month_year');
            $table->index('submitted_at', 'idx_ps_submitted_at');
        });

        Schema::table('payroll_workers', function (Blueprint $table) {
            $table->index('worker_id', 'idx_pw_worker_id');
        });

        Schema::table('monthly_ot_entry_transactions', function (Blueprint $table) {
            $table->index('type', 'idx_moet_type');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_ps_status');
            $table->dropIndex('idx_ps_year');
            $table->dropIndex('idx_ps_month_year');
            $table->dropIndex('idx_ps_submitted_at');
        });

        Schema::table('payroll_workers', function (Blueprint $table) {
            $table->dropIndex('idx_pw_worker_id');
        });

        Schema::table('monthly_ot_entry_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_moet_type');
        });
    }
};
