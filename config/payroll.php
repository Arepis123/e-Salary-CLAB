<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payroll Auto-Calculations
    |--------------------------------------------------------------------------
    |
    | This option controls whether the system should automatically calculate
    | payroll amounts (EPF, SOCSO, overtime rates, net salary, etc.) or
    | rely on admin manual review and entry from external certified systems.
    |
    | When set to false: Clients submit data only, admins enter final amounts.
    | When set to true: System calculates everything automatically.
    |
    */

    'use_auto_calculations' => env('PAYROLL_AUTO_CALCULATIONS', false),

    /*
    |--------------------------------------------------------------------------
    | Disable Submission Window Restriction
    |--------------------------------------------------------------------------
    |
    | This option allows you to bypass the submission window restriction
    | (16th to end of month) for testing purposes. When enabled, contractors
    | can submit payroll at any time of the month.
    |
    | WARNING: This should only be enabled in development/testing environments.
    | Production should enforce the submission window.
    |
    */

    'disable_submission_window' => env('PAYROLL_DISABLE_SUBMISSION_WINDOW', false),

];
