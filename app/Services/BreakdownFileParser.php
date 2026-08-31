<?php

namespace App\Services;

use App\Exceptions\BreakdownFileParseException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads the totals row out of the payroll breakdown spreadsheet an admin
 * uploads when approving a submission.
 *
 * The figure this produces becomes admin_final_amount, which is what the
 * client is billed, so the arithmetic here is deliberately identical to what
 * the review screen has always done:
 *
 *   total = (Gross Salary + EPF + SOCSO + EIS + HRDF)
 *         - (Custom Advance Salary + Custom Accomodation + Custom Npl)
 *
 * Backpay is read for the record but excluded from the total by design.
 */
class BreakdownFileParser
{
    /** Columns the file must contain. */
    public const REQUIRED_COLUMNS = ['Gross Salary', 'EPF', 'SOCSO', 'EIS'];

    /** Columns that add to the total when present. */
    public const OPTIONAL_ADDITION_COLUMNS = ['HRDF', 'Backpay'];

    /** Columns that subtract from the total when present. */
    public const OPTIONAL_DEDUCTION_COLUMNS = ['Custom Advance Salary', 'Custom Accomodation', 'Custom Npl'];

    /**
     * Worker-side statutory deductions.
     *
     * SKBBK is mandatory for foreign workers and comes out of the worker's
     * gross on the way to net pay, exactly like their EPF and SOCSO shares.
     * The client is billed the gross, so these are recorded for the payslip
     * and the breakdown display but never added to or taken off the total.
     */
    public const WORKER_DEDUCTION_COLUMNS = ['SKBBK'];

    /** Rows to scan while hunting for the header row. */
    protected const HEADER_SEARCH_ROWS = 10;

    /** A row needs this many recognisable headers to be treated as the header row. */
    protected const HEADER_MATCH_THRESHOLD = 3;

    /**
     * Parse a breakdown spreadsheet into its itemised totals.
     *
     * @return array{gross_salary: float, epf: float, socso: float, eis: float, hrdf: float, backpay: float, skbbk: float, custom_advance_salary: float, custom_accomodation: float, custom_npl: float, total: float}
     *
     * @throws BreakdownFileParseException
     */
    public function parse(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

            $headers = $this->findHeaders($sheet, $highestColumnIndex);
            $columnIndices = $this->mapColumns($headers);

            $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($columnIndices)));

            if ($missing !== []) {
                throw BreakdownFileParseException::missingColumns($missing, array_values($headers));
            }

            // The last row carries the column sums.
            $totalsRow = $sheet->getHighestRow();

            $totals = [];
            foreach (array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_ADDITION_COLUMNS) as $column) {
                $totals[$column] = isset($columnIndices[$column])
                    ? (float) $sheet->getCellByColumnAndRow($columnIndices[$column], $totalsRow)->getCalculatedValue()
                    : 0.0;
            }

            $deductions = [];
            foreach (self::OPTIONAL_DEDUCTION_COLUMNS as $column) {
                // Deduction columns are written as negatives in the sheet.
                $deductions[$column] = isset($columnIndices[$column])
                    ? abs((float) $sheet->getCellByColumnAndRow($columnIndices[$column], $totalsRow)->getCalculatedValue())
                    : 0.0;
            }

            // Recorded for the payslip; deliberately outside the total.
            $workerDeductions = [];
            foreach (self::WORKER_DEDUCTION_COLUMNS as $column) {
                $workerDeductions[$column] = isset($columnIndices[$column])
                    ? abs((float) $sheet->getCellByColumnAndRow($columnIndices[$column], $totalsRow)->getCalculatedValue())
                    : 0.0;
            }

            $additions = array_sum($totals) - $totals['Backpay'];

            return [
                'gross_salary' => $totals['Gross Salary'],
                'epf' => $totals['EPF'],
                'socso' => $totals['SOCSO'],
                'eis' => $totals['EIS'],
                'hrdf' => $totals['HRDF'],
                'backpay' => $totals['Backpay'],
                'skbbk' => $workerDeductions['SKBBK'],
                'custom_advance_salary' => $deductions['Custom Advance Salary'],
                'custom_accomodation' => $deductions['Custom Accomodation'],
                'custom_npl' => $deductions['Custom Npl'],
                'total' => $additions - array_sum($deductions),
            ];
        } finally {
            // Keeps memory flat when a caller parses hundreds of files in a row.
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Locate the header row and return its normalised cell values, keyed by
     * column index.
     *
     * @return array<int, string>
     *
     * @throws BreakdownFileParseException
     */
    protected function findHeaders($sheet, int $highestColumnIndex): array
    {
        $probeColumns = array_merge(self::REQUIRED_COLUMNS, ['HRDF']);
        $lastRow = min(self::HEADER_SEARCH_ROWS, $sheet->getHighestRow());

        for ($row = 1; $row <= $lastRow; $row++) {
            $rowHeaders = [];

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = $sheet->getCellByColumnAndRow($col, $row)->getValue();

                if ($value === null || $value === '') {
                    continue;
                }

                $rowHeaders[$col] = preg_replace('/\s+/', ' ', trim((string) $value));
            }

            $matches = 0;
            foreach ($probeColumns as $probe) {
                foreach ($rowHeaders as $header) {
                    if (strcasecmp($header, $probe) === 0) {
                        $matches++;
                        break;
                    }
                }
            }

            if ($matches >= self::HEADER_MATCH_THRESHOLD) {
                return $rowHeaders;
            }
        }

        throw BreakdownFileParseException::headerRowNotFound();
    }

    /**
     * Map each known column name to its spreadsheet column index.
     *
     * Where a name appears more than once the LAST occurrence wins: these
     * sheets list the worker's share first and the employer's share second,
     * and it is the employer share the client is billed for.
     *
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    protected function mapColumns(array $headers): array
    {
        $known = array_merge(
            self::REQUIRED_COLUMNS,
            self::OPTIONAL_ADDITION_COLUMNS,
            self::OPTIONAL_DEDUCTION_COLUMNS,
            self::WORKER_DEDUCTION_COLUMNS,
        );

        $indices = [];

        foreach ($known as $column) {
            foreach ($headers as $index => $header) {
                if (strcasecmp($header, $column) === 0) {
                    $indices[$column] = $index;
                }
            }
        }

        return $indices;
    }
}
