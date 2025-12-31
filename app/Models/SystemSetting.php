<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by',
    ];

    /**
     * Get the user who last updated this setting
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get a setting value by key
     *
     * @param  string  $key  The setting key
     * @param  mixed  $default  Default value if setting not found
     * @return mixed The decoded JSON value or default
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        return $setting ? json_decode($setting->value, true) : $default;
    }

    /**
     * Set a setting value by key
     *
     * @param  string  $key  The setting key
     * @param  mixed  $value  The value to store (will be JSON encoded)
     * @param  string|null  $description  Optional description
     */
    public static function set(string $key, $value, ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'description' => $description,
                'updated_by' => auth()->id(),
            ]
        );
    }
}
