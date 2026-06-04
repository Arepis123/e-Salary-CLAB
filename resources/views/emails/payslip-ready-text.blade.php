Payslip Ready for Download
==========================

Contractor: {{ $submission->user->name ?? 'N/A' }}
CLAB No: {{ $submission->contractor_clab_no }}
Period: {{ $submission->month_year }}

Worker payslips for {{ $submission->total_workers }} {{ Str::plural('worker', $submission->total_workers) }} are now ready for download.

Dear Contractor,

The payslip file for {{ $submission->month_year }} has been processed and is now ready for download.

What's included:
- ZIP file containing individual payslip PDFs for all {{ $submission->total_workers }} workers
- Detailed salary breakdown for each worker
- EPF, SOCSO, and other statutory deductions

How to download:
- Login to your e-Salary CLAB account
- Go to "Invoices" or "Timesheet" section
- Click "Download Payslip" for {{ $submission->month_year }}

Please distribute the individual payslip PDFs to your respective workers.

Download payslip:
{{ url('/invoices') }}

If you have any questions or need assistance, please contact the CLAB administration.

--
e-Salary CLAB System
This is an automated notification. Please do not reply to this email.
(c) {{ date('Y') }} e-Salary CLAB. All rights reserved.
