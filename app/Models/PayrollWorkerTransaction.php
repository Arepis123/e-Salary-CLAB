<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollWorkerTransaction extends Model
{
    protected $fillable = [
        'payroll_worker_id',
        'type',
        'description',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the payroll worker this transaction belongs to
     */
    public function payrollWorker()
    {
        return $this->belongsTo(PayrollWorker::class);
    }
}
