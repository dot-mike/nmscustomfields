# Flatten EAV custom-field storage (v2.0) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Flatten the 3-table EAV custom-field storage into a 2-table schema with typed value columns so LibreNMS QueryBuilder can filter on `custom_field_device.value_text` / `value_int`, and fix issue #4 (integer fields accepting text) structurally.

**Architecture:** One migration drops `custom_field_values` and adds typed `value_text` / `value_int` columns on `custom_field_device` plus uniqueness constraints. The `CustomFieldValue` model and `customFieldValue` relation are removed (no shims — v2.0 breaking). Writes route through a `CustomFieldDevice::setValueAttribute()` mutator that picks the column based on `customField->type`. Validation rules upgrade per-controller when `type === 'integer'`. UI inputs render `<input type="number">` for integer fields.

**Tech Stack:** Laravel 10/11 Eloquent, MySQL, Blade, jQuery + bootgrid, LibreNMS plugin scaffolding.

**Execution location:** Use the VS Code devcontainer (see `docs/dev-environment.md`). All `php artisan` / `php lnms` commands run from `$LIBRENMS_FOLDER` (= `/var/www/html/librenms` in the container). Edits happen in the plugin repo at `/workspaces/nmscustomfields`, which is composer-symlinked into LibreNMS — changes are live without rebuild. If the devcontainer isn't running when you reach a verification step, start it manually (VS Code → Reopen in Container) and proceed.

---

## Locked decisions (resolved from the original plan's "Open decisions")

These are locked. If you disagree during implementation, stop and surface the disagreement before changing.

1. **Integer column type:** `BIGINT` (signed). Cost is negligible at plugin scale; futureproofs against larger integer fields.
2. **Backfill cast failures (`type=integer` row whose existing text value is not parseable as int):** write the original string to `value_text`, leave `value_int` null, log a warning with `(field name, device_id, value)`. Lossless; user cleans up after migration. The data is "semantically broken until user fixes it", which is what they had before.
3. **`down()` migration:** kept for dev/test convenience. CFV.id is NOT round-trip stable. Document this in the migration's docblock.
4. **`CustomFieldDevice` parent class:** `Illuminate\Database\Eloquent\Model` (NOT `Pivot`). CFD is a first-class entity — autoincrement `id` PK, route-model bound, accessed via `hasMany` from Device. Pivot's defaults (`$incrementing = false`, `$timestamps = false`) actively fight this usage; switching off `attach()` would have silently stopped writing `created_at`/`updated_at`. The existing `Device->customFields()` belongsToMany does NOT call `->using(CustomFieldDevice::class)`, so swapping CFD to Model has no effect on that relation. `withPivot('device_id')` keeps working. (Overrides the original plan's "stay on Pivot" recommendation.)
5. **JSON shape for integer fields in `GET /devices/{device}/customfields`:** preserve the current `string|null` contract. Implementation site: the accessor `CustomFieldDevice::getValueAttribute()` always returns `string|null` (casting `value_int` to string when used). Controllers therefore just emit `$cfd->value` — no extra cast needed at the controller layer.

## File map

Created:
- `database/migrations/2026_05_27_000000_flatten_custom_field_values.php`
- `docs/superpowers/plans/2026-05-27-flatten-eav.md` (this file)
- `docs/dev-environment.md` (devcontainer + manual-symlink workflow — already created out of band)

Modified:
- `src/Models/CustomFieldDevice.php`
- `src/Providers/CustomFieldsProvider.php`
- `src/Helpers/custom_field_helpers.php`
- `src/Http/Controllers/DeviceCustomFieldController.php`
- `src/Http/Controllers/CustomFieldController.php`
- `src/Http/Controllers/Table/CustomFieldController.php`
- `src/Http/Controllers/Table/CustomFieldValueController.php`
- `src/Http/Controllers/Select/CustomFieldController.php` (verify `type` is returned; fix if not — covers the modal input-type swap)
- `resources/views/device/customfields.blade.php`
- `resources/views/customfield/devices.blade.php`
- `resources/views/device/edit.blade.php`
- `resources/views/device/create.blade.php`
- `composer.json`
- `CHANGELOG.md`

Deleted:
- `src/Models/CustomFieldValue.php`

Untouched (out of scope):
- `resources/views/device/customfields-edit-modal.blade.php` (orphan; references nonexistent routes)
- `src/Http/Middleware/ResolveCustomField.php` `strtolower` quirk (pre-existing bug)

## Pre-flight

Before Task 1, quiesce writes and capture current DB state so you can verify the migration end-to-end.

**Why quiesce:** MySQL DDL implicitly commits, so the multi-step migration (column add → dedup → backfill → constraints → drop CFV) is NOT atomic. A LibreNMS write between dedup and backfill can be lost. Plugin scale makes this low-probability but not zero. For non-throwaway DBs, stop the scheduler / web role for the duration of the migration. For dev DBs, skip.

- [ ] **Pre-flight Step 1:** Stop LibreNMS write traffic on non-throwaway DBs.

```bash
# Adjust to your deployment (systemd / supervisor / docker compose):
sudo systemctl stop librenms-scheduler   # or equivalent
# Optionally also take the web role offline (maintenance page) for the brief migration window.
```

- [ ] **Pre-flight Step 2:** From a LibreNMS dev instance with the plugin installed, snapshot row counts.

```bash
# In the LibreNMS app root, not the plugin repo:
php artisan tinker --execute='
echo "custom_fields:        " . DB::table("custom_fields")->count() . PHP_EOL;
echo "custom_field_device:  " . DB::table("custom_field_device")->count() . PHP_EOL;
echo "custom_field_values:  " . DB::table("custom_field_values")->count() . PHP_EOL;
echo "duplicates name:      " . DB::table("custom_fields")->select("name", DB::raw("count(*) c"))->groupBy("name")->havingRaw("c > 1")->count() . PHP_EOL;
echo "duplicates dev/field: " . DB::table("custom_field_device")->select("device_id","custom_field_id", DB::raw("count(*) c"))->groupBy("device_id","custom_field_id")->havingRaw("c > 1")->count() . PHP_EOL;
echo "values per cfd >1:    " . DB::table("custom_field_values")->select("custom_field_device_id", DB::raw("count(*) c"))->groupBy("custom_field_device_id")->havingRaw("c > 1")->count() . PHP_EOL;
'
```

Record these numbers in your scratchpad. After migration: `custom_field_device` row count should equal the previous deduped `(device_id, custom_field_id)` count; `value_text`+`value_int` non-null count should equal the previous deduped CFV count.

- [ ] **Pre-flight Step 3:** Confirm there is a DB backup before running migrations on any non-throwaway instance.

---

### Migration build strategy (Tasks 1-7)

**Important:** Tasks 1-6 build the migration file in pieces. **Do NOT run `php artisan migrate` between these tasks.** Laravel marks a migration as `ran` on its first execution; a rollback against an empty `down()` is a no-op, and the migration won't re-run. Verify each task by reading the file. Run the migration end-to-end only in Task 7 (after `down()` is implemented).

If you absolutely must test a partial migration, copy the file to a throwaway timestamped name, run+rollback that copy, then delete it. Do not run the real file until Task 7.

---

### Task 1: Create migration skeleton with `up()` column additions

**Files:**
- Create: `database/migrations/2026_05_27_000000_flatten_custom_field_values.php`

- [ ] **Step 1: Write the migration file skeleton**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Flatten EAV custom-field storage (v2.0).
 *
 * Up:
 *   - Adds value_text TEXT NULL, value_int BIGINT NULL on custom_field_device.
 *   - Dedups custom_fields.name (lowest id wins).
 *   - Dedups custom_field_device(device_id, custom_field_id) (lowest id wins).
 *   - Backfills value_text/value_int from custom_field_values (last value per CFD wins).
 *   - Adds UNIQUE(name) on custom_fields, UNIQUE(device_id, custom_field_id) on CFD,
 *     and filter indexes on CFD.
 *   - Drops the custom_field_values table.
 *
 * Down (dev/test only):
 *   - Recreates custom_field_values with new ids (NOT round-trip stable).
 *   - Drops uniques/indexes/columns added in up().
 *
 * Backfill rule for type=integer rows whose existing value does not parse as int:
 *   - Original string is written to value_text, value_int stays null.
 *   - A warning is logged. User cleans up afterwards.
 */
