<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Table;

use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use App\Models\Device;
use App\Http\Controllers\Table\TableController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CustomFieldValueController extends TableController
{
    protected ?string $model = CustomFieldDevice::class;

    protected function baseQuery(Request $request): Builder|\Illuminate\Database\Query\Builder
    {
        return CustomFieldDevice::with(['device', 'customField']);
    }

    protected function search(?string $search, Builder $query, array $fields): Builder
    {
        if (! $search) {
            return $query;
        }

        // Whole-word match by default
        // Use * as a wildcard for substring search
        if (str_contains($search, '*')) {
            $op = 'like';
            $needle = str_replace(['%', '_', '*'], ['\%', '\_', '%'], $search);
        } else {
            $op = 'regexp';
            $needle = '\\b' . preg_quote($search) . '\\b';
        }

        return $query->where(function ($q) use ($op, $needle) {
            $q->whereHas('device', function ($dq) use ($op, $needle) {
                $dq->where('hostname', $op, $needle)
                   ->orWhere('sysName', $op, $needle);
            })
            ->orWhere('custom_field_device.value_text', $op, $needle)
            ->orWhereRaw('CAST(custom_field_device.value_int AS CHAR) ' . strtoupper($op) . ' ?', [$needle]);
        })->distinct();
    }

    protected function searchFields(Request $request): array
    {
        // This will be ignored since we're overriding the search method
        // But we keep it for compatibility with the parent class
        return [];
    }

    protected function filterFields(Request $request): array
    {
        return [
            'custom_field_id',
            'device_id',
        ];
    }

    public function formatItem(Model $model): Model|array|Collection
    {
        return [
            'device_id'              => $model->device_id,
            'hostname'               => $model->device->hostname,
            'sysName'                => $model->device->sysName,
            'custom_field_id'        => $model->custom_field_id,
            'custom_field_value'     => $model->value, // accessor: string|null
            'custom_field_device_id' => $model->id,
        ];
    }

    protected function sort(Request $request, Builder $query): Builder
    {
        if (empty($request->get('sort'))) {
            return $query;
        }

        $joinTables = [];

        foreach ($request->get('sort') as $column => $direction) {
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

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
                    // Order by COALESCE(value_text, CAST(value_int AS CHAR)).
                    $query->orderByRaw("COALESCE(custom_field_device.value_text, CAST(custom_field_device.value_int AS CHAR)) $direction");
                    break;

                default:
                    $query->orderBy("custom_field_device.$column", $direction);
                    break;
            }
        }

        $query->select('custom_field_device.*')->distinct();

        return $query;
    }

    protected function getExportHeaders(): array
    {
        return [
            'Device ID',
            'Hostname',
            'System Name',
            'Custom Field ID',
            'Custom Field Value',
        ];
    }

    protected function formatExportRow(Model $item): array
    {
        return [
            $item->device_id,
            $item->device->hostname,
            $item->device->sysName,
            $item->custom_field_id,
            $item->value,
        ];
    }
}
