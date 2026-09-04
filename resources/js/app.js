import './bootstrap';
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

// CSRF Token Handling
document.addEventListener('DOMContentLoaded', function() {
    // Refresh CSRF token periodically (every 30 minutes)
    setInterval(refreshCsrfToken, 30 * 60 * 1000);

    // Add CSRF token to all AJAX requests
    setupAjaxErrorHandling();
});

/**
 * Refresh CSRF token from server
 */
function refreshCsrfToken() {
    fetch('/csrf-token', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.token) {
            // Update all CSRF token inputs
            document.querySelectorAll('input[name="_token"]').forEach(input => {
                input.value = data.token;
            });

            // Update meta tag
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', data.token);
            }

            console.log('CSRF token refreshed successfully');
        }
    })
    .catch(error => {
        console.warn('Failed to refresh CSRF token:', error);
    });
}

/**
 * Setup global AJAX error handling for 419 errors
 */
function setupAjaxErrorHandling() {
    // For Livewire AJAX requests
    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, content }) => {
                if (status === 419) {
                    showSessionExpiredNotification();
                }
            });
        });
    });

    // For regular fetch requests
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args)
            .then(response => {
                if (response.status === 419) {
                    showSessionExpiredNotification();
                }
                return response;
            });
    };
}

/**
 * Show user-friendly notification when session expires
 */
function showSessionExpiredNotification() {
    // Try to use Flux toast (wait for it to be available)
    if (typeof $flux !== 'undefined' && $flux.toast) {
        $flux.toast({
            heading: 'Session Expired',
            text: 'Your session has expired. The page will refresh automatically in 3 seconds.',
            variant: 'warning',
            duration: 5000
        });

        // Auto-reload after showing the message
        setTimeout(() => {
            window.location.reload();
        }, 3000);
    } else if (typeof Livewire !== 'undefined' && Livewire.dispatch) {
        // Try Livewire event approach
        Livewire.dispatch('flux:toast', {
            heading: 'Session Expired',
            text: 'Your session has expired. The page will refresh automatically.',
            variant: 'warning'
        });

        setTimeout(() => {
            window.location.reload();
        }, 3000);
    } else {
        // Create a custom toast element as final fallback
        showCustomToast();
    }
}

/**
 * Show custom toast notification (fallback when Flux is not available)
 */
