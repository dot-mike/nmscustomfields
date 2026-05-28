<?php

namespace DotMike\NmsCustomFields\Http\Controllers;

use DotMike\NmsCustomFields\Models\CustomField;

use App\Models\Device;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

use Gate;
use InvalidArgumentException;

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
    // GET /api/v0/customfields/query?filters[0][name]=custom_field_name_1&filters[0][value]=value1&filters[1][name]=custom_field_name_2&filters[1][value]=value2&fields=device_id,hostname,sysName&perPage=10&page=1
    // filters = array of custom field names and values to filter on
    // fields = comma separated list of fields to include in the results from the device table
    // perPage = number of results per page
    public function api_query(Request $request)
    {
        $validated = $request->validate([
            'filters' => 'required|array',
            'filters.*.field' => 'required|string',
            'filters.*.operator' => 'required|string|in:eq,ne,lt,gt,lte,gte,like,not_like,exists,not_exists',
            'filters.*.value' => 'required_unless:filters.*.operator,exists,not_exists',
            'fields' => 'array',
            'fields.*' => 'string',
            'perPage' => 'integer|min:1',
            'page' => 'integer|min:1',
        ]);

        // Fetch valid custom fields
        $customFields = CustomField::whereIn('name', collect($validated['filters'])->pluck('field'))->get();

        if ($customFields->isEmpty()) {
            return response()->json(['error' => 'No valid custom fields found'], 404);
        }

        $query = Device::query();

        // Apply filters
        $query->where(function ($q) use ($validated, $customFields) {
            foreach ($validated['filters'] as $filter) {
                $q->where(function ($subQuery) use ($filter, $customFields) {
                    $fieldId = $customFields->where('name', $filter['field'])->pluck('id')->first();
                    $customField = $customFields->where('id', $fieldId)->first();
                    $isNumericField = $customField && $customField->type === 'integer';

                    switch ($filter['operator']) {
                        case 'exists':
                            // Include devices where field exists
                            $subQuery->whereHas('customFieldDevices', function ($existsQuery) use ($filter) {
                                $existsQuery->whereHas('customField', function ($customFieldQuery) use ($filter) {
                                    $customFieldQuery->where('name', $filter['field']);
                                });
                            });
                            break;

                        case 'not_exists':
                            // Exclude devices where field does not exist
                            $subQuery->whereDoesntHave('customFieldDevices', function ($notExistsQuery) use ($filter) {
                                $notExistsQuery->whereHas('customField', function ($customFieldQuery) use ($filter) {
                                    $customFieldQuery->where('name', $filter['field']);
                                });
                            });
                            break;

                        case 'lte':
                        case 'gte':
                        case 'lt':
                        case 'gt':
                            if ($isNumericField) {
                                $operator = $this->mapOperator($filter['operator']);
                                $subQuery->whereHas('customFieldDevices', function ($q) use ($filter, $operator) {
                                    $q->whereHas('customField', fn ($cf) => $cf->where('name', $filter['field']))
                                      ->where('value_int', $operator, (int) $filter['value']);
                                });
                            }
                            break;

                        case 'like':
                        case 'not_like':
                            // Text-only; silently skip on integer fields (matches lt/gt skipping text fields).
                            // Caller-supplied % and _ wildcards are passed through intentionally.
                            if (! $isNumericField) {
                                $sqlOp = $filter['operator'] === 'like' ? 'LIKE' : 'NOT LIKE';
                                $subQuery->whereHas('customFieldDevices', function ($q) use ($filter, $sqlOp) {
                                    $q->whereHas('customField', fn ($cf) => $cf->where('name', $filter['field']))
                                      ->where('value_text', $sqlOp, '%' . $filter['value'] . '%');
                                });
                            }
                            break;

                        default:
                            $operator = $this->mapOperator($filter['operator']);
                            $valueCol = $isNumericField ? 'value_int' : 'value_text';
                            $subQuery->whereHas('customFieldDevices', function ($q) use ($filter, $operator, $valueCol) {
                                $q->whereHas('customField', fn ($cf) => $cf->where('name', $filter['field']))
                                  ->where($valueCol, $operator, $filter['value']);
                            });
                            break;
                    }
                });
            }
        });

        $deviceFields = [
            'device_id',
            'hostname',
            'sysName',
            'ip',
            'display',
            'overwrite_ip',
            'disabled',
            'ignore',
        ];

        // Validate requested fields
        if (!empty($validated['fields'])) {
            $fields = $validated['fields'];
            $invalidFields = array_diff($fields, Schema::getColumnListing((new \App\Models\Device())->getTable()));

            if (!empty($invalidFields)) {
                return response()->json(['error' => 'Invalid fields: ' . implode(', ', $invalidFields)], 400);
            }

            $deviceFields = array_merge($deviceFields, $fields);
        }

        $query->select($deviceFields)->with('customFieldDevices.customField');

        // Pagination
        $paginator = $query->paginate($validated['perPage'] ?? 15, ['*'], 'page', $validated['page'] ?? 1);
        $results = $paginator->getCollection()->map(function ($item) {
            $itemArray = $item->toArray();
            $itemArray['custom_fields'] = $item->customFieldDevices->map(fn ($cfd) => [
                'field_name' => $cfd->customField->name,
                'value'      => $cfd->value,
            ]);
            return $itemArray;
        });

        return response()->json([
            'current_page' => $paginator->currentPage(),
            'data' => $results,
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
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


    // destroy field with json
    // POST DELETE plugins/nmscustomfields/customfield/{customfield}
    public function destroy(Request $request, CustomField $customfield)
    {
        Gate::authorize('admin');

        if ($customfield->devices()->exists()) {
            return redirect()
                ->route('plugin.nmscustomfields.customfield.devices', ['customfield' => $customfield])
                ->with('warning', 'Cannot delete: this custom field is in use by one or more devices. Remove it from all devices first.');
        }

        $customfield->delete();
        return redirect()->route('plugin.nmscustomfields.customfield.index')->with('success', 'Custom field deleted.');
    }

    private function mapOperator(string $operator): ?string
    {
        $operatorMap = [
            'eq' => '=',
            'ne' => '!=',
            'lt' => '<',
            'gt' => '>',
            'lte' => '<=',
            'gte' => '>='
        ];

        return $operatorMap[$operator] ?? throw new InvalidArgumentException("Invalid operator: $operator");
    }
}
