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
        Schema::create('entry_unlock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_ot_entry_id')->constrained()->onDelete('cascade');
            $table->enum('previous_status', ['submitted', 'locked']);
            $table->enum('new_status', ['submitted', 'draft']);
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('unlock_reason')->default('Window manually reopened');
            $table->timestamps();

            $table->index('monthly_ot_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entry_unlock_logs');
    }
};
