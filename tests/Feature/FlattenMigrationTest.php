<?php

namespace DotMike\NmsCustomFields\Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;

#[Group('migration')]
final class FlattenMigrationTest extends BaseTestCase
{
    private const PLUGIN_MIGRATIONS = 'vendor/dot-mike/nmscustomfields/database/migrations';
    private const V1_MIGRATION = self::PLUGIN_MIGRATIONS . '/2024_06_27_172300_create_custom_fields_table.php';

    /** @var int[] */
    private array $deviceIds = [];

    public function createApplication()
    {
        $app = require getenv('LIBRENMS_FOLDER') . '/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env');
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:reset', [
            '--path' => self::PLUGIN_MIGRATIONS,
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => self::V1_MIGRATION,
            '--force' => true,
        ]);

        $this->assertTrue(Schema::hasTable('custom_field_values'));
        $this->assertFalse(Schema::hasColumn('custom_field_device', 'value_text'));
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('custom_field_values')) {
            DB::table('custom_field_values')->delete();
        }
        DB::table('custom_field_device')->whereIn('device_id', $this->deviceIds)->delete();
        DB::table('custom_fields')->where('name', 'like', 'mig\_%')->delete();
        DB::table('devices')->whereIn('device_id', $this->deviceIds)->delete();

        Artisan::call('migrate', [
            '--path' => self::PLUGIN_MIGRATIONS,
            '--force' => true,
        ]);

        parent::tearDown();
    }

    public function test_migration_handles_all_v1_edge_cases(): void
    {
        $d1 = $this->makeDevice('mig-host-1');
        $d2 = $this->makeDevice('mig-host-2');

        $cfTextKeepId = DB::table('custom_fields')->insertGetId([
            'name' => 'mig_dup_name', 'type' => 'text',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cfTextLoserId = DB::table('custom_fields')->insertGetId([
            'name' => 'mig_dup_name', 'type' => 'text',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cfIntId = DB::table('custom_fields')->insertGetId([
            'name' => 'mig_int', 'type' => 'integer',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cfdDupKeep = DB::table('custom_field_device')->insertGetId([
            'device_id' => $d1, 'custom_field_id' => $cfTextKeepId,
        ]);
        $cfdDupLoser = DB::table('custom_field_device')->insertGetId([
            'device_id' => $d1, 'custom_field_id' => $cfTextLoserId,
        ]);

        DB::table('custom_field_values')->insert([
            'custom_field_device_id' => $cfdDupKeep, 'value' => 'OLD',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('custom_field_values')->insert([
            'custom_field_device_id' => $cfdDupKeep, 'value' => 'NEWEST',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Cascades when loser CFD is deduped.
        DB::table('custom_field_values')->insert([
            'custom_field_device_id' => $cfdDupLoser, 'value' => 'STALE_FROM_LOSER',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cfdIntOk = DB::table('custom_field_device')->insertGetId([
            'device_id' => $d2, 'custom_field_id' => $cfIntId,
        ]);
        DB::table('custom_field_values')->insert([
            'custom_field_device_id' => $cfdIntOk, 'value' => '42',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cfdIntBad = DB::table('custom_field_device')->insertGetId([
            'device_id' => $d1, 'custom_field_id' => $cfIntId,
        ]);
        DB::table('custom_field_values')->insert([
            'custom_field_device_id' => $cfdIntBad, 'value' => 'abc',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $exit = Artisan::call('migrate', [
            '--path' => self::PLUGIN_MIGRATIONS,
            '--force' => true,
        ]);
        $this->assertSame(0, $exit);

        $this->assertFalse(Schema::hasTable('custom_field_values'));
        $this->assertTrue(Schema::hasColumn('custom_field_device', 'value_text'));
        $this->assertTrue(Schema::hasColumn('custom_field_device', 'value_int'));

        $this->assertSame(1, DB::table('custom_fields')->where('name', 'mig_dup_name')->count());
        $survivor = DB::table('custom_fields')->where('name', 'mig_dup_name')->first();
        $this->assertSame($cfTextKeepId, (int) $survivor->id);

        $this->assertSame(1, DB::table('custom_field_device')
            ->where('device_id', $d1)->where('custom_field_id', $cfTextKeepId)->count());

        $textRow = DB::table('custom_field_device')
            ->where('device_id', $d1)->where('custom_field_id', $cfTextKeepId)->first();
        $this->assertSame('NEWEST', $textRow->value_text);
        $this->assertNull($textRow->value_int);

        $intOkRow = DB::table('custom_field_device')
            ->where('device_id', $d2)->where('custom_field_id', $cfIntId)->first();
        $this->assertSame(42, (int) $intOkRow->value_int);
        $this->assertNull($intOkRow->value_text);

        $intBadRow = DB::table('custom_field_device')
            ->where('device_id', $d1)->where('custom_field_id', $cfIntId)->first();
        $this->assertSame('abc', $intBadRow->value_text);
        $this->assertNull($intBadRow->value_int);
    }

    private function makeDevice(string $hostname): int
    {
        $id = DB::table('devices')->insertGetId([
            'hostname' => $hostname,
            'sysName' => $hostname,
            'status' => 1,
            'status_reason' => '',
        ]);
        $this->deviceIds[] = $id;
        return $id;
    }
}
