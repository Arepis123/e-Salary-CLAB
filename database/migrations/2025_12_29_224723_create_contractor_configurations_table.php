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
        Schema::create('contractor_configurations', function (Blueprint $table) {
            $table->id();

            // Contractor information
            $table->string('contractor_clab_no')->unique()->index();
            $table->string('contractor_name')->nullable(); // Cached for display

            // Phone topup deduction settings
            $table->boolean('phone_topup_enabled')->default(false);
            $table->json('phone_topup_months')->nullable(); // Array of month numbers [1-12]

            // Service charge exemption
            $table->boolean('service_charge_exempt')->default(false);

            // Audit fields
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractor_configurations');
    }
};
