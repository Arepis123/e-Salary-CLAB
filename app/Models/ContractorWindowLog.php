<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorWindowLog extends Model
{
    protected $fillable = [
        'contractor_clab_no',
        'action',
        'changed_by',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeForContractor($query, string $clabNo)
    {
        return $query->where('contractor_clab_no', $clabNo);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
