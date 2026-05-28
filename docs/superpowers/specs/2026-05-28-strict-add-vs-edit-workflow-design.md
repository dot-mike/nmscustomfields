# Strict Add vs. Edit workflow — Design

**Status:** Approved 2026-05-28. Ready for implementation plan.

**Goal:** Fix the bug where the "Add device" modal on the Custom Field detail page silently overwrites the value of any device picked in the modal, even if that device already has the field — and remove the surrounding UX confusion where "Add device" stays clickable while rows are selected in the table.

## Problem (current state)

Page: `resources/views/customfield/devices.blade.php` — the per-field "Devices and Their Custom Fields" view.

Three stacked problems:

1. **Architectural smell.** Both `bulkEditModal` and `addDeviceModal` POST to the same route `plugin.nmscustomfields.devicefield.bulkedit`, which calls `DeviceCustomFieldController::bulkedit()`. That method uses `CustomFieldDevice::updateOrCreate(...)` — an unconditional upsert.
2. **Data-loss risk.** The Add device modal does no existence check before submitting. Picking a device that already has the field silently overwrites its value. No warning, no diff, no audit cue.
3. **UX confusion.** "Add device" remains enabled even when rows are selected in the table. Users intuit selection should be meaningful to whichever bulk action they click next; the button instead ignores selection and opens an empty form.

Additionally, the Bulk Edit modal carries a hidden footgun: removing a device from the pre-filled multi-select silently **deletes** that device's value (the value is implied by an exclusively-supplied `device_ids[]` against the same upsert backend... no, actually re-reading: the help text says "If you remove a device from the list, the custom field value will also be removed from that device" — but the controller only upserts the provided list; it does not delete unmentioned ones. So the help text is wrong, not the controller. Either way, the modal's "remove = delete" claim is confusing and not actually implemented. Worth correcting as part of this cleanup.)

## Decided workflow — "Strict Add vs. Edit"

Selection in the table is always exclusive: it means "operate on these existing rows". Adding new rows requires no selection.

### Button states

| Table state | Add device | Bulk Edit | Bulk Delete |
|---|---|---|---|
| No rows selected | **enabled** | disabled | disabled |
| ≥1 row selected | **disabled** (tooltip: *"Deselect rows to add new devices"*) | enabled | enabled |

Existing JS already toggles `Bulk Edit` and `Bulk Delete` based on selection; this design extends the same handlers to toggle `Add device` in the opposite direction.

### Add device modal — `addDeviceModal`

- **Title:** "Add custom field to new devices"
- **Device picker** (Select2 multi-select): populated by a **new plugin AJAX endpoint** that returns only devices that don't yet have this `custom_field_id`. Server-filtered — what you can't see, you can't accidentally hit.
- **Value input:** as today.
- **Submit:** new endpoint `POST plugin.nmscustomfields.devicefield.bulkstore` (create-only).
  - Defense in depth: server rejects with `422` if any of the submitted `device_ids` already have this field. Error response names the conflicting device(s). Covers the stale-cache / race window where the dropdown's exclusion list was generated before another admin added the same device.

### Bulk Edit modal — `bulkEditModal`

- **Title:** unchanged — "Bulk Edit Devices - &lt;field name&gt;".
- **Device list:** pre-filled with the rows selected in the table. **Removable, not addable** — Select2 initialized without the AJAX source (no `ajax:` option), so the dropdown stays empty and no search hits the server. Users can deselect entries but cannot type to add new ones.
- **"Remove from list" semantics:** "skip this device, leave its value alone". This **changes the existing help-text claim** that removing a device deletes its value. Want to delete? Use Bulk Delete.
- **Multiple-values warning:** unchanged (existing alert when selected rows hold different values).
- **Submit:** new endpoint `POST plugin.nmscustomfields.devicefield.bulkupdate` (update-only).
  - Defense in depth: server rejects with `422` if any of the submitted `device_ids` doesn't already have this field. Should never happen via the UI (the list comes from the table, which only shows rows that have the field) but covers concurrent deletes.

### Server endpoints

| Action | Old route → method | New route → method | Semantics |
|---|---|---|---|
| Add to devices | `POST bulkedit` → `bulkedit()` | `POST bulkstore` → `bulkstore()` | Insert only. 422 if any device already has the field. |
| Edit existing | `POST bulkedit` → `bulkedit()` | `POST bulkupdate` → `bulkupdate()` | Update only. 422 if any device doesn't have the field. |
| Bulk delete | `POST bulkdestroy` → `bulkDestroy()` | unchanged | Delete. |

The `bulkedit` route and `DeviceCustomFieldController::bulkedit()` are **removed**. v2.0 is the breaking-change vehicle, this lines up naturally.

### New plugin AJAX endpoint

