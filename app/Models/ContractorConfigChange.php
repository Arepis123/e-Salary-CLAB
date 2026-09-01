<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail for everything that can be configured per contractor:
 * service charge / penalty exemptions, the payment lock, enabled deduction
 * templates and the OT entry window.
 */
class ContractorConfigChange extends Model
{
    public const SETTING_SERVICE_CHARGE = 'service_charge_exempt';

    public const SETTING_PENALTY = 'penalty_exempt';

    public const SETTING_PAYMENT = 'payment_enabled';

    public const SETTING_DEDUCTIONS = 'deductions';

    public const SETTING_OT_WINDOW = 'ot_window';

    protected $fillable = [
        'contractor_clab_no',
        'contractor_name',
        'setting',
        'old_value',
        'new_value',
        'description',
        'changed_by',
        'remarks',
    ];

    /**
     * Labels shown in the history table, keyed by setting.
     */
    public static function settingLabels(): array
    {
        return [
            self::SETTING_SERVICE_CHARGE => 'Service Charge',
            self::SETTING_PENALTY => 'Penalty',
            self::SETTING_PAYMENT => 'Payments',
            self::SETTING_DEDUCTIONS => 'Enabled Deductions',
            self::SETTING_OT_WINDOW => 'OT Entry Window',
        ];
    }

    /**
     * Badge colour for each setting, so the history table reads at a glance.
     */
    public static function settingColors(): array
    {
        return [
            self::SETTING_SERVICE_CHARGE => 'blue',
            self::SETTING_PENALTY => 'amber',
            self::SETTING_PAYMENT => 'purple',
            self::SETTING_DEDUCTIONS => 'zinc',
            self::SETTING_OT_WINDOW => 'green',
        ];
    }

    public function getSettingLabelAttribute(): string
    {
        return self::settingLabels()[$this->setting] ?? ucfirst(str_replace('_', ' ', $this->setting));
    }

    public function getSettingColorAttribute(): string
    {
        return self::settingColors()[$this->setting] ?? 'zinc';
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeForContractor($query, string $clabNo)
    {
        return $query->where('contractor_clab_no', $clabNo);
    }

    /**
     * Record a configuration change. Returns null when nothing actually changed,
     * so callers can pass before/after values without checking first.
     */
    public static function record(
        string $clabNo,
        string $setting,
        ?string $oldValue,
        ?string $newValue,
        ?string $contractorName = null,
        ?string $description = null,
        ?string $remarks = null,
        ?int $changedBy = null
    ): ?self {
        if ($oldValue === $newValue) {
            return null;
        }

        return self::create([
            'contractor_clab_no' => $clabNo,
            'contractor_name' => $contractorName,
            'setting' => $setting,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'changed_by' => $changedBy ?? auth()->id(),
            'remarks' => $remarks,
        ]);
    }
}
