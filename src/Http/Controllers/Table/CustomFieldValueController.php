<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Table;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use App\Models\Device;
use App\Http\Controllers\Table\TableController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomFieldValueController extends TableController
{
    protected function baseQuery(Request $request): Builder|\Illuminate\Database\Query\Builder
    {
        return CustomFieldDevice::with(['device', 'customField']);
    }

    protected function search(?string $search, Builder $query, array $fields): Builder
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('device', function ($dq) use ($search) {
                    $dq->where('hostname', 'like', '%' . $search . '%')
                       ->orWhere('sysName', 'like', '%' . $search . '%');
                })
                ->orWhere('custom_field_device.value_text', 'like', '%' . $search . '%')
                ->orWhereRaw('CAST(custom_field_device.value_int AS CHAR) LIKE ?', ['%' . $search . '%']);
            })->distinct();
        }

        return $query;
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

    public function export(Request $request): StreamedResponse
    {
        $query = $this->prepareExportQuery($request);
        $data = $query->get();

        $filenameParts = ['devicefields'];
        if ($request->has('custom_field_id') && !empty($request->get('custom_field_id'))) {
            $customField = CustomField::find($request->get('custom_field_id'));
            if ($customField) {
                $filenameParts[] = Str::slug($customField->name);
            }
        }

        $filenameParts[] = date('Y-m-d-His');
        $filename = implode('-', $filenameParts) . '.csv';

        $headers = $this->getExportHeaders();

        return $this->generateCsvResponse($data, $headers, $filename);
    }

    protected function prepareExportQuery(Request $request): Builder
    {
        $query = $this->baseQuery($request);

        foreach ($this->filterFields($request) as $field) {
            if ($request->has($field) && $request->get($field) !== '') {
                $query->where($field, $request->get($field));
            }
        }

        if ($request->has('search') && !empty($request->get('search'))) {
            $query = $this->search($request->get('search'), $query, $this->searchFields($request));
        }

        if ($request->has('sort')) {
            $query = $this->sort($request, $query);
        }

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

    protected function generateCsvResponse(Collection $data, array $headers, string $filename): StreamedResponse
    {
        return response()->stream(
            function () use ($data, $headers) {
                $output = fopen('php://output', 'w');

                fputcsv($output, $headers);

                foreach ($data as $item) {
                    fputcsv($output, $this->formatExportRow($item));
                }

                fclose($output);
            },
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ]
        );
    }
}
