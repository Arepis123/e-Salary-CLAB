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
        Schema::create('worker_deductions', function (Blueprint $table) {
            $table->id();

            // Links deduction template to specific worker under specific contractor
            $table->foreignId('deduction_template_id')->constrained('deduction_templates')->cascadeOnDelete();
            $table->string('worker_id')->index(); // wkr_id from worker_db (external READ-ONLY database)
            $table->string('contractor_clab_no')->index(); // Contractor assignment context

            // Audit fields - track who assigned and when
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('assignment_notes')->nullable(); // Optional context for why assigned

            $table->timestamps();

            // Ensure a worker can't have duplicate deductions for same template under same contractor
            $table->unique(['deduction_template_id', 'worker_id', 'contractor_clab_no'], 'worker_deduction_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_deductions');
    }
};
