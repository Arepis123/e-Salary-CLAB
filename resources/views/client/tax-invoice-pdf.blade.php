<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tax Invoice #{{ $invoice->tax_invoice_number ?? 'PENDING' }}</title>
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
            position: relative;
        }
        .invoice-container {
            max-width: 100%;
            margin: 0 auto;
        }
        .paid-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            font-weight: bold;
            color: rgba(76, 175, 80, 0.15);
            z-index: -1;
            pointer-events: none;
        }
        .header {
            margin-bottom: 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }
        .tax-invoice-title {
            font-family: 'Inter', sans-serif;
            font-size: 22px;
            font-weight: bold;
            color: #000;
            margin-bottom: 3px;
            letter-spacing: 1px;
        }
        .logo-section {
            text-align: right;
            margin-bottom: 3px;
        }
        .logo-section img {
            max-width: 120px;
            height: auto;
        }
        .company-info-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 6px 8px;
            margin-bottom: 8px;
            font-size: 8px;
            line-height: 1.5;
        }
        .company-info-box strong {
            font-size: 10px;
            display: block;
            margin-bottom: 3px;
        }
        .bill-to-section {
            margin-bottom: 8px;
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
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 5px 0;
        }
        .items-table thead {
            border-bottom: 2px solid #000;
            background-color: #f5f5f5;
        }
        .items-table th {
            padding: 5px 3px;
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
            margin-top: 5px;
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
            background-color: #4CAF50;
            color: #fff;
            padding: 6px 10px;
            margin-top: 3px;
            display: inline-block;
            min-width: 305px;
        }
        .grand-total .total-label {
            font-weight: bold;
            text-transform: uppercase;
        }
        .grand-total .total-value {
            font-weight: bold;
            font-size: 10px;
        }
        .payment-info-box {
            background-color: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 6px 8px;
            margin: 8px 0;
            font-size: 8px;
            border-top-right-radius: 2px;
            border-bottom-right-radius: 2px;
        }
        .payment-info-box strong {
            display: block;
            margin-bottom: 3px;
            font-size: 9px;
        }
        .info-notice {
            background-color: #f5f5f5;
            border-left: 4px solid rgb(110, 165, 247);
            padding: 5px 8px;
            margin-bottom: 7px;
            font-size: 7px;
            border-top-right-radius: 1px;
            border-bottom-right-radius: 1px;
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
        .paid-stamp {
            display: inline-block;
            border: 3px solid #4CAF50;
            color: #4CAF50;
            padding: 4px 12px;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            transform: rotate(-10deg);
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <!-- PAID Watermark -->
    <div class="paid-watermark">PAID</div>

    <div class="invoice-container">
        <!-- Header with Logo -->
        <table style="width: 100%; margin-bottom: 5px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="tax-invoice-title">TAX INVOICE</div>
                    <div style="font-size: 10px; color: #666;">OFFICIAL RECEIPT</div>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right;">
                    <img src="{{ public_path('images/company-logo.png') }}" alt="Company Logo" style="max-width: 120px; height: auto;">
                </td>
            </tr>
        </table>

        <div class="header"></div>

        <!-- Company Information -->
        <table style="width: 100%; margin-bottom: 8px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="company-info-box">
                        <strong>{{ config('app.name') }}</strong>
                        <div><strong>ROC No:</strong> [YOUR-ROC-NUMBER]</div>
                        <div><strong>SST Reg No:</strong> [YOUR-SST-NUMBER]</div>
                        <div style="margin-top: 3px;">
                            [Your Company Address]<br>
                            Tel: [Phone Number]<br>
                            Email: [Email Address]
                        </div>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right;">
                    <table style="font-size: 9px; line-height: 1.5; margin-left: auto; display: inline-table;">
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 2px 10px 2px 0; white-space: nowrap;">Tax Invoice No:</td>
                            <td style="text-align: left; padding: 2px 0;">{{ $invoice->tax_invoice_number }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 2px 10px 2px 0; white-space: nowrap;">Invoice Date:</td>
                            <td style="text-align: left; padding: 2px 0;">{{ $invoice->tax_invoice_generated_at ? $invoice->tax_invoice_generated_at->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 2px 10px 2px 0; white-space: nowrap;">Payment Date:</td>
                            <td style="text-align: left; padding: 2px 0;">{{ $invoice->payment->completed_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 2px 10px 2px 0; white-space: nowrap;">Payment Method:</td>
                            <td style="text-align: left; padding: 2px 0;">{{ $invoice->payment->payment_method ?? 'Online Payment' }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 2px 10px 2px 0; white-space: nowrap;">Transaction ID:</td>
                            <td style="text-align: left; padding: 2px 0;">{{ $invoice->payment->transaction_id ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold; padding: 2px 10px 2px 0; white-space: nowrap;">Payroll Period:</td>
                            <td style="text-align: left; padding: 2px 0;">{{ $invoice->month_year }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Bill To Section -->
        <table style="width: 100%; margin-bottom: 8px;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <div class="bill-to-section">
                        <div class="bill-to-label">Bill To:</div>
                        <div class="bill-to-content">
                            <strong>{{ $contractor ? ($contractor->company_name ?? $contractor->name) : $invoice->contractor_clab_no }}</strong><br>
                            CLAB No: {{ $invoice->contractor_clab_no }}<br>
                            {{ $contractor ? $contractor->email : '' }}
                        </div>
                    </div>
                </td>
                <td style="width: 40%; vertical-align: top; text-align: right;">
                    <div class="paid-stamp">PAID</div>
                </td>
            </tr>
        </table>

        <!-- Invoice Purpose -->
        <div style="background-color: #f5f5f5; padding: 8px 10px; margin-bottom: 8px; border-left: 4px solid #000; font-size: 10px; font-weight: bold;">
            PAYROLL PAYMENT FOR: {{ strtoupper($invoice->month_year) }}
        </div>

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

        <!-- Payment Info and Totals Side by Side -->
        <table style="width: 100%; margin-top: 8px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 15px;">
                    <!-- Payment Information -->
                    <div class="payment-info-box">
                        <strong>PAYMENT CONFIRMATION</strong>
                        <div>Amount Paid: <strong>RM {{ number_format($invoice->client_total, 2) }}</strong></div>
                        <div>Payment Date: {{ $invoice->payment->completed_at?->format('d/m/Y H:i') ?? 'N/A' }}</div>
                        <div>Payment Status: <strong>COMPLETED</strong></div>
                        @if($invoice->payment && $invoice->payment->transaction_id)
                        <div>Reference: {{ $invoice->payment->transaction_id }}</div>
                        @endif
                    </div>

                    <!-- Important Notice about OT -->
                    <div class="info-notice">
                        <strong>OVERTIME (OT) INFORMATION:</strong><br>
                        • The OT hours shown are from the PREVIOUS month and are paid in this month's invoice<br>
                        • Example: November payroll includes October's OT hours<br>
                        • Gross Salary = Basic Salary + OT<br>
                        • EPF is calculated on Basic Salary only (2%)<br>
                        • SOCSO is calculated on Gross Salary using contribution table
                    </div>

                    <!-- Tax Information -->
                    <div style="font-size: 7px; color: #666; margin-top: 5px; line-height: 1.4;">
                        <strong>TAX INFORMATION:</strong><br>
                        This is an official tax invoice for SST purposes. Please retain this document for your records.<br>
                        Service charge is subject to 8% Service and Sales Tax (SST).
                    </div>
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
                            <span class="total-label">TOTAL PAID (RM):</span>
                            <span class="total-value">{{ number_format($invoice->client_total, 2) }}</span>
                        </div>
                        @if($invoice->hasBreakdownFile())
                        <div style="font-size: 7px; color: #666; margin-top: 5px; text-align: right;">
                            Detailed breakdown available for download
                        </div>
                        @endif
                        @if($invoice->has_penalty)
                        <div class="total-row" style="color: #dc3545; margin-top: 5px; font-size: 7px;">
                            <span class="total-label">* Includes 8% late payment penalty</span>
                        </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Signature -->
        <div style="margin-top: 15px; text-align: right;">
            <div style="font-size: 9px; color: #666; font-style: italic;">
                This is a computer-generated tax invoice. No signature required.<br>
                This document serves as official proof of payment.
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <strong>{{ config('app.name') }}</strong> | Tax Invoice Generated: {{ $invoice->tax_invoice_generated_at ? $invoice->tax_invoice_generated_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}<br>
            For inquiries, please contact: [Your Contact Information]
        </div>
    </div>
</body>
</html>
