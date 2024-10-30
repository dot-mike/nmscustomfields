<?php

namespace DotMike\NmsCustomFields\Http\Controllers;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

use Gate;
use Validator;

class CustomFieldController extends Controller
{

    // show all custom fields
    // GET /plugins/nmscustomfields
    public function index()
    {
        Gate::authorize('admin');

        $customfields = CustomField::all();
        return view('nmscustomfields::customfield.main', compact('customfields'));
    }

    // return all custom fields as json
    // GET /api/v0/devices/customfields
    public function api_index(Request $request)
    {
        $customfields = CustomField::all();
        return response()->json($customfields);
    }

    // query custom fields as json
    // GET /api/v0/devices/customfields/query?name=custom_field_name&fields=field1,field2&value=value&perPage=15
    // name = name of custom field
    // fields = comma separated list of fields to include in the results from the device table
    // value = filter on the value of the custom field
    // perPage = number of results per page
    public function api_query(Request $request)
    {
        try {
            $customfield = CustomField::where('name', $request->input('name'))->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Custom field not found'], 404);
        }

        $query = CustomFieldDevice::select('id', 'device_id', 'custom_field_id')
            ->where('custom_field_id', $customfield->id);

        $table = (new \App\Models\Device())->getTable();
        $deviceFields = ['device_id', 'hostname', 'sysName', 'ip', 'display', 'overwrite_ip', 'disabled', 'ignore'];

        if ($request->input('fields')) {
            $fields = explode(',', $request->input('fields'));

            $invalidFields = [];
            foreach ($fields as $field) {
                if (!Schema::hasColumn($table, $field)) {
                    $invalidFields[] = $field;
                }
            }
            if (!empty($invalidFields)) {
                return response()->json(['error' => 'Invalid fields: ' . implode(', ', $invalidFields)], 400);
            }

            $deviceFields = array_merge($deviceFields, $fields);
        }

        $valueFilter = $request->input('value');
        if ($valueFilter) {
            $query->whereHas('customFieldValue', function ($query) use ($valueFilter) {
                $query->where('value', $valueFilter);
            });
        }

        $query->with([
            'device' => function ($query) use ($deviceFields) {
                $query->select($deviceFields);
            },
            'customFieldValue' => function ($query) {
                $query->select('id', 'value', 'custom_field_device_id');
            }
        ]);

        $paginator = $query->paginate($request->input('perPage', 15));
        $results = $paginator->getCollection();

        $results = $results->map(function ($item) {
            $itemArray = $item->toArray();

            $itemArray['custom_field_value'] = $item->customFieldValue ? $item->customFieldValue->value : null;
            unset($itemArray['customFieldValue']);


            if (isset($itemArray['device'])) {
                $itemArray = array_merge($itemArray['device'], $itemArray);
                unset($itemArray['device']);
            }

            return $itemArray;
        });

        return response()->json([
            'current' => $paginator->currentPage(),
            'rowCount' => $paginator->count(),
            'rows' => $results,
            'total' => $paginator->total(),
        ]);
    }


    // show form to create new custom field
    // GET /plugins/nmscustomfields/create
    public function create()
    {
        Gate::authorize('admin');
        return view('nmscustomfields::customfield.create');
    }

    // save new custom field
    // POST /plugins/nmscustomfields/store
    public function store(Request $request)
    {
        Gate::authorize('admin');

        $data = $request->validate([
            'name' =>
            'required|string|max:255',
            'type' =>
            'required|in:text,integer',
        ]);

        $customfield = CustomField::create($data);

        return redirect()->route('plugin.nmscustomfields.customfield.index')->with('success', 'Custom field created.');
    }

    public function show(CustomField $customfield)
    {
        return redirect()->route('plugin.nmscustomfields.customfield.index');
    }

    // show form to edit custom field
    // GET /plugins/nmscustomfields/edit/{customfield}
    public function edit(CustomField $customfield)
    {
        Gate::authorize('admin');

        if (!$customfield) {
            return redirect()->route('plugin.nmscustomfields.customfield.index');
        }

        return view('nmscustomfields::customfield.edit', compact('customfield'));
    }

    // update custom field
    // POST /plugins/nmscustomfields/update/{customfield}
    public function update(Request $request, CustomField $customfield)
    {
        Gate::authorize('admin');

        $data = $request->validate([
            'name' =>
            'required|string|max:255',
            'type' =>
            'required|in:text,integer',
        ]);


        $customfield->update($data);

        return redirect()->route('plugin.nmscustomfields.customfield.index')->with('success', 'Custom field updated.');
    }


    // view to show all fields in use by devices
    // GET /plugins/nmscustomfields/devices/{device?}
    public function devices(Request $request)
    {
        Gate::authorize('admin');

        $customfield = CustomField::find($request->input('customfield')) ?? CustomField::first();

        return view('nmscustomfields::customfield.devices', compact('customfield'));
    }


    // return all custom fields as json
    public function fields()
    {
        Gate::authorize('admin');

        $fields = CustomField::select('id', 'name', 'type')->get();

        if ($fields->isEmpty()) {
            return response()->json([])->setStatusCode(204);
        }

        return response()->json($fields);
    }

    // destroy field with json
    // POST DELETE plugins/nmscustomfields/customfield/{customfield}
    public function destroy(Request $request, CustomField $customfield)
    {
        Gate::authorize('admin');

        $validator = Validator::make($request->all(), [])->after(function ($validator) use ($customfield) {
            if ($customfield->devices()->count() > 0) {
                $validator->errors()->add(
                    'customfield',
                    'Custom field is in use by one or more devices.
                <a href="' . route('plugin.nmscustomfields.customfield.devices', ['customfield' => $customfield]) . '">View devices with this field.</a>'
                );
            }
        });

        if ($validator->fails()) {
            return redirect()->route('plugin.nmscustomfields.customfield.index')->withErrors($validator);
        }

        $customfield->delete();
        return redirect()->route('plugin.nmscustomfields.customfield.index')->with('success', 'Custom field deleted.');
    }
}
