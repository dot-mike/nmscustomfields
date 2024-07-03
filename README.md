# nmscustomfields

*nmscustomfields* - A LibreNMS plugin package to add support for creating custom fields for devices.

## Installation

Go to the LibreNMS base directory and run the following commands as librenms user:

```bash
./lnms plugin:add dot-mike/nmscustomfields
php artisan migrate --path=vendor/dot-mike/nmscustomfields/database/migrations
php artisan route:clear
php lnms --force -n migrate
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

