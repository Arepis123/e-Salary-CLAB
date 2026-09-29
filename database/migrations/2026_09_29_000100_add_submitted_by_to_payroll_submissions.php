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
            // Admin who submitted on the contractor's behalf (null = client / auto-submit)
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
        });
    }
};
