<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Table;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use DotMike\NmsCustomFields\Models\CustomFieldValue;


use App\Http\Controllers\Table\TableController;
use App\Models\Device;
use App\Models\Port;

use LibreNMS\Util\Number;
use LibreNMS\Util\Rewrite;
use LibreNMS\Util\Url;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomFieldController extends TableController
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

    /**
     * Defines the base query for this resource
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    protected function baseQuery($request)
    {
        // find all custom fields with a value for this device
        // custom_field_device holds the relationship between custom_field and device
        // custom_field_values holds the value for the custom field and relationship to custom_field_device with custom_field_device_id

        $device_id = $request->input('device_id');
        $query = CustomFieldValue::whereHas('customFieldDevice', function ($query) use ($device_id) {
            $query->where('device_id', $device_id);
        })
            ->with(['customFieldDevice.customField', 'customFieldDevice.device' => function ($query) {
                $query->select('device_id', 'hostname', 'sysName');
            }]);
        return $query;
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator&\Countable  $paginator
     * @return \Illuminate\Http\JsonResponse
     */
    protected function formatResponse($paginator)
    {

        // paginator contains a multi-dimensional array that contains device info, nested custom field info, and nested custom field value info
        // we need to flatten this to a single array for the table
        $rows = collect($paginator->items())->map(function ($item) {
            return [
                'custom_field_value_id' => $item->id,
                'custom_field_id' => $item->customFieldDevice->customField->id,
                'custom_field_name' => $item->customFieldDevice->customField->name,
                'custom_field_value' => $item->value,
                'device_id' => $item->customFieldDevice->device->device_id,
                'device_hostname' => $item->customFieldDevice->device->hostname,
            ];
        });

        return response()->json([
            'current' => $paginator->currentPage(),
            'rowCount' => $paginator->count(),
            'rows' => $rows,
            'total' => $paginator->total(),
        ]);
    }
}
