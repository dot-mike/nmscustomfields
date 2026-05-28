<?php

namespace DotMike\NmsCustomFields\Tests\Feature;

use App\Models\Device;
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use DotMike\NmsCustomFields\Tests\TestCase;

final class HelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/src/Helpers/custom_field_helpers.php';
    }

    public function test_returns_string_for_text_field(): void
    {
        [$device, $cf] = $this->makeFieldOnDevice('helper_txt', 'text');
        CustomFieldDevice::create([
            'device_id' => $device->device_id,
            'custom_field_id' => $cf->id,
            'value' => 'world',
        ]);

        $this->assertSame('world', get_custom_field_value($device, 'helper_txt'));
    }

    public function test_returns_string_for_integer_field(): void
    {
        [$device, $cf] = $this->makeFieldOnDevice('helper_int', 'integer');
        CustomFieldDevice::create([
            'device_id' => $device->device_id,
            'custom_field_id' => $cf->id,
            'value' => '13',
        ]);

        $this->assertSame('13', get_custom_field_value($device, 'helper_int'));
    }

    public function test_returns_null_for_missing_field(): void
    {
        $device = Device::first() ?? Device::factory()->create();
        $this->assertNull(get_custom_field_value($device, '__does_not_exist__'));
    }

    private function makeFieldOnDevice(string $name, string $type): array
    {
        $cf = CustomField::create(['name' => $name, 'type' => $type]);
        $device = Device::first() ?? Device::factory()->create();

        return [$device, $cf];
    }
}
