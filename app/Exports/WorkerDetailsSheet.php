<?php

namespace App\Exports;

use App\Models\Contractor;
use App\Models\InactiveWorker;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkerDetailsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $workers;

    protected $contractorNames = [];

    /** @var array<int, \App\Models\InactiveWorker> keyed by worker id */
    protected $inactiveRecords = [];

    public function __construct($workers)
    {
        $this->workers = $workers;

        // Bank details live in a separate table; pull them all in one query
        // rather than lazy-loading per row.
        if ($workers instanceof \Illuminate\Database\Eloquent\Collection) {
            $workers->loadMissing('latestBank');
        }

        // Preload contractor names to avoid N+1 queries
        $clabNos = collect($workers)->map(function ($worker) {
            if (is_object($worker)) {
                $contract = $worker->contract_info ?? $worker->activeContract ?? null;
                if ($contract && isset($contract->con_ctr_clab_no)) {
                    return $contract->con_ctr_clab_no;
                }

                return $worker->wkr_currentemp ?? null;
            }

            return null;
        })->filter()->unique()->values()->toArray();

        if (! empty($clabNos)) {
            // The contractors table carries the registered company name; fall
            // back to the portal user's name when a contractor row is missing.
            $this->contractorNames = Contractor::whereIn('ctr_clab_no', $clabNos)
                ->pluck('ctr_comp_name', 'ctr_clab_no')
                ->filter()
                ->toArray();

            $missing = array_diff($clabNos, array_keys($this->contractorNames));

            if (! empty($missing)) {
                $this->contractorNames += \App\Models\User::whereIn('contractor_clab_no', $missing)
                    ->pluck('name', 'contractor_clab_no')
                    ->toArray();
            }
        }

        // Workers an admin has manually deactivated are inactive regardless of
        // their contract dates — the same rule the worker screens apply.
        $workerIds = collect($workers)->map(fn ($w) => is_object($w) ? $w->wkr_id : null)->filter()->all();

        if (! empty($workerIds)) {
            $this->inactiveRecords = InactiveWorker::whereIn('worker_id', $workerIds)
                ->get()
                ->keyBy('worker_id')
                ->all();
        }
    }

    public function collection()
    {
        return $this->workers;
    }

    public function title(): string
    {
        return 'Worker Details';
    }

    public function headings(): array
    {
        return [
            // Identity
            'Worker ID',
            'Name',
            'Passport Number',
            'Date of Birth',
            'Age',
            'Gender',
            'Nationality',
            'Position/Trade',
            // Contact
            'Phone',
            'Email',
            'Home Address',
            'Local Address',
            // Documents
            'Passport Expiry',
            'Permit Expiry',
            'Entry Date',
            // Statutory
            'SOCSO Number',
            'EPF/KWSP Number',
            // Bank
            'Bank Name',
            'Bank Account Number',
            'Bank Account Type',
            'Bank Details Updated',
            // Employment
            'Basic Salary',
            'CLAB ID',
            'Contractor Name',
            'Contract Start',
            'Contract End',
            'Contract Days Remaining',
            'Contract Status',
            'Deactivated On',
            // Emergency contact
            'Next of Kin',
            'Relationship',
        ];
    }

    public function map($worker): array
    {
        if (! is_object($worker)) {
            return array_fill(0, count($this->headings()), '-');
        }

        // Handle both client context (contract_info) and admin context (activeContract)
        $contract = null;
        if (isset($worker->contract_info)) {
            // Client context
            $contract = $worker->contract_info;
        } elseif (isset($worker->activeContract)) {
            // Admin context
            $contract = $worker->activeContract;
        } else {
            // Try to load the relationship if not loaded
            $worker->load('activeContract');
            $contract = $worker->activeContract;
        }

        $inactiveRecord = $this->inactiveRecords[$worker->wkr_id] ?? null;
        $contractActive = $contract && $contract->isActive() && ! $inactiveRecord;

        // Get CLAB ID
        $clabId = '-';
        if ($contract && isset($contract->con_ctr_clab_no)) {
            $clabId = $contract->con_ctr_clab_no;
        } elseif (isset($worker->wkr_currentemp)) {
            $clabId = $worker->wkr_currentemp ?: '-';
        }

        // Get Contractor Name from preloaded data
        $contractorName = $this->contractorNames[$clabId] ?? '-';

        $bank = $worker->latestBank;

        return [
            // Identity
            $worker->wkr_id,
            $worker->name ?: '-',
            $worker->ic_number ?: '-',
            $this->date($worker->wkr_dob),
            $worker->wkr_dob ? $worker->wkr_dob->age : '-',
            $this->getGender($worker->wkr_gender),
            $worker->country->cty_desc ?? '-',
            $worker->position ?? ($worker->workTrade->trade_desc ?? '-'),
            // Contact
            $worker->phone ?: '-',
            $worker->wkr_email ?: '-',
            $worker->wkr_homeaddr ?: '-',
            $this->localAddress($worker),
            // Documents
            $this->date($worker->wkr_passexp),
            $this->date($worker->wkr_permitexp),
            $this->date($worker->wkr_entrydate),
            // Statutory
            $worker->wkr_socso_id ?: '-',
            $worker->wkr_kwsp ?: '-',
            // Bank
            $bank?->bank_name ?: '-',
            $bank?->account_no ?: '-',
            $bank && $bank->type ? ucfirst(str_replace('-', ' ', $bank->type)) : '-',
            $bank ? $this->date($bank->created_at) : '-',
            // Employment
            $worker->basic_salary ? 'RM '.number_format($worker->basic_salary, 2) : '-',
            $clabId,
            $contractorName,
            $contract ? $this->date($contract->con_start) : '-',
            $contract ? $this->date($contract->con_end) : '-',
            $contract && $contract->isActive() ? $contract->daysRemaining() : '-',
            $contractActive ? 'Active' : 'Inactive',
            $inactiveRecord ? $this->date($inactiveRecord->deactivated_at) : '-',
            // Emergency contact
            $worker->wkr_next_of_kin ?: '-',
            $worker->wkr_relationship ?: '-',
        ];
    }

    /**
     * The worker's Malaysian address, assembled from the parts the source
     * system stores separately.
     */
    private function localAddress($worker): string
    {
        $parts = array_filter([
            $worker->wkr_address1,
            $worker->wkr_address2,
            $worker->wkr_address3,
            $worker->wkr_pcode,
            $worker->wkr_state,
        ], fn ($part) => filled($part));

        return $parts ? implode(', ', $parts) : '-';
    }

    private function date($value): string
    {
        if (empty($value)) {
            return '-';
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : (string) $value;
    }

    private function getGender($gender)
    {
        return match ($gender) {
            1, '1' => 'Male',
            2, '2' => 'Female',
            default => '-'
        };
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Worker ID
            'B' => 28,  // Name
            'C' => 18,  // Passport Number
            'D' => 14,  // Date of Birth
            'E' => 8,   // Age
            'F' => 10,  // Gender
            'G' => 18,  // Nationality
            'H' => 24,  // Position/Trade
            'I' => 16,  // Phone
            'J' => 26,  // Email
            'K' => 34,  // Home Address
            'L' => 34,  // Local Address
            'M' => 16,  // Passport Expiry
            'N' => 16,  // Permit Expiry
            'O' => 14,  // Entry Date
            'P' => 18,  // SOCSO Number
            'Q' => 18,  // EPF/KWSP Number
            'R' => 18,  // Bank Name
            'S' => 22,  // Bank Account Number
            'T' => 16,  // Bank Account Type
            'U' => 18,  // Bank Details Updated
            'V' => 14,  // Basic Salary
            'W' => 14,  // CLAB ID
            'X' => 32,  // Contractor Name
            'Y' => 14,  // Contract Start
            'Z' => 14,  // Contract End
            'AA' => 20, // Contract Days Remaining
            'AB' => 14, // Contract Status
            'AC' => 16, // Deactivated On
            'AD' => 26, // Next of Kin
            'AE' => 16, // Relationship
        ];
    }
}
