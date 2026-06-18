<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index for the Missing Submissions / Out of Sync page.
 *
 * The Out of Sync scan filters payroll_submissions by (month, year, status)
 * with no contractor_clab_no. A prior migration already added a (month, year)
 * index (idx_ps_month_year) and a separate status index; this composite covers
 * all three columns so the status filter is served by the index too.
 *
 * worker_id (payroll_workers) and status (payroll_submissions) are already
 * indexed by 2026_05_21_102435_add_performance_indexes_to_key_tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasIndex('payroll_submissions', 'idx_ps_period_status')) {
            Schema::table('payroll_submissions', function (Blueprint $table) {
                $table->index(['month', 'year', 'status'], 'idx_ps_period_status');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('payroll_submissions', 'idx_ps_period_status')) {
            Schema::table('payroll_submissions', function (Blueprint $table) {
                $table->dropIndex('idx_ps_period_status');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return (bool) DB::table('information_schema.statistics')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
