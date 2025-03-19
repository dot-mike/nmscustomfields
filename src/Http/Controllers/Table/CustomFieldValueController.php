<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Table;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use App\Models\Device;
use App\Http\Controllers\Table\TableController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CustomFieldValueController extends TableController
{
    /**
     * Defines the base query for this resource
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    protected function baseQuery($request)
    {
        return CustomFieldDevice::with(['device', 'customFieldValue']);
    }

    /**
     * Apply the search phrase to the query
     *
     * @param  string  $search
     * @param  Builder  $query
     * @param  array  $fields
     * @return Builder
     */
    protected function search($search, $query, $fields)
    {
        if ($search) {
            $query->where(function ($subquery) use ($search) {
                $subquery->whereHas('device', function ($deviceQuery) use ($search) {
                    $deviceQuery->where('hostname', 'like', '%' . $search . '%')
                        ->orWhere('sysName', 'like', '%' . $search . '%');
                })
                    ->orWhereHas('customFieldValue', function ($valueQuery) use ($search) {
                        $valueQuery->where('value', 'like', '%' . $search . '%');
                    });
            });

            $query->distinct();
        }

        return $query;
    }

    /**
     * Define searchable columns for the controller
     *
     * @param Request $request
     * @return array
     */
    protected function searchFields(Request $request)
    {
        // This will be ignored since we're overriding the search method
        // But we keep it for compatibility with the parent class
        return [];
    }

    /**
     * Define filterable columns for the controller
     *
     * @return array
     */
    protected function filterFields(Request $request)
    {
        return [
            'custom_field_id',
            'device_id',
        ];
    }

    /**
     * Format an individual model for the response
     *
     * @param CustomFieldDevice $model
     * @return array
     */
    public function formatItem($model)
    {
        return [
            'device_id' => $model->device_id,
            'hostname' => $model->device->hostname,
            'sysName' => $model->device->sysName,
            'custom_field_id' => $model->custom_field_id,
            'custom_field_value' => $model->customFieldValue->value,
            'custom_field_value_id' => $model->customFieldValue->id,
        ];
    }

    /**
     * Sort the query by request parameters
     *
     * @param Request $request
     * @param Builder $query
     * @return Builder
     */
    protected function sort($request, $query)
    {
        if (empty($request->get('sort'))) {
            return $query;
        }

        $joinTables = [];

        foreach ($request->get('sort') as $column => $direction) {
            switch ($column) {
                case 'hostname':
                case 'sysName':
                    if (!in_array('devices', $joinTables)) {
                        $query->leftJoin('devices', 'custom_field_device.device_id', '=', 'devices.device_id');
                        $joinTables[] = 'devices';
                    }
                    $query->orderBy("devices.$column", $direction);
                    break;

                case 'custom_field_value':
                    if (!in_array('custom_field_values', $joinTables)) {
                        $query->leftJoin('custom_field_values', 'custom_field_device.id', '=', 'custom_field_values.custom_field_device_id');
                        $joinTables[] = 'custom_field_values';
                    }
                    $query->orderBy('custom_field_values.value', $direction);
                    break;

                default:
                    $query->orderBy("custom_field_device.$column", $direction);
                    break;
            }
        }

        $query->select('custom_field_device.*')->distinct();

        return $query;
    }
}
