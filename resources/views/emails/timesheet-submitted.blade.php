<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Timesheet Submission</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 15px;
        }
        .contractor-info {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .contractor-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #000;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
            color: #1e40af;
        }
        .message-content {
            background-color: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.8;
            color: #000;
        }
        .message-content ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .message-content li {
            margin: 8px 0;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
        }
        a.btn {
            display: inline-block;
            padding: 12px 30px;
            background: #138B85;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin: 10px 0;
        }

        a.btn:link,
        a.btn:visited,
        a.btn:hover,
        a.btn:active {
            color: #ffffff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>📋 New Timesheet Submission</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="contractor-info">
                <p><strong>Contractor:</strong> {{ $submission->user->name ?? 'N/A' }}</p>
                <p><strong>CLAB No:</strong> {{ $submission->contractor_clab_no }}</p>
                <p><strong>Period:</strong> {{ $submission->month_year }}</p>
                <p><strong>Submitted At:</strong> {{ $submission->submitted_at ? $submission->submitted_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A') }}</p>
            </div>

            <div class="info-box">
                <p><strong>📥 New Submission Received</strong></p>
                <p>A contractor has submitted their payroll timesheet and is awaiting your review</p>
            </div>

            <div class="message-content">
                <p><strong>Dear Admin,</strong></p>
                <p>A new payroll timesheet submission has been received from <strong>{{ $submission->user->name ?? 'N/A' }}</strong> ({{ $submission->contractor_clab_no }}) for the period <strong>{{ $submission->month_year }}</strong>.</p>

                <p><strong>Submission Details:</strong></p>
                <ul>
                    <li><strong>{{ $submission->total_workers }}</strong> workers included</li>
                    <li>Total Payroll: <strong>RM {{ number_format($submission->admin_final_amount, 2) }}</strong></li>
                    <li>Service Charge: <strong>RM {{ number_format($submission->calculated_service_charge, 2) }}</strong></li>
                    <li>SST (8%): <strong>RM {{ number_format($submission->calculated_sst, 2) }}</strong></li>
                    <li>Client Total: <strong>RM {{ number_format($submission->client_total, 2) }}</strong></li>
                </ul>

                <p><strong>Next Steps:</strong></p>
                <ul>
                    <li>Review the submitted timesheet details</li>
                    <li>Verify worker data and calculations</li>
                    <li>Upload breakdown file from external system</li>
                    <li>Approve or request corrections</li>
                </ul>

                <p>Please review this submission at your earliest convenience to ensure timely payroll processing.</p>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/admin/salary?status=submitted') }}" class="btn">
                    Review Submission
                </a>
            </div>

            <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                This submission is currently in "Submitted" status and requires admin review and approval before an invoice can be generated.
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>e-Salary CLAB System</strong></p>
            <p>This is an automated notification. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} e-Salary CLAB. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
