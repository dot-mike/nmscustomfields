<?php

namespace DotMike\NmsCustomFields\Models;

use App\Models\Device;
use Illuminate\Database\Eloquent\Model;

/**
 * First-class entity, not a junction-only pivot. Has its own autoincrement `id`,
 * is route-model bound, and is accessed via Device::hasMany. We do NOT extend
 * Pivot — Pivot's defaults ($incrementing = false, $timestamps = false) would
 * break PK round-trip and silently stop writing timestamps once we move off
 * BelongsToMany::attach() to save()/updateOrCreate().
 */
class CustomFieldDevice extends Model
{
    protected $table = 'custom_field_device';

    protected $guarded = ['id'];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id', 'id');
    }

    /**
     * Read-side: return the typed value from whichever column is populated.
     * Use !== null — integer 0 is falsy but a legitimate stored value.
     */
    public function getValueAttribute()
    {
        $int = $this->attributes['value_int'] ?? null;
        if ($int !== null) {
            return (int) $int;
        }
        return $this->attributes['value_text'] ?? null;
    }

    /**
     * Write-side: route to value_int when the field's type is integer, else value_text.
     * Requires custom_field_id already set on the model. Eloquent fill() preserves
     * array order, and updateOrCreate sets criteria-array attributes first.
     */
    public function setValueAttribute(mixed $v): void
    {
        $cfId = $this->attributes['custom_field_id'] ?? null;
        if (! $cfId) {
            throw new \LogicException('CustomFieldDevice::setValueAttribute requires custom_field_id to be set first.');
        }

        $type = CustomField::query()->whereKey($cfId)->value('type') ?? 'text';

        foreach (self::columnsFor($type, $v) as $col => $val) {
            $this->attributes[$col] = $val;
        }
    }

    /**
     * Pure mapping from (type, value) → column array. Bulk callers use this directly
     * to avoid one CustomField lookup per row; the mutator above delegates here.
     * Unknown type falls back to text so callers never get a half-built array.
     */
    public static function columnsFor(string $type, mixed $value): array
    {
        if ($type === 'integer') {
            return [
                'value_int'  => $value === null ? null : (int) $value,
                'value_text' => null,
            ];
        }
        return [
            'value_text' => $value === null ? null : (string) $value,
            'value_int'  => null,
        ];
    }
}
