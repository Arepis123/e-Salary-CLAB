<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Receipt #{{ $invoice->tax_invoice_number ?? 'PENDING' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 20mm 25mm;
            size: A4 portrait;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            background-color: #fff;
            color: #000;
            padding: 15mm 15mm;
        }

        .receipt-container {
            max-width: 100%;
            background: white;
            margin: 0 auto;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            padding: 10px 0 24px 0;
            border-bottom: 2px solid #000;
        }

        .company-name {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .company-details {
            font-size: 9px;
            line-height: 1.6;
        }

        /* ── Title ── */
        .receipt-title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            padding: 10px 0;
            border-bottom: 1px solid #ccc;
            letter-spacing: 1px;
        }

        /* ── Receipt Info ── */
        .receipt-info-table {
            width: 100%;
            border-collapse: collapse;
            padding: 12px 0;
        }

        .to-name {
            font-weight: bold;
            margin-bottom: 2px;
            font-size: 10px;
        }

        .to-address {
            font-size: 9px;
            line-height: 1.5;
            margin-bottom: 1px;
        }

        .receipt-meta {
            text-align: right;
            white-space: nowrap;
            font-size: 10px;
            line-height: 1.8;
            vertical-align: top;
        }

        /* ── Table ── */
        .table-container {
            padding: 14px 0;
            border-bottom: 1px solid #ccc;
        }

        .transaction-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transaction-table th {
            background-color: #f0f0f0;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 7px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
        }

        .transaction-table td {
            padding: 6px 6px;
            font-size: 9px;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        .summary-row td {
            border-bottom: none;
        }

        .summary-row td strong { font-size: 9px; }

        .grand-total-row td {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 7px 6px;
        }

        /* ── Amount in Words ── */
        .amount-words {
            padding: 10px 0;
            border-bottom: 1px solid #ccc;
            font-size: 10px;
        }

        .amount-words-label {
            font-weight: bold;
            margin-right: 6px;
        }

        /* ── Footer ── */
        .footer-note {
            padding: 10px 0 0 0;
            font-size: 9px;
            color: #555;
            font-style: italic;
        }
    </style>
</head>
<body>
@php
    $payrollAmount    = $invoice->admin_final_amount ?? 0;
    $serviceCharge    = $invoice->calculated_service_charge;
    $sstAmount        = $invoice->calculated_sst;
    $totalBeforeSst   = $payrollAmount + $serviceCharge;
    $grandTotal       = $invoice->payment ? $invoice->payment->amount : $invoice->client_total;
    $workerCount      = $invoice->billable_workers_count;
    $transactionId    = $invoice->payment->transaction_id ?? 'N/A';
    $paidAt           = $invoice->payment?->completed_at?->format('d M Y') ?? now()->format('d M Y');
    $receiptNo        = $invoice->tax_invoice_number ?? 'PENDING';
    $periodLabel      = \Carbon\Carbon::create($invoice->year, $invoice->month, 1)->format('F Y');
    $contractorName   = $contractor ? ($contractor->company_name ?? $contractor->name) : $invoice->contractor_clab_no;
    $contractorClab   = $invoice->contractor_clab_no;
    $addComma = fn($s) => $s ? (str_ends_with(trim($s), ',') ? trim($s) : trim($s) . ',') : null;
    $pcodeLine = trim(($contractorRecord->ctr_pcode ?? '') . ' ' . ($contractorRecord->ctr_addr3 ?? '')) ?: null;
    $addressParts = array_values(array_filter([
        $addComma($contractorRecord->ctr_addr1 ?? null),
        $addComma($contractorRecord->ctr_addr2 ?? null),
        $addComma($pcodeLine),
        $contractorState ?? null,
    ]));
    $contractorPhone = $contractor?->phone ?? ($contractorRecord->ctr_contact_mobileno ?? $contractorRecord->ctr_telno ?? '');

    // Amount in words
    function numberToWords(float $amount): string {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen',
                 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $intPart  = (int) floor($amount);
        $censPart = (int) round(($amount - $intPart) * 100);

        $convert = function (int $n) use (&$convert, $ones, $tens): string {
            if ($n === 0) return '';
            if ($n < 20)  return $ones[$n];
            if ($n < 100) return $tens[(int)($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
            if ($n < 1000) return $ones[(int)($n / 100)] . ' Hundred' . ($n % 100 ? ' ' . $convert($n % 100) : '');
            if ($n < 1000000) return $convert((int)($n / 1000)) . ' Thousand' . ($n % 1000 ? ' ' . $convert($n % 1000) : '');
            return $convert((int)($n / 1000000)) . ' Million' . ($n % 1000000 ? ' ' . $convert($n % 1000000) : '');
        };

        $words = $intPart === 0 ? 'Zero' : $convert($intPart);
        if ($censPart > 0) {
            $words .= ' And ' . $convert($censPart) . ' Cents';
        }
        return $words;
    }

    $amountInWords = numberToWords((float) $grandTotal);
@endphp

<div class="receipt-container">

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width:70px; vertical-align:middle; padding-right:12px; padding-bottom:20px;">
                <img src="{{ public_path('logo-clab.png') }}" alt="CLAB Logo" style="width:65px; height:auto;">
            </td>
            <td style="vertical-align:middle; padding-bottom:20px;">
                <div class="company-name">CONSTRUCTION LABOUR EXCHANGE CENTRE BERHAD (CLAB)</div>
                <div class="company-details">
                    <div>Level 2, Annexe Block, Menara Millenium, No. 8, Jalan Damanlela, Bukit Damansara, 50490 Kuala Lumpur</div>
                    <div>Tel: 03-2095 9559 &nbsp;|&nbsp; Fax: 03-2095 9566 &nbsp;|&nbsp; Email: info@clab.com.my</div>
                    <div>Website: www.clab.com.my &nbsp;|&nbsp; No Daftar Kastam: W10-1808-32001804</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Title --}}
    <div class="receipt-title">OFFICIAL RECEIPT / INVOICE</div>

    {{-- Receipt Info --}}
    <table class="receipt-info-table">
        <tr>
            <td style="vertical-align:top; padding-right:10px; padding-bottom:7px;">
                <div class="to-name">{{ $contractorName }}</div>
                @foreach($addressParts as $line)
                    <div class="to-address">{{ $line }}</div>
                @endforeach
                @if($contractorPhone)
                    <div class="to-address">{{ $contractorPhone }}</div>
                @endif
                @if($contractor && $contractor->email)
                    <div class="to-address">{{ $contractor->email }}</div>
                @endif
            </td>
            <td class="receipt-meta" style="width:170px;">
                <div>Official Receipt No &nbsp;: <strong>{{ $receiptNo }}</strong></div>
                <div>Invoice No &nbsp;: <strong>#PAY{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</strong></div>
                <div>Date : {{ $paidAt }}</div>
            </td>
        </tr>
    </table>

    {{-- Table --}}
    <div class="table-container">
        <table class="transaction-table">
            <thead>
                <tr>
                    <th style="width:16%">TRANSACTION NO.</th>
                    <th>PAYROLL SUMMARY</th>
                    <th style="width:14%">TOTAL (RM)</th>
                </tr>
            </thead>
            <tbody>
                {{-- Row 1: Payroll Amount --}}
                <tr>
                    <td class="text-center">{{ $transactionId }}</td>
                    <td>
                        <div><strong>Payroll Payment — {{ $periodLabel }}</strong></div>
                        <div style="color:#555; margin-top:2px;">Workers: {{ $workerCount }} pax</div>
                        <div style="color:#555;">Payroll Amount for {{ $periodLabel }}</div>
                    </td>
                    <td class="text-right">{{ number_format($payrollAmount, 2) }}</td>
                </tr>

                {{-- Row 2: Admin Fees --}}
                <tr>
                    <td></td>
                    <td>
                        <div><strong>Admin Fees — {{ $periodLabel }}</strong></div>
                        <div style="color:#555; margin-top:2px;">RM 200.00 × {{ $workerCount }} Workers</div>
                    </td>
                    <td class="text-right">{{ number_format($serviceCharge, 2) }}</td>
                </tr>

                {{-- SST row --}}
                <tr>
                    <td colspan="1"></td>
                    <td>Service Tax (SST) 8%</td>
                    <td class="text-right">{{ number_format($sstAmount, 2) }}</td>
                </tr>

                {{-- Penalty rows --}}
                @if($invoice->has_penalty && $invoice->penalty_amount > 0)
                <tr class="summary-row">
                    <td colspan="1"></td>
                    <td><strong>Total (RM)</strong></td>
                    <td class="text-right"><strong>{{ number_format($invoice->client_total, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="1"></td>
                    <td>Late Payment Penalty (8%)</td>
                    <td class="text-right">{{ number_format($invoice->penalty_amount, 2) }}</td>
                </tr>
                @endif

                {{-- Grand Total row --}}
                <tr class="grand-total-row">
                    <td colspan="1"></td>
                    <td><strong>Grand Total (RM)</strong></td>
                    <td class="text-right"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Amount in Words --}}
    <div class="amount-words">
        <span class="amount-words-label">Amount in words (RM):</span>
        <span>{{ strtoupper($amountInWords) }} RINGGIT MALAYSIA ONLY</span>
    </div>

    {{-- Footer --}}
    <div class="footer-note">
        This is a computer generated receipt, no signature required.<br>
        This document serves as official proof of payment. For inquiries: 03-2095 9559 | esalary@clab.com.my
    </div>

</div>
</body>
</html>
