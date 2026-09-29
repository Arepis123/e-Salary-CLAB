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
        Schema::create('payroll_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id')->index(); // References wkr_id from worker_db
            $table->string('worker_name');
            $table->string('worker_passport')->nullable();
            $table->string('contractor_clab_no')->nullable()->index();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->text('remarks');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // One exception per worker per payroll month
            $table->unique(['worker_id', 'month', 'year']);
            $table->index(['month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_exceptions');
    }
};
