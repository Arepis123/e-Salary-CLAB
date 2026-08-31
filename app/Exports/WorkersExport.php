<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WorkersExport implements WithMultipleSheets
{
    protected $workers;

    /**
     * When set, the export is scoped to a single contractor: payroll rows
     * belonging to other contractors are excluded. Null means an unscoped
     * (admin) export.
     */
    protected ?string $clabNo;

    public function __construct($workers, ?string $clabNo = null)
    {
        $this->workers = $workers;
        $this->clabNo = $clabNo;
    }

    public function sheets(): array
    {
        return [
            new WorkerDetailsSheet($this->workers),
            new PayrollDetailsSheet($this->workers, $this->clabNo),
        ];
    }
}
