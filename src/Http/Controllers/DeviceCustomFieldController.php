<?php

namespace DotMike\NmsCustomFields\Http\Controllers;

use DotMike\NmsCustomFields\Models\CustomFieldValue;
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
            $device->load('customFieldDevices.customFieldValue');
            $customFieldValues = $device->customFieldDevices->map(function ($customFieldDevice) {
                return [
                    'id' => $customFieldDevice->id,
                    'value' => $customFieldDevice->customFieldValue->value,
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

    public function show(Request $request, Device $device)
    {
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

        $validator = Validator::make($request->all(), [
            'custom_field_id' => 'required|exists:custom_fields,id',
            'value' => 'required',
        ])->after($this->ensureDeviceDoesNotHaveCustomField($device, $request->custom_field_id));

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            } else {
                return redirect()->route('plugin.nmscustomfields.device.index', $device)->withErrors($validator);
            }
        }

        $device->customFields()->attach($request->custom_field_id);
        $device = $device->fresh();
        $customFieldDevice = $device->customFieldDevices->where('custom_field_id', $request->custom_field_id)->first();
        CustomFieldValue::create([
            'custom_field_device_id' => $customFieldDevice->id,
            'value' => $request->value,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        } else {
            return redirect()->route('plugin.nmscustomfields.device.index', $device);
        }
    }

    // Save the custom field for a device
    // PUT /device/{device}/customfields/devicefield/{customFieldDevice}
    public function update(Request $request, Device $device, CustomFieldDevice $customdevicefield)
    {
        Gate::authorize('admin');

        // validate the request
        $request->validate([
            'value' => 'required',
        ]);

        $customdevicefield->customFieldValue->value = $request->value;
        $customdevicefield->customFieldValue->save();

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

        $customdevicefield->load('customFieldValue');

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

        $customdevicefield->customFieldValue->delete();
        $customdevicefield->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        } else {
            return redirect()->route('plugin.nmscustomfields.device.index', $device);
        }
    }

    // Bulk edit custom fields for multiple devices
    // POST /plugins/nmscustomfields/bulkedit
    // ajax request
    public function bulkedit(Request $request)
    {
        Gate::authorize('admin');

        $request->validate([
            'device_ids' => 'required|array',
            'custom_field_id' => 'required|exists:custom_fields,id',
            'custom_field_value' => 'required',
        ]);

        $device_ids = $request->device_ids;
        $custom_field_id = $request->custom_field_id;
        $custom_field_value = $request->custom_field_value;
        // the device_id list is the master list of devices
        // that is supposed to have this field with this value
        // if any device is missing the field, we need to create it
        // if any device has the field but with a different value, we need to update it
        // if any device has this field but no in the array of device_ids, we need to delete it
        $customFieldDevice = CustomFieldDevice::where('custom_field_id', $custom_field_id)
            ->get();

        $customFieldDevice->each(function ($customFieldDevice) use ($device_ids, $custom_field_value) {
            if (!in_array($customFieldDevice->device_id, $device_ids)) {
                $customFieldDevice->customFieldValue->delete();
                $customFieldDevice->delete();
            } elseif ($customFieldDevice->customFieldValue->value != $custom_field_value) {
                $customFieldDevice->customFieldValue->value = $custom_field_value;
                $customFieldDevice->customFieldValue->save();
            }
        });

        $device_ids = array_diff($device_ids, $customFieldDevice->pluck('device_id')->toArray());
        $device_ids = Device::whereIn('device_id', $device_ids)->get();
        $device_ids->each(function ($device) use ($custom_field_id, $custom_field_value) {
            $device->customFields()->attach($custom_field_id);
            $device = $device->fresh();
            $customFieldDevice = $device->customFieldDevices->where('custom_field_id', $custom_field_id)->first();
            CustomFieldValue::create([
                'custom_field_device_id' => $customFieldDevice->id,
                'value' => $custom_field_value,
            ]);
        });



        return response()->json(['success' => true]);
    }

    // Bulk destroty custom fields for multiple devices
    // POST /plugins/nmscustomfields/bulkdestroy
    // ajax request
    public function bulkDestroy(Request $request)
    {
        Gate::authorize('admin');

        $request->validate([
            'device_ids' => 'required|string',
            'custom_field_id' => 'required|exists:custom_fields,id',
        ]);

        // device_ids may be a comma separated list
        $request->device_ids = explode(',', $request->device_ids);

        $customFieldDevice = CustomFieldDevice::where('custom_field_id', $request->custom_field_id)
            ->whereIn('device_id', $request->device_ids)
            ->get();

        $customFieldDevice->each(function ($customFieldDevice) {
            $customFieldDevice->customFieldValue->delete();
            $customFieldDevice->delete();
        });

        return response()->json(['success' => true]);
    }


    protected function ensureDeviceDoesNotHaveCustomField($device, $custom_field_id)
    {
        //return $device->customFieldDevices->contains('custom_field_id', $custom_field_id);
        return static function ($validator) use ($device, $custom_field_id) {
            $validator->errors()->addIf(
                $device->customFieldDevices->contains('custom_field_id', $custom_field_id),
                'custom_field_id',
                'The custom field is already assigned to this device.'
            );
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
