<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-month breakdown for a No-Pay Leave (NPL) transaction. One NPL
     * transaction may span several months, each charged at that month's own
     * daily rate (monthly salary / actual days in that month).
     *
     * Rows attach polymorphically to either a MonthlyOTEntryTransaction (client
     * entry stage) or a PayrollWorkerTransaction (submitted payroll stage).
     *
     * NPL transactions with no rows here are legacy records and keep the old
     * flat "salary / 26" calculation.
     */
    public function up(): void
    {
        Schema::create('npl_month_details', function (Blueprint $table) {
            $table->id();
            $table->morphs('nplable');

            // The month the leave was actually taken. This is deliberately
            // independent of the payroll month the deduction is applied in.
            $table->unsignedSmallInteger('npl_year');
            $table->unsignedTinyInteger('npl_month');

            // Snapshot of the inputs so a historical row can always be
            // re-explained, even if salary or calendar assumptions change.
            $table->unsignedTinyInteger('days_in_month');
            $table->decimal('npl_days', 5, 1);
            $table->decimal('monthly_salary', 10, 2);
            $table->decimal('daily_rate', 10, 2);
            $table->decimal('amount', 10, 2);

            $table->timestamps();

            // One row per month per transaction.
            $table->unique(
                ['nplable_type', 'nplable_id', 'npl_year', 'npl_month'],
                'idx_npl_detail_unique_month'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npl_month_details');
    }
};
