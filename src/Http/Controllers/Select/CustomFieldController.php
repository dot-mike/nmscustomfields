<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Select;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;

use App\Http\Controllers\Select\SelectController;

class CustomFieldController extends SelectController
{
    protected function rules()
    {
        return [];
    }

    protected function filterFields($request)
    {
        return [];
    }

    protected function sortFields($request)
    {
        return [];
    }

    protected function baseQuery($request)
    {
        $query = CustomField::select('id', 'name');

        $filter = $request->input('filter', 'all');
        $device = $request->input('device');
        $term = $request->input('term');

        if ($device && is_numeric($device)) {
            if ($filter === 'assigned') {
                $query->whereHas('devices', function ($query) use ($device) {
                    $query->where('devices.device_id', $device);
                });
            } elseif ($filter === 'unassigned') {
                $query->whereDoesntHave('devices', function ($query) use ($device) {
                    $query->where('devices.device_id', $device);
                });
            }
        }

        if ($term) {
            $query->where('name', 'like', '%' . $term . '%');
        }

        return $query;
    }
}
