<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A contractor can only be managed by one PIC, so contractor_clab_no must be unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop any contractor assigned to more than one PIC, keeping the earliest one
        $duplicates = DB::table('user_contractor_assignments')
            ->select('contractor_clab_no', DB::raw('MIN(id) as keep_id'))
            ->groupBy('contractor_clab_no')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('user_contractor_assignments')
                ->where('contractor_clab_no', $duplicate->contractor_clab_no)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('user_contractor_assignments', function (Blueprint $table) {
            // Replaced by the unique index below
            $table->dropIndex('user_contractor_assignments_contractor_clab_no_index');
            $table->unique('contractor_clab_no');
        });
    }

    public function down(): void
    {
        Schema::table('user_contractor_assignments', function (Blueprint $table) {
            $table->dropUnique('user_contractor_assignments_contractor_clab_no_unique');
            $table->index('contractor_clab_no');
        });
    }
};