class FlattenCustomFieldValues extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_device', function (Blueprint $table) {
            $table->text('value_text')->nullable()->after('custom_field_id');
            $table->bigInteger('value_int')->nullable()->after('value_text');
        });
    }

    public function down(): void
    {
        // Implemented in a later task.
    }
}
```

- [ ] **Step 2: Verify the file syntactically parses**

```bash
php -l database/migrations/2026_05_27_000000_flatten_custom_field_values.php
```

Expected: `No syntax errors detected ...`.

Do NOT run `php artisan migrate` yet — see "Migration build strategy" above.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_27_000000_flatten_custom_field_values.php
git commit -m "feat(migration): scaffold flatten-eav migration with value_text/value_int columns"
```

---

### Task 2: Migration — dedup `custom_fields.name`

**Files:**
- Modify: `database/migrations/2026_05_27_000000_flatten_custom_field_values.php`

- [ ] **Step 1: Add the dedup logic to `up()`**

Insert immediately after the `Schema::table('custom_field_device', ...)` block:

```php
        // Dedup custom_fields by name (lowest id wins).
        $dups = DB::table('custom_fields')
            ->select('name', DB::raw('MIN(id) AS keep_id'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dups as $row) {
            $loserIds = DB::table('custom_fields')
                ->where('name', $row->name)
                ->where('id', '!=', $row->keep_id)
                ->pluck('id');

            DB::table('custom_field_device')
                ->whereIn('custom_field_id', $loserIds)
                ->update(['custom_field_id' => $row->keep_id]);

            DB::table('custom_fields')->whereIn('id', $loserIds)->delete();
        }
```

- [ ] **Step 2: Verify file parses**

```bash
php -l database/migrations/2026_05_27_000000_flatten_custom_field_values.php
```

Expected: `No syntax errors detected`. End-to-end run is deferred to Task 7.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_27_000000_flatten_custom_field_values.php
git commit -m "feat(migration): dedup custom_fields by name, repointing CFD rows to survivor"
```

---

### Task 3: Migration — dedup `(device_id, custom_field_id)` on `custom_field_device`

**Files:**
- Modify: `database/migrations/2026_05_27_000000_flatten_custom_field_values.php`

- [ ] **Step 1: Add CFD dedup logic to `up()`**

Append after the custom_fields dedup block:

```php
        // Dedup custom_field_device by (device_id, custom_field_id) (lowest id wins).
        // CFV rows attached to losers cascade out via FK when the CFD row is deleted.
        $cfdDups = DB::table('custom_field_device')
            ->select('device_id', 'custom_field_id', DB::raw('MIN(id) AS keep_id'))
            ->groupBy('device_id', 'custom_field_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($cfdDups as $row) {
            DB::table('custom_field_device')
                ->where('device_id', $row->device_id)
                ->where('custom_field_id', $row->custom_field_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }
```

- [ ] **Step 2: Verify file parses**

```bash
php -l database/migrations/2026_05_27_000000_flatten_custom_field_values.php
```

Expected: `No syntax errors detected`. End-to-end run is deferred to Task 7.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_27_000000_flatten_custom_field_values.php
git commit -m "feat(migration): dedup (device_id, custom_field_id) on custom_field_device"
```

---

### Task 4: Migration — backfill `value_text` / `value_int` from CFV

**Files:**
- Modify: `database/migrations/2026_05_27_000000_flatten_custom_field_values.php`

- [ ] **Step 1: Add backfill logic to `up()`**

Append after the CFD dedup block:

```php
        // Backfill: last CFV per CFD wins (order by CFV.id DESC, take first).
        // CFV.id is monotonic since it's an autoincrement; no created_at index.
        $rows = DB::table('custom_field_values as cfv')
            ->join('custom_field_device as cfd', 'cfv.custom_field_device_id', '=', 'cfd.id')
            ->join('custom_fields as cf', 'cfd.custom_field_id', '=', 'cf.id')
            ->orderBy('cfv.id', 'desc')
            ->select('cfd.id as cfd_id', 'cf.name as field_name', 'cf.type as field_type', 'cfd.device_id', 'cfv.value as raw_value')
            ->get();

        $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r->cfd_id])) {
                continue; // older value, skip — last-id-wins
            }
            $seen[$r->cfd_id] = true;

            $update = ['value_text' => null, 'value_int' => null];

            if ($r->field_type === 'integer') {
                $trimmed = trim((string) $r->raw_value);
                if (preg_match('/^-?\d+$/', $trimmed)) {
                    $update['value_int'] = (int) $trimmed;
                } else {
                    Log::warning('flatten-eav: integer field has non-integer value; storing in value_text', [
                        'field'     => $r->field_name,
                        'device_id' => $r->device_id,
                        'value'     => $r->raw_value,
                    ]);
                    $update['value_text'] = $r->raw_value;
                }
            } else {
                $update['value_text'] = $r->raw_value;
            }

            DB::table('custom_field_device')->where('id', $r->cfd_id)->update($update);
        }
```

- [ ] **Step 2: Verify file parses**

```bash
php -l database/migrations/2026_05_27_000000_flatten_custom_field_values.php
```

Expected: `No syntax errors detected`. End-to-end run is deferred to Task 7.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_27_000000_flatten_custom_field_values.php
git commit -m "feat(migration): backfill value_text/value_int from custom_field_values (last-id wins, log non-int)"
```

---

### Task 5: Migration — add UNIQUE constraints and filter indexes

**Files:**
- Modify: `database/migrations/2026_05_27_000000_flatten_custom_field_values.php`

- [ ] **Step 1: Add the constraint/index statements to `up()`**

Append after the backfill block:

```php
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->unique('name', 'custom_fields_name_unique');
        });

        Schema::table('custom_field_device', function (Blueprint $table) {
            $table->unique(['device_id', 'custom_field_id'], 'cfd_device_field_unique');
            $table->index(['custom_field_id', 'value_int'], 'cfd_field_value_int_idx');
            // value_text(64): index prefix on TEXT — required by MySQL.
            DB::statement('CREATE INDEX cfd_field_value_text_idx ON custom_field_device (custom_field_id, value_text(64))');
        });
```

- [ ] **Step 2: Verify file parses**

```bash
php -l database/migrations/2026_05_27_000000_flatten_custom_field_values.php
```

Expected: `No syntax errors detected`. End-to-end run is deferred to Task 7.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_27_000000_flatten_custom_field_values.php
git commit -m "feat(migration): add UNIQUE(name), UNIQUE(device_id,custom_field_id), filter indexes on CFD"
```

---

### Task 6: Migration — drop `custom_field_values` table

**Files:**
- Modify: `database/migrations/2026_05_27_000000_flatten_custom_field_values.php`

- [ ] **Step 1: Append DROP TABLE to `up()`**

```php
        // Drop legacy CFV. The FK custom_field_values.custom_field_device_id → CFD goes with the table.
        // No other table references CFV.
        Schema::dropIfExists('custom_field_values');
```

- [ ] **Step 2: Verify file parses**

```bash
php -l database/migrations/2026_05_27_000000_flatten_custom_field_values.php
```