function showCustomToast() {
    // Create toast container if it doesn't exist
    let container = document.getElementById('custom-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'custom-toast-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(container);
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.style.cssText = `
        background: #f59e0b;
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 10px;
        min-width: 300px;
        animation: slideIn 0.3s ease-out;
    `;
    toast.innerHTML = `
        <div style="font-weight: 600; margin-bottom: 4px;">Session Expired</div>
        <div style="font-size: 14px;">Your session has expired. Refreshing page in 3 seconds...</div>
    `;

    // Add animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    container.appendChild(toast);

    // Auto-reload after 3 seconds
    setTimeout(() => {
        window.location.reload();
    }, 3000);
}

/**
 * Show session warning before expiry (optional - if you want to warn users)
 */
function showSessionWarning(minutesLeft) {
    if (window.Flux && window.Flux.toast) {
        window.Flux.toast({
            heading: 'Session Expiring Soon',
            text: `Your session will expire in ${minutesLeft} minutes. Save your work!`,
            variant: 'info',
            duration: 8000
        });
    }
}

// Optional: Warn users before session expires
// Uncomment if you want to add this feature
// setTimeout(() => showSessionWarning(5), (115 * 60 * 1000)); // 5 min warning if session is 120 min

// ===========================
// Driver.js Tutorial System
// ===========================

/**
 * Tutorial configurations for different pages
 */
const tutorialConfigs = {
    dashboard: {
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Welcome to e-Payroll System! 👋',
                    description: 'Let\'s take a quick tour to help you get started with managing your payroll submissions.',
                    side: 'center',
                    align: 'center'
                }
            },
            {
                element: '#dashboard-stats',
                popover: {
                    title: 'Dashboard Statistics',
                    description: 'Four quick-glance cards: (1) My Workers — active headcount with contract expiry alerts, (2) This Month — total payroll amount due with payment deadline, (3) Outstanding Balance — any unpaid or overdue invoices, (4) Paid This Year — cumulative payments made since January.',
                    side: 'bottom'
                }
            },
            {
                element: '[href*="timesheet"]',
                popover: {
                    title: 'Timesheet Submission',
                    description: 'Submit monthly timesheets for your workers here. This is where you enter basic salary and regular working hours.',
                    side: 'right'
                }
            },
            {
                element: '[href*="ot-entry"]',
                popover: {
                    title: 'OT & Transaction Entry',
                    description: 'Add overtime hours (weekday, rest day, public holiday) and transactions like advances or deductions for your workers.',
                    side: 'right'
                }
            },
            {
                element: '[href*="worker"]',
                popover: {
                    title: 'Worker Management',
                    description: 'View and manage all your contracted workers. Check their contract status and employment details.',
                    side: 'right'
                }
            },
            {
                element: '[href*="invoice"]',
                popover: {
                    title: 'Invoices & Payments',
                    description: 'View your invoices, make payments, and download receipts. Track all your payment history here.',
                    side: 'right'
                }
            },
            {
                element: '[href*="faq"]',
                popover: {
                    title: 'FAQ',
                    description: 'Have a question? Click FAQ to open our answers to common questions about timesheets, OT entry, payments and invoices in a new tab. We keep it updated.',
                    side: 'right'
                }
            },
            {
                element: '[href*="settings"]',
                popover: {
                    title: 'Settings',
                    description: 'Update your profile and adjust appearance preferences (light/dark mode) here.',
                    side: 'right'
                }
            },
            {
                element: '#tutorial-button',
                popover: {
                    title: 'Need Help Again?',
                    description: 'You can replay this tutorial anytime by clicking this button. Happy managing your payroll! 🎉',
                    side: 'bottom'
                }
            }
        ]
    },

    timesheet: {
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Timesheet Management Tutorial',
                    description: 'Learn how to review your workers\' payroll data and understand the automatic submission process.',
                    side: 'center',
                    align: 'center'
                }
            },
            {
                element: '#current-period-info',
                popover: {
                    title: 'Period & Key Dates',
                    description: 'The period you are reviewing, how many workers it covers, and the two dates that matter: when the timesheet submits automatically, and when payment falls due. Watch the deadline to avoid the 8% late payment penalty.',
                    side: 'bottom'
                }
            },
            {
                element: '#worker-verification-table',
                popover: {
                    title: 'Worker Payroll Table',
                    description: 'This table shows all your workers with their basic salary, OT hours, and transactions already filled in from the OT & Transaction Entry page. Review this data to make sure everything is correct before the 16th.',
                    side: 'top'
                }
            },
            {
                element: '#worker-verification-table',
                popover: {
                    title: 'Pre-filled Data',
                    description: 'Basic Salary is set automatically. OT Hours and Transactions are entered via the OT & Transaction Entry page (available from the 1st to the 15th of each month). Review the entries here to ensure accuracy.',
                    side: 'top'
                }
            },
            {
                element: '#worker-verification-table',
                popover: {
                    title: '⚡ Automatic Submission on the 16th',
                    description: 'You no longer need to manually submit payroll. The system automatically submits all worker timesheets on the 16th of every month. Make sure all OT hours and transactions are entered and correct before then!',
                    side: 'top'
                }
            },
            {
                element: '#submission-history',
                popover: {
                    title: 'Recent Timesheets',
                    description: 'View all your past timesheets and their current status: In Progress (admin is processing), Approved, Pending Payment, Paid, or Overdue. You can also view the invoice or pay directly from the actions menu.',
                    side: 'top'
                }
            },
            {
                element: '#tutorial-button',
                popover: {
                    title: 'Quick Workflow Recap',
                    description: 'Remember: 1) Enter OT & Transactions in the OT Entry page (1st–15th), 2) Review worker data here to ensure accuracy, 3) The system auto-submits on the 16th — no manual action needed! Click tutorial anytime to replay.',
                    side: 'bottom'
                }
            }
        ]
    },

    'ot-entry': {
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'OT & Transaction Entry Tutorial',
                    description: 'This page has three parts: enter overtime hours, record deductions and earnings for each worker, and upload the signed Salary Deduction Form. Your OT and transaction data saves automatically — no submit button needed.',
                    side: 'center',
                    align: 'center'
                }
            },
            {
                element: '#entry-window-status',
                popover: {
                    title: 'Entry Window Status',
                    description: 'Everything on this page is only editable between the 1st and 15th of each month, and it covers the <strong>previous</strong> month. This card shows whether the window is open and how many days you have left.',
                    side: 'bottom'
                }
            },
            {
                element: '#download-template-btn',
                popover: {
                    title: 'Download Template',
                    description: 'Download the Excel template to see the required format for bulk importing OT hours and transactions. Fill it in and import it back using the "Import from File" button.',
                    side: 'bottom'
                }
            },
            {
                element: '#import-file-btn',
                popover: {
                    title: 'Import from File',
                    description: 'Upload a filled Excel or CSV template to bulk-import OT hours and transactions for all workers at once. Supports .xlsx, .xls, and .csv files up to 2MB. All imported data is saved automatically.',
                    side: 'bottom'
                }
            },
            {
                element: '#ot-entry-table',
                popover: {
                    title: 'OT Entry Table',
                    description: 'You can also enter OT hours directly in this table. Each row is a worker — enter Weekday OT (1.5× rate), Rest Day OT (2× rate), and Public Holiday OT (3× rate). Data is saved automatically when you move to the next field.',
                    side: 'top'
                }
            },
            {
                element: '#ot-entry-table',
                popover: {
                    title: 'Manage Deductions & Earnings',
                    description: 'Click the <strong>Manage</strong> button in the Actions column of any worker row to record their transactions. <strong>Deductions:</strong> Accommodation, Advance Payment, No-Pay Leave (NPL). <strong>Earnings:</strong> Allowance, Backpay, Medical Claim. Click "Save Transactions" to store them — the Transactions column then shows a summary for that worker.',
                    side: 'top'
                }
            },
            {
                element: '#ot-entry-actions',
                popover: {
                    title: '✅ Auto-Save — No Action Needed',
                    description: 'Every change you make is saved automatically to the database. You will see "Draft auto-saved" here after each save. There is no Submit button — just enter your data and the system handles the rest.',
                    side: 'top'
                }
            },
            {
                element: '#salary-deduction-form',
                popover: {
                    title: 'Salary Deduction Form',
                    description: 'Any deduction you record must be backed by a signed declaration. This section builds that form for you — the badge shows how many workers have deductions this period. It is a two-step process: download and sign, then upload the signed copy back here.',
                    side: 'top'
                }
            },
            {
                element: '#download-deduction-form-btn',
                popover: {
                    title: 'Step 1 — Download & Sign',
                    description: 'Downloads a single PDF containing one pre-filled signature page per worker with deductions. Print it, then collect <strong>both</strong> signatures: your officer and the worker. If no deductions were recorded this period, this button stays disabled — nothing to sign.',
                    side: 'top'
                }
            },
            {
                element: '#salary-deduction-form',
                popover: {
                    title: 'Step 2 — Upload Signed Copy',
                    description: 'Drop the signed form into the upload box on the right (PDF, JPG or PNG up to 10 MB), then click "Upload Signed Form". Once uploaded you can <strong>View Uploaded</strong> to check it, or <strong>Replace</strong> it while the window is still open. After the 15th the upload box closes for that period.',
                    side: 'top'
                }
            },
            {
                element: '#ot-entry-table',
                popover: {
                    title: '⚠️ Auto-Submit on the 16th',
                    description: 'On the 16th of each month, the system automatically submits and locks all draft OT entries and transactions. You do NOT need to do anything — just make sure your data is entered and the signed deduction form is uploaded before the 15th ends.',
                    side: 'top'
                }
            },
            {
                element: '#tutorial-button',
                popover: {
                    title: 'Quick Workflow Recap',
                    description: '1) Enter OT hours manually OR import from file. 2) Click <strong>Manage</strong> on each worker to add deductions and earnings. 3) Download the Salary Deduction Form, collect both signatures, and upload the signed copy. 4) Everything saves automatically and locks on the 16th. Click the tutorial button anytime to replay.',
                    side: 'bottom'
                }
            }
        ]
    },

    'ot-import': {
        steps: [
            {
                element: '#import-modal-header',
                popover: {
                    title: 'Import OT & Transactions',
                    description: 'This feature allows you to bulk import overtime hours and transactions from an Excel or CSV file. Let\'s walk through the process!',
                    side: 'bottom'
                }
            },
            {
                element: '#import-file-input-container',
                popover: {
                    title: 'Step 1: Select Your File',
                    description: 'Click the file input to select your Excel (.xlsx, .xls) or CSV file. Maximum file size is 2MB. Make sure you\'ve downloaded and filled in the template first!',
                    side: 'bottom'
                }
            },
            {
                element: '#import-instructions',
                popover: {
                    title: 'Import File Format',
                    description: 'Your file must include: Worker passport, name, OT hours (weekday/rest/public), and transactions. Transaction types: advance_payment, deduction, npl (no-pay leave), or allowance. You can have multiple rows for the same worker.',
                    side: 'top'
                }
            },
            {
                element: '#import-modal-actions',
                popover: {
                    title: 'Step 2: Process File',
                    description: 'After selecting your file, click "Process File" to validate the data. The system will check for errors and show you a preview of what will be imported.',
                    side: 'top'
                }
            },
            {
                element: 'body',
                popover: {
                    title: 'Preview & Confirm',
                    description: 'If your file has valid data, you\'ll see a preview table showing all records to be imported. Review carefully, then click "Confirm & Import" to complete the process. Any errors will be shown at the top.',
                    side: 'center',
                    align: 'center'
                }
            }
        ]
    },

    workers: {
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Worker Management Tutorial 👥',
                    description: 'Welcome! This page helps you manage all workers contracted to your company. Let\'s explore the powerful features available to you.',
                    side: 'center',
                    align: 'center'
                }
            },
            {
                element: '.grid.gap-4.md\\:grid-cols-4',
                popover: {
                    title: 'Worker Statistics Overview',
                    description: 'Quick overview of your workforce: Total workers, how many are Active/Inactive, and Average Salary across all workers. These update in real-time!',
                    side: 'bottom'
                }
            },
            {
                element: '#filter-section',
                popover: {
                    title: 'Advanced Search & Filters',
                    description: 'Click the "Search & Filters" header to expand this powerful filtering section. You can search by name or passport, and filter by Status, Country, and Position simultaneously!',
                    side: 'bottom',
                    onHighlightStarted: () => {
                        // Auto-expand filters if collapsed
                        const filterContent = document.getElementById('filter-content');
                        const filterChevron = document.getElementById('filter-chevron');
                        if (filterContent && filterContent.style.display === 'none') {
                            filterContent.style.display = 'block';
                            if (filterChevron) {
                                filterChevron.style.transform = 'rotate(180deg)';
                            }
                        }
                    }
                }
            },
            {
                element: '[wire\\:model\\.live\\.debounce\\.500ms="search"]',
                popover: {
                    title: 'Smart Search Bar',
                    description: 'Search workers by name or passport number. The search updates automatically as you type. Very useful for quickly finding specific workers!',
                    side: 'bottom'
                }
            },
            {
                element: '[wire\\:model\\.live="status"]',
                popover: {
                    title: 'Status Filter',
                    description: 'Filter workers by contract status: Active (currently working) or Inactive (contract ended). This helps you focus on workers who need attention.',
                    side: 'bottom'
                }
            },
            {
                element: '[wire\\:model\\.live="country"]',
                popover: {
                    title: 'Country Filter',
                    description: 'Filter by worker nationality. Useful if you need to manage workers from specific countries or for reporting purposes.',
                    side: 'bottom'
                }
            },
            {
                element: '[wire\\:model\\.live="position"]',
                popover: {
                    title: 'Position/Trade Filter',
                    description: 'Filter by job position (trade). Great for viewing all workers in a specific role like "Construction Worker", "Welder", etc.',
                    side: 'bottom'
                }
            },
            {
                element: '[wire\\:click="resetFilters"]',
                popover: {
                    title: 'Clear All Filters',
                    description: 'Click "Clear" to reset all filters and search back to default. Active filters are shown as badges above the filter section.',
                    side: 'left'
                }
            },
            {
                element: '#workers-table-section',
                popover: {
                    title: 'Worker Information Table',
                    description: 'This table shows all workers with key details: Name, Passport Number, Passport/Permit Expiry dates, Country, Position, Basic Salary, and Status. All columns are sortable!',
                    side: 'top'
                }
            },
            {
                element: '#workers-table-section',
                popover: {
                    title: 'Sortable Columns',
                    description: 'Click any column header to sort by that field. Click again to reverse the sort order. Notice the arrow indicators showing current sort direction.',
                    side: 'top'
                }
            },
            {
                element: '#workers-table-section',
                popover: {
                    title: '⚠️ Expiry Warnings',
                    description: 'Pay attention to expiry dates! Expired documents show "(Expired)" in red, while documents expiring soon (passport within 60 days, permit within 30 days) show "(Soon)" in orange.',
                    side: 'top'
                }
            },
            {
                element: '[wire\\:click="export"]',
                popover: {
                    title: 'Export Worker Data',
                    description: 'Download all worker information (with current filters applied) as an Excel file. Perfect for record-keeping or external reporting.',
                    side: 'left'
                }
            },
            {
                element: '#actions-column-header',
                popover: {
                    title: 'Worker Actions Menu',
                    description: 'Each worker row has an actions menu (three dots icon) in this column. Click it to access options like "View Details" to see complete worker information including contract details.',
                    side: 'left'
                }
            },
            {
                element: '#workers-table-section',
                popover: {
                    title: 'Pagination Controls',
                    description: 'At the bottom, you\'ll find pagination controls to navigate through pages if you have many workers. It shows current page, total results, and page numbers for easy navigation.',
                    side: 'top'
                }
            },
            {
                element: '#tutorial-button',
                popover: {
                    title: 'Need Help Again?',
                    description: 'You can replay this tutorial anytime by clicking this button. Pro tip: Use filters to manage large worker lists efficiently! 🎯',
                    side: 'bottom'
                }
            }
        ]
    },

    invoices: {
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Invoices & Payments Tutorial 💳',
                    description: 'Welcome! Learn how to view your invoices, track payment status, and make payments securely. Let\'s explore!',
                    side: 'center',
                    align: 'center'
                }
            },
            {
                element: '#invoice-stats',
                popover: {
                    title: 'Invoice Statistics Overview',
                    description: 'Quick summary of your invoices: Pending Invoices (awaiting payment), Paid Invoices (completed), and Total Invoiced amount. These stats update in real-time!',
                    side: 'bottom'
                }
            },
            {
                element: '#invoice-filters-section',
                popover: {
                    title: 'Search & Filters Section',
                    description: 'Click "Search & Filters" to expand this section. You can search by invoice number or period, filter by status (All, Draft, Pending, Paid, Overdue), and select which year to view. Very powerful for managing large invoice lists!',
                    side: 'bottom',
                    onHighlightStarted: () => {
                        // Auto-expand filters if collapsed
                        const filterContent = document.getElementById('invoice-filter-content');
                        const filterChevron = document.getElementById('invoice-filter-chevron');
                        if (filterContent && filterContent.style.display === 'none') {
                            filterContent.style.display = 'block';
                            if (filterChevron) {
                                filterChevron.style.transform = 'rotate(180deg)';
                            }
                        }
                    }
                }
            },
            {
                element: '[wire\\:model\\.live\\.debounce\\.500ms="search"]',
                popover: {
                    title: 'Smart Search',
                    description: 'Search invoices by invoice number (e.g., "INV-0001") or by period (e.g., "December 2025"). Results update automatically as you type!',
                    side: 'bottom'
                }
            },
            {
                element: '[wire\\:model\\.live="statusFilter"]',
                popover: {
                    title: 'Status Filter',
                    description: 'Filter by invoice status: Draft (incomplete), Pending Payment (awaiting payment), Paid (completed), or Overdue (past deadline with 8% penalty applied).',
                    side: 'bottom'
                }
            },
            {
                element: '[wire\\:model\\.live="year"]',
                popover: {
                    title: 'Year Filter',
                    description: 'View invoices from different years. The dropdown shows all years where you have invoice records.',
                    side: 'bottom'
                }
            },
            {
                element: '[wire\\:click="resetFilters"]',
                popover: {
                    title: 'Clear Filters',
                    description: 'Click "Clear" to reset all search and filter options back to default. Active filters are shown as badges above this section.',
                    side: 'left'
                }
            },
            {
                element: '#all-invoices-table',
                popover: {
                    title: 'All Invoices Table',
                    description: 'This table displays all your invoices with complete information: Invoice Number, Period, Workers count, Grand Total, Issue Date, Due Date, Status badges, and Action buttons. All columns are sortable!',
                    side: 'top'
                }
            },
            {
                element: '#all-invoices-table',
                popover: {
                    title: 'Sortable Columns',
                    description: 'Click any column header (Invoice #, Period, Workers, Amount, Issue Date, Due Date, Status) to sort by that column. Click again to reverse the sort order.',
                    side: 'top'
                }
            },
            {
                element: '#all-invoices-table',
                popover: {
                    title: '⚠️ Payment Deadlines',
                    description: 'Pay close attention to the Due Date column! Overdue invoices show in red with an "Overdue" badge. Late payments automatically incur an 8% penalty on top of the original amount.',
                    side: 'top'
                }
            },
            {
                element: '#all-invoices-table',
                popover: {
                    title: 'Invoice Actions Menu',
                    description: 'Each invoice row has an actions menu (three dots) in the last column. Available actions depend on status: View Invoice, Download Invoice, Download Breakdown, or Pay Now for unpaid invoices.',
                    side: 'top'
                }
            },
            {
                element: '#all-invoices-table',
                popover: {
                    title: 'Payment Process',
                    description: 'For approved/pending invoices, click the actions menu and select "Pay Now". You\'ll be redirected to Billplz payment gateway where you can pay securely using Online Banking (FPX).',
                    side: 'top'
                }
            },
            {
                element: '#all-invoices-table',
                popover: {
                    title: 'Download Options',
                    description: 'After payment, you can download: Pro Forma Invoice (before payment) and Tax Invoice/Receipt (after payment). Both documents are official and can be used for accounting purposes.',
                    side: 'top'
                }
            },
            {
                element: '#tutorial-button',
                popover: {
                    title: 'Quick Tips Recap',
                    description: 'Remember: Check invoices regularly, pay before the deadline to avoid 8% penalty, download receipts for records. Click tutorial button anytime to replay! 💡',
                    side: 'bottom'
                }
            }
        ]
    }
};

