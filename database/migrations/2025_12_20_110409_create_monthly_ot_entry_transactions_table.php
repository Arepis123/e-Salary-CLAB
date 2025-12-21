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
        Schema::create('monthly_ot_entry_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_ot_entry_id')->constrained('monthly_ot_entries')->onDelete('cascade');
            $table->string('type'); // 'advance_payment', 'deduction', 'npl', 'allowance'
            $table->decimal('amount', 10, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Index for faster lookups
            $table->index('monthly_ot_entry_id', 'idx_monthly_txn_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_ot_entry_transactions');
    }
};
