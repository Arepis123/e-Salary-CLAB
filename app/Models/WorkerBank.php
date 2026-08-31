<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * WorkerBank Model
 *
 * READ-ONLY MODEL
 * This table is from the second database (worker_db) managed by another system.
 * This payroll system only reads bank data. Do not create, update, or delete records.
 */
class WorkerBank extends Model
{
    /**
     * The connection name for the model.
     * This points to the second database (worker_db)
     */
    protected $connection = 'worker_db';

    /**
     * The table associated with the model.
     */
    protected $table = 'wkr_bank';

    /**
     * The table has a created_at column but no updated_at column.
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Fixed badge colours for the banks we already know about, so the same
     * bank always reads the same colour across pages. Keys are normalised
     * names (uppercase, alphanumeric only) and are matched as a prefix, so
     * "MAYBANK BERHAD" still resolves to "MAYBANK".
     */
    protected const BANK_COLORS = [
        'MAYBANK' => 'yellow',
        'CIMB' => 'red',
        'BAYOPAY' => 'cyan',
        'PUBLICBANK' => 'blue',
        'RHB' => 'sky',
        'HONGLEONG' => 'indigo',
        'AMBANK' => 'orange',
        'BANKISLAM' => 'emerald',
        'BANKRAKYAT' => 'green',
        'BSN' => 'violet',
        'AFFIN' => 'purple',
        'OCBC' => 'rose',
        'UOB' => 'teal',
        'HSBC' => 'pink',
        'AGROBANK' => 'lime',
        'ALLIANCE' => 'fuchsia',
        'MBSB' => 'amber',
    ];

    /**
     * Palette used for banks that are not in the map above. The upstream
     * system can add banks at any time, so an unknown name is hashed onto
     * this palette — deterministic, and no code change needed.
     */
    protected const FALLBACK_COLORS = [
        'blue', 'green', 'purple', 'orange', 'teal', 'pink',
        'indigo', 'lime', 'rose', 'cyan', 'amber', 'emerald',
        'violet', 'sky', 'fuchsia', 'red', 'yellow',
    ];

    /**
     * Resolve the Flux badge colour for a bank name.
     * Blank / unknown-less names fall back to zinc.
     */
    public static function colorFor(?string $bankName): string
    {
        $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $bankName));

        if ($key === '') {
            return 'zinc';
        }

        // Longest keys first so "PUBLICBANK" wins over a shorter prefix.
        $known = self::BANK_COLORS;
        uksort($known, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($known as $name => $color) {
            if (str_starts_with($key, $name)) {
                return $color;
            }
        }

        return self::FALLBACK_COLORS[crc32($key) % count(self::FALLBACK_COLORS)];
    }

    /**
     * Get the worker these bank details belong to
     */
    public function worker()
    {
        return $this->belongsTo(Worker::class, 'wkr_id', 'wkr_id');
    }
}
