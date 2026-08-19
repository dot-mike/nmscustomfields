<?php

namespace DotMike\NmsCustomFields\Hooks;

use App\Plugins\Hooks\QueryBuilderFilterHook;
use DotMike\NmsCustomFields\Models\CustomField;

/**
 * Exposes every custom field to the alert rule / device group query builders as
 * a correlated subquery. Keyed by field id, not name, so renaming a field does
 * not break rules that reference it.
 */
class QueryBuilderFilter extends QueryBuilderFilterHook
{
    public function filters(): array
    {
        return CustomField::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CustomField $field) => [
                'cf_' . $field->id => [
                    'label' => 'Custom Field: ' . $field->name,
                    'type' => $field->type === 'integer' ? 'integer' : 'string',
                    'sql' => sprintf(
                        '(SELECT %s FROM custom_field_device WHERE custom_field_device.custom_field_id = %d AND custom_field_device.device_id = %%devices.device_id LIMIT 1)',
                        $field->type === 'integer' ? 'value_int' : 'value_text',
                        $field->id
                    ),
                ],
            ])->all();
    }
}
