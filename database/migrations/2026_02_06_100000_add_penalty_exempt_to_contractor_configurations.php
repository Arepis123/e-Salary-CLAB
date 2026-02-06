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
            $table->boolean('penalty_exempt')->default(false)->after('service_charge_exempt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractor_configurations', function (Blueprint $table) {
            $table->dropColumn('penalty_exempt');
        });
    }
};
