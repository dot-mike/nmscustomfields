# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.2] - 2026-08-19

Re-release of 2.0.1. That tag was moved after it had been published, so Packagist
locked it to the original commit. No code changes.

## [2.0.1] - 2026-08-19

### Changed
- Table search matches whole words, so `ampere` no longer matches `deciAmpere`. Use `*` as a wildcard for the old substring behaviour: `amp*`, `*ampere*`, `pdu*timeout`.
- CSV export now uses LibreNMS' own bootgrid export (the download dropdown in the table toolbar) instead of a plugin-specific button.
- Tidied the toolbar layout on the per-field devices page.

## [2.0.0] - 2026-05-27

**Requires LibreNMS 26.5+.**

Storage was simplified: custom field values used to live in their own
table, now they sit directly on the device-field link. The REST API behaves the same.

Text and integer fields are now separate types. Integer fields only accept whole numbers.

### Breaking
- `custom_field_values` table dropped. Values now live on `custom_field_device` as `value_text` / `value_int`.
- `CustomFieldValue` model and `customFieldValue` relation removed.

### Fixed
- issue [#4](../../issues/4): integer fields no longer accept text. Rejected with 422 on all write paths.
- Bulk edit / "add to devices" form on the per-field page silently dropped `custom_field_id` because the hidden input's `name` attribute had a leading space.
- Device's custom fields page rendered "View [device.header] not found" on recent LibreNMS releases.

### Migration notes
The migration runs automatically and cleans up old data:
- If you have two fields with the same name, the older one is kept and the duplicate is removed.
- If a device has the same field set twice, the older link is kept.
- For each device+field, the **newest** value from the old table wins. Older history is discarded. 

## [1.0.11] - 2026-05-27

### Fixed

Fixed issue #1. Compatible with Librenms v26.5+.

## [1.0.10] - 2025-03-19

### Fixed

- Sorting the custom field values list on the plugin page now works correctly.
  The sorting was previously broken and would not sort the values

- Show which custom field is being edited in the modal.

### Added

- Added export functionality to the custom field values list on the plugin page.
  The export is available in CSV format and can be downloaded by clicking the "Export" button.


## [1.0.9] - 2024-11-20

### Changed

- Api for `/devices/{device}/customfields` now returns the proper value for the custom field id and name with the value.
  The response now includes the `id` and `name` keys.

- Api for `/customfields/query` has been refactored to use POST-method instead of GET.
  The method has been updated to accept a JSON body with a much more flexible query structure to allow for more complex queries.
  The response has been updated to reflect standard response format for paginated results.

## [1.0.8] - 2024-11-01

### Fixed

- Fixed a bug where searching for custom field values would not work correctly.
  It would return all devices containing the value instead of only the devices with the value set for the custom field.

## [1.0.7] - 2024-10-30

### Added

- Added a new API method for fetching all custom fields defined in the system
  The method is a GET to /customfields

- Added a new API method for querying all custom fields with optional filter
  The method is a GET to /customfields/query

## [1.0.6] - 2024-10-28

### Fixed

- Compatible with LibreNMS version >=24.9

## [1.0.5] - 2024-10-25

### Fixed

- Fixed PHP error preventing plugin to work and breaking librenms [#3](../../issues/3)

## [1.0.4] - 2024-10-18

### Added

- Show both hostname and sysName with clickable links in the custom field list on plugin page.

### Fixed

- Fix modal state reset [#2](../../issues/2)
- Fix bulk delete so it does not delet all values. Behaves correctly.
- Fix JS code to use let instead of var.

## [1.0.3] - 2024-10-18

### Added

- Added a new blade helper to retrieve the custom field value of a device.
  The helper is `get_custom_field_value($device, $custom_field_name)`.

## [1.0.2] - 2024-07-04

### Added

- Added a new API method for upserting a custom field to a device.
  The method is a POST/PUT to /devices/{device_id}/customfields
  and supports the key `custom_field` with the field name or the field id along with the value.

- Added a new API method for showing a custom field of a device.
  The method is a GET to /devices/{device_id}/customfields/{customdevicefield}

### Changed

- The API method for POST to /devices/{device_id}/customfields
  now supports upserting a custom field to a device.
  The key `custom_field_id` was renamed to `custom_field`
  and now supports the field name or the field id.

## [1.0.0] - 2024-07-03

Inital release
