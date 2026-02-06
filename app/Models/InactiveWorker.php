<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InactiveWorker Model
 *
 * Tracks workers that have been deactivated in the payroll system.
 * Since the Worker table is in a read-only external database,
 * we use this local table to mark workers as inactive for payroll purposes.
 */
class InactiveWorker extends Model
{
    protected $fillable = [
        'worker_id',
        'worker_name',
        'worker_passport',
        'contractor_clab_no',
        'reason',
        'deactivated_by',
        'deactivated_at',
    ];

    protected $casts = [
        'deactivated_at' => 'datetime',
    ];

    /**
     * Get the user who deactivated this worker
     */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Check if a worker is inactive
     */
    public static function isInactive(string $workerId): bool
    {
        return self::where('worker_id', $workerId)->exists();
    }

    /**
     * Get all inactive worker IDs
     */
    public static function getInactiveWorkerIds(): array
    {
        return self::pluck('worker_id')->toArray();
    }

    /**
     * Deactivate a worker
     */
    public static function deactivate(
        string $workerId,
        string $workerName,
        ?string $workerPassport,
        ?string $contractorClabNo,
        ?string $reason,
        int $deactivatedBy
    ): self {
        return self::create([
            'worker_id' => $workerId,
            'worker_name' => $workerName,
            'worker_passport' => $workerPassport,
            'contractor_clab_no' => $contractorClabNo,
            'reason' => $reason,
            'deactivated_by' => $deactivatedBy,
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Reactivate a worker (remove from inactive list)
     */
    public static function reactivate(string $workerId): bool
    {
        return self::where('worker_id', $workerId)->delete() > 0;
    }
}
