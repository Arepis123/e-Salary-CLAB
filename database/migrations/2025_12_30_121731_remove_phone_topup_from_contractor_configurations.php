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
        Schema::table('contractor_configurations', function (Blueprint $table) {
            $table->dropColumn(['phone_topup_enabled', 'phone_topup_months']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractor_configurations', function (Blueprint $table) {
            $table->boolean('phone_topup_enabled')->default(false);
            $table->json('phone_topup_months')->nullable();
        });
    }
};
