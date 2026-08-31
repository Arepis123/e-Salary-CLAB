<?php

namespace App\Services;

use App\Models\Contractor;
use Carbon\Carbon;

/**
 * Builds the pre-filled Salary Deduction Form (CLAB declaration) for every
 * worker who has a deduction recorded in the OT & Transaction Entry period.
 *
 * Only deductions the client keys in on that page are declared here (advance
 * payment, accommodation, NPL). Deductions configured by an admin under
 * Configuration > Contractor Settings are deliberately excluded.
 *
 * The form is a declaration the contractor's officer and the worker both sign,
 * so only the identifying data is pre-filled — signature, position and date
 * lines are deliberately left blank.
 */
class SalaryDeductionFormService
{
    /**
     * Transaction types that count as a salary deduction. Earnings
     * (allowance, backpay, medical claim) never produce a form.
     */
    public const DEDUCTION_TYPES = ['advance_payment', 'accommodation', 'npl', 'deduction'];

    /**
     * Types with their own tick box in section (b). Anything else falls
     * through to the free-text "Others" line.
     */
    protected const TICKED_TYPES = ['advance_payment', 'accommodation', 'npl'];

    protected const TYPE_LABELS = [
        'advance_payment' => 'Advance Payment',
        'accommodation' => 'Accommodation',
        'npl' => 'No-Pay Leave (NPL)',
        'deduction' => 'Deduction',
    ];

    /**
     * Reduce the OT entry rows to one form payload per worker with deductions.
     *
     * @param  array  $entries  the OTEntry component's $entries array
     * @return array<int, array> one entry per worker, in the order given
     */
    public function buildFormsFromEntries(array $entries): array
    {
        $forms = [];

        foreach ($entries as $entry) {
            $deductions = $this->deductionsFor($entry['transactions'] ?? []);

            if (empty($deductions)) {
                continue;
            }

            $forms[] = $this->buildWorkerForm($entry, $deductions);
        }

        return $forms;
    }

