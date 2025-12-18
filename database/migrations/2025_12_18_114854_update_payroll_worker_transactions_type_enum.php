<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the type enum to include 'npl' and 'allowance'
        DB::statement("ALTER TABLE `payroll_worker_transactions` MODIFY `type` ENUM('advance_payment', 'deduction', 'npl', 'allowance') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE `payroll_worker_transactions` MODIFY `type` ENUM('advance_payment', 'deduction') NOT NULL");
    }
};
