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
        Schema::create('deduction_templates', function (Blueprint $table) {
            $table->id();

            // Deduction details
            $table->string('name'); // E.g., "Phone Topup", "Uniform Fee"
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2); // Fixed amount (RM)
            $table->json('apply_months'); // Array of month numbers [1-12]
            $table->boolean('is_active')->default(true);

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deduction_templates');
    }
};
