<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Ready for Download</title>
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
        .success-box {
            background-color: #d1f2eb;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success-box p {
            margin: 5px 0;
            color: #065f46;
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
            <h1>✓ Payslip Ready for Download</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="contractor-info">
                <p><strong>Contractor:</strong> {{ $submission->user->name ?? 'N/A' }}</p>
                <p><strong>CLAB No:</strong> {{ $submission->contractor_clab_no }}</p>
                <p><strong>Period:</strong> {{ $submission->month_year }}</p>
            </div>

            <div class="success-box">
                <p><strong>✓ Payslip Available</strong></p>
                <p>Worker payslips for {{ $submission->total_workers }} {{ Str::plural('worker', $submission->total_workers) }} are now ready for download</p>
            </div>

            <div class="message-content">
                <p><strong>Dear Contractor,</strong></p>
                <p>We're pleased to inform you that the payslip file for <strong>{{ $submission->month_year }}</strong> has been processed and is now ready for download.</p>

                <p><strong>What's included:</strong></p>
                <ul>
                    <li>ZIP file containing individual payslip PDFs for all {{ $submission->total_workers }} workers</li>
                    <li>Detailed salary breakdown for each worker</li>
                    <li>EPF, SOCSO, and other statutory deductions</li>
                </ul>

                <p><strong>How to download:</strong></p>
                <ul>
                    <li>Login to your e-Salary CLAB account</li>
                    <li>Go to "Invoices" or "Timesheet" section</li>
                    <li>Click on "Download Payslip" button for {{ $submission->month_year }}</li>
                </ul>

                <p>Please distribute the individual payslip PDFs to your respective workers.</p>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/invoices') }}" class="btn">
                    Download Payslip Now
                </a>
            </div>

            <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                If you have any questions or need assistance, please contact the CLAB administration.
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
