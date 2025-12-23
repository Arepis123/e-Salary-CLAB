<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Log an activity
     *
     * @param  string  $module  Module name (e.g., 'payment', 'timesheet', 'worker')
     * @param  string  $action  Action performed (e.g., 'created', 'updated', 'submitted')
     * @param  string  $description  Human-readable description
     * @param  Model|null  $subject  The model that was acted upon
     * @param  array|null  $oldValues  Previous state
     * @param  array|null  $newValues  New state
     * @param  array|null  $properties  Additional metadata
     */
    protected function logActivity(
        string $module,
        string $action,
        string $description,
        $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $properties = null
    ): ActivityLog {
        $user = Auth::user();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'contractor_clab_no' => $user?->contractor_clab_no,
            'user_name' => $user?->name ?? $user?->company_name,
            'user_email' => $user?->email,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'properties' => $properties,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
        ]);
    }

    /**
     * Log a payment activity
     */
    protected function logPaymentActivity(
        string $action,
        string $description,
        $payment = null,
        ?array $properties = null
    ): ActivityLog {
        return $this->logActivity(
            module: 'payment',
            action: $action,
            description: $description,
            subject: $payment,
            properties: $properties
        );
    }

    /**
     * Log a timesheet activity
     */
    protected function logTimesheetActivity(
        string $action,
        string $description,
        $timesheet = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $properties = null
    ): ActivityLog {
        return $this->logActivity(
            module: 'timesheet',
            action: $action,
            description: $description,
            subject: $timesheet,
            oldValues: $oldValues,
            newValues: $newValues,
            properties: $properties
        );
    }

    /**
     * Log a worker activity
     */
    protected function logWorkerActivity(
        string $action,
        string $description,
        $worker = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $properties = null
    ): ActivityLog {
        return $this->logActivity(
            module: 'worker',
            action: $action,
            description: $description,
            subject: $worker,
            oldValues: $oldValues,
            newValues: $newValues,
            properties: $properties
        );
    }

    /**
     * Log an invoice activity
     */
    protected function logInvoiceActivity(
        string $action,
        string $description,
        $invoice = null,
        ?array $properties = null
    ): ActivityLog {
        return $this->logActivity(
            module: 'invoice',
            action: $action,
            description: $description,
            subject: $invoice,
            properties: $properties
        );
    }

    /**
     * Log an authentication activity
     */
    protected function logAuthActivity(
        string $action,
        string $description,
        ?array $properties = null
    ): ActivityLog {
        return $this->logActivity(
            module: 'authentication',
            action: $action,
            description: $description,
            properties: $properties
        );
    }
}
