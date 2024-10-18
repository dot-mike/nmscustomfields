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
    // Find the custom field device record associated with the device and field name
    $customFieldDevice = CustomFieldDevice::whereHas('customField', function ($query) use ($fieldName) {
        $query->where('name', $fieldName);
    })->where('device_id', $device->device_id)->first();

    // Return the value if the custom field device exists
    return $customFieldDevice && $customFieldDevice->customFieldValue
        ? $customFieldDevice->customFieldValue->value
        : null;
}
