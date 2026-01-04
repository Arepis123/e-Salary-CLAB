<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PaymentSummaryExport implements WithMultipleSheets
{
    protected $stats;

    protected $clientPayments;

    protected $topWorkers;

    protected $chartData;

    protected $selectedMonth;

    protected $selectedYear;

    public function __construct($stats, $clientPayments, $topWorkers, $chartData, $selectedMonth, $selectedYear)
    {
        $this->stats = $stats;
        $this->clientPayments = $clientPayments;
        $this->topWorkers = $topWorkers;
        $this->chartData = $chartData;
        $this->selectedMonth = $selectedMonth;
        $this->selectedYear = $selectedYear;
    }

    /**
     * Return array of sheets
     */
    public function sheets(): array
    {
        return [
            new PaymentSummarySheet($this->stats, $this->clientPayments, $this->topWorkers, $this->chartData, $this->selectedMonth, $this->selectedYear),
        ];
    }
}
