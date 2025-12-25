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
        Schema::table('payroll_workers', function (Blueprint $table) {
            $table->boolean('is_pro_rated')->default(false)->after('basic_salary');
            $table->integer('days_worked')->nullable()->after('is_pro_rated');
            $table->integer('total_days_in_month')->nullable()->after('days_worked');
            $table->text('prorating_notes')->nullable()->after('total_days_in_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_workers', function (Blueprint $table) {
            $table->dropColumn(['is_pro_rated', 'days_worked', 'total_days_in_month', 'prorating_notes']);
        });
    }
};
