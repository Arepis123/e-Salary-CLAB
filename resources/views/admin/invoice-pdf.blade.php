<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #INV-{{ str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 20px;
            size: A4 landscape;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 15px 20px;
        }
        .invoice-container {
            max-width: 100%;
            margin: 0 auto;
        }
        .header {
            margin-bottom: 10px;
        }
        .invoice-title {
            font-family: 'Inter', sans-serif;
            font-size: 20px;
            font-weight: 600;
            color: #000;
            margin-bottom: 5px;
        }
        .logo-section {
            text-align: right;
            margin-bottom: 5px;
        }
        .logo-section img {
            max-width: 120px;
            height: auto;
        }
        .company-info {
            font-size: 9px;
            color: #666;
            margin-bottom: 10px;
        }
        .bill-to-section {
            margin-bottom: 10px;
        }
        .bill-to-label {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 3px;
        }
        .bill-to-content {
            font-size: 9px;
            line-height: 1.5;
        }
        .invoice-details {
            text-align: right;
            font-size: 9px;
            line-height: 1.6;
        }
        .invoice-details-label {
            display: inline-block;
            width: 90px;
            font-weight: bold;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 5px 0;
        }
        .items-table thead {
            border-bottom: 1px solid #000;
        }
        .items-table th {
            padding: 4px 3px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 5px 3px;
            font-size: 8px;
            vertical-align: top;
            border-bottom: 1px solid #e0e0e0;
        }
        .items-table .worker-name {
            width: 15%;
        }
        .items-table .basic-salary {
            width: 9%;
            text-align: right;
        }
        .items-table .ot-col {
            width: 9%;
            text-align: right;
        }
        .items-table .deduction-col {
            width: 12%;
            text-align: right;
        }
        .items-table .gross-salary,
        .items-table .worker-epf,
        .items-table .worker-socso,
        .items-table .net-salary,
        .items-table .employer-epf,
        .items-table .employer-socso,
        .items-table .total-cost {
            width: 8%;
            text-align: right;
        }
        .description-main {
            font-weight: bold;
            margin-bottom: 2px;
        }
        .description-sub {
            font-size: 7px;
            color: #666;
        }
        .ot-hours {
            font-weight: bold;
        }
        .ot-amount {
            font-size: 7px;
            color: #666;
        }
        .transaction-item {
            font-size: 7px;
            margin-bottom: 2px;
        }
        .advance-payment {
            color: #f97316;
        }
        .deduction {
            color: #dc3545;
        }
        .totals-section {
            margin-top: 3px;
            text-align: right;
        }
        .total-row {
            margin-bottom: 2px;
            font-size: 8px;
        }
        .total-label {
            display: inline-block;
            width: 200px;
            text-align: right;
            padding-right: 10px;
            white-space: nowrap;
        }
        .total-value {
            display: inline-block;
            width: 90px;
            text-align: right;
        }
        .grand-total {
            background-color: #000;
            color: #fff;
            padding: 5px 10px;
            margin-top: 3px;
            display: inline-block;
            min-width: 220px;
        }
        .grand-total .total-label {
            font-weight: bold;
            text-transform: uppercase;
        }
        .grand-total .total-value {
            font-weight: bold;
            font-size: 9px;
        }
        .signature-section {
            margin-top: 15px;
            text-align: right;
        }
        .signature-label {
            font-size: 8px;
            color: #666;
            margin-bottom: 10px;
        }
        .signature-line {
            font-family: 'Brush Script MT', cursive;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .footer {
            position: fixed;
            bottom: 15px;
            left: 30px;
            right: 30px;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #e0e0e0;
            padding-top: 8px;
        }
        .penalty-notice {
            background-color: #f5f5f5;
            border-left: 4px solid rgb(255, 229, 151);
            padding: 5px 8px;
            margin: 7px 0;
            font-size: 7px;
            border-bottom-right-radius: 1px;
            border-top-right-radius: 1px;
        }
        .payment-status {
            background-color: #f5f5f5;
            border-left: 4px solid rgb(133, 215, 152);
            padding: 5px 8px;
            margin: 7px 0;
            font-size: 7px;
            border-bottom-right-radius: 1px;
            border-top-right-radius: 1px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header with Logo -->
        <div class="header">
            <div class="invoice-title">PRO FORMA INVOICE</div>
            <div class="logo-section">
                <img src="{{ public_path('images/company-logo.png') }}" alt="Company Logo">
            </div>
        </div>

        <!-- Invoice Purpose -->
        <div style="background-color: #f5f5f5; padding: 8px 10px; margin-bottom: 10px; border-left: 4px solid #000; font-size: 10px; font-weight: bold;">
            PAYROLL PAYMENT FOR: {{ strtoupper($invoice->month_year) }}
        </div>

        <!-- Bill To and Invoice Details -->
        <table style="width: 100%; margin-bottom: 8px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="bill-to-section">
                        <div class="bill-to-label">Bill To:</div>
                        <div class="bill-to-content">
                            <strong>{{ $contractor ? ($contractor->company_name ?? $contractor->name) : $invoice->contractor_clab_no }}</strong><br>
                            CLAB No: {{ $invoice->contractor_clab_no }}<br>
                            {{ $contractor ? $contractor->email : '' }}
                        </div>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right;">
                    <table style="font-size: 9px; line-height: 1.4; margin-left: auto; display: inline-table;">
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Invoice No:</td>
                            <td style="text-align: left; padding: 1px 0;">INV-{{ str_pad($invoice->id, 4, '0', STR_PAD_LEFT) }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Issue date:</td>
                            <td style="text-align: left; padding: 1px 0;">{{ $invoice->submitted_at ? $invoice->submitted_at->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Due date:</td>
                            <td style="text-align: left; padding: 1px 0;">{{ $invoice->payment_deadline->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Period:</td>
                            <td style="text-align: left; padding: 1px 0;">{{ $invoice->month_year }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Reference:</td>
                            <td style="text-align: left; padding: 1px 0;">{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Simplified Payroll Summary -->
        <div style="background-color: #f5f5f5; padding: 15px; margin: 15px 0; border-radius: 4px;">
            <h3 style="margin: 0 0 10px 0; font-size: 12px;">Payroll Summary:</h3>
            <table style="width: 100%; font-size: 10px;">
                <tr>
                    <td style="width: 50%;"><strong>Total Workers:</strong></td>
                    <td>{{ $invoice->total_workers }}</td>
                </tr>
                <tr>
                    <td><strong>Payroll Period:</strong></td>
                    <td>{{ $invoice->month_year }}</td>
                </tr>
                @if($invoice->hasBreakdownFile())
                <tr>
                    <td><strong>Detailed Breakdown:</strong></td>
                    <td style="color: #0066cc;">
                        Available for download ({{ $invoice->breakdown_file_name }})
                    </td>
                </tr>
                @endif
            </table>
            <p style="margin-top: 10px; font-size: 9px; color: #666; font-style: italic;">
                Detailed payroll breakdown is available as a separate file from our certified payroll system.
                @if($invoice->hasBreakdownFile())
                    Download at: {{ $invoice->getBreakdownFileUrl() }}
                @endif
            </p>
        </div>

        <!-- Notices and Totals Side by Side -->
        <table style="width: 100%; margin-top: 8px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 15px;">


                    <!-- Penalty Notice -->
                    @if($invoice->has_penalty)
                    <div class="penalty-notice">
                        <strong>LATE PAYMENT PENALTY APPLIED:</strong> This invoice is overdue. An 8% penalty has been added to the total amount.
                    </div>
                    @endif

                    <!-- Payment Status -->
                    @if($invoice->status === 'paid')
                    <div class="payment-status">
                        <strong>PAYMENT RECEIVED:</strong> This invoice was paid on {{ $invoice->payment->completed_at?->format('d/m/Y H:i') }}.
                        @if($invoice->payment->transaction_id)
                            Transaction ID: {{ $invoice->payment->transaction_id }}
                        @endif
                    </div>
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <!-- Totals with Breakdown -->
                    <div class="totals-section">
                        @if($invoice->hasAdminReview())
                        <div style="text-align: right; margin-bottom: 5px;">
                            <div class="total-row">
                                <span class="total-label">Payroll Amount:</span>
                                <span class="total-value">{{ number_format($invoice->admin_final_amount, 2) }}</span>
                            </div>
                            <div class="total-row">
                                <span class="total-label">Service Charge (RM 200 × {{ $invoice->billable_workers_count }}):</span>
                                <span class="total-value">{{ number_format($invoice->calculated_service_charge, 2) }}</span>
                            </div>
                            <div class="total-row">
                                <span class="total-label">SST (8%):</span>
                                <span class="total-value">{{ number_format($invoice->calculated_sst, 2) }}</span>
                            </div>
                        </div>
                        @endif
                        <div class="grand-total">
                            <span class="total-label">TOTAL AMOUNT (RM):</span>
                            <span class="total-value">{{ number_format($invoice->client_total, 2) }}</span>
                        </div>
                        @if($invoice->has_penalty)
                        <div class="total-row" style="color: #dc3545; margin-top: 10px; font-size: 7px;">
                            <span class="total-label">* Includes 8% late payment penalty</span>
                        </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Signature -->
        <div class="signature-section">
            <div class="signature-label" style="font-size: 9px; color: #666; font-style: italic;">
                This is computer generated. No signature required.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <strong>{{ config('app.name') }}</strong> | Generated: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