    /**
     * Count of workers that would receive a form for the given entries.
     */
    public function countWorkersWithDeductions(array $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            if (! empty($this->deductionsFor($entry['transactions'] ?? []))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The deduction transactions of a single worker, ignoring zero amounts.
     *
     * @return array<int, array>
     */
    protected function deductionsFor(array $transactions): array
    {
        $deductions = [];

        foreach ($transactions as $txn) {
            $type = $txn['type'] ?? null;

            if (! in_array($type, self::DEDUCTION_TYPES, true)) {
                continue;
            }

            $amount = (float) ($txn['amount'] ?? 0);
            $nplDetails = $txn['npl_details'] ?? [];
            $days = null;

            if ($type === 'npl') {
                // An NPL transaction stores its total day count in `amount`
                // (see OTEntry::addNplTransaction); the ringgit value is the
                // sum of its per-month breakdown, priced at each month's own
                // daily rate. The form declares money, so convert here.
                $days = $amount;
                $amount = round(array_sum(array_map(
                    fn ($detail) => (float) ($detail['amount'] ?? 0),
                    $nplDetails
                )), 2);
            }

            // Judge NPL on days, not ringgit: a legacy row saved before the
            // per-month rule carries no breakdown to price it from, and must
            // still be declared rather than silently dropped.
            if (($type === 'npl' ? $days : $amount) <= 0) {
                continue;
            }

            $deductions[] = [
                'type' => $type,
                'label' => self::TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', (string) $type)),
                'amount' => $amount,
                'days' => $days,
                'remarks' => trim((string) ($txn['remarks'] ?? '')),
                'npl_details' => $nplDetails,
            ];
        }

        return $deductions;
    }

    /**
     * Shape one worker's deductions into the fields the form prints.
     *
     * Advance Payment, Accommodation and NPL each get their own tick box in
     * section (b); anything left over lands on the free-text "Others" line.
     */
    protected function buildWorkerForm(array $entry, array $deductions): array
    {
        $ticked = array_fill_keys(self::TICKED_TYPES, 0.0);
        // Presence is tracked apart from the total so an NPL row with no
        // priceable breakdown still ticks its box.
        $present = array_fill_keys(self::TICKED_TYPES, false);
        $nplDays = 0.0;
        $otherTotal = 0.0;
        $otherLabels = [];
        $remarkLines = [];

        foreach ($deductions as $deduction) {
            if (array_key_exists($deduction['type'], $ticked)) {
                $ticked[$deduction['type']] += $deduction['amount'];
                $present[$deduction['type']] = true;
                $nplDays += $deduction['type'] === 'npl' ? (float) ($deduction['days'] ?? 0) : 0;
            } else {
                $otherTotal += $deduction['amount'];
                $otherLabels[] = $deduction['label'];
            }

            // Section (b) already states each type and amount, so (c) only
            // carries what it cannot show: the client's stated reason, and the
            // months an NPL deduction is charged against.
            if ($line = $this->remarkLineFor($deduction)) {
                $remarkLines[] = $line;
            }
        }

        return [
            'worker_id' => $entry['worker_id'] ?? null,
            'worker_name' => $entry['worker_name'] ?? '',
            'worker_passport' => $entry['worker_passport'] ?? '',
            'has_advance_payment' => $present['advance_payment'],
            'advance_payment_amount' => $ticked['advance_payment'],
            'has_accommodation' => $present['accommodation'],
            'accommodation_amount' => $ticked['accommodation'],
            'has_npl' => $present['npl'],
            'npl_amount' => $ticked['npl'],
            'npl_days' => $nplDays,
            'other_label' => implode(', ', array_unique($otherLabels)),
            'other_amount' => $otherTotal,
            'total_amount' => array_sum($ticked) + $otherTotal,
            'remark_lines' => $remarkLines,
            'deductions' => $deductions,
        ];
    }

    /**
     * One line of section (c) — the reason the deduction is being made.
     * NPL expands to the months charged so the worker can verify them.
     *
     * Returns null when there is nothing section (b) has not already said —
     * no stated reason and no NPL breakdown — so (c) stays readable.
     */
    protected function remarkLineFor(array $deduction): ?string
    {
        $months = [];

        if ($deduction['type'] === 'npl' && ! empty($deduction['npl_details'])) {
            $months = array_map(
                fn ($detail) => ($detail['month_label'] ?? '').' ('.$this->formatDays((float) ($detail['npl_days'] ?? 0)).')',
                $deduction['npl_details']
            );
        }

        if (empty($months) && $deduction['remarks'] === '') {
            return null;
        }

        // A legacy NPL row has no breakdown to price it from, so state the
        // days rather than an untrue "RM 0.00".
        $value = $deduction['amount'] > 0
            ? 'RM '.number_format($deduction['amount'], 2)
            : $this->formatDays((float) ($deduction['days'] ?? 0));

        $line = $deduction['label'].' — '.$value;

        if (! empty($months)) {
            $line .= ' for '.implode(', ', $months);
        }

        if ($deduction['remarks'] !== '') {
            $line .= ': '.$deduction['remarks'];
        }

        return $line;
    }

    /**
     * Day count for display: trims a trailing ".0" and pluralises.
     */
    protected function formatDays(float $days): string
    {
        $formatted = rtrim(rtrim(number_format($days, 1), '0'), '.');

        return $formatted.' '.($days == 1.0 ? 'day' : 'days');
    }

    /**
     * Applicant (section a) details, pulled from the contractor record.
     */
    public function applicantDetails(?string $clabNo): array
    {
        $contractor = $clabNo
            ? Contractor::where('ctr_clab_no', $clabNo)->first()
            : null;

        return [
            'clab_no' => $clabNo ?? '',
            'company_name' => $contractor?->ctr_comp_name ?? '',
            'officer_name' => $contractor?->ctr_contact_name ?? '',
            'telephone' => $contractor?->ctr_contact_mobileno ?: ($contractor?->ctr_telno ?? ''),
            'email' => $contractor?->ctr_email ?? '',
        ];
    }

    /**
     * Filename for the form, e.g. "Deduction_Form_ADVATIS_TECHNOLOGIES_Aug_2026.pdf".
     *
     * Both directions share it: the pre-filled bundle the client downloads and
     * the signed copy they upload back are the same document for the same
     * period, so they carry the same name.
     *
     * The trading name identifies the file at a glance, so it stands in for
     * the CLAB number, shortened the same way breakdown files are — see
     * Contractor::fileNameSlug(). The CLAB number is the fallback when the
     * contractor record yields nothing usable.
     */
    public function fileName(?string $clabNo, int $month, int $year, string $extension = 'pdf'): string
    {
        $contractor = $clabNo
            ? Contractor::where('ctr_clab_no', $clabNo)->first()
            : null;

        $client = Contractor::fileNameSlug($contractor?->ctr_comp_name);

        if ($client === '') {
            $client = trim(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $clabNo), '_');
        }

        $period = Carbon::create($year, $month, 1)->format('M_Y');
        $extension = strtolower(trim($extension, '.')) ?: 'pdf';

        return 'Deduction_Form_'.($client !== '' ? $client.'_' : '').$period.'.'.$extension;
    }
}