Expected: `No syntax errors detected`. End-to-end run is deferred to Task 7.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_27_000000_flatten_custom_field_values.php
git commit -m "feat(migration): drop custom_field_values table"
```

---

### Task 7: Migration — implement `down()` for dev/test rollback

**Files:**
- Modify: `database/migrations/2026_05_27_000000_flatten_custom_field_values.php`

- [ ] **Step 1: Fill the `down()` body**

Replace the empty `down()` with:

```php
    public function down(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->increments('id')->unsigned()->index();
            $table->unsignedInteger('custom_field_device_id')->index();
            $table->text('value');
            $table->timestamps();
            $table->foreign('custom_field_device_id')->references('id')->on('custom_field_device')->onDelete('cascade');
        });

        // Recreate CFV rows from CFD's value_text/value_int. CFV.id is NOT preserved.
        // Group the OR-where in a closure so future chained constraints bind correctly.
        // Use chunkById (available on QueryBuilder) — each() is Eloquent-only.
        DB::table('custom_field_device')
            ->where(function ($q) {
                $q->whereNotNull('value_text')->orWhereNotNull('value_int');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $cfd) {
                    $value = $cfd->value_text ?? (string) $cfd->value_int;
                    DB::table('custom_field_values')->insert([
                        'custom_field_device_id' => $cfd->id,
                        'value'                  => $value,
                        'created_at'             => $cfd->created_at,
                        'updated_at'             => $cfd->updated_at,
                    ]);
                }
            });

        Schema::table('custom_field_device', function (Blueprint $table) {
            $table->dropUnique('cfd_device_field_unique');
            $table->dropIndex('cfd_field_value_int_idx');
            $table->dropIndex('cfd_field_value_text_idx');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropUnique('custom_fields_name_unique');
        });

        Schema::table('custom_field_device', function (Blueprint $table) {
            $table->dropColumn(['value_text', 'value_int']);
        });
    }
```

- [ ] **Step 2: Run the migration end-to-end for the first time**

In `$LIBRENMS_FOLDER`:

```bash
php artisan migrate
```

Expected: completes with no error. The migration file at this point performs add-columns → dedup-names → dedup-CFD → backfill → constraints/indexes → drop CFV in one run.

- [ ] **Step 3: Verify the resulting schema and data**

```bash
php artisan tinker --execute='
var_dump(Schema::hasTable("custom_field_values"));    // false
print_r(Schema::getColumnListing("custom_field_device"));
echo "rows with value_text: " . DB::table("custom_field_device")->whereNotNull("value_text")->count() . PHP_EOL;
echo "rows with value_int:  " . DB::table("custom_field_device")->whereNotNull("value_int")->count() . PHP_EOL;
print_r(DB::select("SHOW INDEX FROM custom_fields WHERE Key_name = \"custom_fields_name_unique\""));
print_r(DB::select("SHOW INDEX FROM custom_field_device WHERE Key_name IN (\"cfd_device_field_unique\",\"cfd_field_value_int_idx\",\"cfd_field_value_text_idx\")"));
'
```

Compare row counts against the pre-flight snapshot. If a CFD row had a CFV value before, it must have `value_text` OR `value_int` now (sum of the two counts = deduped CFV count).

- [ ] **Step 4: Verify down() then up() cycle**

```bash
php artisan migrate:rollback --step=1
php artisan tinker --execute='
var_dump(Schema::hasTable("custom_field_values"));   // true (recreated)
print_r(Schema::getColumnListing("custom_field_device"));  // value_text/value_int gone
echo "CFV rows: " . DB::table("custom_field_values")->count() . PHP_EOL;
'
php artisan migrate
```

Expected: rollback recreates CFV with row count ≈ the previous deduped CFV count (off only by losing the original CFV.id values, which is documented). Re-migrate restores Task-6 state.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_27_000000_flatten_custom_field_values.php
git commit -m "feat(migration): implement down() for dev/test rollback (CFV.id not preserved)"
```

---

### Task 8: Update `CustomFieldDevice` model — accessor, mutator, drop CFV relation

**Files:**
- Modify: `src/Models/CustomFieldDevice.php`

- [ ] **Step 1: Rewrite the model**

```php
<?php

namespace DotMike\NmsCustomFields\Models;

use App\Models\Device;
use Illuminate\Database\Eloquent\Model;

/**
 * First-class entity, not a junction-only pivot. Has its own autoincrement `id`,
 * is route-model bound, and is accessed via Device::hasMany. We do NOT extend
 * Pivot — Pivot's defaults ($incrementing = false, $timestamps = false) would
 * break PK round-trip and silently stop writing timestamps once we move off
 * BelongsToMany::attach() to save()/updateOrCreate().
 */
class CustomFieldDevice extends Model
{
    protected $table = 'custom_field_device';

    protected $guarded = ['id'];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id', 'id');
    }

    /**
     * Read-side: return string|null regardless of which column stores the value.
     * Preserves the pre-v2 API contract (value is always string in JSON).
     *
     * IMPORTANT: use !== null, not truthy checks. The integer value 0 is falsy
     * but is a legitimate stored value that must round-trip as the string "0".
     */
    public function getValueAttribute()
    {
        $int  = $this->attributes['value_int']  ?? null;
        $text = $this->attributes['value_text'] ?? null;

        if ($int !== null) {
            return (string) $int;
        }
        if ($text !== null) {
            return (string) $text;
        }
        return null;
    }

    /**
     * Write-side: route to value_int when the field's type is integer, else value_text.
     * Requires custom_field_id already set on the model. Eloquent fill() preserves
     * array order, and updateOrCreate sets criteria-array attributes first.
     */
    public function setValueAttribute($v): void
    {
        $cfId = $this->attributes['custom_field_id'] ?? null;
        if (! $cfId) {
            throw new \LogicException('CustomFieldDevice::setValueAttribute requires custom_field_id to be set first.');
        }

        $type = CustomField::query()->whereKey($cfId)->value('type') ?? 'text';

        if ($type === 'integer') {
            $this->attributes['value_int']  = $v === null ? null : (int) $v;
            $this->attributes['value_text'] = null;
        } else {
            $this->attributes['value_text'] = $v === null ? null : (string) $v;
            $this->attributes['value_int']  = null;
        }
    }
}
```

- [ ] **Step 2: Verify in tinker — PK + timestamps + accessor/mutator**

```bash
php artisan tinker --execute='
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;

$f = CustomField::firstOrCreate(["name"=>"_test_int"], ["type"=>"integer"]);
$d = \App\Models\Device::value("device_id");

$cfd = CustomFieldDevice::updateOrCreate(
    ["device_id"=>$d, "custom_field_id"=>$f->id],
    ["value"=>"42"]
);
echo "id (non-null int):     " . var_export($cfd->id, true) . PHP_EOL;
echo "created_at (non-null): " . var_export((string)$cfd->created_at, true) . PHP_EOL;
echo "updated_at (non-null): " . var_export((string)$cfd->updated_at, true) . PHP_EOL;
echo "value_int:             " . $cfd->value_int . PHP_EOL;
echo "value_text:            " . var_export($cfd->value_text, true) . PHP_EOL;
echo "accessor:              " . var_export($cfd->value, true) . PHP_EOL;

// Refetch and confirm PK + value round-trip.
$fresh = CustomFieldDevice::find($cfd->id);
echo "refetch.value_int:     " . $fresh->value_int . PHP_EOL;

// belongsToMany compatibility check: Device->customFields still works.
$dev  = \App\Models\Device::find($d);
echo "belongsToMany count:   " . $dev->customFields()->count() . PHP_EOL;

// False-zero regression check: integer value 0 must round-trip as string "0".
$cfd->value = 0; $cfd->save(); $cfd->refresh();
echo "zero round-trip:       " . var_export($cfd->value, true) . PHP_EOL; // expect: "0"

// Cleanup
$cfd->delete(); $f->delete();
'
```

Expected: `id` is a non-null int; `created_at` and `updated_at` are non-empty strings; `value_int: 42`, `value_text: NULL`, `accessor: '42'`; `zero round-trip: '0'` (NOT `NULL` — guards against the false-zero bug in the accessor). If anything is off, stop and investigate.

- [ ] **Step 3: Commit**

```bash
git add src/Models/CustomFieldDevice.php
git commit -m "feat!: route CustomFieldDevice value through typed accessor/mutator; drop customFieldValue relation"
```

---

> **Reorder note:** The original "delete `CustomFieldValue.php`" was Task 9. It has been moved to **Task 20a** (after the last reference is removed in Task 20), so that every intermediate commit leaves the codebase autoloadable. Task 9 below is now the Provider rewrite (previously Task 10). Task 10 is removed; tasks 11+ keep their original numbers.

