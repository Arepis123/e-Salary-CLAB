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
        Schema::create('contractor_window_settings', function (Blueprint $table) {
            $table->id();

            // Contractor information
            $table->string('contractor_clab_no')->unique()->index();
            $table->string('contractor_name')->nullable(); // Cached for display

            // Window control
            $table->boolean('is_window_open')->default(false);
            $table->timestamp('window_opened_at')->nullable();
            $table->timestamp('window_closed_at')->nullable();

            // Optional custom date range (for future enhancement)
            $table->date('custom_start_date')->nullable();
            $table->date('custom_end_date')->nullable();

            // Audit fields
            $table->foreignId('last_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('last_change_remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractor_window_settings');
    }
};
