<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account Has Been Created</title>
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
        .credentials-box {
            background-color: #f8f9fa;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .credentials-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .credentials-box td {
            padding: 8px 0;
            font-size: 14px;
            color: #000;
            vertical-align: top;
        }
        .credentials-box td.label {
            width: 140px;
            color: #6c757d;
            font-weight: 600;
        }
        .credentials-box td.value {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 600;
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
        .warning-box {
            background-color: #fff4e5;
            border-left: 4px solid #f59e0b;
            padding: 12px 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
            color: #92400e;
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
            <h1>✓ Welcome to e-Salary CLAB</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="message-content">
                <p><strong>Dear {{ $user->name }},</strong></p>
                <p>Your account has been created successfully. Please use the credentials below to log in to the e-Salary CLAB system.</p>
            </div>

            <div class="credentials-box">
                <table>
                    <tr>
                        <td class="label">Login ID</td>
                        <td class="value">{{ $user->username }}</td>
                    </tr>
                    <tr>
                        <td class="label">Email</td>
                        <td class="value">{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <td class="label">Password</td>
                        <td class="value">{{ $plainPassword }}</td>
                    </tr>
                    <tr>
                        <td class="label">Role</td>
                        <td class="value">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</td>
                    </tr>
                </table>
            </div>

            <div class="warning-box">
                <strong>⚠ Security notice:</strong> For your protection, do not share these credentials with anyone.
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ url('/login') }}" class="btn">
                    Log In to Your Account
                </a>
            </div>

            <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
                If you did not expect this account or have any questions, please contact the CLAB administration.
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
