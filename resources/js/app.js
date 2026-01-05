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
                    description: 'Here you can see your current month\'s payroll summary, outstanding balance, and year-to-date payments at a glance.',
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
                    title: 'Timesheet Submission Tutorial',
                    description: 'Learn how to verify and submit your monthly payroll in just a few simple steps!',
                    side: 'center',
                    align: 'center'
                }
            },
            {
                element: '#timesheet-stats',
                popover: {
                    title: 'Submission Overview',
                    description: 'Quick summary of your payroll status: total submissions this month, how many are paid, pending payment, and workers not yet submitted.',
                    side: 'bottom'
                }
            },
            {
                element: '#current-period-info',
                popover: {
                    title: 'Current Period & Deadline',
                    description: 'Shows the current payroll period and payment deadline. Pay attention to the deadline to avoid late payment penalties (8%)!',
                    side: 'bottom'
                }
            },
            {
                element: '#worker-verification-table',
                popover: {
                    title: 'Worker Verification Table',
                    description: 'This table shows all your workers with their basic salary, OT hours, and transactions already filled in from the OT Entry page. Your job is to verify the information is correct.',
                    side: 'top'
                }
            },
            {
                element: '#worker-verification-table',
                popover: {
                    title: 'Select Workers to Submit',
                    description: 'Use the checkboxes to select which workers to include in this submission. You can select all workers or only specific ones. The "Select All" checkbox at the top selects everyone.',
                    side: 'top'
                }
            },
            {
                element: '#worker-verification-table',
                popover: {
                    title: 'Pre-filled Data',
                    description: 'Basic Salary is set automatically. OT Hours and Transactions were entered in the OT & Transaction Entry page (available 1st-15th of each month). Everything is ready - you just verify and submit!',
                    side: 'top'
                }
            },
            {
                element: '#submission-actions',
                popover: {
                    title: 'Submit Your Payroll',
                    description: 'After selecting workers and verifying the data: Click "Save as Draft" to save for later, or "Submit" to send for admin review. Once submitted, an invoice will be generated.',
                    side: 'top'
                }
            },
            {
                element: '#submission-history',
                popover: {
                    title: 'Submission History',
                    description: 'View all your past payroll submissions, their status (Draft, Pending Payment, Paid), and access invoices or receipts.',
                    side: 'top'
                }
            },
            {
                element: '#tutorial-button',
                popover: {
                    title: 'Quick Workflow Recap',
                    description: 'Remember: 1) Enter OT & Transactions (1st-15th), 2) Come here to verify data, 3) Select workers, 4) Submit! Click tutorial button anytime to replay.',
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
                    description: 'Learn how to add overtime hours and transactions for your workers efficiently.',
                    side: 'center',
                    align: 'center'
                }
            },
            {
                element: '#entry-window-status',
                popover: {
                    title: 'Entry Window Status',
                    description: 'OT entries can only be made between the 1st and 15th of each month. This shows the current window status and days remaining.',
                    side: 'bottom'
                }
            },
            {
                element: '#download-template-btn',
                popover: {
                    title: 'Download Template',
                    description: 'First, download the Excel template by clicking this button. The template shows the exact format needed for bulk importing OT hours and transactions.',
                    side: 'bottom'
                }
            },
            {
                element: '#import-file-btn',
                popover: {
                    title: 'Import from File',
                    description: 'After filling the template with your data, click "Import from File" to upload it. The system supports Excel (.xlsx, .xls) and CSV files up to 2MB.',
                    side: 'bottom'
                }
            },
            {
                element: '#ot-entry-table',
                popover: {
                    title: 'OT Entry Table',
                    description: 'Alternatively, you can manually enter OT hours here. The table shows all your workers and you can enter: Weekday OT (1.5x rate), Rest Day OT (2x rate), and Public Holiday OT (3x rate).',
                    side: 'top'
                }
            },
            {
                element: '#ot-entry-table',
                popover: {
                    title: 'Transaction Management',
                    description: 'Each worker has a "Manage" button to add transactions like advances, deductions, allowances, or no-pay leave (NPL). These will be calculated automatically in the payroll.',
                    side: 'top'
                }
            },
            {
                element: '#ot-entry-actions',
                popover: {
                    title: 'Saving Your Work',
                    description: 'After entering OT hours and transactions, you have two options at the bottom of the table. Let\'s understand what each button does.',
                    side: 'top'
                }
            },
            {
                element: '#save-draft-btn',
                popover: {
                    title: '💾 Save Draft Button',
                    description: 'Click "Save Draft" to save your OT entries without finalizing them. You can come back anytime before the 15th to edit, add more data, or delete entries. Draft entries are NOT included in payroll yet.',
                    side: 'top'
                }
            },
            {
                element: '#submit-entries-btn',
                popover: {
                    title: '🚀 Submit All Entries Button',
                    description: 'Click "Submit All Entries" when you\'re done entering all OT and transactions. This LOCKS all entries and they will be automatically included in your next month\'s payroll submission. Once submitted, you CANNOT edit them!',
                    side: 'top'
                }
            },
            {
                element: '#ot-entry-table',
                popover: {
                    title: '⚠️ Important Reminder',
                    description: 'Remember: Save Draft = temporary (can edit later), Submit = final and locked. After the 15th of the month, all entries are automatically locked, so make sure to submit before then!',
                    side: 'top'
                }
            },
            {
                element: '#tutorial-button',
                popover: {
                    title: 'Quick Workflow Recap',
                    description: '1) Enter OT hours manually OR import from file, 2) Add transactions using "Manage" button, 3) Save Draft (optional), 4) Submit All Entries when ready. Click tutorial button anytime to replay!',
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
                    description: 'Search workers by name or passport number. The search updates automatically as you type (with a 500ms delay to avoid lag). Very useful for quickly finding specific workers!',
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

    const driverObj = driver({
        showProgress: true,
        showButtons: ['next', 'previous', 'close'],
        progressText: '{{current}} of {{total}}',
        nextBtnText: 'Next',
        prevBtnText: 'Previous',
        doneBtnText: 'Finish',
        popoverClass: 'driver-popover-custom',
        steps: config.steps,
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