### Task 9: Provider — remove `customFieldValues` HasManyThrough, rewrite `customFieldValuesWithNames`, remove dead Blade directive

**Files:**
- Modify: `src/Providers/CustomFieldsProvider.php`

- [ ] **Step 1: Replace the `registerDynamicRelations()` and `boot()` cleanups**

Replace the entire `registerDynamicRelations()` method with:

```php
    protected function registerDynamicRelations(): void
    {
        Device::resolveRelationUsing('customFields', function ($device) {
            return $device->belongsToMany(
                \DotMike\NmsCustomFields\Models\CustomField::class,
                'custom_field_device',
                'device_id',
                'custom_field_id'
            )->withPivot('device_id');
        });

        Device::resolveRelationUsing('customFieldDevices', function ($device) {
            return $device->hasMany(
                \DotMike\NmsCustomFields\Models\CustomFieldDevice::class,
                'device_id',
                'device_id'
            );
        });

        // Used only by DeviceOverview hook. Aliases MUST stay as field_name / field_value —
        // overview.blade.php reads them by those names.
        Device::resolveRelationUsing('customFieldValuesWithNames', function ($device) {
            return $device->customFieldDevices()
                ->join('custom_fields', 'custom_field_device.custom_field_id', '=', 'custom_fields.id')
                ->select(
                    'custom_fields.name as field_name',
                    \DB::raw('COALESCE(custom_field_device.value_text, CAST(custom_field_device.value_int AS CHAR)) as field_value')
                );
        });
    }
```

- [ ] **Step 2: Drop the dead Blade directive in `boot()`**

In `boot()`, delete these four lines:

```php
        Blade::directive('customFieldValue', function ($expression) {
            return "<?php echo customFieldValue($expression); ?>";
        });
```

(Verify: `customFieldValue()` PHP function does not exist in this codebase. The directive is dead.)

- [ ] **Step 3: Remove the unused `use Illuminate\Support\Facades\Blade;` import**

If `Blade` is no longer referenced anywhere in the file after Step 2, drop the import:

```bash
grep -n 'Blade::' src/Providers/CustomFieldsProvider.php
```

Expected: no matches. If empty, remove `use Illuminate\Support\Facades\Blade;`.

- [ ] **Step 4: Verify the overview hook still gets the right shape**

```bash
php artisan tinker --execute='
$device = \App\Models\Device::first();
$rows = $device->customFieldValuesWithNames()->get();
foreach ($rows as $r) {
    echo $r->field_name . " => " . var_export($r->field_value, true) . PHP_EOL;
}
'
```

Expected: prints `name => value` pairs (or nothing if the test device has no fields).

- [ ] **Step 5: Commit**

```bash
git add src/Providers/CustomFieldsProvider.php
git commit -m "feat!: drop customFieldValues hasManyThrough and dead customFieldValue Blade directive; rewrite customFieldValuesWithNames against flat CFD"
```

---

### Task 11: Update `get_custom_field_value()` helper

**Files:**
- Modify: `src/Helpers/custom_field_helpers.php`

- [ ] **Step 1: Replace the helper body**

```php
<?php

use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use App\Models\Device;

/**
 * Get the custom field value by field name and device.
 *
 * @param  \App\Models\Device  $device
 * @param  string  $fieldName
 * @return string|null
 */
function get_custom_field_value(Device $device, string $fieldName)
{
    $cfd = CustomFieldDevice::query()
        ->whereHas('customField', fn ($q) => $q->where('name', $fieldName))
        ->where('device_id', $device->device_id)
        ->first();

    return $cfd ? $cfd->value : null;
}
```

- [ ] **Step 2: Verify**

```bash
php artisan tinker --execute='
$device = \App\Models\Device::first();
$name = \DotMike\NmsCustomFields\Models\CustomField::value("name");
echo "Helper: " . var_export(get_custom_field_value($device, $name), true) . PHP_EOL;
'
```

Expected: prints the value (or `NULL` if the first device has no value for that field).

- [ ] **Step 3: Commit**

```bash
git add src/Helpers/custom_field_helpers.php
git commit -m "refactor: read get_custom_field_value() from flat CFD; preserve string|null contract"
```

---

### Task 12: `DeviceCustomFieldController` — `index`, `show`, `destroy` cleanup

**Files:**
- Modify: `src/Http/Controllers/DeviceCustomFieldController.php`

- [ ] **Step 1: Drop the `use CustomFieldValue` import at top of file**

Delete the line:

```php
use DotMike\NmsCustomFields\Models\CustomFieldValue;
```

- [ ] **Step 2: Rewrite `index()` JSON branch**

Replace lines that build `$customFieldValues`:

```php
        if ($request->expectsJson()) {
            $device->load('customFieldDevices.customField');
            $customFieldValues = $device->customFieldDevices->map(function ($cfd) {
                return [
                    'id'    => $cfd->customField->id,
                    'name'  => $cfd->customField->name,
                    'value' => $cfd->value,
                ];
            });
            return response()->json($customFieldValues);
        }
```

(Note: `$cfd->value` is the accessor; returns string|null.)

- [ ] **Step 3: Rewrite `show()` JSON branch**

```php
    public function show(Request $request, Device $device, CustomFieldDevice $customdevicefield)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'id'                => $customdevicefield->id,
                'custom_field_id'   => $customdevicefield->custom_field_id,
                'custom_field_name' => $customdevicefield->customField->name,
                'value'             => $customdevicefield->value,
            ]);
        }

        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }
```

- [ ] **Step 4: Rewrite `destroy()` — single delete, no CFV cascade**

```php
    public function destroy(Request $request, Device $device, CustomFieldDevice $customdevicefield = null)
    {
        Gate::authorize('admin');

        if (is_null($customdevicefield)) {
            return $this->handleNotFound($request, $device);
        }

        $customdevicefield->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }
```

- [ ] **Step 5: Rewrite `edit()` — drop the `->load('customFieldValue')` call**

Change:

```php
        $customdevicefield->load('customFieldValue');
```

to (just delete the line — `edit.blade.php` reads `$customdevicefield->value` via accessor).

- [ ] **Step 6: Smoke test in browser**

Navigate to a device's custom-fields page (`/device/<id>/customfields`) and verify the list renders and the row delete button works. We will rewrite the blade in Task 22 — for now, the page may still reference `custom_field_value_id` in JS, which is OK (controller responses haven't changed shape yet — that comes in Task 19).

- [ ] **Step 7: Commit**

```bash
git add src/Http/Controllers/DeviceCustomFieldController.php
git commit -m "refactor(controller): index/show/destroy read from CFD accessor; drop CFV import"
```

---

### Task 13: `DeviceCustomFieldController::store` — type-aware validation + flat write

**Files:**
- Modify: `src/Http/Controllers/DeviceCustomFieldController.php`

- [ ] **Step 1: Replace the `store` method body**

```php
    public function store(Request $request, Device $device)
    {
        Gate::authorize('admin');

        $cfId   = $request->input('custom_field_id');
        $cfType = CustomField::query()->whereKey($cfId)->value('type');

        $rules = [
            'custom_field_id' => 'required|exists:custom_fields,id',
            'value'           => $cfType === 'integer' ? 'required|integer' : 'required',
        ];

        $validator = Validator::make($request->all(), $rules)
            ->after($this->ensureDeviceDoesNotHaveCustomField($device, $cfId));

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->route('plugin.nmscustomfields.device.index', $device)->withErrors($validator);
        }

        CustomFieldDevice::create([
            'device_id'       => $device->device_id,
            'custom_field_id' => $cfId,
            'value'           => $request->input('value'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }
```

(Mutator routes `value` → `value_int` or `value_text` based on the field type.)

- [ ] **Step 2: Verify with curl (integer field, valid + invalid)**

