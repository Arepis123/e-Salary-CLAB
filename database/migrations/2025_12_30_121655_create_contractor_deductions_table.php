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
        Schema::create('contractor_deductions', function (Blueprint $table) {
            $table->id();

            // Links contractor to deduction template
            $table->foreignId('contractor_configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_template_id')->constrained()->cascadeOnDelete();

            // Audit fields
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enabled_at')->nullable();

            $table->timestamps();

            // Ensure a contractor can only have each deduction template once
            $table->unique(['contractor_configuration_id', 'deduction_template_id'], 'contractor_deduction_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractor_deductions');
    }
};
