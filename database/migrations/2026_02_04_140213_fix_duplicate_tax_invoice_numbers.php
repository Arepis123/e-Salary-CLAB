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
        // Step 1: Find and fix duplicate tax_invoice_numbers
        $this->fixDuplicates();

        // Step 2: Add unique constraint to prevent future duplicates
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->unique('tax_invoice_number', 'payroll_submissions_tax_invoice_number_unique');
        });
    }

    /**
     * Fix duplicate tax_invoice_numbers by regenerating them
     */
    protected function fixDuplicates(): void
    {
        // Find all duplicate tax_invoice_numbers
        $duplicates = DB::table('payroll_submissions')
            ->select('tax_invoice_number', DB::raw('COUNT(*) as count'))
            ->whereNotNull('tax_invoice_number')
            ->groupBy('tax_invoice_number')
            ->having('count', '>', 1)
            ->pluck('tax_invoice_number');

        if ($duplicates->isEmpty()) {
            return;
        }

        foreach ($duplicates as $duplicateTaxNumber) {
            // Get all submissions with this duplicate tax_invoice_number
            // Keep the first one (oldest by tax_invoice_generated_at), regenerate others
            $submissions = DB::table('payroll_submissions')
                ->where('tax_invoice_number', $duplicateTaxNumber)
                ->orderBy('tax_invoice_generated_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // Skip the first one (it keeps its number)
            $submissionsToFix = $submissions->skip(1);

            foreach ($submissionsToFix as $submission) {
                // Generate a new unique tax_invoice_number for this submission
                $newTaxNumber = $this->generateNewTaxInvoiceNumber($submission->year, $submission->month);

                DB::table('payroll_submissions')
                    ->where('id', $submission->id)
                    ->update([
                        'tax_invoice_number' => $newTaxNumber,
                        'tax_invoice_generated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Generate a new unique tax_invoice_number
     */
    protected function generateNewTaxInvoiceNumber(int $year, int $month): string
    {
        $yearShort = substr((string) $year, -2);
        $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
        $prefix = 'OR-P-' . $yearShort . $monthPadded;

        // Find the highest running number for this year-month
        $latestInvoice = DB::table('payroll_submissions')
            ->whereNotNull('tax_invoice_number')
            ->where('tax_invoice_number', 'LIKE', $prefix . '%')
            ->orderByRaw("CAST(SUBSTRING(tax_invoice_number, -4) AS UNSIGNED) DESC")
            ->first();

        if ($latestInvoice && $latestInvoice->tax_invoice_number) {
            $lastNumber = (int) substr($latestInvoice->tax_invoice_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s%04d', $prefix, $nextNumber);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_submissions', function (Blueprint $table) {
            $table->dropUnique('payroll_submissions_tax_invoice_number_unique');
        });
    }
};
