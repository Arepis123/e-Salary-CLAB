<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Server-side gate for payroll data that arrives from a client's browser.
 *
 * Livewire public properties travel to the browser and back, and a client can
 * rewrite any of them before an action runs. Payroll rows therefore must never
 * be saved as received: salary, worker identity and the lock flags come from the
 * copy the server built when the page loaded, and only the fields a client is
 * genuinely allowed to key in (OT hours and transactions, while not locked) are
 * taken from the request — and those are validated here.
 */
class ClientPayrollInputGuard
{
    public const TRANSACTION_TYPES = [
        'accommodation',
        'advance_payment',
        'npl',
        'allowance',
        'backpay',
        'medical_claim',
        'deduction',
    ];

    public const MAX_OT_HOURS = 744; // 31 days x 24 hours

    public const MAX_TRANSACTION_AMOUNT = 100000;

    public function __construct(
        protected NplCalculatorService $nplCalculator
    ) {}

    /**
     * Rebuild the client's worker rows from the trusted server copy.
     *
     * @param  array  $clientRows  rows as they came back from the browser
     * @param  array  $trustedRows  rows the server built on load, keyed by worker_id
     * @return array rows safe to persist, in the client's order
     *
     * @throws InvalidArgumentException when a row names a worker the server never loaded
     */
    public function workerRows(array $clientRows, array $trustedRows, ?string $clabNo): array
    {
        $clean = [];

        foreach ($clientRows as $key => $row) {
            $workerId = $row['worker_id'] ?? null;

            if ($workerId === null || ! isset($trustedRows[$workerId])) {
                throw new InvalidArgumentException('One of the workers in this payroll does not belong to your company. Please reload the page and try again.');
            }

            $safe = $trustedRows[$workerId];

            if (! ($safe['ot_from_monthly_entry'] ?? false)) {
                $safe['ot_normal_hours'] = $this->hours($row['ot_normal_hours'] ?? 0, $safe['worker_name'] ?? '');
                $safe['ot_rest_hours'] = $this->hours($row['ot_rest_hours'] ?? 0, $safe['worker_name'] ?? '');
                $safe['ot_public_hours'] = $this->hours($row['ot_public_hours'] ?? 0, $safe['worker_name'] ?? '');
            }

            if (! ($safe['transactions_from_monthly_entry'] ?? false)) {
                $safe['transactions'] = $this->transactions($row['transactions'] ?? [], (int) $workerId, $clabNo);
            }

            $transactions = collect($safe['transactions'] ?? []);
            $safe['advance_payment'] = (float) $transactions->where('type', 'advance_payment')->sum('amount');
            $safe['deduction'] = (float) $transactions->where('type', 'deduction')->sum('amount');

            $clean[$key] = $safe;
        }

        return $clean;
    }

    /**
     * Validate one OT hours value.
     */
    public function hours($value, string $workerName = ''): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (! is_numeric($value) || $value < 0 || $value > self::MAX_OT_HOURS) {
            throw new InvalidArgumentException(trim("Invalid overtime hours {$workerName}").'. Hours must be between 0 and '.self::MAX_OT_HOURS.'.');
        }

        return round((float) $value, 2);
    }

    /**
     * Validate a worker's transactions and recompute NPL amounts server-side.
     *
     * @return array<int, array{type:string, amount:float, remarks:string, npl_details:array}>
     */
    public function transactions($transactions, int $workerId, ?string $clabNo): array
    {
        if (! is_array($transactions)) {
            return [];
        }

        $clean = [];
        $monthlySalary = null;

        foreach (array_values($transactions) as $txn) {
            if (! is_array($txn)) {
                throw new InvalidArgumentException('Invalid transaction data.');
            }

            $type = $txn['type'] ?? null;

            if (! is_string($type) || ! in_array($type, self::TRANSACTION_TYPES, true)) {
                throw new InvalidArgumentException('Invalid transaction type.');
            }

            $amount = $txn['amount'] ?? null;

            if (! is_numeric($amount) || $amount < 0 || $amount > self::MAX_TRANSACTION_AMOUNT) {
                throw new InvalidArgumentException('Transaction amounts must be between RM 0 and RM '.number_format(self::MAX_TRANSACTION_AMOUNT).'.');
            }

            $remarks = $txn['remarks'] ?? '';
            $remarks = is_scalar($remarks) ? mb_substr((string) $remarks, 0, 1000) : '';

            $nplDetails = [];

            if ($type === 'npl' && ! empty($txn['npl_details']) && is_array($txn['npl_details'])) {
                // Never trust the browser's salary, rate or amount for NPL: keep
                // only which months and how many days, and price them here.
                $monthlySalary ??= $this->nplCalculator->resolveMonthlySalary($workerId, $clabNo);

                foreach ($txn['npl_details'] as $detail) {
                    $year = (int) ($detail['npl_year'] ?? 0);
                    $month = (int) ($detail['npl_month'] ?? 0);
                    $days = $detail['npl_days'] ?? 0;

                    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12
                        || ! is_numeric($days) || $days <= 0 || $days > 31) {
                        throw new InvalidArgumentException('Invalid NPL month or days.');
                    }

                    $nplDetails[] = $this->nplCalculator->calculateMonth($monthlySalary, $year, $month, (float) $days);
                }

                // An NPL transaction's `amount` is its total day count; the
                // ringgit value lives in the breakdown rows.
                $amount = round(array_sum(array_column($nplDetails, 'npl_days')), 1);
            }

            $clean[] = [
                'type' => $type,
                'amount' => round((float) $amount, 2),
                'remarks' => $remarks,
                'npl_details' => $nplDetails,
            ];
        }

        return $clean;
    }
}
