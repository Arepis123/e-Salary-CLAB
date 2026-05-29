<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds fields needed to record off-platform payments (e.g. direct bank
     * transfers into the company account) that don't flow through Billplz FPX.
     */
    public function up(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table) {
            // Path + original name of the uploaded proof (bank-in slip / transfer confirmation)
            $table->string('payment_proof_path')->nullable()->after('payment_response');
            $table->string('payment_proof_name')->nullable()->after('payment_proof_path');

            // Admin/finance user who manually recorded the payment (audit trail)
            $table->foreignId('recorded_by')->nullable()->after('payment_proof_name')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['payment_proof_path', 'payment_proof_name']);
        });
    }
};
