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
        Schema::create('contractor_window_logs', function (Blueprint $table) {
            $table->id();
            $table->string('contractor_clab_no')->index();
            $table->enum('action', ['opened', 'closed'])->index();
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable(); // Store additional context
            $table->timestamps();

            // Composite index for efficient querying
            $table->index(['contractor_clab_no', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractor_window_logs');
    }
};