```bash
# Valid (assumes the first integer field id is 1; adjust as needed):
curl -X POST -H "X-CSRF-TOKEN: $(read token here)" -d "custom_field_id=1&value=42" http://localhost/device/1/customfields/devicefield
# Invalid (text into integer field):
curl -X POST -H "X-CSRF-TOKEN: $(read token here)" -d "custom_field_id=1&value=notnumeric" http://localhost/device/1/customfields/devicefield
```

Expected: first returns success; second returns 422 with `errors.value`.

(If running CSRF-protected web routes from CLI is awkward, exercise via the browser UI in Task 23.)

- [ ] **Step 3: Commit**

```bash
git add src/Http/Controllers/DeviceCustomFieldController.php
git commit -m "feat!(#4): integer-typed validation + flat write in store()"
```

---

### Task 14: `DeviceCustomFieldController::update` — type-aware validation + flat write

**Files:**
- Modify: `src/Http/Controllers/DeviceCustomFieldController.php`

- [ ] **Step 1: Replace the `update` method body**

```php
    public function update(Request $request, Device $device, CustomFieldDevice $customdevicefield)
    {
        Gate::authorize('admin');

        $cfType = $customdevicefield->customField->type;

        $validator = Validator::make($request->all(), [
            'value' => $cfType === 'integer' ? 'required|integer' : 'required',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->route('plugin.nmscustomfields.device.index', $device)->withErrors($validator);
        }

        $customdevicefield->value = $request->input('value');
        $customdevicefield->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }
```

- [ ] **Step 2: Verify in tinker**

```bash
php artisan tinker --execute='
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
$f = CustomField::firstOrCreate(["name"=>"_test_int2"], ["type"=>"integer"]);
$d = \App\Models\Device::value("device_id");
$cfd = CustomFieldDevice::updateOrCreate(["device_id"=>$d,"custom_field_id"=>$f->id],["value"=>"7"]);
$cfd->value = "99"; $cfd->save();
$cfd->refresh();
echo "value_int=" . $cfd->value_int . " value_text=" . var_export($cfd->value_text,true) . PHP_EOL;
$cfd->delete(); $f->delete();
'
```

Expected: `value_int=99 value_text=NULL`.

- [ ] **Step 3: Commit**

```bash
git add src/Http/Controllers/DeviceCustomFieldController.php
git commit -m "feat!(#4): integer-typed validation + flat write in update()"
```

---

### Task 15: `DeviceCustomFieldController::upsert` — type-aware validation + flat write

**Files:**
- Modify: `src/Http/Controllers/DeviceCustomFieldController.php`

- [ ] **Step 1: Replace the `upsert` method body**

```php
    public function upsert(Request $request, Device $device)
    {
        Gate::authorize('admin');

        // Resolve to numeric id first so we can read the type for the value rule.
        $cfRef = $request->input('custom_field');
        $customField = is_numeric($cfRef)
            ? CustomField::find($cfRef)
            : CustomField::where('name', $cfRef)->first();

        $rules = [
            'custom_field' => ['required', $this->customFieldExists($device)],
            'value'        => ($customField && $customField->type === 'integer') ? 'required|integer' : 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->route('plugin.nmscustomfields.device.index', $device)->withErrors($validator);
        }

        $cfd = CustomFieldDevice::updateOrCreate(
            ['device_id' => $device->device_id, 'custom_field_id' => $customField->id],
            ['value' => $request->input('value')]
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'custom_field_device_id' => $cfd->id]);
        }
        return redirect()->route('plugin.nmscustomfields.device.index', $device);
    }
```

(Note: the response JSON key `custom_field_device_id` was already used here in v1 — unchanged in v2.)

- [ ] **Step 2: Verify**

```bash
php artisan tinker --execute='
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
$f = CustomField::firstOrCreate(["name"=>"_test_up"], ["type"=>"text"]);
$d = \App\Models\Device::value("device_id");
$cfd = CustomFieldDevice::updateOrCreate(["device_id"=>$d,"custom_field_id"=>$f->id],["value"=>"hello"]);
echo "value_text=" . $cfd->value_text . PHP_EOL;
$cfd->delete(); $f->delete();
'
```

Expected: `value_text=hello`.

- [ ] **Step 3: Commit**

```bash
git add src/Http/Controllers/DeviceCustomFieldController.php
git commit -m "feat!(#4): integer-typed validation + flat write in upsert()"
```

---

### Task 16: `DeviceCustomFieldController::bulkedit` — type-aware validation + bulk upsert (no N+1)

**Files:**
- Modify: `src/Http/Controllers/DeviceCustomFieldController.php`

- [ ] **Step 1: Replace the `bulkedit` method body**

The mutator on `CustomFieldDevice::setValueAttribute()` issues a SELECT against `custom_fields` per save() to look up the type. In a bulk loop with the same field, that's N+1. Resolve the type once here and write the typed columns directly, bypassing the mutator.

```php
    public function bulkedit(Request $request)
    {
        Gate::authorize('admin');

        $cfId   = $request->input('custom_field_id');
        $cfType = CustomField::query()->whereKey($cfId)->value('type'); // one SELECT

        $request->validate([
            'device_ids'         => 'required|array',
            'device_ids.*'       => 'integer',
            'custom_field_id'    => 'required|exists:custom_fields,id',
            'custom_field_value' => $cfType === 'integer' ? 'required|integer' : 'required',
        ]);

        $raw = $request->input('custom_field_value');
        $cols = $cfType === 'integer'
            ? ['value_int' => (int) $raw, 'value_text' => null]
            : ['value_text' => (string) $raw, 'value_int' => null];

        // Direct column write — bypasses setValueAttribute(), so no per-row CustomField lookup.
        foreach ($request->input('device_ids') as $deviceId) {
            CustomFieldDevice::updateOrCreate(
                ['device_id' => $deviceId, 'custom_field_id' => $cfId],
                $cols
            );
        }

        return response()->json(['success' => true]);
    }
```

- [ ] **Step 2: Verify a small bulk against tinker**

```bash
php artisan tinker --execute='
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
$f = CustomField::firstOrCreate(["name"=>"_test_bulk"], ["type"=>"integer"]);
$ids = \App\Models\Device::limit(2)->pluck("device_id")->all();
foreach ($ids as $d) {
    CustomFieldDevice::updateOrCreate(["device_id"=>$d,"custom_field_id"=>$f->id],["value"=>"123"]);
}
echo CustomFieldDevice::where("custom_field_id",$f->id)->whereIn("device_id",$ids)->sum("value_int") . PHP_EOL;
CustomFieldDevice::where("custom_field_id",$f->id)->delete(); $f->delete();
'
```

Expected: `246` (123 * 2).

- [ ] **Step 3: Commit**

```bash
git add src/Http/Controllers/DeviceCustomFieldController.php
git commit -m "feat!(#4): integer-typed validation + per-device updateOrCreate in bulkedit()"
```

---

### Task 17: `DeviceCustomFieldController::bulkDestroy` — single-table delete

**Files:**
- Modify: `src/Http/Controllers/DeviceCustomFieldController.php`

- [ ] **Step 1: Replace the `bulkDestroy` method body**

```php
    public function bulkDestroy(Request $request)
    {
        Gate::authorize('admin');

        $request->validate([
            'device_ids'      => 'required|string',
            'custom_field_id' => 'required|exists:custom_fields,id',
        ]);

        $deviceIds = explode(',', $request->input('device_ids'));

        CustomFieldDevice::where('custom_field_id', $request->input('custom_field_id'))
            ->whereIn('device_id', $deviceIds)
            ->delete();

        return response()->json(['success' => true]);
    }
```

- [ ] **Step 2: Commit**

```bash
git add src/Http/Controllers/DeviceCustomFieldController.php
git commit -m "refactor!: simplify bulkDestroy to a single-table delete"
```

---

### Task 18: `CustomFieldController::api_query` — filter on typed columns

**Files:**
- Modify: `src/Http/Controllers/CustomFieldController.php`

- [ ] **Step 1: Drop the `use CustomFieldValue` import (if present)**

Check:

