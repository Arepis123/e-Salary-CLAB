<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// ============================================================================
// PUBLIC ROUTES (No authentication required)
// ============================================================================

// CSRF Token Refresh Endpoint
Route::get('csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('web');

// Preview of the Cloudflare-style "payments disabled" block page (local only).
// Visit /preview/payment-blocked in the browser to see exactly what a blocked
// client sees. Not registered in production.
if (app()->environment('local')) {
    Route::get('preview/payment-blocked', function (\Illuminate\Http\Request $request) {
        return response()->view('client.payment-blocked', [
            'title' => 'Internal server error',
            'errorCode' => 500,
            'time' => now()->utc()->format('Y-m-d H:i:s').' UTC',
            'host' => $request->getHost(),
            'cloudflareLocation' => 'Kuala Lumpur',
            'rayId' => strtolower(bin2hex(random_bytes(8))).'-KUL',
        ], 403);
    })->name('preview.payment-blocked');
}

// ============================================================================
// EXTERNAL API ROUTES (No CSRF, authenticated via X-Payslip-Token header)
// ============================================================================
Route::post('api/payslip/notify', [\App\Http\Controllers\Api\PayslipNotifyController::class, 'notify'])
    ->name('api.payslip.notify')
    ->middleware('api')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('api/payslip/contractors-by-ic', [\App\Http\Controllers\Api\PayslipNotifyController::class, 'contractorsByIc'])
    ->name('api.payslip.contractors-by-ic')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('user-manual', \App\Livewire\UserManual::class)->name('user-manual');

// ============================================================================
// UNIFIED ROUTES - Role-agnostic URLs (Security Enhancement)
// ============================================================================
// Routes without /admin or /client prefixes to prevent role enumeration

Route::middleware(['auth', 'verified'])->group(function () {

    // Tutorial completion route - accepts page parameter
    Route::post('tutorial/complete', function (\Illuminate\Http\Request $request) {
        $page = $request->input('page', 'dashboard');
        auth()->user()->markTutorialCompleted($page);

        return response()->json(['success' => true, 'page' => $page]);
    })->name('tutorial.complete');

    // Dashboard - Unified route that dispatches based on role
    Route::get('dashboard', function (\Illuminate\Http\Request $request) {
        if (in_array(auth()->user()->role, ['admin', 'super_admin', 'finance'])) {
            return view('admin.dashboard');
        } elseif (auth()->user()->role === 'management') {
            return view('management.dashboard');
        } elseif (auth()->user()->role === 'client') {
            return view('client.dashboard');
        }
        abort(403);
    })->name('dashboard');

    // Workers - Route to Livewire component for all roles
    Route::get('workers', function (\Illuminate\Http\Request $request) {
        // Admin and super admin use admin Livewire component
        if (in_array(auth()->user()->role, ['admin', 'super_admin'])) {
            return view('admin.workers-live');
        }

        // Client uses client Livewire component
        return view('client.workers-live');
    })->name('workers');

    // Worker Detail - Unified route
    Route::get('workers/{worker}', function (\Illuminate\Http\Request $request, $worker) {
        return match (auth()->user()->role) {
            'admin', 'super_admin' => app(\App\Http\Controllers\Admin\WorkerController::class)->show($worker),
            'client' => app(\App\Http\Controllers\Client\WorkersController::class)->show($request, $worker),
            default => abort(403)
        };
    })->name('workers.show');

    // Invoices - Unified route redirects to role-specific Livewire pages (real-time search & filtering)
    Route::get('invoices', function () {
        return match (auth()->user()->role) {
            'admin', 'super_admin', 'finance' => redirect()->route('invoices.admin'),
            'client' => redirect()->route('invoices.client'),
            default => abort(403)
        };
    })->name('invoices');

    // Invoice Detail - Unified route
    Route::get('invoices/{id}', function ($id) {
        return match (auth()->user()->role) {
            'admin', 'super_admin', 'finance' => app(\App\Http\Controllers\Admin\InvoiceController::class)->show($id),
            'client' => app(\App\Http\Controllers\Client\InvoiceController::class)->show($id),
            default => abort(403)
        };
    })->name('invoices.show');

    // Invoice Download - Unified route (Pro Forma Invoice)
    Route::get('invoices/{id}/download', function ($id) {
        return match (auth()->user()->role) {
            'admin', 'super_admin', 'finance' => app(\App\Http\Controllers\Admin\InvoiceController::class)->download($id),
            'client' => app(\App\Http\Controllers\Client\InvoiceController::class)->download($id),
            default => abort(403)
        };
    })->name('invoices.download');

    // Tax Invoice Download - Unified route (Only for paid invoices)
    Route::get('invoices/{id}/download-tax-invoice', function ($id) {
        return match (auth()->user()->role) {
            'admin', 'super_admin', 'finance' => app(\App\Http\Controllers\Admin\InvoiceController::class)->downloadTaxInvoice($id),
            'client' => app(\App\Http\Controllers\Client\InvoiceController::class)->downloadTaxInvoice($id),
            default => abort(403)
        };
    })->name('invoices.download-tax');

    // Payroll Breakdown File Download - Unified route (Admin-uploaded breakdown from external system)
    Route::get('payroll/breakdown/{submission}', [\App\Http\Controllers\PayrollBreakdownController::class, 'download'])
        ->name('payroll.breakdown.download');

    // Payroll Payslip File Download - Unified route (Admin-uploaded payslip ZIP file)
    Route::get('payroll/payslip/{submission}', [\App\Http\Controllers\PayrollPayslipController::class, 'download'])
        ->name('payroll.payslip.download');

    // Uploaded Documents - Inline viewing (opens in a new tab)
    // FAQ document (linked from the client sidebar) and admin document preview
    Route::get('faq', [\App\Http\Controllers\DocumentController::class, 'faq'])->name('faq');
    // The trailing {filename} segment is ignored server-side; it only controls the browser tab title
    // when the PDF opens inline (browsers use the last URL path segment as the tab name).
    Route::get('documents/{document}/{filename?}', [\App\Http\Controllers\DocumentController::class, 'show'])->name('documents.view');

    // Signed Salary Deduction Form - inline view and download
    // The download route is declared first so "download" is not swallowed by the {filename?} segment below.
    Route::get('salary-deduction-forms/{form}/download', [\App\Http\Controllers\SalaryDeductionFormController::class, 'download'])
        ->name('salary-deduction-forms.download');
    Route::get('salary-deduction-forms/{form}/{filename?}', [\App\Http\Controllers\SalaryDeductionFormController::class, 'show'])
        ->name('salary-deduction-forms.view');

    // ========================================================================
    // ADMIN-ONLY ROUTES (No client equivalent)
    // ========================================================================

    // Payroll & Invoice routes - accessible by admin and finance
    Route::middleware(['auth', 'verified', 'roles:admin,finance'])->group(function () {
        Route::get('payroll', \App\Livewire\Admin\Salary::class)->name('payroll');
        Route::get('payroll/{id}', \App\Livewire\Admin\SalaryDetail::class)->name('payroll.detail');
        Route::get('ot-transactions', \App\Livewire\Admin\OtTransactions::class)->name('ot-transactions');
        Route::get('invoices-admin', \App\Livewire\Admin\Invoices::class)->name('invoices.admin');
    });

    // Admin-only routes (no finance access)
    Route::middleware('role:admin')->group(function () {
        Route::get('missing-submissions', \App\Livewire\Admin\MissingSubmissions::class)->name('missing-submissions');
        Route::get('missing-submissions/{clabNo}', \App\Livewire\Admin\MissingSubmissionsDetail::class)->name('missing-submissions.detail');
        Route::get('contractors', \App\Livewire\Admin\Contractors::class)->name('contractors');
        Route::get('contractors/{clabNo}', \App\Livewire\Admin\ContractorDetail::class)->name('contractors.detail');
        Route::get('notifications', \App\Livewire\Admin\Notifications::class)->name('notifications');
        Route::get('email-logs', \App\Livewire\Admin\EmailLogs::class)->name('email-logs');
        Route::get('news', \App\Livewire\Admin\NewsManagement::class)->name('news');
    });

    // ========================================================================
    // SUPER ADMIN-ONLY ROUTES
    // ========================================================================

    // Report route - accessible by super_admin, admin, and finance
    Route::middleware(['auth', 'verified', 'roles:admin,finance'])->group(function () {
        Route::get('report', \App\Livewire\Admin\Report::class)->name('report');
        Route::post('report/download-receipts', [\App\Http\Controllers\TaxInvoiceController::class, 'downloadReceipts'])->name('report.download-receipts');
    });

    // Super admin only routes (no finance access)
    Route::middleware('role:super_admin')->group(function () {
        Route::get('configuration', \App\Livewire\Admin\Configuration::class)->name('configuration');
        Route::get('activity-logs', \App\Livewire\Admin\ActivityLogs::class)->name('activity-logs');
        Route::get('users', \App\Livewire\Admin\Users::class)->name('users');
    });

    // ========================================================================
    // CLIENT-ONLY ROUTES (No admin equivalent)
    // ========================================================================

    Route::middleware('role:client')->group(function () {
        Route::get('ot-entry', \App\Livewire\Client\OTEntry::class)->name('ot-entry');
        Route::get('timesheet', \App\Livewire\Client\Timesheet::class)->name('timesheet');
        Route::get('timesheet/{id}', [\App\Http\Controllers\Client\TimesheetController::class, 'show'])->name('timesheet.show');
        Route::get('timesheet/{id}/edit', \App\Livewire\Client\TimesheetEdit::class)->name('timesheet.edit');
        Route::get('payments', \App\Livewire\Client\Payments::class)->name('payments');
        Route::post('payment/{submission}', [\App\Http\Controllers\Client\PaymentController::class, 'createPayment'])->name('payment.create');
        Route::get('invoices-client', \App\Livewire\Client\Invoices::class)->name('invoices.client');
        Route::get('invoices/{id}/receipt', [\App\Http\Controllers\TaxInvoiceController::class, 'downloadSingleReceipt'])->name('invoices.receipt');
    });
});

// ============================================================================
// BACKWARD COMPATIBILITY REDIRECTS
// ============================================================================
// Old /admin and /client routes redirect to new unified routes

Route::middleware(['auth', 'verified'])->group(function () {
    // Admin redirects
    Route::redirect('/admin/dashboard', '/dashboard')->name('admin.dashboard');
    Route::redirect('/admin/worker', '/workers')->name('admin.worker');
    Route::get('/admin/workers/{worker}', fn ($worker) => redirect()->route('workers.show', $worker))->name('admin.workers.show');
    Route::redirect('/admin/salary', '/payroll')->name('admin.salary');
    Route::get('/admin/salary/{id}', fn ($id) => redirect()->route('payroll.detail', $id))->name('admin.salary.detail');
    Route::redirect('/admin/missing-submissions', '/missing-submissions')->name('admin.missing-submissions');
    Route::get('/admin/missing-submissions/{clabNo}', fn ($clabNo) => redirect()->route('missing-submissions.detail', $clabNo))->name('admin.missing-submissions.detail');
    Route::redirect('/admin/contractors', '/contractors')->name('admin.contractors');
    Route::redirect('/admin/invoices', '/invoices')->name('admin.invoices');
    Route::get('/admin/invoices/{id}', fn ($id) => redirect()->route('invoices.show', $id))->name('admin.invoices.show');
    Route::get('/admin/invoices/{id}/download', fn ($id) => redirect()->route('invoices.download', $id))->name('admin.invoices.download');
    Route::redirect('/admin/notifications', '/notifications')->name('admin.notifications');
    Route::redirect('/admin/report', '/report')->name('admin.report');
    Route::redirect('/admin/news', '/news')->name('admin.news');
    Route::redirect('/admin/configuration', '/configuration')->name('admin.configuration');
    Route::redirect('/admin/activity-logs', '/activity-logs')->name('admin.activity-logs');

    // Client redirects
    Route::redirect('/client/dashboard', '/dashboard')->name('client.dashboard');
    Route::redirect('/client/workers', '/workers')->name('client.workers');
    Route::get('/client/workers/{worker}', fn ($worker) => redirect()->route('workers.show', $worker))->name('client.workers.show');
    Route::redirect('/client/timesheet', '/timesheet')->name('client.timesheet');
    Route::get('/client/timesheet/{id}', fn ($id) => redirect()->route('timesheet.show', $id))->name('client.timesheet.show');
    Route::get('/client/timesheet/{id}/edit', fn ($id) => redirect()->route('timesheet.edit', $id))->name('client.timesheet.edit');
    Route::redirect('/client/payments', '/payments')->name('client.payments');
    Route::redirect('/client/invoices', '/invoices')->name('client.invoices');
    Route::get('/client/invoices/{id}', fn ($id) => redirect()->route('invoices.show', $id))->name('client.invoices.show');
    Route::get('/client/invoices/{id}/download', fn ($id) => redirect()->route('invoices.download', $id))->name('client.invoices.download');

    // Payment creation needs to stay as POST, not redirect
    Route::post('/client/payment/{submission}', [\App\Http\Controllers\Client\PaymentController::class, 'createPayment'])->name('client.payment.create');
});

// ============================================================================
// EXTERNAL ROUTES (Payment Gateway)
// ============================================================================
// Billplz routes (No auth/middleware required - users may have lost session)

Route::post('/billplz/callback', [\App\Http\Controllers\Client\PaymentController::class, 'callback'])->name('billplz.callback');
Route::get('/client/payment/{submission}/return', [\App\Http\Controllers\Client\PaymentController::class, 'return'])->name('client.payment.return');

// Brevo transactional email delivery webhook (secured by ?token=...)
Route::post('/webhooks/brevo/email', [\App\Http\Controllers\Webhooks\BrevoWebhookController::class, 'email'])->name('webhooks.brevo.email');

// ============================================================================
// OTHER ROUTES
// ============================================================================

Route::view('posts', 'posts')
    ->middleware(['auth', 'verified'])
    ->name('posts');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

require __DIR__.'/auth.php';
