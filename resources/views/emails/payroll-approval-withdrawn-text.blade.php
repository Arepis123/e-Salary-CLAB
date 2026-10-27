Payroll Under Re-review
=======================

Dear {{ $submission->user->name ?? 'Contractor' }},

Your OT hours or transactions for this period were changed after your payroll was approved. We have updated your payroll with the latest data, so the earlier approval has been withdrawn while our admin team re-confirms the amount.

Submission Details:
- Period: {{ $submission->month_year }}
- CLAB No: {{ $submission->contractor_clab_no }}
- Workers updated: {{ $workersUpdated }}

What this means:
1. Please do not make payment for this period yet.
2. Any amount or breakdown file sent with the earlier approval no longer applies.
3. You will receive a new approval email with the updated amount once the review is done.

View your timesheet:
{{ route('timesheet.show', $submission->id) }}

If you have any questions, please contact our support team.

--
This is an automated email from the e-Salary CLAB System.
(c) {{ date('Y') }} e-Salary CLAB. All rights reserved.
