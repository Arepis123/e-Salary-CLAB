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
        Schema::create('monthly_ot_entries', function (Blueprint $table) {
            $table->id();
            $table->string('contractor_clab_no');
            $table->string('worker_id');
            $table->string('worker_name');
            $table->string('worker_passport')->nullable();

            // Month/Year these entries are FOR (e.g., November)
            $table->integer('entry_month');
            $table->integer('entry_year');

            // Month/Year when these entries were submitted (e.g., December 1-15)
            $table->integer('submission_month');
            $table->integer('submission_year');

            // OT Hours
            $table->decimal('ot_normal_hours', 8, 2)->default(0);
            $table->decimal('ot_rest_hours', 8, 2)->default(0);
            $table->decimal('ot_public_hours', 8, 2)->default(0);

            // Status
            $table->enum('status', ['draft', 'submitted', 'locked'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable(); // Locked after 15th

            $table->timestamps();

            // Unique constraint: one entry per worker per month
            $table->unique(['contractor_clab_no', 'worker_id', 'entry_month', 'entry_year'], 'unique_worker_entry_month');

            // Indexes (with custom short names to avoid MariaDB length limit)
            $table->index(['contractor_clab_no', 'entry_month', 'entry_year'], 'idx_ot_contractor_entry');
            $table->index('status', 'idx_ot_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_ot_entries');
    }
};
