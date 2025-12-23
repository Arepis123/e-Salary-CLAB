<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollSubmissionsExport implements WithMultipleSheets
{
    protected $submissions;

    protected $filters;

    public function __construct($submissions, $filters = [])
    {
        $this->submissions = $submissions;
        $this->filters = $filters;
    }

    /**
     * Return array of sheets
     */
    public function sheets(): array
    {
        return [
            new PayrollSubmissionsSheet($this->submissions, $this->filters),
            new PayrollSummarySheet($this->submissions, $this->filters),
        ];
    }
}
