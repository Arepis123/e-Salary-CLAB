<?php

use App\Models\PayrollSubmission;
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
        Schema::table('payroll_submissions', function (Blueprint $table) {
            // Admin review fields
            $table->unsignedBigInteger('admin_reviewed_by')->nullable()->after('status');
            $table->timestamp('admin_reviewed_at')->nullable()->after('admin_reviewed_by');
            $table->decimal('admin_final_amount', 10, 2)->nullable()->after('admin_reviewed_at');
            $table->text('admin_notes')->nullable()->after('admin_final_amount');

            // Breakdown file fields
            $table->string('breakdown_file_path')->nullable()->after('admin_notes');
            $table->string('breakdown_file_name')->nullable()->after('breakdown_file_path');

            // Migration tracking
            $table->boolean('is_legacy_submission')->default(false)->after('breakdown_file_name');

            // Foreign key
            $table->foreign('admin_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        // Update existing submissions status enum to include new statuses
        DB::statement("ALTER TABLE payroll_submissions MODIFY COLUMN status ENUM('draft', 'submitted', 'approved', 'pending_payment', 'paid', 'overdue') DEFAULT 'draft'");

        // Migrate existing data
        $this->migrateExistingData();
    }

    /**
     * Migrate existing payroll submissions data
     */
    private function migrateExistingData(): void
    {
        // Mark all existing submissions as legacy
        PayrollSubmission::query()->update(['is_legacy_submission' => true]);

        // Pre-populate admin_final_amount for all existing submissions with grand_total
        PayrollSubmission::query()->update([
            'admin_final_amount' => DB::raw('grand_total'),
        ]);

        // Update status for pending_payment submissions (they need admin review in new workflow)
        // But keep paid submissions as paid (already completed)
        PayrollSubmission::where('status', 'pending_payment')
            ->update(['status' => 'submitted']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status changes first
        DB::statement("UPDATE payroll_submissions SET status = 'pending_payment' WHERE status = 'submitted'");
        DB::statement("UPDATE payroll_submissions SET status = 'pending_payment' WHERE status = 'approved'");

        // Revert to original enum values
        DB::statement("ALTER TABLE payroll_submissions MODIFY COLUMN status ENUM('draft', 'pending_payment', 'paid', 'overdue') DEFAULT 'draft'");

        Schema::table('payroll_submissions', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['admin_reviewed_by']);

            // Drop columns
            $table->dropColumn([
                'admin_reviewed_by',
                'admin_reviewed_at',
                'admin_final_amount',
                'admin_notes',
                'breakdown_file_path',
                'breakdown_file_name',
                'is_legacy_submission',
            ]);
        });
    }
};
