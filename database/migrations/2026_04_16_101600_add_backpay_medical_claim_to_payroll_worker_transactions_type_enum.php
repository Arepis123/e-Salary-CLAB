<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `payroll_worker_transactions` MODIFY `type` ENUM('advance_payment', 'deduction', 'npl', 'allowance', 'accommodation', 'backpay', 'medical_claim') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `payroll_worker_transactions` MODIFY `type` ENUM('advance_payment', 'deduction', 'npl', 'allowance', 'accommodation') NOT NULL");
    }
};
