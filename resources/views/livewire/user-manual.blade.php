<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="text-center mb-12">
            <flux:heading size="xl" class="text-gray-900 dark:text-white mb-4">
                {{ __('e-Salary CLAB System User Manual') }}
            </flux:heading>
            <flux:subheading class="text-gray-600 dark:text-gray-400">
                {{ __('Comprehensive guide for managing foreign construction worker salary') }}
            </flux:subheading>
        </div>

        {{-- Quick Navigation --}}
        <flux:card class="mb-8">
            <flux:heading size="lg" class="mb-4">{{ __('Quick Navigation') }}</flux:heading>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="#introduction" class="text-blue-600 dark:text-blue-400 hover:underline">1. Introduction</a>
                <a href="#dashboard" class="text-blue-600 dark:text-blue-400 hover:underline">2. Dashboard</a>
                <a href="#workers" class="text-blue-600 dark:text-blue-400 hover:underline">3. Worker Management</a>
                <a href="#timesheet" class="text-blue-600 dark:text-blue-400 hover:underline">4. Timesheet (Detailed)</a>
                <a href="#ot-entry" class="text-blue-600 dark:text-blue-400 hover:underline">5. OT & Transaction Entry (Detailed)</a>
                <a href="#payments" class="text-blue-600 dark:text-blue-400 hover:underline">6. Payments</a>
                <a href="#invoices" class="text-blue-600 dark:text-blue-400 hover:underline">7. Invoices</a>
            </div>
        </flux:card>

        {{-- 1. Introduction --}}
        <flux:card class="mb-8" id="introduction">
            <flux:heading size="lg" class="mb-4">{{ __('1. Introduction') }}</flux:heading>

            <flux:subheading class="mb-3">{{ __('About This System') }}</flux:subheading>
            <flux:text class="mb-4">
                {{ __('The e-Salary CLAB System is designed specifically for managing payroll of foreign construction workers under CLAB work permit. It follows official Malaysian labor regulations and formulas for salary calculations, overtime, EPF, and SOCSO contributions.') }}
            </flux:text>

            <flux:subheading class="mb-3">{{ __('Key Features for Contractors') }}</flux:subheading>
            <ul class="list-disc list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>View your workers' details including personal info, contract, next of kin, salary breakdown, and payroll history</li>
                <li>Enter OT hours and transactions via OT & Transaction page (Weekday 1.5x, Rest Day 2.0x, Public Holiday 3.0x)</li>
                <li>Verify and submit monthly payroll via Timesheet page</li>
                <li>Track overtime by specific date and worker with detailed transaction history</li>
                <li>Automatic salary calculations with EPF (2%) and SOCSO deductions</li>
                <li>View and download invoices (Pro Forma and Tax Invoice)</li>
                <li>Online payment via Billplz (FPX, Credit Card, etc.)</li>
                <li>Real-time submission status tracking</li>
            </ul>

            <flux:card variant="outline" class="bg-yellow-50 dark:bg-yellow-900/20 mt-4">
                <flux:heading size="sm" class="mb-2">{{ __('Note: Worker Management') }}</flux:heading>
                <flux:text class="text-sm">
                    {{ __('Workers are registered and managed by CLAB admin office. Contractors can view worker details but cannot add or edit worker information directly. Contact CLAB office for worker registration or updates.') }}
                </flux:text>
            </flux:card>
        </flux:card>

        {{-- 2. Dashboard --}}
        <flux:card class="mb-8" id="dashboard">
            <flux:heading size="lg" class="mb-4">{{ __('2. Dashboard') }}</flux:heading>

            <flux:text class="mb-4">
                {{ __('The dashboard is your main landing page after login. It provides an overview of your account status and important information at a glance.') }}
            </flux:text>

            <flux:subheading class="mb-3">{{ __('Dashboard Overview') }}</flux:subheading>
            <ul class="list-disc list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li><strong>Active Workers:</strong> Total count of your registered workers</li>
                <li><strong>Submission Status:</strong> Current month timesheet status (Not Submitted, Pending, Approved)</li>
                <li><strong>Pending Payments:</strong> Outstanding invoices that need to be paid</li>
                <li><strong>Recent Announcements:</strong> Important news and updates from CLAB</li>
                <li><strong>Quick Actions:</strong> Direct links to OT & Transaction entry and timesheet verification</li>
            </ul>

            <flux:subheading class="mb-3">{{ __('Dashboard Widgets') }}</flux:subheading>
            <flux:text class="mb-4">
                {{ __('Your dashboard displays key information cards:') }}
            </flux:text>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Worker Statistics') }}</flux:heading>
                    <flux:text class="text-sm">View total active workers and their permit status.</flux:text>
                </flux:card>

                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Monthly Submission') }}</flux:heading>
                    <flux:text class="text-sm">Track your timesheet submission status and deadline.</flux:text>
                </flux:card>

                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Payment Alerts') }}</flux:heading>
                    <flux:text class="text-sm">See unpaid invoices and make quick payments.</flux:text>
                </flux:card>

                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('News & Updates') }}</flux:heading>
                    <flux:text class="text-sm">Stay informed about system updates and announcements.</flux:text>
                </flux:card>
            </div>
        </flux:card>

        {{-- 3. Worker Management --}}
        <flux:card class="mb-8" id="workers">
            <flux:heading size="lg" class="mb-4">{{ __('3. Worker Management') }}</flux:heading>

            <flux:text class="mb-4">
                {{ __('View and monitor your foreign construction workers assigned to your company. Workers are added by the CLAB admin office.') }}
            </flux:text>

            <flux:subheading class="mb-3">{{ __('Viewing Your Workers') }}</flux:subheading>
            <ol class="list-decimal list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>Navigate to "My Workers" from the sidebar</li>
                <li>You will see a list of all workers assigned to your company</li>
                <li>Use the search function to find specific workers by name or passport number</li>
                <li>Click on any worker to view their complete details</li>
            </ol>

            <flux:subheading class="mb-3">{{ __('Worker Detail Page') }}</flux:subheading>
            <flux:text class="mb-3">
                {{ __('When you click on a worker, you can view comprehensive information organized into sections:') }}
            </flux:text>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <flux:card variant="outline" class="bg-green-50 dark:bg-green-900/20">
                    <flux:heading size="sm" class="mb-2">{{ __('1. Personal Information') }}</flux:heading>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        <li>Full Name</li>
                        <li>Passport Number</li>
                        <li>Nationality</li>
                        <li>Date of Birth</li>
                        <li>Contact Number</li>
                        <li>Address</li>
                    </ul>
                </flux:card>

                <flux:card variant="outline" class="bg-blue-50 dark:bg-blue-900/20">
                    <flux:heading size="sm" class="mb-2">{{ __('2. Contract Information') }}</flux:heading>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        <li>Work Permit Number</li>
                        <li>Permit Expiry Date</li>
                        <li>Employment Start Date</li>
                        <li>Contract Type</li>
                        <li>Job Position</li>
                        <li>Employment Status</li>
                    </ul>
                </flux:card>

                <flux:card variant="outline" class="bg-purple-50 dark:bg-purple-900/20">
                    <flux:heading size="sm" class="mb-2">{{ __('3. Next of Kin') }}</flux:heading>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        <li>Emergency Contact Name</li>
                        <li>Relationship</li>
                        <li>Contact Number</li>
                        <li>Address</li>
                    </ul>
                </flux:card>

                <flux:card variant="outline" class="bg-yellow-50 dark:bg-yellow-900/20">
                    <flux:heading size="sm" class="mb-2">{{ __('4. Salary Breakdown') }}</flux:heading>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        <li>Basic Salary</li>
                        <li>Overtime Rates</li>
                        <li>EPF Contributions</li>
                        <li>SOCSO Contributions</li>
                        <li>Net Salary</li>
                    </ul>
                </flux:card>
            </div>

            <flux:card variant="outline" class="bg-gray-50 dark:bg-gray-800 mb-4">
                <flux:heading size="sm" class="mb-2">{{ __('5. Payroll History') }}</flux:heading>
                <flux:text class="mb-2">View complete monthly payroll records for the worker:</flux:text>
                <ul class="list-disc list-inside text-sm space-y-1 text-gray-700 dark:text-gray-300">
                    <li>Monthly salary statements</li>
                    <li>Overtime hours breakdown (Weekday, Rest Day, Public Holiday)</li>
                    <li>EPF and SOCSO deductions per month</li>
                    <li>Payment dates and amounts</li>
                    <li>Year-to-date totals</li>
                </ul>
            </flux:card>

            <flux:separator />

            <flux:subheading class="mb-3 mt-4">{{ __('Important Notes') }}</flux:subheading>
            <flux:card variant="outline" class="bg-blue-50 dark:bg-blue-900/20">
                <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">                    
                    <li><strong>Updating Information:</strong> If worker details need to be updated, contact CLAB admin office.</li>
                    {{-- <li><strong>Permit Monitoring:</strong> Monitor permit expiry dates and inform admin in advance for renewals.</li> --}}
                    <li><strong>Minimum Salary:</strong> All foreign construction workers must receive minimum RM 1,700 basic salary.</li>
                    <li><strong>Salary History:</strong> Use payroll history to verify past payments and for record-keeping.</li>
                </ul>
            </flux:card>
        </flux:card>

        {{-- 4. Timesheet (Detailed) --}}
        <flux:card class="mb-8" id="timesheet">
            <flux:heading size="lg" class="mb-4">{{ __('4. Timesheet (Detailed Guide)') }}</flux:heading>

            <flux:card variant="outline" class="bg-blue-50 dark:bg-blue-900/20 mb-4">
                <flux:heading size="sm" class="mb-2 text-blue-700 dark:text-blue-300">{{ __('IMPORTANT: Timesheet is for Verification Only') }}</flux:heading>
                <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                    <li><strong>Timesheet Purpose:</strong> Review and verify your workers' OT and transactions before final submission</li>
                    <li><strong>Data Entry Location:</strong> You MUST enter OT and transactions at the "OT & Transaction" page (see Section 5)</li>
                    <li><strong>Timesheet Shows:</strong> Summary of all OT and transactions you've entered via OT & Transaction page</li>
                    <li><strong>Final Step:</strong> Use Timesheet to verify everything is correct, then submit for processing</li>
                </ul>
            </flux:card>

            <flux:text class="mb-4">
                {{ __('The Timesheet module is where you verify and submit your monthly payroll. All OT hours and transactions must be entered through the OT & Transaction page first (see Section 5), then the Timesheet will display everything for your final review before submission.') }}
            </flux:text>

            <flux:subheading class="mb-3">{{ __('4.1 Understanding the Timesheet') }}</flux:subheading>
            <flux:text class="mb-4">
                {{ __('A timesheet represents one month of payroll data for all your workers. It displays a summary that includes:') }}
            </flux:text>
            <ul class="list-disc list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li><strong>Month & Year:</strong> The payroll period (e.g., December 2025)</li>
                <li><strong>Workers List:</strong> All active workers under your account</li>
                <li><strong>Overtime Hours:</strong> Totals from OT & Transaction page, showing three OT categories per worker</li>
                <li><strong>Transactions:</strong> All advance payments, deductions, allowances, and NPL entered via OT & Transaction page</li>
                <li><strong>Submission Status:</strong> Draft, Approved, or Pending Payment</li>
                <li><strong>Deadline:</strong> End of each month (e.g., December payroll must be submitted by 31st December)</li>
            </ul>

            <flux:card variant="outline" class="bg-red-50 dark:bg-red-900/20 mb-4">
                <flux:heading size="sm" class="mb-2 text-red-700 dark:text-red-300">{{ __('Important: Submission Deadline & Penalty') }}</flux:heading>
                <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                    <li><strong>Due Date:</strong> Timesheet must be submitted by the last day of each month</li>
                    <li><strong>Late Submission Penalty:</strong> 8% penalty will be charged on late submissions</li>
                    <li><strong>Example:</strong> December 2025 payroll must be submitted by 31st December 2025. Any submission after this date will incur 8% penalty on the total invoice amount.</li>
                </ul>
            </flux:card>

            <flux:subheading class="mb-3">{{ __('4.2 Workflow: From OT Entry to Timesheet Submission') }}</flux:subheading>
            <ol class="list-decimal list-inside space-y-3 mb-4 text-gray-700 dark:text-gray-300">
                <li>
                    <strong>Step 1 - Enter Data at OT & Transaction Page:</strong>
                    <flux:text class="ml-6 mt-1">Go to "OT & Transaction" page and enter all OT hours and transactions (see Section 5)</flux:text>
                    <flux:text class="ml-6 mt-1">This is where you input all the data throughout the month</flux:text>
                </li>
                <li>
                    <strong>Step 2 - Navigate to Timesheet:</strong>
                    <flux:text class="ml-6 mt-1">Click "Timesheet" from the sidebar menu</flux:text>
                    <flux:text class="ml-6 mt-1">The timesheet will automatically load all data from OT & Transaction page</flux:text>
                </li>
                <li>
                    <strong>Step 3 - Verify Worker Data:</strong>
                    <flux:text class="ml-6 mt-1">Review each worker's information displayed in the timesheet</flux:text>
                    <flux:text class="ml-6 mt-1">Check that OT totals and transactions match your records</flux:text>
                </li>
                <li>
                    <strong>Step 4 - Final Submission:</strong>
                    <flux:text class="ml-6 mt-1">Once verified, submit the timesheet for processing</flux:text>
                    <flux:text class="ml-6 mt-1">After submission, you cannot edit the data</flux:text>
                </li>
            </ol>

            <flux:subheading class="mb-3">{{ __('4.3 What the Timesheet Displays') }}</flux:subheading>
            <flux:text class="mb-3">
                {{ __('The timesheet shows a summary of data entered via OT & Transaction page, including three types of overtime:') }}
            </flux:text>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <flux:card variant="outline" class="bg-green-50 dark:bg-green-900/20">
                    <flux:heading size="sm" class="mb-2">{{ __('1. Hari Biasa (Weekday OT)') }}</flux:heading>
                    <flux:text class="mb-2">Overtime on regular working days (Monday-Friday)</flux:text>
                    <flux:badge color="green">Rate: 1.5x hourly rate</flux:badge>
                </flux:card>

                <flux:card variant="outline" class="bg-blue-50 dark:bg-blue-900/20">
                    <flux:heading size="sm" class="mb-2">{{ __('2. Cuti Rehat (Rest Day OT)') }}</flux:heading>
                    <flux:text class="mb-2">Overtime on rest days (Usually Saturday/Sunday)</flux:text>
                    <flux:badge color="blue">Rate: 2.0x hourly rate</flux:badge>
                </flux:card>

                <flux:card variant="outline" class="bg-purple-50 dark:bg-purple-900/20">
                    <flux:heading size="sm" class="mb-2">{{ __('3. Cuti Umum (Public Holiday OT)') }}</flux:heading>
                    <flux:text class="mb-2">Overtime on public holidays (Gazetted holidays)</flux:text>
                    <flux:badge color="purple">Rate: 3.0x hourly rate</flux:badge>
                </flux:card>
            </div>

            <flux:subheading class="mb-3">{{ __('4.4 Step-by-Step: Reviewing the Timesheet') }}</flux:subheading>
            <ol class="list-decimal list-inside space-y-3 mb-4 text-gray-700 dark:text-gray-300">
                <li>
                    <strong>Locate Each Worker:</strong>
                    <flux:text class="ml-6 mt-1">Find each worker in the list (use search if you have many workers)</flux:text>
                </li>
                <li>
                    <strong>Verify Weekday OT Hours:</strong>
                    <flux:text class="ml-6 mt-1">Check the "OT Hari Biasa" column shows correct total weekday overtime hours</flux:text>                    
                </li>
                <li>
                    <strong>Verify Rest Day OT Hours:</strong>
                    <flux:text class="ml-6 mt-1">Check the "OT Cuti Rehat" column shows correct total rest day overtime hours</flux:text>                
                </li>
                <li>
                    <strong>Verify Public Holiday OT Hours:</strong>
                    <flux:text class="ml-6 mt-1">Check the "OT Cuti Umum" column shows correct total public holiday overtime hours</flux:text>                    
                </li>
                <li>
                    <strong>Check All Workers:</strong>
                    <flux:text class="ml-6 mt-1">Review every worker to ensure data is accurate</flux:text>
                    <flux:text class="ml-6 mt-1">If you find errors, go back to OT & Transaction page to fix them before submitting</flux:text>
                </li>
            </ol>

            {{-- <flux:subheading class="mb-3">{{ __('4.5 Salary Calculation Example') }}</flux:subheading>
            <flux:card variant="outline" class="bg-gray-50 dark:bg-gray-800 mb-4">
                <flux:heading size="sm" class="mb-3">{{ __('Example: Ahmad - Construction Worker') }}</flux:heading>

                <div class="space-y-2 text-sm">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="font-semibold">Basic Salary:</div>
                        <div>RM 1,700.00</div>

                        <div class="font-semibold">Weekday OT (10 hours @ RM 12.26):</div>
                        <div>RM 122.60</div>

                        <div class="font-semibold">Rest Day OT (8 hours @ RM 16.34):</div>
                        <div>RM 130.72</div>

                        <div class="font-semibold">Public Holiday OT (0 hours):</div>
                        <div>RM 0.00</div>

                        <div class="font-semibold border-t pt-2">Gross Salary:</div>
                        <div class="border-t pt-2">RM 1,953.32</div>

                        <div class="font-semibold text-red-600">EPF Deduction (2%):</div>
                        <div class="text-red-600">- RM 39.07</div>

                        <div class="font-semibold text-red-600">SOCSO Deduction:</div>
                        <div class="text-red-600">- RM 9.75</div>

                        <div class="font-semibold border-t pt-2 text-green-600 text-base">Net Salary (Worker Receives):</div>
                        <div class="border-t pt-2 text-green-600 text-base font-bold">RM 1,904.50</div>
                    </div>
                </div>
            </flux:card> --}}

            <flux:subheading class="mb-3">{{ __('4.5 Submitting the Timesheet') }}</flux:subheading>
            <ol class="list-decimal list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>Review all entries carefully - verify OT hours and transactions match your records</li>
                <li>If any data is incorrect, go back to OT & Transaction page to fix it</li>
                <li>Once all data is verified, click "Submit Timesheet" button</li>
                <li>Confirm the submission</li>
                <li>Status changes to "Pending Approval"</li>
                <li>Wait for admin to review and approve</li>
            </ol>

            <flux:subheading class="mb-3">{{ __('4.6 Making Changes Before Submission') }}</flux:subheading>
            <flux:text class="mb-3">
                {{ __('The Timesheet itself is read-only (for verification only). To make changes to OT hours or transactions:') }}
            </flux:text>
            <ul class="list-disc list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>Navigate back to the "OT & Transaction" page</li>
                <li>Edit or delete the incorrect transactions</li>
                <li>Add any missing transactions</li>
                <li>Return to Timesheet to verify the updated data</li>
                <li>Submit when everything is correct</li>
            </ul>

            <flux:card variant="outline" class="bg-red-50 dark:bg-red-900/20 mb-4">
                <flux:heading size="sm" class="mb-2 text-red-700 dark:text-red-300">{{ __('Important: After Timesheet Submission') }}</flux:heading>
                <flux:text class="text-sm">
                    Once the timesheet is submitted, you CANNOT edit the data anymore. Make sure all OT hours and transactions are correct in the OT & Transaction page before submitting the timesheet. If you need changes after submission, contact the CLAB admin office.
                </flux:text>
            </flux:card>

            <flux:separator />

            <flux:subheading class="mb-3 mt-4">{{ __('Important Reminders') }}</flux:subheading>
            <flux:card variant="outline" class="bg-yellow-50 dark:bg-yellow-900/20">
                <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                    <li><strong>Data Entry First:</strong> Always enter OT and transactions in the OT & Transaction page first (Section 5)</li>
                    <li><strong>Timesheet = Verification:</strong> The timesheet only displays data for verification - you cannot enter data here</li>
                    <li><strong>Critical Deadline:</strong> Submit timesheet by the last day of the month (e.g., 31st Dec for December payroll)</li>
                    <li><strong>Late Penalty:</strong> 8% penalty on total invoice amount for late submissions - plan ahead!</li>
                    <li><strong>Accuracy:</strong> Double-check all OT hours and transactions in OT & Transaction page before submitting timesheet</li>
                    <li><strong>No Edits After Submission:</strong> You cannot edit after timesheet submission - verify carefully!</li>
                    <li><strong>Documentation:</strong> Keep attendance records to support your OT entries</li>
                    <li><strong>Early Submission:</strong> Submit early to avoid last-minute technical issues</li>
                </ul>
            </flux:card>
        </flux:card>

        {{-- 5. OT & Transaction Entry (Detailed) --}}
        <flux:card class="mb-8" id="ot-entry">
            <flux:heading size="lg" class="mb-4">{{ __('5. OT & Transaction Entry (Detailed Guide)') }}</flux:heading>

            <flux:card variant="outline" class="bg-green-50 dark:bg-green-900/20 mb-4">
                <flux:heading size="sm" class="mb-2 text-green-700 dark:text-green-300">{{ __('PRIMARY DATA ENTRY LOCATION - START HERE') }}</flux:heading>
                <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                    <li><strong>This is where you enter data:</strong> ALL overtime hours and transactions MUST be entered here</li>
                    <li><strong>Timesheet is for verification only:</strong> The Timesheet page only displays what you enter here</li>
                    <li><strong>Required for payroll:</strong> You must use this page to submit your workers' OT and transactions</li>                    
                </ul>
            </flux:card>

            <flux:card variant="outline" class="bg-blue-50 dark:bg-blue-900/20 mb-4">
                <flux:heading size="sm" class="mb-2 text-blue-700 dark:text-blue-300">{{ __('OT Submission Window & Payroll Inclusion') }}</flux:heading>
                <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                    <li><strong>Submission Period:</strong> OT transactions can be entered from the 1st to 15th of the following month</li>
                    <li><strong>Example:</strong> For December work, you can enter OT transactions from 1st January to 15th January</li>
                    <li><strong>Payroll Inclusion:</strong> OT transactions submitted during this window will be included in the <strong>following month's payroll</strong></li>
                    <li><strong>Example:</strong> December OT (entered Jan 1-15) will be included in January payroll</li>
                    <li><strong>After 15th:</strong> The system will close the submission window for that period</li>
                </ul>
            </flux:card>

            <flux:subheading class="mb-3">{{ __('5.1 Understanding OT & Transaction Entry') }}</flux:subheading>
            <flux:text class="mb-4">
                {{ __('This is the ONLY place where you can enter data. The OT & Transaction Entry page allows you to:') }}
            </flux:text>
            <ul class="list-disc list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>Record ALL overtime hours for your workers (required for payroll)</li>
                <li>Enter transactions: advance payments, deductions, allowances, and NPL (No-Pay Leave)</li>
                <li>Track OT by specific date and worker for accurate record-keeping</li>
                <li>Add notes or remarks for each transaction</li>
                <li>View cumulative OT hours and transactions for the current month</li>
                <li>Edit or delete entries before the 15th of the following month</li>
                <li>Record data as it happens (daily/weekly) instead of waiting until month-end</li>
            </ul>

            <flux:subheading class="mb-3">{{ __('5.2 Creating an OT Transaction') }}</flux:subheading>
            <ol class="list-decimal list-inside space-y-3 mb-4 text-gray-700 dark:text-gray-300">
                <li>
                    <strong>Navigate to OT Entry:</strong>
                    <flux:text class="ml-6 mt-1">Click "OT Entry" from the sidebar</flux:text>
                </li>
                <li>
                    <strong>Click "Add Transaction":</strong>
                    <flux:text class="ml-6 mt-1">A modal form will appear</flux:text>
                </li>
                <li>
                    <strong>Select Worker:</strong>
                    <flux:text class="ml-6 mt-1">Choose the worker from the dropdown list</flux:text>
                    <flux:text class="ml-6 mt-1 text-sm italic">Tip: Use search to find workers quickly</flux:text>
                </li>
                <li>
                    <strong>Select Date:</strong>
                    <flux:text class="ml-6 mt-1">Choose the date when the OT was performed</flux:text>
                    <flux:text class="ml-6 mt-1 text-sm italic">Must be within the current month</flux:text>
                </li>
                <li>
                    <strong>Select OT Type:</strong>
                    <flux:text class="ml-6 mt-1">Choose from: Hari Biasa, Cuti Rehat, or Cuti Umum</flux:text>
                </li>
                <li>
                    <strong>Enter Hours:</strong>
                    <flux:text class="ml-6 mt-1">Input the number of OT hours (can use decimals, e.g., 2.5)</flux:text>
                </li>
                <li>
                    <strong>Add Notes (Optional):</strong>
                    <flux:text class="ml-6 mt-1">Add remarks like "Emergency repair work" or "Weekend project"</flux:text>
                </li>
                <li>
                    <strong>Save Transaction:</strong>
                    <flux:text class="ml-6 mt-1">Click "Save" to record the transaction</flux:text>
                </li>
            </ol>

            <flux:subheading class="mb-3">{{ __('5.3 Viewing and Managing Transactions') }}</flux:subheading>

            <flux:text class="mb-3 font-semibold">{{ __('Transaction List View:') }}</flux:text>
            <ul class="list-disc list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>All transactions are listed in chronological order</li>
                <li>Filter by worker, date range, or OT type</li>
                <li>Search by worker name or notes</li>
                <li>View total OT hours per category at the top</li>
            </ul>

            <flux:text class="mb-3 font-semibold">{{ __('Editing a Transaction:') }}</flux:text>
            <ol class="list-decimal list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>Find the transaction in the list</li>
                <li>Click the "Edit" icon/button</li>
                <li>Modify the details</li>
                <li>Save changes</li>
            </ol>

            <flux:text class="mb-3 font-semibold">{{ __('Deleting a Transaction:') }}</flux:text>
            <ol class="list-decimal list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>Find the transaction in the list</li>
                <li>Click the "Delete" icon/button</li>
                <li>Confirm deletion</li>
                <li>Transaction is permanently removed</li>
            </ol>

            <flux:subheading class="mb-3">{{ __('5.4 Monthly Summary and Export') }}</flux:subheading>
            <flux:text class="mb-4">
                {{ __('At any time during the month, you can view a summary of all OT transactions:') }}
            </flux:text>

            <ul class="list-disc list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li><strong>Per Worker Summary:</strong> Total OT hours by type for each worker</li>
                <li><strong>Overall Summary:</strong> Total OT hours across all workers</li>
                <li><strong>Export Options:</strong> Download as Excel or PDF for records</li>
                <li><strong>Pre-submission Review:</strong> Verify all entries before month-end</li>
            </ul>

            <flux:subheading class="mb-3">{{ __('5.5 Submission Timeline & Process') }}</flux:subheading>

            <flux:card variant="outline" class="bg-purple-50 dark:bg-purple-900/20 mb-4">
                <flux:heading size="sm" class="mb-2">{{ __('Critical Timeline') }}</flux:heading>
                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    <div><strong>Work Period:</strong> December 2025 (example)</div>
                    <div><strong>OT Entry Window:</strong> 1st January 2026 - 15th January 2026</div>
                    <div><strong>Included in Payroll:</strong> January 2026 payroll</div>
                    <div class="text-red-600 dark:text-red-400 font-semibold mt-2">⚠️ After 15th January, the system closes - no more entries allowed!</div>
                </div>
            </flux:card>

            <flux:text class="mb-3 font-semibold">{{ __('Step-by-Step Process:') }}</flux:text>
            <ol class="list-decimal list-inside space-y-3 mb-4 text-gray-700 dark:text-gray-300">
                <li>
                    <strong>During Work Period (e.g., December):</strong>
                    <flux:text class="ml-6 mt-1">Enter OT transactions as they occur throughout the month</flux:text>
                </li>
                <li>
                    <strong>Early Following Month (1st-15th):</strong>
                    <flux:text class="ml-6 mt-1">Continue entering any remaining OT transactions</flux:text>
                    <flux:text class="ml-6 mt-1">Review all entries for accuracy</flux:text>
                </li>
                <li>
                    <strong>Before 15th Deadline:</strong>
                    <flux:text class="ml-6 mt-1">Export summary for your records</flux:text>
                    <flux:text class="ml-6 mt-1">Make any final corrections</flux:text>
                </li>
                <li>
                    <strong>After 15th:</strong>
                    <flux:text class="ml-6 mt-1">System locks the period automatically</flux:text>
                    <flux:text class="ml-6 mt-1">OT data is processed for the following month's payroll</flux:text>
                </li>
            </ol>

            <flux:subheading class="mb-3">{{ __('5.6 Advanced Features') }}</flux:subheading>

            <div class="space-y-3 mb-4">
                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Bulk Entry') }}</flux:heading>
                    <flux:text>Upload multiple transactions via CSV file for faster data entry (if available).</flux:text>
                </flux:card>

                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Templates') }}</flux:heading>
                    <flux:text>Save common OT patterns (e.g., "Saturday Full Day") for quick entry (if available).</flux:text>
                </flux:card>

                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Monthly Summary View') }}</flux:heading>
                    <flux:text>View totals per worker and overall summary before verifying in Timesheet.</flux:text>
                </flux:card>

                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Automatic Timesheet Integration') }}</flux:heading>
                    <flux:text>All data entered here automatically appears in the Timesheet for verification - no need to re-enter!</flux:text>
                </flux:card>
            </div>

            <flux:separator />

            <flux:subheading class="mb-3 mt-4">{{ __('Best Practices') }}</flux:subheading>
            <flux:card variant="outline" class="bg-blue-50 dark:bg-blue-900/20">
                <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                    <li><strong>Always Start Here:</strong> Enter ALL OT and transactions in this OT & Transaction page - don't try to enter in Timesheet</li>
                    <li><strong>Remember the 15th Deadline:</strong> All OT entries must be completed by the 15th of the following month</li>
                    <li><strong>Daily Recording:</strong> Enter OT transactions daily while fresh in memory - don't wait until month-end</li>
                    <li><strong>Early Completion:</strong> Complete entries well before the 15th to allow time for verification in Timesheet</li>
                    <li><strong>Verify Before Timesheet:</strong> Review your OT summary here before going to Timesheet page</li>
                    <li><strong>Detailed Notes:</strong> Add context to help with future reference and audits</li>
                    <li><strong>Regular Review:</strong> Check weekly summaries to catch errors early</li>
                    <li><strong>Backup Records:</strong> Keep attendance sheets that match your OT entries</li>
                    <li><strong>Consistency:</strong> Use the same format for notes across all entries</li>
                    <li><strong>Corrections:</strong> Fix errors here immediately before the 15th deadline</li>
                </ul>
            </flux:card>

            {{-- <flux:separator /> --}}

            {{-- <flux:subheading class="mb-3 mt-4">{{ __('Common Mistakes to Avoid') }}</flux:subheading>
            <flux:card variant="outline" class="bg-red-50 dark:bg-red-900/20">
                <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                    <li><strong class="text-red-600">Trying to enter data in Timesheet:</strong> Timesheet is read-only - you MUST enter data here in OT & Transaction page!</li>
                    <li><strong class="text-red-600">Missing the 15th Deadline:</strong> System closes after 15th - plan ahead!</li>
                    <li>Forgetting the two-step workflow: Enter in OT page → Verify in Timesheet → Submit</li>
                    <li>Mixing up OT types (e.g., entering Rest Day as Weekday)</li>
                    <li>Forgetting to save transactions after entry</li>
                    <li>Entering hours in wrong format (minutes instead of decimal hours)</li>
                    <li>Waiting until the last day (15th) to review - review earlier!</li>
                    <li>Not understanding payroll inclusion - December OT goes into January payroll</li>
                    <li>Deleting transactions by accident without confirmation</li>
                    <li>Entering OT for workers who are on leave</li>
                    <li>Exceeding reasonable OT limits without justification</li>
                    <li>Not reviewing OT summary before going to Timesheet page</li>
                </ul>
            </flux:card> --}}
        </flux:card>

        {{-- 6. Payments --}}
        <flux:card class="mb-8" id="payments">
            <flux:heading size="lg" class="mb-4">{{ __('6. Payments') }}</flux:heading>

            <flux:text class="mb-4">
                {{ __('View and pay your invoices online through the integrated Billplz payment gateway.') }}
            </flux:text>

            <flux:subheading class="mb-3">{{ __('Making a Payment') }}</flux:subheading>
            <ol class="list-decimal list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li>Navigate to "Payments" or "Invoices"</li>
                <li>View pending invoices</li>
                <li>Click "Pay Now" on the invoice</li>
                <li>Choose payment method</li>
                <li>Complete payment on Billplz gateway</li>
                <li>Return to system for confirmation</li>
            </ol>

            <flux:subheading class="mb-3">{{ __('Payment Status') }}</flux:subheading>
            <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                <li><flux:badge color="yellow">Pending</flux:badge> - Awaiting payment</li>
                <li><flux:badge color="blue">Processing</flux:badge> - Payment in progress</li>
                <li><flux:badge color="green">Paid</flux:badge> - Payment successful</li>
                <li><flux:badge color="red">Failed</flux:badge> - Payment failed, retry needed</li>
            </ul>
        </flux:card>

        {{-- 7. Invoices --}}
        <flux:card class="mb-8" id="invoices">
            <flux:heading size="lg" class="mb-4">{{ __('7. Invoices') }}</flux:heading>

            <flux:text class="mb-4">
                {{ __('View and download invoices for your payroll submissions.') }}
            </flux:text>

            <flux:subheading class="mb-3">{{ __('Invoice Types') }}</flux:subheading>
            <div class="space-y-3 mb-4">
                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Pro Forma Invoice') }}</flux:heading>
                    <flux:text>Preliminary invoice sent before payment. Used for budgeting and approval.</flux:text>
                </flux:card>

                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Official Receipt') }}</flux:heading>
                    <flux:text>Official invoice issued after payment. Used for accounting and tax purposes.</flux:text>
                </flux:card>
            </div>

            <flux:subheading class="mb-3">{{ __('Downloading Invoices') }}</flux:subheading>
            <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                <li>Go to "Invoices" page</li>
                <li>Click on the invoice you want</li>
                <li>Click "Download Invoice" button</li>
                <li>For paid invoices, you can also download receipt</li>
            </ul>
        </flux:card>

        {{-- 9. Reports --}}
        {{-- <flux:card class="mb-8" id="reports">
            <flux:heading size="lg" class="mb-4">{{ __('9. Reports') }}</flux:heading>

            <flux:text class="mb-4">
                {{ __('Generate various reports for analysis and compliance.') }}
            </flux:text>

            <flux:subheading class="mb-3">{{ __('Available Reports') }}</flux:subheading>
            <ul class="list-disc list-inside space-y-2 mb-4 text-gray-700 dark:text-gray-300">
                <li><strong>Payroll Summary:</strong> Monthly payroll totals by contractor</li>
                <li><strong>EPF Report:</strong> EPF contributions for statutory submission</li>
                <li><strong>SOCSO Report:</strong> SOCSO contributions for statutory submission</li>
                <li><strong>Worker List:</strong> Active workers with permit status</li>
                <li><strong>OT Analysis:</strong> Overtime trends and patterns</li>
                <li><strong>Payment History:</strong> Invoice and payment records</li>
                <li><strong>Activity Logs:</strong> System usage and audit trail (Super Admin only)</li>
            </ul>

            <flux:subheading class="mb-3">{{ __('Generating Reports') }}</flux:subheading>
            <ol class="list-decimal list-inside space-y-2 text-gray-700 dark:text-gray-300">
                <li>Navigate to "Reports"</li>
                <li>Select report type</li>
                <li>Choose date range or month</li>
                <li>Apply filters if needed</li>
                <li>Click "Generate Report"</li>
                <li>Download as PDF or Excel</li>
            </ol>
        </flux:card> --}}

        {{-- Support & Contact --}}
        <flux:card class="mb-8">
            <flux:heading size="lg" class="mb-4">{{ __('Need Help?') }}</flux:heading>

            <flux:text class="mb-4">
                {{ __('If you have questions or encounter issues while using the system, please contact the CLAB office for assistance.') }}
            </flux:text>

            <flux:subheading class="mb-3">{{ __('Common Questions') }}</flux:subheading>
            <div class="space-y-3 mb-4">
                <flux:card variant="outline" class="bg-red-50 dark:bg-red-900/20">
                    <flux:heading size="sm" class="mb-2 text-red-700 dark:text-red-300">{{ __('When is the timesheet deadline?') }}</flux:heading>
                    <flux:text class="text-sm">Timesheet must be submitted by the <strong>last day of each month</strong>. For example, December 2025 payroll must be submitted by 31st December 2025. Late submissions incur 8% penalty!</flux:text>
                </flux:card>

                <flux:card variant="outline" class="bg-blue-50 dark:bg-blue-900/20">
                    <flux:heading size="sm" class="mb-2 text-blue-700 dark:text-blue-300">{{ __('When can I submit OT transactions?') }}</flux:heading>
                    <flux:text class="text-sm">OT transactions can be entered from the <strong>1st to 15th of the following month</strong>. For example, December OT can be entered from 1st-15th January. These will be included in January's payroll.</flux:text>
                </flux:card>

                {{-- <flux:card variant="outline" class="bg-gray-50 dark:bg-gray-800">
                    <flux:heading size="sm" class="mb-2">{{ __('What happens if I submit late?') }}</flux:heading>
                    <flux:text class="text-sm">Late timesheet submissions will incur an <strong>8% penalty</strong> on the total invoice amount. Always submit before the deadline to avoid penalties.</flux:text>
                </flux:card> --}}

                <flux:card variant="outline" class="bg-gray-50 dark:bg-gray-800">
                    <flux:heading size="sm" class="mb-2">{{ __('Can I enter OT hours directly in the Timesheet?') }}</flux:heading>
                    <flux:text class="text-sm">No, the Timesheet is read-only for verification purposes. You MUST enter all OT hours and transactions in the "OT & Transaction" page first. The Timesheet will then display your data for review before final submission.</flux:text>
                </flux:card>

                <flux:card variant="outline" class="bg-gray-50 dark:bg-gray-800">
                    <flux:heading size="sm" class="mb-2">{{ __('Can I edit my timesheet after submission?') }}</flux:heading>
                    <flux:text class="text-sm">No, once submitted you cannot edit it. Make sure all data is correct in the OT & Transaction page before submitting the timesheet. If you need changes after submission, contact the CLAB admin office.</flux:text>
                </flux:card>

                <flux:card variant="outline" class="bg-gray-50 dark:bg-gray-800">
                    <flux:heading size="sm" class="mb-2">{{ __('When will December OT be paid?') }}</flux:heading>
                    <flux:text class="text-sm">December OT transactions (entered 1st-15th January) will be included in <strong>January's payroll</strong>, not December's payroll.</flux:text>
                </flux:card>

                <flux:card variant="outline" class="bg-gray-50 dark:bg-gray-800">
                    <flux:heading size="sm" class="mb-2">{{ __('How do I know my payment was successful?') }}</flux:heading>
                    <flux:text class="text-sm">You will receive a confirmation on screen and your invoice status will change to "Paid". You can also download the Receipt as proof.</flux:text>
                </flux:card>
            </div>

            <flux:subheading class="mb-3">{{ __('Contact CLAB Office') }}</flux:subheading>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Technical Support') }}</flux:heading>
                    <flux:text>For login issues, system errors, or technical problems.</flux:text>
                </flux:card>

                <flux:card variant="outline">
                    <flux:heading size="sm" class="mb-2">{{ __('Payroll Support') }}</flux:heading>
                    <flux:text>For questions about salary calculations, submissions, or payments.</flux:text>
                </flux:card>
            </div>
        </flux:card>

        {{-- Back to Top --}}
        <div class="text-center">
            <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline">{{ __('Back to Top') }}</a>
        </div>
    </div>
</div>
