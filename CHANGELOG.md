# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
