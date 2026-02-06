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
        Schema::create('inactive_workers', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id')->index(); // References wkr_id from worker_db
            $table->string('worker_name');
            $table->string('worker_passport')->nullable();
            $table->string('contractor_clab_no')->nullable()->index();
            $table->string('reason')->nullable();
            $table->foreignId('deactivated_by')->constrained('users');
            $table->timestamp('deactivated_at');
            $table->timestamps();

            // Unique constraint to prevent duplicate entries
            $table->unique(['worker_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inactive_workers');
    }
};
