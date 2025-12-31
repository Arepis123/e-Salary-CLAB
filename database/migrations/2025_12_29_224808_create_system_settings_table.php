<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            // Settings key-value
            $table->string('key')->unique()->index();
            $table->text('value'); // Stored as JSON for flexibility
            $table->text('description')->nullable();

            // Audit fields
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // Insert default phone topup amount
        DB::table('system_settings')->insert([
            'key' => 'phone_topup_amount',
            'value' => json_encode(50.00),
            'description' => 'Phone topup deduction amount (RM)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
