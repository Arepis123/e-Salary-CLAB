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
        Schema::table('payroll_payments', function (Blueprint $table) {
            // Add payment_type column to track B2B or B2C
            $table->string('payment_type')->nullable()->after('payment_method');

            // Add bank_name column to track which bank/payment method was used
            $table->string('bank_name')->nullable()->after('payment_type');
        });

        // Update status enum to include 'redirected'
        DB::statement("ALTER TABLE payroll_payments MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'redirected') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'bank_name']);
        });

        // Revert status enum back to original
        DB::statement("ALTER TABLE payroll_payments MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending'");
    }
};
