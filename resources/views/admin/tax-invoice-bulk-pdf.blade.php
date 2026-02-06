<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Official Receipts - Bulk Download</title>
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
            padding: 0;
        }
        .receipt-page {
            padding: 15px 20px;
            page-break-after: always;
            position: relative;
            min-height: 90vh;
        }
        .receipt-page:last-child {
            page-break-after: avoid;
        }
        .invoice-container {
            max-width: 100%;
            margin: 0 auto;
        }
        .header {
            margin-bottom: 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }
        .tax-invoice-title {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
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
            font-size: 9px;
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
        .footer {
            position: absolute;
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
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 2px;
            transform: rotate(-10deg);
            margin-left: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
@foreach($invoices as $invoice)
    @php $contractor = $invoice->user; @endphp
    <div class="receipt-page">
        <div class="invoice-container">
            <!-- Header with Logo -->
            <table style="width: 100%; margin-bottom: 5px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div class="tax-invoice-title">OFFICIAL RECEIPT</div>
                        <div style="font-size: 10px; color: #666;">e-Salary CLAB</div>
                    </td>
                    <td style="width: 50%; vertical-align: top; text-align: right;">
                        <img src="{{ public_path('images/company-logo.png') }}" alt="Company Logo" style="max-width: 140px; height: auto;">
                    </td>
                </tr>
            </table>

            <div class="header"></div>

            <!-- Company Information -->
            <table style="width: 100%; margin-bottom: 5px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="line-height: 1.3;">
                            <strong>CONSTRUCTION LABOUR EXCHANGE CENTRE BERHAD</strong><br>
                            Level 2, Annexe Block, Menara Milenium, Jln Damanlela, Pusat Bandar Damansara, 50490 Kuala Lumpur<br>
                            ROC: 200301031975  |  SST Reg: W10-1808-32001604<br>
                        </div>
                    </td>
                    <td style="width: 50%; vertical-align: top; text-align: right;">
                        <table style="font-size: 9px; line-height: 1.3; margin-left: auto; display: inline-table;">
                            <tr>
                                <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Official Receipt No:</td>
                                <td style="text-align: left; padding: 1px 0;">{{ $invoice->tax_invoice_number }}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Invoice Date:</td>
                                <td style="text-align: left; padding: 1px 0;">{{ $invoice->tax_invoice_generated_at ? $invoice->tax_invoice_generated_at->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Payment Date:</td>
                                <td style="text-align: left; padding: 1px 0;">{{ $invoice->payment->completed_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Payment Method:</td>
                                <td style="text-align: left; padding: 1px 0;">{{ $invoice->payment->payment_method ?? 'Online Payment' }}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; font-weight: bold; padding: 1px 8px 1px 0; white-space: nowrap;">Transaction ID:</td>
                                <td style="text-align: left; padding: 1px 0;">{{ $invoice->payment->transaction_id ?? 'N/A' }}</td>
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
                            <div class="bill-to-label">Invoiced To:</div>
                            <div class="bill-to-content">
                                <strong>{{ $contractor ? ($contractor->company_name ?? $contractor->name) : $invoice->contractor_clab_no }}</strong><br>
                                CLAB No: {{ $invoice->contractor_clab_no }}<br>
                                @if($contractor && $contractor->address)
                                    {{ $contractor->address }}<br>
                                    @if($contractor->postcode || $contractor->city || $contractor->state)
                                        {{ $contractor->postcode }} {{ $contractor->city }}@if($contractor->state), {{ $contractor->state }}@endif<br>
                                    @endif
                                @endif
                                @if($contractor && $contractor->phone)
                                    Tel: {{ $contractor->phone }}<br>
                                @endif
                                {{ $contractor ? $contractor->email : '' }}
                            </div>
                        </div>
                    </td>
                    <td style="width: 40%; vertical-align: top; text-align: right;">
                        <div class="paid-stamp">PAID</div>
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
            <div style="margin-top: 25px; text-align: right;">
                <div style="font-size: 9px; color: #666; font-style: italic;">
                    This is a computer-generated official receipt. No signature required.<br>
                    This document serves as official proof of payment.
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <strong>{{ config('app.name') }}</strong> | Official Receipt Generated: {{ $invoice->tax_invoice_generated_at ? $invoice->tax_invoice_generated_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}<br>
                For inquiries, please contact: 03-2095 9599
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
