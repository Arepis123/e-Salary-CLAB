<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the signed Salary Deduction Form a contractor uploads back after
     * downloading the pre-filled declaration from the OT & Transaction Entry
     * page. One row per contractor per entry period; re-uploading replaces the
     * previous file.
     */
    public function up(): void
    {
        Schema::create('salary_deduction_forms', function (Blueprint $table) {
            $table->id();
            $table->string('contractor_clab_no', 50);
            $table->unsignedTinyInteger('entry_month');
            $table->unsignedSmallInteger('entry_year');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedSmallInteger('workers_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['contractor_clab_no', 'entry_month', 'entry_year'], 'salary_deduction_form_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_deduction_forms');
    }
};
