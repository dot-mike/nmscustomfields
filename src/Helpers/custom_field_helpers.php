<?php

use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use App\Models\Device;

/**
 * Get the custom field value by field name and device.
 *
 * @param  \App\Models\Device  $device
 * @param  string  $fieldName
 * @return string|null
 */
function get_custom_field_value(Device $device, string $fieldName)
{
    $cfd = CustomFieldDevice::query()
        ->whereHas('customField', fn ($q) => $q->where('name', $fieldName))
        ->where('device_id', $device->device_id)
        ->first();

    return $cfd ? $cfd->value : null;
}
