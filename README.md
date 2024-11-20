# nmscustomfields

_nmscustomfields_ - A LibreNMS plugin package to add support for creating custom fields for devices.

## Installation

### Without Docker

Go to the LibreNMS base directory and run the following commands as librenms user:

```bash
./lnms plugin:add dot-mike/nmscustomfields
php artisan migrate --path=vendor/dot-mike/nmscustomfields/database/migrations
php artisan route:clear
php lnms --force -n migrate
```

### With Docker

If you are using LibreNMS with Docker, you can install the plugin by customizing the Dockerfile.

Example Dockerfile:

```Dockerfile
ARG VERSION=librenms:23.8.2
FROM librenms/$VERSION

RUN apk --update --no-cache add -t build-dependencies php-xmlwriter
RUN mkdir -p "${LIBRENMS_PATH}/vendor"

RUN echo $'#!/usr/bin/with-contenv sh\n\
set -e\n\
if [ "$SIDECAR_DISPATCHER" = "1" ] || [ "$SIDECAR_SYSLOGNG" = "1" ] || [ "$SIDECAR_SNMPTRAPD" = "1" ]; then\n\
  exit 0\n\
fi\n\
chown -R librenms:librenms "${LIBRENMS_PATH}/composer.json" "${LIBRENMS_PATH}/composer.lock" "${LIBRENMS_PATH}/vendor"\n\
lnms plugin:add dot-mike/nmscustomfields\n\
php artisan route:clear\n\
php lnms --force -n migrate\n\
' > /etc/cont-init.d/99-nmscustomfields.sh
```

## Usage

To get started, open LibreNMS and enable the plugin by navigating to Overview->Plugins->Plugins Admin and enable the `nmscustomfields` plugin.

### Add and manage Custom Fields

Navigate to Overview->Plugins->Custom Fields Plugin to start adding custom fields that will be available for devices.
Here you will also be able to manage the field values in bulk.

### Editing Custom Fields for a Device

Navigate to a device page and you will see the custom fields section where you will find a link to edit the custom fields for the device.

## Screenshots

![Edit Custom Fields](/screenshots/edit-custom-fields.png?raw=true)
![Edit Custom Field Values](/screenshots/edit-custom-field-values.png?raw=true)
![Device Custom Fields](/screenshots/device-custom-fields.png?raw=true)

## Helper functions

The `get_custom_field_value` helper is used to retrieve a custom field's value for a specific device.

### Usage

You can use the `get_custom_field_value` helper in your Blade templates to access custom fields associated with a device. This is especially useful for displaying dynamic content based on custom field values.

### Syntax

```php
get_custom_field_value(Device $device, string $fieldName): string|null
```

### Example Usage

```php
{{ get_custom_field_value($device, 'custom_field_name') }}

@if ('yes' == get_custom_field_value($device, 'description'))
<b>hello</b>
@endif
```

## API Documentation

The plugin also adds API endpoints to manage the custom fields for devices.

### `Devices` API

#### List Custom Fields

```
GET /api/v0/devices/{device}/customfields
```

- **Description**: Retrieves a list of custom fields for a specified device.
- **Parameters**:
  - `{device}`: The identifier of the device.

#### Show Custom Field

```
GET /api/v0/devices/{device}/customfields/{customdevicefield}
```

- **Description**: Retrieves details of a specific custom field for a specified device.
- **Parameters**:
  - `{device}`: The identifier of the device.
  - `{customdevicefield}`: The identifier of the custom field.

#### Delete Custom Field

```
DELETE /api/v0/devices/{device}/customfields/{customdevicefield}
```

- **Description**: Deletes a specific custom field for a specified device.
- **Parameters**:
  - `{device}`: The identifier of the device.
  - `{customdevicefield}`: The identifier of the custom field.

#### Update Custom Field

```
PATCH /api/v0/devices/{device}/customfields/{customdevicefield}
```

- **Description**: Partially updates a specific custom field for a specified device.
- **Parameters**:
  - `{device}`: The identifier of the device.
  - `{customdevicefield}`: The identifier of the custom field.
  ```json
  {
    "value": "value"
  }
  ```

#### Upsert Custom Field

```
PUT / POST /api/v0/devices/{device}/customfields
```

- **Description**: Creates or updates a custom field for a specified device.
- **Parameters**:
  - `{device}`: The identifier of the device.
  - Request body containing the custom field data.
  ```json
  {
    "custom_field": "field_name or field_id",
    "value": "value"
  }
  ```


### `customfields` API

#### List All defined Custom Fields

```
GET /api/v0/customfields
```

- **Description**: Retrieves a list of custom fields defined in the system.

#### Query Custom Fields

```
POST /api/v0/customfields/query
```

- **Description**: Retrieves a list of custom fields with the specified filter in JSON format.
- **Parameters**:
  - Request body containing the filter data.
```json
{
     "filters": [
         {"field": "description", "operator": "eq", "value": "testing2"},
         {"field": "isok", "operator": "eq", "value": "exists"},
         {"field": "nonexistant", "operator": "eq", "value": "not_exists"},

     ],
     "fields": ["device_id", "hostname", "sysName"],
     "perPage": 15,
     "page": 1
 }'
```

Possible parameters for the filter are:
- `field`: The field name to filter on.
- `operator`: The operator to use for the filter. Possible values are `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `not_like`, `exists`, `not_exists`.
- `value`: The value to filter on if the operator is not `exists` or `not_exists`.

Example output:
```json
{
    "current_page": 1,
    "data": [
        {
            "device_id": 1,
            "device": {
                "device_id": 1,
                "hostname": "snmpsim",
                "sysName": "zeus",
                "ip": null,
                "display": null,
                "overwrite_ip": null,
                "disabled": 0,
                "ignore": 0
            },
            "custom_fields": [
                {
                    "field_name": "description",
                    "value": "testing2"
                },
                {
                    "field_name": "isok",
                    "value": "yes"
                }
            ]
        }
    ],
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
}
```