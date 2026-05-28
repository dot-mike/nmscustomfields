<?php

namespace DotMike\NmsCustomFields\Hooks;

use App\Plugins\Hooks\DeviceOverviewHook;

class DeviceOverview extends DeviceOverviewHook
{
    public string $view = 'nmscustomfields::device.overview';

    public function data($device): array
    {
        return [
            'device' => $device,
            'customFields' => $device->customFieldDevices()->with('customField')->get(),
        ];
    }
}
