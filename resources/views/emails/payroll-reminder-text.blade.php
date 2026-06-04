Payroll Submission Reminder
===========================

Contractor: {{ $contractorName }}
CLAB No: {{ $contractorClabNo }}

PENDING SUBMISSION
{{ $pendingWorkers }} of {{ $totalWorkers }} workers not submitted for {{ $periodMonth }}.

{!! $reminderMessage !!}

Submit payroll now:
{{ url('/client/timesheet') }}

If you have already submitted the payroll, please disregard this message.
For any questions or assistance, please contact the CLAB administration.

--
e-Salary CLAB System
This is an automated reminder. Please do not reply to this email.
(c) {{ date('Y') }} e-Salary CLAB. All rights reserved.