```bash
grep -n "CustomFieldValue" src/Http/Controllers/CustomFieldController.php
```

If found, delete the import line.

- [ ] **Step 2: Rewrite the comparison/default operator branches**

Replace the `lte/gte/lt/gt` case AND the `default` case in `api_query()`:

```php
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

                        default:
                            $operator = $this->mapOperator($filter['operator']);
                            $valueCol = $isNumericField ? 'value_int' : 'value_text';
                            $subQuery->whereHas('customFieldDevices', function ($q) use ($filter, $operator, $valueCol) {
                                $q->whereHas('customField', fn ($cf) => $cf->where('name', $filter['field']))
                                  ->where($valueCol, $operator, $filter['value']);
                            });
                            break;
```

- [ ] **Step 3: Rewrite the per-device field mapping (around line 156)**

Replace the `map()` over the paginator collection:

```php
        $results = $paginator->getCollection()->map(function ($item) {
            $itemArray = $item->toArray();

            $customFields = CustomFieldDevice::where('device_id', $item->device_id)
                ->with('customField')
                ->get()
                ->map(function ($cfd) {
                    return [
                        'field_name' => $cfd->customField->name,
                        'value'      => $cfd->value, // string|null via accessor
                    ];
                });

            $itemArray['custom_fields'] = $customFields;
            return $itemArray;
        });
```

- [ ] **Step 4: Verify with curl**

```bash
# Existence:
curl -s -X POST -H "X-Auth-Token: <yourtoken>" -H 'Content-Type: application/json' \
  -d '{"filters":[{"field":"<your_int_field>","operator":"gte","value":"10"}]}' \
  http://localhost/api/v0/customfields/query | jq .
```

Expected: paginated response with `data[].custom_fields` containing the right field/value pairs.

- [ ] **Step 5: Commit**

```bash
git add src/Http/Controllers/CustomFieldController.php
git commit -m "feat!: filter api_query against typed value_int/value_text on flat CFD"
```

---

### Task 19: `Table/CustomFieldController` — start from CFD; rename payload key

**Files:**
- Modify: `src/Http/Controllers/Table/CustomFieldController.php`

- [ ] **Step 1: Replace `baseQuery` to start from `CustomFieldDevice`**

```php
    protected function baseQuery(Request $request): Builder|\Illuminate\Database\Query\Builder
    {
        $device_id = $request->input('device_id');
        return CustomFieldDevice::where('device_id', $device_id)
            ->with('customField');
    }
```

- [ ] **Step 2: Replace `formatResponse` to emit `custom_field_device_id`**

```php
    protected function formatResponse($paginator): JsonResponse
    {
        $rows = collect($paginator->items())->map(function ($cfd) {
            return [
                'custom_field_device_id' => $cfd->id,
                'custom_field_id'        => $cfd->customField->id,
                'custom_field_name'      => $cfd->customField->name,
                'custom_field_value'     => $cfd->value, // string|null
            ];
        });

        return response()->json([
            'current'  => $paginator->currentPage(),
            'rowCount' => $paginator->count(),
            'rows'     => $rows,
            'total'    => $paginator->total(),
        ]);
    }
```

- [ ] **Step 3: Drop the `use CustomFieldValue` import**

```bash
grep -n "CustomFieldValue" src/Http/Controllers/Table/CustomFieldController.php
```

Delete the import line if present.

- [ ] **Step 4: Commit**

```bash
git add src/Http/Controllers/Table/CustomFieldController.php
git commit -m "feat!(table): start from CFD; rename custom_field_value_id -> custom_field_device_id in payload"
```

---

### Task 20: `Table/CustomFieldValueController` — search/sort/format against CFD

**Files:**
- Modify: `src/Http/Controllers/Table/CustomFieldValueController.php`

- [ ] **Step 1: Replace `baseQuery`**

```php
    protected function baseQuery(Request $request): Builder|\Illuminate\Database\Query\Builder
    {
        return CustomFieldDevice::with(['device', 'customField']);
    }
```

- [ ] **Step 2: Replace `search`**

```php
    protected function search(?string $search, Builder $query, array $fields): Builder
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('device', function ($dq) use ($search) {
                    $dq->where('hostname', 'like', '%' . $search . '%')
                       ->orWhere('sysName', 'like', '%' . $search . '%');
                })
                ->orWhere('custom_field_device.value_text', 'like', '%' . $search . '%')
                ->orWhereRaw('CAST(custom_field_device.value_int AS CHAR) LIKE ?', ['%' . $search . '%']);
            })->distinct();
        }

        return $query;
    }
```

- [ ] **Step 3: Replace `formatItem`**

```php
    public function formatItem(Model $model): Model|array|Collection
    {
        return [
            'device_id'              => $model->device_id,
            'hostname'               => $model->device->hostname,
            'sysName'                => $model->device->sysName,
            'custom_field_id'        => $model->custom_field_id,
            'custom_field_value'     => $model->value, // accessor: string|null
            'custom_field_device_id' => $model->id,
        ];
    }
```

- [ ] **Step 4: Replace the `custom_field_value` case in `sort()`**

```php
                case 'custom_field_value':
                    // Order by COALESCE(value_text, CAST(value_int AS CHAR)).
                    $query->orderByRaw("COALESCE(custom_field_device.value_text, CAST(custom_field_device.value_int AS CHAR)) $direction");
                    break;
```

Also: the previous `select('custom_field_device.*')->distinct()` at the end of `sort()` is still required when devices join is in play — leave it as-is.

- [ ] **Step 5: Replace `formatExportRow`**

```php
    protected function formatExportRow(Model $item): array
    {
        return [
            $item->device_id,
            $item->device->hostname,
            $item->device->sysName,
            $item->custom_field_id,
            $item->value,
        ];
    }
```

- [ ] **Step 6: Drop `use CustomFieldValue` if present**

```bash
grep -n "CustomFieldValue" src/Http/Controllers/Table/CustomFieldValueController.php
```

