<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the itemised totals parsed from the admin's uploaded breakdown
     * file (gross salary, employer EPF/SOCSO/EIS/HRDF and the custom
     * deduction columns). Previously only the single summed
     * admin_final_amount survived the review, so the client payment
     * breakdown had no way to show what made up that figure.
     */
    public function up(): void
    {
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->json('admin_breakdown')->nullable()->after('admin_final_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->dropColumn('admin_breakdown');
        });
    }
};
