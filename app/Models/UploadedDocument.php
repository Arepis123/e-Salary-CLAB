<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadedDocument extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer',
    ];

    /**
     * The user who uploaded this document.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to active documents only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the latest active document for a given key (e.g. 'faq').
     */
    public static function activeForKey(string $key): ?self
    {
        return static::active()
            ->where('key', $key)
            ->latest()
            ->first();
    }

    /**
     * URL-safe filename used as the last path segment when viewing inline,
     * so the browser tab shows the document title instead of "view".
     */
    public function getPublicFilenameAttribute(): string
    {
        // Keep spaces and original casing; only strip characters unsafe for filenames/URLs.
        // The browser URL-encodes spaces and decodes them back when showing the tab title.
        $name = preg_replace('/[\/\\\\:*?"<>|]+/', '', (string) $this->title);
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return ($name !== '' ? $name : 'document').'.pdf';
    }

    /**
     * Human-readable file size (e.g. "1.2 MB").
     */
    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }
}
