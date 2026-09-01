<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_config_changes', function (Blueprint $table) {
            $table->id();
            $table->string('contractor_clab_no')->index();
            $table->string('contractor_name')->nullable();
            // What was changed: service_charge_exempt, penalty_exempt, payment_enabled, deductions, ot_window
            $table->string('setting');
            // Human-readable before/after values (e.g. "Exempt" -> "Not Exempt", or a deduction name list)
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['contractor_clab_no', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_config_changes');
    }
};
