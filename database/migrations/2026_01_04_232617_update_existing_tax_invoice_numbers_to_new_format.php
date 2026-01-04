<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollSubmission;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert existing tax invoice numbers from TINV-YYYY-NNNN to OR-P-YYMMNNNN format
     */
    public function up(): void
    {
        // Get all submissions with tax invoice numbers
        $submissions = PayrollSubmission::whereNotNull('tax_invoice_number')
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('tax_invoice_generated_at')
            ->get();

        if ($submissions->isEmpty()) {
            echo "No existing tax invoice numbers to update.\n";
            return;
        }

        echo "Found " . $submissions->count() . " tax invoice(s) to update.\n";

        // Group by year-month to maintain sequential numbering
        $groupedSubmissions = $submissions->groupBy(function ($submission) {
            return $submission->year . '-' . str_pad($submission->month, 2, '0', STR_PAD_LEFT);
        });

        $totalUpdated = 0;

        foreach ($groupedSubmissions as $yearMonth => $group) {
            $runningNumber = 1;

            foreach ($group as $submission) {
                $year = $submission->year;
                $month = $submission->month;
                $yearShort = substr((string) $year, -2);
                $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);

                // Generate new format: OR-P-YYMMNNNN
                $newInvoiceNumber = sprintf('OR-P-%s%s%04d', $yearShort, $monthPadded, $runningNumber);

                // Store old invoice number for logging
                $oldInvoiceNumber = $submission->tax_invoice_number;

                // Update the invoice number
                DB::table('payroll_submissions')
                    ->where('id', $submission->id)
                    ->update(['tax_invoice_number' => $newInvoiceNumber]);

                echo sprintf(
                    "Updated ID %d: %s -> %s\n",
                    $submission->id,
                    $oldInvoiceNumber,
                    $newInvoiceNumber
                );

                $runningNumber++;
                $totalUpdated++;
            }
        }

        echo "Successfully updated " . $totalUpdated . " tax invoice number(s) to new format.\n";
    }

    /**
     * Reverse the migrations.
     * Note: This cannot perfectly restore original numbers, but provides a valid format
     */
    public function down(): void
    {
        // Get all submissions with new format tax invoice numbers
        $submissions = PayrollSubmission::whereNotNull('tax_invoice_number')
            ->where('tax_invoice_number', 'LIKE', 'OR-P-%')
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('tax_invoice_generated_at')
            ->get();

        if ($submissions->isEmpty()) {
            echo "No tax invoice numbers to revert.\n";
            return;
        }

        echo "Found " . $submissions->count() . " tax invoice(s) to revert.\n";

        // Group by year to maintain sequential numbering
        $groupedSubmissions = $submissions->groupBy('year');

        $totalReverted = 0;

        foreach ($groupedSubmissions as $year => $group) {
            $runningNumber = 1;

            foreach ($group as $submission) {
                // Revert to old format: TINV-YYYY-NNNN
                $oldInvoiceNumber = sprintf('TINV-%d-%04d', $year, $runningNumber);

                // Store new invoice number for logging
                $newInvoiceNumber = $submission->tax_invoice_number;

                // Update the invoice number
                DB::table('payroll_submissions')
                    ->where('id', $submission->id)
                    ->update(['tax_invoice_number' => $oldInvoiceNumber]);

                echo sprintf(
                    "Reverted ID %d: %s -> %s\n",
                    $submission->id,
                    $newInvoiceNumber,
                    $oldInvoiceNumber
                );

                $runningNumber++;
                $totalReverted++;
            }
        }

        echo "Successfully reverted " . $totalReverted . " tax invoice number(s) to old format.\n";
    }
};
