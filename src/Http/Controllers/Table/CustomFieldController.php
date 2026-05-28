<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Table;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;

use App\Http\Controllers\Table\TableController;
use App\Models\Device;
use App\Models\Port;

use LibreNMS\Util\Number;
use LibreNMS\Util\Rewrite;
use LibreNMS\Util\Url;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomFieldController extends TableController
{
    protected function rules(): array
    {
        return [];
    }

    protected function filterFields(Request $request): array
    {
        return [];
    }

    protected function sortFields(Request $request): array
    {
        return [];
    }

    protected function baseQuery(Request $request): Builder|\Illuminate\Database\Query\Builder
    {
        $device_id = $request->input('device_id');
        return CustomFieldDevice::where('device_id', $device_id)
            ->with('customField');
    }

    protected function formatResponse($paginator): JsonResponse
    {
        $rows = collect($paginator->items())->map(function ($cfd) {
            return [
                'custom_field_device_id' => $cfd->id,
                'custom_field_id'        => $cfd->customField->id,
                'custom_field_name'      => $cfd->customField->name,
                'custom_field_value'     => $cfd->value, // string|null
            ];
        });

        return response()->json([
            'current'  => $paginator->currentPage(),
            'rowCount' => $paginator->count(),
            'rows'     => $rows,
            'total'    => $paginator->total(),
        ]);
    }
}
