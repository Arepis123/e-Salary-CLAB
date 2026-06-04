New Timesheet Submission
========================

Contractor: {{ $submission->user->name ?? 'N/A' }}
CLAB No: {{ $submission->contractor_clab_no }}
Period: {{ $submission->month_year }}
Submitted At: {{ $submission->submitted_at ? $submission->submitted_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A') }}

Dear Admin,

A new payroll timesheet submission has been received from {{ $submission->user->name ?? 'N/A' }} ({{ $submission->contractor_clab_no }}) for the period {{ $submission->month_year }}.

Submission Details:
- {{ $submission->total_workers }} workers included
- Total Payroll: RM {{ number_format($submission->admin_final_amount, 2) }}
- Service Charge: RM {{ number_format($submission->calculated_service_charge, 2) }}
- SST (8%): RM {{ number_format($submission->calculated_sst, 2) }}
- Client Total: RM {{ number_format($submission->client_total, 2) }}

Next Steps:
- Review the submitted timesheet details
- Verify worker data and calculations
- Upload breakdown file from external system
- Approve or request corrections

Review submission:
{{ url('/admin/salary?status=submitted') }}

--
e-Salary CLAB System
This is an automated notification. Please do not reply to this email.
(c) {{ date('Y') }} e-Salary CLAB. All rights reserved.
