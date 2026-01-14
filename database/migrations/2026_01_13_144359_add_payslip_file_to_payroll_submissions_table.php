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
            // Add columns for payslip ZIP file storage
            $table->string('payslip_file_path')->nullable()->after('breakdown_file_name');
            $table->string('payslip_file_name')->nullable()->after('payslip_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->dropColumn(['payslip_file_path', 'payslip_file_name']);
        });
    }
};
