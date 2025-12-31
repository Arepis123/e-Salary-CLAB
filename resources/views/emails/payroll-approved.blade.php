<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Approved</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .success-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 20px 0;
        }
        .info-box {
            background: #f3f4f6;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box strong {
            color: #667eea;
        }
        .amount-box {
            background: #ecfdf5;
            border: 2px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
        }
        .amount-box .label {
            color: #059669;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .amount-box .amount {
            color: #065f46;
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        .notes-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .notes-box .title {
            color: #d97706;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background: #5568d3;
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            border-radius: 0 0 10px 10px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Payroll Approved</h1>
    </div>

    <div class="content">
        <p>Dear <strong>{{ $submission->user->name ?? 'Contractor' }}</strong>,</p>

        <p>Good news! Your payroll submission has been reviewed and <strong>approved</strong> by our admin team.</p>

        <div class="success-badge">
            ✓ APPROVED
        </div>

        <div class="info-box">
            <strong>Submission Details:</strong><br>
            <strong>Period:</strong> {{ $submission->month_year }}<br>
            <strong>CLAB No:</strong> {{ $submission->contractor_clab_no }}<br>
            <strong>Total Workers:</strong> {{ $submission->total_workers }}
        </div>

        <!-- <div class="amount-box">
            <div class="label">Final Approved Amount</div>
            <div class="amount">RM {{ number_format($finalAmount, 2) }}</div>
            @if($submission->has_penalty)
            <div style="color: #dc2626; font-size: 14px; margin-top: 10px;">
                (Including 8% late penalty: RM {{ number_format($submission->penalty_amount, 2) }})
            </div>
            @endif
        </div> -->

        @if($adminNotes)
        <div class="notes-box">
            <div class="title">📝 Admin Notes:</div>
            <div>{{ $adminNotes }}</div>
        </div>
        @endif

        <div class="divider"></div>

        <p><strong>Next Steps:</strong></p>
        <ol>
            <li>Review the approved amount above</li>
            <li>Proceed to make payment through the system</li>
            <li>Payment deadline: <strong>{{ $submission->payment_deadline->format('F d, Y') }}</strong></li>
        </ol>

        <div style="text-align: center;">
            <a href="{{ route('invoices.show', $submission->id) }}" class="button">
                View Invoice & Make Payment
            </a>
        </div>

        <p style="margin-top: 30px; font-size: 14px; color: #6b7280;">
            If you have any questions about this approval, please contact our support team.
        </p>
    </div>

    <div class="footer">
        <p>
            This is an automated email from the e-Salary CLAB System.<br>
            © {{ date('Y') }} e-Salary CLAB. All rights reserved.
        </p>
    </div>
</body>
</html>
