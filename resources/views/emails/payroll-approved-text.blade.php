Payroll Approved
================

Dear {{ $submission->user->name ?? 'Contractor' }},

Good news! Your payroll submission has been reviewed and APPROVED by our admin team.

Submission Details:
- Period: {{ $submission->month_year }}
- CLAB No: {{ $submission->contractor_clab_no }}
- Total Workers: {{ $submission->total_workers }}
@if($adminNotes)

Admin Notes:
{!! $adminNotes !!}
@endif

Next Steps:
1. Review the approved amount in the system.
2. Proceed to make payment through the system.
3. Payment deadline: {{ $submission->payment_deadline->format('F d, Y') }}

View invoice & make payment:
{{ route('invoices.show', $submission->id) }}

If you have any questions about this approval, please contact our support team.

--
This is an automated email from the e-Salary CLAB System.
(c) {{ date('Y') }} e-Salary CLAB. All rights reserved.