Delete the import if present (the file lists it via `use DotMike\NmsCustomFields\Models\CustomField;` — that's `CustomField`, keep it). Verify carefully.

- [ ] **Step 7: Verify the table loads in browser**

Navigate to `/plugin/settings/nmscustomfields/customfield/devices?customfield=<id>` and check:
- Rows render
- Sort by Value works
- Search by value text works

- [ ] **Step 8: Commit**

```bash
git add src/Http/Controllers/Table/CustomFieldValueController.php
git commit -m "feat!(table): rewrite values table against CFD; rename payload key; sort/search on typed cols"
```

---

### Task 20a: Delete `CustomFieldValue` model

After Task 20 there is no remaining PHP reference to `CustomFieldValue`. Deleting it now keeps every intermediate commit autoloadable.

**Files:**
- Delete: `src/Models/CustomFieldValue.php`

- [ ] **Step 1: Confirm zero references remain**

```bash
grep -rn "CustomFieldValue\b\|customFieldValue\b" src/ resources/ routes/ database/
```

Expected: NO matches. (The blade files reference `custom_field_value_id` strings — that's a different identifier and stays in Tasks 21-22.) If anything matches, stop and resolve before deleting the file.

- [ ] **Step 2: Delete and commit**

```bash
git rm src/Models/CustomFieldValue.php
git commit -m "feat!: drop CustomFieldValue model (no remaining references)"
```

---

### Task 21: Update `device/customfields.blade.php` JS — rename payload key

**Files:**
- Modify: `resources/views/device/customfields.blade.php`

- [ ] **Step 1: Rename column id (line 28)**

Change:

```html
<th data-column-id="custom_field_value_id" data-identifier="true" data-type="numeric" data-visible="false">Field ID</th>
```

to:

```html
<th data-column-id="custom_field_device_id" data-identifier="true" data-type="numeric" data-visible="false">Field ID</th>
```

- [ ] **Step 2: Rename the three JS references (lines 64-65, 84, 91)**

In the `commands` formatter:

```js
"commands": function(column, row) {
    return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.custom_field_device_id + "\"><span class=\"glyphicon glyphicon-edit\"></span></button> " +
        "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.custom_field_device_id + "\"><span class=\"glyphicon glyphicon-trash\"></span></button>";
}
```

In the edit handler:

```js
var url = fieldEditUrl.replace(':device', device_id).replace(':customfield', row.custom_field_device_id);
```

In the delete handler:

```js
var url = fieldDeleteUrl.replace(':device', device_id).replace(':customfield', row.custom_field_device_id);
```

- [ ] **Step 3: Verify in browser**

Load `/device/<id>/customfields`. Edit and Delete buttons must navigate / DELETE to the right URL containing the CFD id. (Check the network tab.)

- [ ] **Step 4: Commit**

```bash
git add resources/views/device/customfields.blade.php
git commit -m "feat!(ui): rename custom_field_value_id -> custom_field_device_id in device customfields table"
```

---

### Task 22: Update `customfield/devices.blade.php` JS — rename payload key + type-aware input

**Files:**
- Modify: `resources/views/customfield/devices.blade.php`

- [ ] **Step 1: Rename JS payload keys (line 185, 187, 219-220)**

In the `commands` formatter:

```js
"commands": function(column, row) {
    let editUrl = fieldEditUrl.replace(':device', row.device_id).replace(':customfield', row.custom_field_device_id);
    return "<a href=\"" + editUrl + "\" class=\"btn btn-xs btn-default command-edit\"><span class=\"glyphicon glyphicon-edit\"></span > </a> " +
        "<button class=\"btn btn-xs btn-default command-delete\" x-data-device_id=\"" + row.device_id + "\" x-data-custom_field_device_id=\"" + row.custom_field_device_id + "\"><span class=\"glyphicon glyphicon-trash\"></span></button>";
}
```

In the delete handler:

```js
let device_id = $(this).attr('x-data-device_id');
let custom_field_device_id = $(this).attr('x-data-custom_field_device_id');
let url = fieldDeleteUrl.replace(':device', device_id).replace(':customfield', custom_field_device_id);
```

- [ ] **Step 2: Stash the server-rendered field type so initial modal opens are correct**

Near the top of the `@section('scripts')` block, alongside the existing `var fieldEditUrl = ...`, add:

```js
var initialFieldType = "{{ $customfield->type }}";
```

This gives JS the initial field's type before any user dropdown change.

- [ ] **Step 3: Add a helper and the `change` handler**

Inside the outer `$(function() { ... })` block, define:

```js
function applyValueInputType(type) {
    let isInt = (type === 'integer');
    $('#blkeddit-custom-field-value, #adddevice-custom-field-value')
        .attr('type', isInt ? 'number' : 'text');
}

// Initial swap based on the field currently selected at page render.
applyValueInputType(initialFieldType);
```

Then find the `init_select2(custom_field_id, ...)` block (around line 137). Right after the `.on("select2:select", ...)` handler for `custom_field_id`, append:

```js
custom_field_id.on('change', function() {
    // Pull the chosen field's type from the select2 data and update the modal inputs.
    let data = $(this).select2('data')[0];
    applyValueInputType(data ? data.type : 'text');
});
```

This depends on the `select.customfields` endpoint returning `type` in its JSON. Task 24 ensures that.

- [ ] **Step 4: Verify**

Open `/plugin/settings/nmscustomfields/customfield/devices?customfield=<integer_field_id>` directly. Open Bulk Edit modal WITHOUT changing the dropdown — input should already be `type="number"`. Switch the field dropdown to a `text` field — input should be `type="text"`. Re-open Add modal — same.

- [ ] **Step 5: Commit**

```bash
git add resources/views/customfield/devices.blade.php
git commit -m "feat!(ui): rename custom_field_value_id -> custom_field_device_id; swap modal input type for integer fields (initial + on-change)"
```

---

### Task 23: Update `device/edit.blade.php` — accessor read + conditional `type="number"`

**Files:**
- Modify: `resources/views/device/edit.blade.php`

- [ ] **Step 1: Replace the value input block (line 39)**

Change:

```blade
<input type="text" class="form-control" name="value" id="value" value="{{ $customdevicefield->customFieldValue->value ?? old('value') }}" required="required" autofocus="autofocus">
```

to:

```blade
<input
    type="{{ $customdevicefield->customField->type === 'integer' ? 'number' : 'text' }}"
    @if($customdevicefield->customField->type === 'integer') step="1" @endif
    class="form-control"
    name="value"
    id="value"
    value="{{ $customdevicefield->value ?? old('value') }}"
    required="required"
    autofocus="autofocus">
```

- [ ] **Step 2: Verify in browser**

Edit a custom field on a device — for an `integer` field, the input must be `type="number"` and reject typed letters on form submit. For a `text` field, behavior unchanged.

- [ ] **Step 3: Commit**

```bash
git add resources/views/device/edit.blade.php
git commit -m "feat!(ui)(#4): read \$cfd->value via accessor; render type=number for integer fields in edit form"
```

---

### Task 24: Update `device/create.blade.php` — conditional `type="number"` on field change + verify Select endpoint exposes `type`

**Files:**
- Modify: `resources/views/device/create.blade.php`
- Modify (if needed): `src/Http/Controllers/Select/CustomFieldController.php`

- [ ] **Step 1: Replace the value input (line 37)**

```blade
<input type="text" class="form-control" id="value" name="value" value="">
```

This stays `text` initially; JS swaps to `number` on field selection.

- [ ] **Step 2: Append JS to swap input type on field change**

After the `init_select2('#custom_field_id', ...)` call in the `scripts` section:

```js
$('#custom_field_id').on('change', function() {
    let data = $(this).select2('data')[0];
    let isInt = data && data.type === 'integer';
    let $value = $('#value');
    $value.attr('type', isInt ? 'number' : 'text');
    if (isInt) {
        $value.attr('step', '1');
    } else {
        $value.removeAttr('step');
    }
});
```

- [ ] **Step 3: Ensure `Select/CustomFieldController` emits `type` in its JSON response**

The current `baseQuery` selects `id, name` only. The parent `App\Http\Controllers\Select\SelectController` (in LibreNMS) projects rows to `{id, text}` for select2 — `type` will be dropped unless we explicitly include it.

First, read the parent to find the formatter hook (likely `formatResponse` or `formatItem`):

```bash
grep -n "formatResponse\|formatItem\|->text\|->id" $LIBRENMS_FOLDER/app/Http/Controllers/Select/SelectController.php
```

Then update the plugin's `Select/CustomFieldController.php`:

a) **Add `type` to the select list:**

```php
        $query = CustomField::select('id', 'name', 'type');
```

b) **Override the formatter to emit `type`.** Add this method to the class (matching whichever hook the parent exposes — most common shape):

```php
    protected function formatResponse($paginator)
    {
        return response()->json([
            'results' => collect($paginator->items())->map(fn ($cf) => [
                'id'   => $cf->id,
                'text' => $cf->name,
                'type' => $cf->type,
            ])->values(),
            'pagination' => ['more' => $paginator->hasMorePages()],
        ]);
    }
```

If the parent uses a different method name (e.g. `formatItem(Model $m): array`), adapt accordingly — the goal is that the JSON for each row includes a `type` key. Verify the override's signature matches the parent's.

c) **Verify the response shape:**

```bash
curl -s -u admin:admin "http://localhost:8000/plugin/settings/nmscustomfields/ajax/select/customfields?term=" | jq .
```

Expected: each `results[]` entry has `id`, `text`, AND `type`.

- [ ] **Step 4: Browser-verify**

Open `/device/<id>/customfields/devicefield/create`. Pick a `text` field — input stays text. Pick an `integer` field — input becomes `type="number"`. Submitting "abc" should 422 on server.

- [ ] **Step 5: Commit**

```bash
git add resources/views/device/create.blade.php src/Http/Controllers/Select/CustomFieldController.php
git commit -m "feat!(ui)(#4): swap value input type on field-dropdown change in create form; include type in select endpoint"
```

---

### Task 25: Version bump, CHANGELOG

