<?php

namespace DotMike\NmsCustomFields\Hooks;

use DotMike\NmsCustomFields\Models\CustomField;

use App\Plugins\Hooks\DeviceOverviewHook;

class DeviceHook extends DeviceOverviewHook
{
    public string $view = 'nmscustomfields::device.overview';

    public function data($device): array
    {

        $customFields = $device->customFieldValuesWithNames()->get();

        return [
            'device' => $device,
            'customFields' => $customFields,
        ];
    }
}
