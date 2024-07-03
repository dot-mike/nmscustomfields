<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Table;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;

use App\Models\Device;
use App\Http\Controllers\Table\TableController;

use App\Models\Port;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LibreNMS\Util\Number;
use LibreNMS\Util\Rewrite;
use LibreNMS\Util\Url;

class CustomFieldValueController extends TableController
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
        $query = CustomFieldDevice::with('device', 'customFieldValue')
            ->when($request->input('custom_field_id'), function ($query, $custom_field_id) {
                return $query->where('custom_field_id', $custom_field_id);
            })
            ->when($request->input('device_id'), function ($query, $device_id) {
                return $query->where('device_id', $device_id);
            });
        // if we have searchPhrase then we need to filter the results on device hostname, custom field value
        if ($request->input('searchPhrase')) {
            $searchPhrase = '%' . $request->input('searchPhrase') . '%';

            $query->whereHas('device', function ($query) use ($searchPhrase) {
                $query->where('hostname', 'like', $searchPhrase);
            })->orWhereHas('customFieldValue', function ($query) use ($searchPhrase) {
                $query->where(
                    'value',
                    'like',
                    $searchPhrase
                );
            });
        }
        return $query;
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator&\Countable  $paginator
     * @return \Illuminate\Http\JsonResponse
     */
    protected function formatResponse($paginator)
    {
        $customfields = collect($paginator->items())->map(function ($item) {
            return [
                'device_id' => $item->device_id,
                'hostname' => $item->device->hostname,
                'custom_field_id' => $item->custom_field_id,
                'custom_field_value' => $item->customFieldValue->value,
                'custom_field_value_id' => $item->customFieldValue->id,
            ];
        });

        return response()->json([
            'current' => $paginator->currentPage(),
            'rowCount' => $paginator->count(),
            'rows' => $customfields,
            'total' => $paginator->total(),
        ]);
    }
}
