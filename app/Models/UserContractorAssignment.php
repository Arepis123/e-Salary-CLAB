<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Links an admin / super admin (the PIC) to a contractor they manage.
 */
class UserContractorAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'contractor_clab_no',
        'assigned_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * CLAB numbers managed by the given PIC users.
     *
     * @param  array<int>  $userIds
     * @return array<string>
     */
    public static function clabNosForUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return self::whereIn('user_id', $userIds)
            ->pluck('contractor_clab_no')
            ->unique()
            ->values()
            ->all();
    }
}
