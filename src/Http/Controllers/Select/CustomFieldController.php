<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Select;

use DotMike\NmsCustomFields\Models\CustomField;

use App\Http\Controllers\Select\SelectController;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

class CustomFieldController extends SelectController
{
    public function formatItem(Model $model): array
    {
        return [
            'id'   => $model->id,
            'text' => $model->name,
            'type' => $model->type,
        ];
    }


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

    protected function baseQuery(Request $request): EloquentBuilder|Builder
    {
        $query = CustomField::select('id', 'name', 'type');

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
