<?php

namespace DotMike\NmsCustomFields\Models;

use App\Models\Device;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CustomFieldDevice extends Pivot
{
    protected $guarded = ['id'];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id', 'id');
    }

    public function customFieldValue()
    {
        return $this->hasOne(CustomFieldValue::class, 'custom_field_device_id', 'id');
    }
}
