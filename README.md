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
