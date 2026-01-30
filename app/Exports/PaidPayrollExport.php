<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PaidPayrollExport implements WithMultipleSheets
{
    protected $data;

    protected $period;

    public function __construct($data, $period = [])
    {
        $this->data = $data;
        $this->period = $period;
    }

    public function sheets(): array
    {
        return [
            new PaidPayrollSummarySheet($this->data, $this->period),
            new PaidPayrollWorkersSheet($this->data, $this->period),
        ];
    }
}
