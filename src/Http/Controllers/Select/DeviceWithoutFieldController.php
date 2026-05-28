<?php

namespace DotMike\NmsCustomFields\Http\Controllers\Select;

use App\Http\Controllers\Select\SelectController;
use App\Models\Device;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * @extends SelectController<Device>
 */
class DeviceWithoutFieldController extends SelectController
{
    protected function rules(): array
    {
        return [
            'custom_field_id' => 'required|exists:custom_fields,id',
        ];
    }

    protected function searchFields($request): array
    {
        return ['hostname', 'sysName'];
    }

    protected function baseQuery(Request $request): EloquentBuilder|Builder
    {
        $customFieldId = (int) $request->input('custom_field_id');

        return Device::hasAccess($request->user())
            ->select(['device_id', 'hostname', 'sysName', 'display', 'icon'])
            ->whereNotIn('device_id', function ($query) use ($customFieldId) {
                $query->select('device_id')
                    ->from((new CustomFieldDevice())->getTable())
                    ->where('custom_field_id', $customFieldId);
            })
            ->orderBy('hostname');
    }

    /**
     * @param  Device  $model
     * @return array{id: int|string, text: string, icon?: string}
     */
    public function formatItem(Model $model): array
    {
        /** @var Device $model */
        return [
            'id' => $model->device_id,
            'text' => $model->displayName(),
            'icon' => $model->icon,
        ];
    }
}
