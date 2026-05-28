<?php

namespace DotMike\NmsCustomFields\Tests\Feature;

use App\Models\Device;
use App\Models\User;
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use DotMike\NmsCustomFields\Tests\TestCase;
use Spatie\Permission\Models\Role;

final class BulkCustomFieldControllerTest extends TestCase
{
    private Device $deviceA;
    private Device $deviceB;
    private CustomField $field;
    private string $storeUrl = '/plugin/settings/nmscustomfields/bulkstore';
    private string $updateUrl = '/plugin/settings/nmscustomfields/bulkupdate';

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceA = Device::factory()->create();
        $this->deviceB = Device::factory()->create();
        $this->field = CustomField::create(['name' => 'bulk_text', 'type' => 'text']);

        Role::findOrCreate('admin');
        $admin = User::factory()->create(['auth_type' => 'mysql', 'enabled' => 1]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_bulkstore_inserts_for_devices_without_field(): void
    {
        $response = $this->postJson($this->storeUrl, [
            'device_ids' => [$this->deviceA->device_id, $this->deviceB->device_id],
            'custom_field_id' => $this->field->id,
            'custom_field_value' => 'hello',
        ]);

        $response->assertOk();
        $this->assertSame(2, CustomFieldDevice::where('custom_field_id', $this->field->id)->count());
        $this->assertSame('hello', CustomFieldDevice::where('device_id', $this->deviceA->device_id)
            ->where('custom_field_id', $this->field->id)->first()->value_text);
    }

    public function test_bulkstore_rejects_when_any_device_already_has_field(): void
    {
        CustomFieldDevice::create([
            'device_id' => $this->deviceA->device_id,
            'custom_field_id' => $this->field->id,
            'value' => 'pre-existing',
        ]);

        $response = $this->postJson($this->storeUrl, [
            'device_ids' => [$this->deviceA->device_id, $this->deviceB->device_id],
            'custom_field_id' => $this->field->id,
            'custom_field_value' => 'new',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('device_ids', $response->json('errors'));

        // Pre-existing value untouched, no row added for deviceB.
        $this->assertSame('pre-existing', CustomFieldDevice::where('device_id', $this->deviceA->device_id)
            ->where('custom_field_id', $this->field->id)->first()->value_text);
        $this->assertFalse(CustomFieldDevice::where('device_id', $this->deviceB->device_id)
            ->where('custom_field_id', $this->field->id)->exists());
    }

    public function test_bulkupdate_updates_existing_rows(): void
    {
        CustomFieldDevice::create([
            'device_id' => $this->deviceA->device_id,
            'custom_field_id' => $this->field->id,
            'value' => 'old-a',
        ]);
        CustomFieldDevice::create([
            'device_id' => $this->deviceB->device_id,
            'custom_field_id' => $this->field->id,
            'value' => 'old-b',
        ]);

        $response = $this->postJson($this->updateUrl, [
            'device_ids' => [$this->deviceA->device_id, $this->deviceB->device_id],
            'custom_field_id' => $this->field->id,
            'custom_field_value' => 'new',
        ]);

        $response->assertOk();
        $this->assertSame('new', CustomFieldDevice::where('device_id', $this->deviceA->device_id)
            ->where('custom_field_id', $this->field->id)->first()->value_text);
        $this->assertSame('new', CustomFieldDevice::where('device_id', $this->deviceB->device_id)
            ->where('custom_field_id', $this->field->id)->first()->value_text);
    }

    public function test_bulkupdate_rejects_when_any_device_lacks_field(): void
    {
        CustomFieldDevice::create([
            'device_id' => $this->deviceA->device_id,
            'custom_field_id' => $this->field->id,
            'value' => 'old',
        ]);

        $response = $this->postJson($this->updateUrl, [
            'device_ids' => [$this->deviceA->device_id, $this->deviceB->device_id],
            'custom_field_id' => $this->field->id,
            'custom_field_value' => 'new',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('device_ids', $response->json('errors'));

        // Existing row untouched.
        $this->assertSame('old', CustomFieldDevice::where('device_id', $this->deviceA->device_id)
            ->where('custom_field_id', $this->field->id)->first()->value_text);
    }

    public function test_bulkstore_routes_integer_value_to_int_column(): void
    {
        $intField = CustomField::create(['name' => 'bulk_int', 'type' => 'integer']);

        $response = $this->postJson($this->storeUrl, [
            'device_ids' => [$this->deviceA->device_id],
            'custom_field_id' => $intField->id,
            'custom_field_value' => '42',
        ]);

        $response->assertOk();
        $cfd = CustomFieldDevice::where('device_id', $this->deviceA->device_id)
            ->where('custom_field_id', $intField->id)->first();
        $this->assertSame(42, (int) $cfd->value_int);
        $this->assertNull($cfd->value_text);
    }

    public function test_bulkedit_route_is_gone(): void
    {
        $response = $this->postJson('/plugin/settings/nmscustomfields/bulkedit', [
            'device_ids' => [$this->deviceA->device_id],
            'custom_field_id' => $this->field->id,
            'custom_field_value' => 'anything',
        ]);

        $response->assertStatus(404);
    }

    public function test_bulkstore_requires_admin(): void
    {
        $nonAdmin = User::factory()->create(['auth_type' => 'mysql', 'enabled' => 1]);
        $this->actingAs($nonAdmin);

        $response = $this->postJson($this->storeUrl, [
            'device_ids' => [$this->deviceA->device_id],
            'custom_field_id' => $this->field->id,
            'custom_field_value' => 'nope',
        ]);

        $response->assertStatus(403);
    }

    public function test_bulkupdate_requires_admin(): void
    {
        $nonAdmin = User::factory()->create(['auth_type' => 'mysql', 'enabled' => 1]);
        $this->actingAs($nonAdmin);

        $response = $this->postJson($this->updateUrl, [
            'device_ids' => [$this->deviceA->device_id],
            'custom_field_id' => $this->field->id,
            'custom_field_value' => 'nope',
        ]);

        $response->assertStatus(403);
    }
}
