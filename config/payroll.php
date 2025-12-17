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

];