**Files:**
- Modify: `composer.json`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump version**

In `composer.json`, change:

```json
  "version": "1.0.11",
```

to:

```json
  "version": "2.0.0",
```

- [ ] **Step 2: Add a 2.0.0 entry to `CHANGELOG.md`**

Prepend a section above the existing entries (keep the existing entries intact):

```markdown
## [2.0.0] - 2026-05-27

### Breaking
- Flattened EAV storage: `custom_field_values` table dropped. `custom_field_device` now stores `value_text TEXT NULL` and `value_int BIGINT NULL` directly.
- Dropped `DotMike\NmsCustomFields\Models\CustomFieldValue` and the `customFieldValue` Eloquent relation. Any third-party PHP code reaching into the namespace will break.
- Removed the dead `customFieldValue` Blade directive (the underlying function never existed).
- Bootgrid JSON payloads for both the device-customfields table and the customfield-devices table now return `custom_field_device_id` instead of `custom_field_value_id`. Public REST API contracts (URLs and `GET /devices/{device}/customfields` shape) are unchanged.
- `PATCH`/`PUT`/`POST` write paths now reject non-integer values for fields with `type === 'integer'` (HTTP 422 instead of silent text storage). Fixes #4.

### Added
- `UNIQUE(custom_fields.name)`.
- `UNIQUE(custom_field_device.device_id, custom_field_id)`.
- Filter indexes `(custom_field_id, value_int)` and `(custom_field_id, value_text(64))` on `custom_field_device`.
- Type-aware UI: integer-typed fields render `<input type="number" step="1">` in create/edit forms and bulk modals.

### Fixed
- #4: integer-typed custom fields no longer accept text. Validated server-side across all four write paths (single store, single update, upsert, bulk edit) and structurally enforced via the `CustomFieldDevice::setValueAttribute()` mutator.

### Migration notes
- Dedup is deterministic: lowest `id` survives for both `custom_fields.name` and `(device_id, custom_field_id)` duplicates.
- Backfill rule for `type === 'integer'` rows whose existing string value does not match `^-?\d+$`: the original string is preserved in `value_text` (not silently coerced), a warning is logged, and the field stays semantically broken until the user fixes it.
- The migration's `down()` recreates `custom_field_values` from CFD columns but does NOT preserve `custom_field_values.id`. Use for dev/test rollback only.
```

- [ ] **Step 3: Commit**

```bash
git add composer.json CHANGELOG.md
git commit -m "chore: bump to 2.0.0; document breaking changes from EAV flatten"
```

---

### Task 26: End-to-end smoke test

This task does no code edits. It runs the full plugin through the UI as a final gate before tagging the release. If anything is broken, return to the relevant task.

- [ ] **Step 1: Fresh migration on a throwaway DB**

> ⚠ **`migrate:fresh` drops EVERY table in the LibreNMS DB** — devices, users, settings, history. Only run on the devcontainer's throwaway DB or a dedicated test instance. Never on a real LibreNMS deployment. If unsure, skip this step and just run the targeted rollback shown in `docs/dev-environment.md` ("Resetting the DB").

```bash
cd $LIBRENMS_FOLDER
php artisan migrate:fresh
php artisan db:seed
php lnms --force -n migrate
# Re-add the snmpsim test device since migrate:fresh nuked it:
php lnms -n device:add -r 1161 -2 -c demo -- snmpsim
```

Expected: no errors.

- [ ] **Step 2: Create a text and an integer custom field**

Browser: `/plugin/settings/nmscustomfields/customfield/create` — create one of each. Confirm UNIQUE(name) by attempting a dupe (expect 422 / visible error).

- [ ] **Step 3: Assign both fields to a device**

`/device/<id>/customfields/devicefield/create`:
- Pick the integer field; input must be `type="number"`. Try "abc" — must reject. Submit "42".
- Pick the text field; input is `type="text"`. Submit "hello".

- [ ] **Step 4: Edit via the device page**

`/device/<id>/customfields`:
- Edit the integer field to "100" — saves. Try "garbage" — 422.
- Edit the text field to "world" — saves.

- [ ] **Step 5: Bulk edit via the field-devices page**

`/plugin/settings/nmscustomfields/customfield/devices?customfield=<integer_field_id>`:
- Select two devices, Bulk Edit, value `7` — saves. Try `xyz` — 422.

- [ ] **Step 6: REST query**

```bash
curl -s -X POST -u <user>:<pass> -H 'Content-Type: application/json' \
  -d '{"filters":[{"field":"<your_int_field_name>","operator":"gt","value":"5"}]}' \
  http://localhost/api/v0/customfields/query | jq '.data | length, .data[0].custom_fields'
```

Expected: at least the devices set in Step 5 appear; `custom_fields` includes the right pair.

- [ ] **Step 7: REST upsert + delete**

```bash
# Upsert by name
curl -s -X PUT -u <user>:<pass> -H 'Content-Type: application/json' \
  -d '{"custom_field":"<your_int_field_name>","value":"99"}' \
  http://localhost/api/v0/devices/<id>/customfields
# Invalid value (text into integer)
curl -s -X PUT -u <user>:<pass> -H 'Content-Type: application/json' \
  -d '{"custom_field":"<your_int_field_name>","value":"abc"}' \
  http://localhost/api/v0/devices/<id>/customfields
# Delete by route key
curl -s -X DELETE -u <user>:<pass> http://localhost/api/v0/devices/<id>/customfields/<your_int_field_name>
```

Expected: first 200; second 422 with `errors.value`; third 200.

- [ ] **Step 8: Device overview hook**

Navigate to `/device/<id>` — the "Custom Device Fields Plugin" panel must render the field names and values correctly. (Tests the `field_name`/`field_value` alias rewrite.)

- [ ] **Step 9: get_custom_field_value() helper**

```bash
php artisan tinker --execute='
$d = \App\Models\Device::find(<id>);
echo "int: "  . var_export(get_custom_field_value($d, "<your_int_field_name>"), true) . PHP_EOL;
echo "text: " . var_export(get_custom_field_value($d, "<your_text_field_name>"), true) . PHP_EOL;
'
```

Expected: both return strings (or null if unset). Integer comes through as a string per the API contract.

- [ ] **Step 10: Tag and push (no automatic commit — user decision)**

Stop here. Report results. If everything passes, the user can decide whether to tag `v2.0.0` and push.

---

## Self-review (run before handoff)

- [x] Every spec line from `docs/tmp/2026-05-27-flatten-eav-plan.md` is covered: schema (Tasks 1-7), model + helper (8, 9, 11), all 4 write paths and the read API (12-17, 18, 19, 20), legacy model delete (20a), every blade rename (21-23) and the type-aware UI (22-24), versioning + changelog (25), end-to-end (26).
- [x] No "TBD" / "fill in later" / "similar to Task N" placeholders. Each code step has the actual code.
- [x] Type consistency: `value_int` and `value_text` column names are stable across all tasks. The accessor returns `string|null` throughout (false-zero bug guarded with `!== null` checks). Bootgrid payload key is `custom_field_device_id` in every place it appears (Tasks 19, 20, 21, 22).
- [x] Open decisions from the original spec resolved in the "Locked decisions" block. If any are wrong, the executor should stop and surface — not silently change direction.
- [x] Migration build strategy: Tasks 1-6 only write the file (verify with `php -l`); Task 7 runs the migration end-to-end. No broken incremental migrate/rollback loop.
- [x] Reorder: `CustomFieldValue.php` is deleted in Task 20a (after Task 20 strips the last reference). Every intermediate commit autoloads.
- [x] Test environment documented in `docs/dev-environment.md`. Plan's verification steps assume the devcontainer is running.

## Known follow-ups not in this plan

- LibreNMS upstream filter-hook integration (consumes the flat schema this plan produces) — separate session.
- `ResolveCustomField::strtolower` bug — pre-existing, out of scope.
- `customfields-edit-modal.blade.php` orphan view — out of scope.
- Test suite — repo has none today; not adding one as part of this v2.0 cut.
