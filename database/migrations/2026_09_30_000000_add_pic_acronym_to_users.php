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
        Schema::table('users', function (Blueprint $table) {
            // Short tag (max 3 letters) shown for the PIC on the salary list
            $table->string('pic_acronym', 3)->nullable()->unique()->after('person_in_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pic_acronym']);
            $table->dropColumn('pic_acronym');
        });
    }
};
