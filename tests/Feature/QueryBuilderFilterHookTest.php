<?php

namespace DotMike\NmsCustomFields\Tests\Feature;

use DotMike\NmsCustomFields\Hooks\QueryBuilderFilter;
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Tests\TestCase;

/**
 * Needs the LibreNMS core patch (patches/librenms-querybuilder-filter-hook.patch).
 * Skipped when it isn't applied.
 */
final class QueryBuilderFilterHookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(\App\Plugins\Hooks\QueryBuilderFilterHook::class)) {
            $this->markTestSkipped('LibreNMS core does not have QueryBuilderFilterHook');
        }
    }

    public function test_text_field_filter_selects_value_text(): void
    {
        $cf = CustomField::create(['name' => 'qb_text', 'type' => 'text']);

        $filter = (new QueryBuilderFilter)->filters()["cf_{$cf->id}"];

        $this->assertSame('string', $filter['type']);
        $this->assertSame('Custom Field: qb_text', $filter['label']);
        $this->assertStringContainsString('SELECT value_text', $filter['sql']);
        $this->assertStringContainsString("custom_field_id = {$cf->id}", $filter['sql']);
        $this->assertStringContainsString('%devices.device_id', $filter['sql']);
    }

    public function test_integer_field_filter_selects_value_int(): void
    {
        $cf = CustomField::create(['name' => 'qb_int', 'type' => 'integer']);

        $filter = (new QueryBuilderFilter)->filters()["cf_{$cf->id}"];

        $this->assertSame('integer', $filter['type']);
        $this->assertStringContainsString('SELECT value_int', $filter['sql']);
    }

    public function test_handle_prefixes_filters_with_plugin_name(): void
    {
        $cf = CustomField::create(['name' => 'qb_prefix', 'type' => 'text']);

        $this->assertArrayHasKey("nmscustomfields_cf_{$cf->id}", (new QueryBuilderFilter)->handle('nmscustomfields'));
    }

    public function test_generated_sql_matches_only_devices_with_the_value(): void
    {
        $cf = CustomField::create(['name' => 'qb_sql', 'type' => 'text']);
        $device = \App\Models\Device::query()->first();

        if (! $device) {
            $this->markTestSkipped('no devices in the test database');
        }

        \DotMike\NmsCustomFields\Models\CustomFieldDevice::create([
            'device_id' => $device->device_id,
            'custom_field_id' => $cf->id,
            'value_text' => 'oslo',
        ]);

        $sql = (new QueryBuilderFilter)->filters()["cf_{$cf->id}"]['sql'];
        $sql = str_replace('%devices.device_id', (string) $device->device_id, $sql);

        $this->assertSame('oslo', \DB::selectOne("SELECT $sql AS v")->v);
    }
}
