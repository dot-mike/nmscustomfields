<?php

namespace DotMike\NmsCustomFields\Http\Controllers;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;

use App\Models\Device;
use App\Models\Vminfo;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

use Gate;
use Validator;

class DeviceCustomFieldController extends Controller
{

    // Display the custom fields for a device
    // GET /device/{device}/customfields
    public function index(Request $request, Device $device)
    {
        Gate::authorize('admin');

        if ($request->expectsJson()) {
            $device->load('customFieldDevices.customField');
            $customFieldValues = $device->customFieldDevices->map(function ($cfd) {
                return [
                    'id'    => $cfd->customField->id,
                    'name'  => $cfd->customField->name,
                    'value' => $cfd->value,
                ];
            });
            return response()->json($customFieldValues);
        } else {
            $alert_class = $device->disabled ? 'alert-info' : ($device->status ? '' : 'alert-danger');
            $parent_id = Vminfo::guessFromDevice($device)->value('device_id');
            $overview_graphs = [];
            return view('nmscustomfields::device.customfields', compact('device', 'alert_class', 'parent_id', 'overview_graphs'));
        }
    }

    // Display the details of a custom field device
    // GET /device/{device}/customfields/devicefield/{customFieldDevice}
    public function show(Request $request, Device $device, CustomFieldDevice $customdevicefield)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'id'                => $customdevicefield->id,
                'custom_field_id'   => $customdevicefield->custom_field_id,
                'custom_field_name' => $customdevicefield->customField->name,
                'value'             => $customdevicefield->value,
            ]);
        }

        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }

    // show form to add custom field to device
    // GET device/{device}/customfields/devicefield/create
    public function create(Request $request, Device $device)
    {
        Gate::authorize('admin');

        $alert_class = $device->disabled ? 'alert-info' : ($device->status ? '' : 'alert-danger');
        $parent_id = Vminfo::guessFromDevice($device)->value('device_id');
        $overview_graphs = [];

        return view('nmscustomfields::device.create', compact('device', 'alert_class', 'parent_id', 'overview_graphs'));
    }

    // Add a custom field to a device
    // POST /device/{device}/customfields/devicefield
    public function store(Request $request, Device $device)
    {
        Gate::authorize('admin');

        $cfId        = $request->input('custom_field_id');
        $customField = CustomField::find($cfId);

        $rules = [
            'custom_field_id' => 'required|exists:custom_fields,id',
            'value'           => $customField?->valueRule() ?? 'required',
        ];

        $validator = Validator::make($request->all(), $rules)
            ->after($this->ensureDeviceDoesNotHaveCustomField($device, $cfId));

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->route('plugin.nmscustomfields.device.index', $device)->withErrors($validator);
        }

        CustomFieldDevice::create(array_merge(
            [
                'device_id'       => $device->device_id,
                'custom_field_id' => $cfId,
            ],
            CustomFieldDevice::columnsFor($customField->type, $request->input('value'))
        ));

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }

    // Save the custom field for a device
    // PUT /device/{device}/customfields/devicefield/{customFieldDevice}
    public function update(Request $request, Device $device, CustomFieldDevice $customdevicefield)
    {
        Gate::authorize('admin');

        $validator = Validator::make($request->all(), [
            'value' => $customdevicefield->customField->valueRule(),
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->route('plugin.nmscustomfields.device.index', $device)->withErrors($validator);
        }

        $customdevicefield->update(CustomFieldDevice::columnsFor(
            $customdevicefield->customField->type,
            $request->input('value')
        ));

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }

    // Edit value of a custom field for a device
    // GET /device/{device}/customfields/devicefield/{customFieldDevice}/edit
    public function edit(Request $request, Device $device, CustomFieldDevice $customdevicefield)
    {
        Gate::authorize('admin');

        $alert_class = $device->disabled ? 'alert-info' : ($device->status ? '' : 'alert-danger');
        $parent_id = Vminfo::guessFromDevice($device)->value('device_id');
        $overview_graphs = [];

        return view('nmscustomfields::device.edit', compact('device', 'customdevicefield', 'alert_class', 'parent_id', 'overview_graphs'));
    }

    // Delete a custom field from a device
    // DELETE /device/{device}/customfields/devicefield/{customFieldDevice}
    public function destroy(Request $request, Device $device, CustomFieldDevice $customdevicefield = null)
    {
        Gate::authorize('admin');

        if (is_null($customdevicefield)) {
            return $this->handleNotFound($request, $device);
        }

        $customdevicefield->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }

    // Upsert a custom field for a device
    // PUT /device/{device}/customfields/devicefield
    public function upsert(Request $request, Device $device)
    {
        Gate::authorize('admin');

        // Resolve to numeric id first so we can read the type for the value rule.
        $cfRef = $request->input('custom_field');
        $customField = is_numeric($cfRef)
            ? CustomField::find($cfRef)
            : CustomField::where('name', $cfRef)->first();

        $rules = [
            'custom_field' => ['required', $this->customFieldExists($device)],
            'value'        => $customField?->valueRule() ?? 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->route('plugin.nmscustomfields.device.index', $device)->withErrors($validator);
        }

        $cfd = CustomFieldDevice::updateOrCreate(
            ['device_id' => $device->device_id, 'custom_field_id' => $customField->id],
            CustomFieldDevice::columnsFor($customField->type, $request->input('value'))
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'custom_field_device_id' => $cfd->id]);
        }
        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }

    // Bulk-add a custom field to multiple devices (insert-only).
    // POST /plugins/nmscustomfields/bulkstore
    // ajax request — rejects with 422 if any device already has the field.
    public function bulkStore(Request $request)
    {
        Gate::authorize('admin');

        $cfId        = $request->input('custom_field_id');
        $customField = CustomField::find($cfId);

        $request->validate([
            'device_ids'         => 'required|array',
            'device_ids.*'       => 'integer',
            'custom_field_id'    => 'required|exists:custom_fields,id',
            'custom_field_value' => $customField?->valueRule() ?? 'required',
        ]);

        $deviceIds = $request->input('device_ids');

        $conflicts = CustomFieldDevice::where('custom_field_id', $cfId)
            ->whereIn('device_id', $deviceIds)
            ->pluck('device_id')
            ->all();

        if (! empty($conflicts)) {
            $hostnames = Device::whereIn('device_id', $conflicts)
                ->pluck('hostname', 'device_id')
                ->all();
            $names = array_map(
                fn ($id) => $hostnames[$id] ?? "device #{$id}",
                $conflicts
            );

            return response()->json([
                'message' => 'Some devices already have this field.',
                'errors'  => [
                    'device_ids' => ['Already has the field: ' . implode(', ', $names)],
                ],
            ], 422);
        }

        $cols = CustomFieldDevice::columnsFor($customField->type, $request->input('custom_field_value'));

        foreach ($deviceIds as $deviceId) {
            CustomFieldDevice::create(array_merge(
                ['device_id' => $deviceId, 'custom_field_id' => $cfId],
                $cols
            ));
        }

        return response()->json(['success' => true]);
    }

    // Bulk-update the value of a custom field for multiple devices (update-only).
    // POST /plugins/nmscustomfields/bulkupdate
    // ajax request — rejects with 422 if any device doesn't already have the field.
    public function bulkUpdate(Request $request)
    {
        Gate::authorize('admin');

        $cfId        = $request->input('custom_field_id');
        $customField = CustomField::find($cfId);

        $request->validate([
            'device_ids'         => 'required|array',
            'device_ids.*'       => 'integer',
            'custom_field_id'    => 'required|exists:custom_fields,id',
            'custom_field_value' => $customField?->valueRule() ?? 'required',
        ]);

        $deviceIds = $request->input('device_ids');

        $present = CustomFieldDevice::where('custom_field_id', $cfId)
            ->whereIn('device_id', $deviceIds)
            ->pluck('device_id')
            ->all();
        $missing = array_values(array_diff($deviceIds, $present));

        if (! empty($missing)) {
            $hostnames = Device::whereIn('device_id', $missing)
                ->pluck('hostname', 'device_id')
                ->all();
            $names = array_map(
                fn ($id) => $hostnames[$id] ?? "device #{$id}",
                $missing
            );

            return response()->json([
                'message' => 'Some devices do not have this field.',
                'errors'  => [
                    'device_ids' => ['Missing the field: ' . implode(', ', $names)],
                ],
            ], 422);
        }

        $cols = CustomFieldDevice::columnsFor($customField->type, $request->input('custom_field_value'));

        CustomFieldDevice::where('custom_field_id', $cfId)
            ->whereIn('device_id', $deviceIds)
            ->update($cols);

        return response()->json(['success' => true]);
    }

    // Bulk destroty custom fields for multiple devices
    // POST /plugins/nmscustomfields/bulkdestroy
    // ajax request
    public function bulkDestroy(Request $request)
    {
        Gate::authorize('admin');

        $request->validate([
            'device_ids'      => 'required|string',
            'custom_field_id' => 'required|exists:custom_fields,id',
        ]);

        $deviceIds = explode(',', $request->input('device_ids'));

        CustomFieldDevice::where('custom_field_id', $request->input('custom_field_id'))
            ->whereIn('device_id', $deviceIds)
            ->delete();

        return response()->json(['success' => true]);
    }


    protected function ensureDeviceDoesNotHaveCustomField($device, $custom_field_id)
    {
        return static function ($validator) use ($device, $custom_field_id) {
            $validator->errors()->addIf(
                $device->customFieldDevices->contains('custom_field_id', $custom_field_id),
                'custom_field_id',
                'The custom field is already assigned to this device.'
            );
        };
    }

    protected function customFieldExists(Device $device)
    {
        return function ($attribute, $value, $fail) use ($device) {
            $customField = is_numeric($value)
                ? CustomField::find($value)
                : CustomField::where('name', $value)->first();

            if (!$customField) {
                $fail('The selected ' . $attribute . ' is invalid.');
            }
        };
    }

    protected function handleNotFound(Request $request, Device $device)
    {
        return $request->expectsJson()
            ? response()->json(['error' => 'CustomFieldDevice not found'], 404)
            : redirect()->route('plugin.nmscustomfields.device.index', $device)
            ->withErrors(['error' => 'CustomFieldDevice not found']);
    }
}
