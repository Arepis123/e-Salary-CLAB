<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Under Re-review</title>
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
        <h1>🔄 Payroll Under Re-review</h1>
    </div>

    <div class="content">
        <p>Dear <strong>{{ $submission->user->name ?? 'Contractor' }}</strong>,</p>

        <p>Your OT hours or transactions for this period were changed after your payroll was approved. We have updated your payroll with the latest data, so the earlier approval has been <strong>withdrawn</strong> while our admin team re-confirms the amount.</p>

        <div class="info-box">
            <strong>Submission Details:</strong><br>
            <strong>Period:</strong> {{ $submission->month_year }}<br>
            <strong>CLAB No:</strong> {{ $submission->contractor_clab_no }}<br>
            <strong>Workers updated:</strong> {{ $workersUpdated }}
        </div>

        <div class="notes-box">
            <div class="title">⚠️ Please do not make payment for this period yet.</div>
            <div>Any amount or breakdown file sent with the earlier approval no longer applies.</div>
        </div>

        <div class="divider"></div>

        <p>You will receive a new approval email with the updated amount once the review is done.</p>

        <div style="text-align: center;">
            <a href="{{ route('timesheet.show', $submission->id) }}" class="button">
                View Timesheet
            </a>
        </div>

        <p style="margin-top: 30px; font-size: 14px; color: #6b7280;">
            If you have any questions, please contact our support team.
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