/**
 * Start tutorial for a specific page
 */
window.startTutorial = function(page = 'dashboard') {
    const config = tutorialConfigs[page];

    if (!config) {
        console.warn(`No tutorial configuration found for page: ${page}`);
        return;
    }

    // Only keep steps whose target element is actually on the page. Some items are
    // conditional (e.g. the FAQ sidebar link only appears when a FAQ document is uploaded),
    // so this prevents the tour from breaking when an element is missing.
    const steps = config.steps.filter(step => {
        if (!step.element || step.element === 'body') {
            return true;
        }
        return document.querySelector(step.element) !== null;
    });

    const driverObj = driver({
        showProgress: true,
        showButtons: ['next', 'previous', 'close'],
        progressText: '{{current}} of {{total}}',
        nextBtnText: 'Next',
        prevBtnText: 'Previous',
        doneBtnText: 'Finish',
        popoverClass: 'driver-popover-custom',
        steps: steps,
        onDestroyed: () => {
            // Mark tutorial as completed for this specific page
            fetch('/tutorial/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ page: page })
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      console.log(`Tutorial for ${page} marked as completed`);
                  }
              })
              .catch(error => console.warn('Failed to mark tutorial as completed:', error));
        }
    });

    driverObj.drive();
};

// Backward compatibility - keep old function name for dashboard
window.startClientTutorial = function() {
    window.startTutorial('dashboard');
};
