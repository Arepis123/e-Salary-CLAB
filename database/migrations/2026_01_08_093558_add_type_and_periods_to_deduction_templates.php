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
        Schema::table('deduction_templates', function (Blueprint $table) {
            // Add type discriminator: 'contractor' (applies to all workers) or 'worker' (applies to specific workers)
            $table->enum('type', ['contractor', 'worker'])->default('contractor')->after('name');

            // Add target payroll periods for worker-level deductions (JSON array like [2, 7, 12])
            $table->json('apply_periods')->nullable()->after('apply_months');

            // Add index for filtering by type
            $table->index('type');
        });

        // Backfill existing templates as contractor-level
        DB::table('deduction_templates')->update(['type' => 'contractor']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deduction_templates', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'apply_periods']);
        });
    }
};
