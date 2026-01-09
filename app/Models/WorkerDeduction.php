<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerDeduction extends Model
{
    protected $fillable = [
        'deduction_template_id',
        'worker_id',
        'contractor_clab_no',
        'assigned_by',
        'assigned_at',
        'assignment_notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /**
     * Get the deduction template for this assignment
     */
    public function deductionTemplate()
    {
        return $this->belongsTo(DeductionTemplate::class);
    }

    /**
     * Get the user who assigned this deduction
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the worker from external database (READ-ONLY)
     */
    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id', 'wkr_id');
    }

    /**
     * Scope: filter by contractor
     */
    public function scopeByContractor($query, string $clabNo)
    {
        return $query->where('contractor_clab_no', $clabNo);
    }

    /**
     * Scope: filter by worker
     */
    public function scopeByWorker($query, string $workerId)
    {
        return $query->where('worker_id', $workerId);
    }

    /**
     * Scope: filter by template
     */
    public function scopeByTemplate($query, int $templateId)
    {
        return $query->where('deduction_template_id', $templateId);
    }
}
