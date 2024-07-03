<?php

namespace DotMike\NmsCustomFields\Models;

use App\Models\Device;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    protected $fillable = ['name', 'type'];

    public function devices()
    {
        return $this->belongsToMany(Device::class, 'custom_field_device', 'custom_field_id', 'device_id')
            ->withPivot('id as custom_field_device_id')
            ->withTimestamps();
    }
}