- `GET ajax/select/devices-without-field?custom_field_id={id}` (named `plugin.nmscustomfields.select.devices-without-field`)
- Controller: `src/Http/Controllers/Select/DeviceWithoutFieldController.php`, extends host `App\Http\Controllers\Select\SelectController` (same pattern as the existing `Select/CustomFieldController`).
- Returns devices that don't have a `custom_field_device` row for the given `custom_field_id`.
- Search fields: `hostname`, `sysName` — matches the host's `DeviceController`.
- Required input: `custom_field_id` (validation rule: `required|exists:custom_fields,id`).
- Used by `addDeviceModal`'s Select2 (replaces the current `ajax.select.device` source).

## File map

**Modified:**
- `routes/web.php` — drop `bulkedit`, add `bulkstore`, `bulkupdate`, add `ajax/select/devices-without-field`.
- `src/Http/Controllers/DeviceCustomFieldController.php` — remove `bulkedit()`, add `bulkstore()` and `bulkupdate()` with strict pre-validators.
- `resources/views/customfield/devices.blade.php` — Add device button disable-on-selection, switch addDeviceModal Select2 source to new endpoint, change Bulk Edit help text + non-addable picker, point both forms at new routes.
- `CHANGELOG.md` — note breaking endpoint rename under the current unreleased entry (the v2.0 entry on this branch).

**Created:**
- `src/Http/Controllers/Select/DeviceWithoutFieldController.php`.
- `tests/Feature/BulkStoreTest.php` — feature test: happy path inserts; 422 when any device already has the field; admin gate.
- `tests/Feature/BulkUpdateTest.php` — feature test: happy path updates; 422 when any device lacks the field; admin gate.

**Unchanged (explicit non-goals):**
- `resources/views/device/create.blade.php`, `resources/views/device/edit.blade.php` — single-device flows already correctly use `store`/`update`.
- `bulkDestroy()` and its modal binding.
- Export CSV.

## Validation rules (new endpoints)

Shared input shape (mirrors current `bulkedit`):

```
device_ids:           required|array
device_ids.*:         integer
custom_field_id:      required|exists:custom_fields,id
custom_field_value:   {CustomField::valueRule()}  // existing helper
```

Additional, endpoint-specific:

- **`bulkstore`:** after generic validation, query `custom_field_device` for `(custom_field_id, device_id IN device_ids)`. If any hit, fail with `422` and an error message listing the conflicting device IDs (hostnames resolved if cheap; otherwise just IDs).
- **`bulkupdate`:** after generic validation, query the same. If `count(found) < count(device_ids)`, fail with `422` listing the missing device IDs.

Both then perform their writes in the same loop shape as today's `bulkedit` (using `CustomFieldDevice::columnsFor($customField->type, $value)` to keep typed-column behavior), but with the operation locked to insert-only (`create`) or update-only (`update` on the found rows).

## Error responses (AJAX)

JSON shape consistent with the existing `bulkedit` validator failure (Laravel default 422 with `errors`). Conflict errors live under a domain key, e.g.:

```json
{
  "message": "Some devices already have this field.",
  "errors": {
    "device_ids": ["Already has the field: device #12, device #34"]
  }
}
```

Client-side: display the message in the modal's existing `#alert-container` (already wired for the multiple-values warning).

## Test plan

- **Unit / Feature:**
  - `bulkstore` 200 on fresh devices; rows created with correct typed column.
  - `bulkstore` 422 when one device already has the field; no rows touched.
  - `bulkupdate` 200 when all devices have the field; rows updated.
  - `bulkupdate` 422 when one device lacks the field; no rows touched.
  - Both endpoints reject non-admin via the `admin` gate.
  - Integer field type still routed to `value_int`; text to `value_text` (sanity, covered by the typed-column helper but worth one assertion).
  - `DeviceWithoutFieldController` excludes devices that have the field; includes devices that don't.
- **Manual smoke (devcontainer):**
  - With no rows selected: Add device enabled; picker omits already-assigned devices.
  - With rows selected: Add device disabled with tooltip; Bulk Edit's picker is pre-filled and not expandable.
  - Removing a device from Bulk Edit's picker and saving leaves that device's value untouched.
  - `bulkedit` route returns 404 (removed) — quick `curl` confirms.
- **Smoke render test** (`tests/Feature/ViewRenderSmokeTest.php`) stays green for the changed view.

## Out of scope

- Per-device Add Field flow on the device's own custom-fields page (`device/customfields.blade.php`) — already correctly handled by `store()` which already errors on duplicate field.
- Granular per-row "switch to edit" link from the Add modal when a conflict is hit. The 422 with device names is sufficient for now; users dismiss the modal and use Bulk Edit instead.
- Audit log / change history.

## Notes for the implementation plan

- The four `.blade.php` view files currently dirty in `git status` (customfield/create, customfield/edit, device/create, device/edit) are part of a separate v2.0 work-in-progress and are **not** in scope. Coordinate sequencing: this design assumes those land first or are at least non-conflicting.
- Existing `multipleValuesWarning` UX in `bulkEditModal` should be preserved verbatim.
- Keep CHANGELOG explicit about the route rename — anyone calling `POST .../bulkedit` directly (custom scripts) will break.
