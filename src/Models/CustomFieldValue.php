<?php

namespace DotMike\NmsCustomFields\Models;

use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    protected $guarded = ['id'];

    protected $fillable = ['custom_field_device_id', 'value'];

    public function customFieldDevice()
    {
        return $this->belongsTo(CustomFieldDevice::class, 'custom_field_device_id', 'id');
    }
}
