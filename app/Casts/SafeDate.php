<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Date cast that tolerates the junk dates stored by the legacy worker_db.
 *
 * MariaDB happily hands back values such as '0000-00-00' or '1970-00-00'.
 * Laravel's built-in date cast turns those into nonsense Carbon instances
 * (e.g. -0001-11-30), which then leak into the client and admin screens.
 * This cast returns null for them instead, so the existing `@if($date)`
 * checks in the views fall through to the '-' placeholder.
 *
 * Usage:
 *   'wkr_passexp'     => SafeDate::class,
 *   'wkr_createddate' => SafeDate::class.':Y-m-d H:i:s',
 */
class SafeDate implements CastsAttributes
{
    public function __construct(protected string $format = 'Y-m-d') {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        $date = $this->toCarbon($value);

        if ($date === null) {
            return null;
        }

        return $this->format === 'Y-m-d' ? $date->startOfDay() : $date;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->toCarbon($value)?->format($this->format);
    }

    /**
     * Convert a raw value to Carbon, or null when it is missing or invalid.
     */
    protected function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value) && ! is_string($value)) {
            return Carbon::createFromTimestamp($value);
        }

        $value = trim((string) $value);

        if ($value === '' || ! $this->isRealDate($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Reject zeroed-out dates like '0000-00-00' and '1970-00-00'.
     */
    protected function isRealDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            // Not a Y-m-d string; let Carbon decide.
            return true;
        }

        [, $year, $month, $day] = array_map('intval', $m);

        return $year > 0 && checkdate($month, $day, $year);
    }
}
