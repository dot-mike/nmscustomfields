<?php

namespace DotMike\NmsCustomFields\Tests\Feature;

use App\Models\Device;
use App\Models\User;
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use DotMike\NmsCustomFields\Tests\TestCase;
use Spatie\Permission\Models\Role;

final class DeviceCustomFieldControllerTest extends TestCase
{
    private Device $device;
    private CustomField $intField;

    protected function setUp(): void
    {
        parent::setUp();
        $this->device = Device::first() ?? Device::factory()->create();
        $this->intField = CustomField::create(['name' => 'ctrl_int', 'type' => 'integer']);

        Role::findOrCreate('admin');
        $admin = User::factory()->create(['auth_type' => 'mysql', 'enabled' => 1]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_store_accepts_integer_for_integer_field(): void
    {
        $response = $this->postJson(
            "/device/{$this->device->device_id}/customfields/devicefield",
            ['custom_field_id' => $this->intField->id, 'value' => '42']
        );

        $response->assertOk();
        $cfd = CustomFieldDevice::where('device_id', $this->device->device_id)
            ->where('custom_field_id', $this->intField->id)->first();
        $this->assertNotNull($cfd);
        $this->assertSame(42, (int) $cfd->value_int);
        $this->assertNull($cfd->value_text);
    }

    public function test_store_rejects_text_for_integer_field(): void
    {
        $response = $this->postJson(
            "/device/{$this->device->device_id}/customfields/devicefield",
            ['custom_field_id' => $this->intField->id, 'value' => 'abc']
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('value', $response->json('errors'));
    }

    public function test_update_rejects_text_for_integer_field(): void
    {
        $cfd = CustomFieldDevice::create([
            'device_id' => $this->device->device_id,
            'custom_field_id' => $this->intField->id,
            'value' => '1',
        ]);

        $response = $this->putJson(
            "/device/{$this->device->device_id}/customfields/devicefield/{$cfd->id}",
            ['value' => 'abc']
        );

        $response->assertStatus(422);
    }

    public function test_upsert_routes_to_value_int_by_field_name(): void
    {
        $rawToken = bin2hex(random_bytes(16));
        $token = new \App\Models\ApiToken();
        $token->user_id = auth()->id();
        $token->token_hash = $rawToken;
        $token->description = 'phpunit';
        $token->save();

        $response = $this->putJson(
            "/api/v0/devices/{$this->device->device_id}/customfields",
            ['custom_field' => 'ctrl_int', 'value' => '99'],
            ['X-Auth-Token' => $rawToken]
        );

        $response->assertOk();
        $cfd = CustomFieldDevice::where('device_id', $this->device->device_id)
            ->where('custom_field_id', $this->intField->id)->first();
        $this->assertSame(99, (int) $cfd->value_int);
    }

    public function test_api_destroy_resolves_route_param_by_field_name(): void
    {
        // ResolveCustomField middleware is only mounted on API routes. The route
        // param is the FIELD NAME (mixed case), which the middleware lower-cases
        // and looks up via the customField relation.
        $cfd = CustomFieldDevice::create([
            'device_id' => $this->device->device_id,
            'custom_field_id' => $this->intField->id,
            'value' => '5',
        ]);

        $rawToken = bin2hex(random_bytes(16));
        $token = new \App\Models\ApiToken();
        $token->user_id = auth()->id();
        $token->token_hash = $rawToken;
        $token->description = 'phpunit';
        $token->save();

        $response = $this->deleteJson(
            "/api/v0/devices/{$this->device->device_id}/customfields/CTRL_INT",
            [],
            ['X-Auth-Token' => $rawToken]
        );

        $response->assertOk();
        $this->assertFalse(
            CustomFieldDevice::where('id', $cfd->id)->exists(),
            'CFD should be deleted after name-resolved DELETE'
        );
    }

    public function test_index_json_returns_typed_int_value_for_integer_field(): void
    {
        CustomFieldDevice::create([
            'device_id' => $this->device->device_id,
            'custom_field_id' => $this->intField->id,
            'value' => '7',
        ]);

        $response = $this->getJson("/device/{$this->device->device_id}/customfields");

        $response->assertOk();
        $row = collect($response->json())->firstWhere('name', 'ctrl_int');
        $this->assertSame(7, $row['value']);
    }
}
