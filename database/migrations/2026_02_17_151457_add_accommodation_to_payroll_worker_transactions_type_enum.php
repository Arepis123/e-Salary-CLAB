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
        // Add 'accommodation' to the type enum
        DB::statement("ALTER TABLE `payroll_worker_transactions` MODIFY `type` ENUM('advance_payment', 'deduction', 'npl', 'allowance', 'accommodation') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `payroll_worker_transactions` MODIFY `type` ENUM('advance_payment', 'deduction', 'npl', 'allowance') NOT NULL");
    }
};
