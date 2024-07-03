<?php

namespace DotMike\NmsCustomFields\Http\Controllers;

use DotMike\NmsCustomFields\Models\CustomField;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

use Gate;
use Session;
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
