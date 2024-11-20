# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
