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
        Schema::table('payroll_submissions', function (Blueprint $table) {
            // Sequential tax invoice number (generated after payment)
            $table->string('tax_invoice_number', 50)->nullable()->after('id');
            // Timestamp when tax invoice was generated
            $table->timestamp('tax_invoice_generated_at')->nullable()->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->dropColumn(['tax_invoice_number', 'tax_invoice_generated_at']);
        });
    }
};
