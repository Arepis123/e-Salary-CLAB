<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UnpaidPayrollExport implements WithMultipleSheets
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
            new UnpaidPayrollSummarySheet($this->data, $this->period),
            new UnpaidPayrollWorkersSheet($this->data, $this->period),
        ];
    }
}
