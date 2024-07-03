<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['resolve.device', 'web', 'auth'], 'guard' => 'auth'], function () {
    Route::namespace('DotMike\NmsCustomFields\Http\Controllers')->group(function () {

        // named routes uses prefix plugin.nmscustomfields.
        Route::name('plugin.nmscustomfields.')->group(function () {

            // Admin routes
            Route::prefix('plugin/settings/nmscustomfields')->group(function () {
                Route::get('/', 'PluginAdminController@index')->name('index');
                Route::get('/customfield/devices', 'CustomFieldController@devices')->name('customfield.devices');
                // named route: plugin.nmscustomfields.customfield.
                Route::resource('customfield', 'CustomFieldController');

                // Bulk actions
                Route::post('bulkedit', 'DeviceCustomFieldController@bulkEdit')->name('devicefield.bulkedit');
                Route::post('bulkdestroy', 'DeviceCustomFieldController@bulkDestroy')->name('devicefield.bulkdestroy');

                // Ajax routes
                Route::prefix('ajax')->group(function () {
                    Route::prefix('select')->namespace('Select')->group(function () {
                        Route::get('customfields', 'CustomFieldController')->name('select.customfields');
                    });

                    Route::prefix('table')->namespace('Table')->group(function () {
                        Route::post('customfields', 'CustomFieldController')->name('table.customfields');
                        Route::post('customfieldvalues', 'CustomFieldValueController')->name('table.customfieldvalues');
                    });
                });
            });

            // Device routes
            Route::prefix('device/{device}/customfields')->group(function () {
                Route::get('/', 'DeviceCustomFieldController@index')->name('device.index');
                // named route: plugin.nmscustomfields.devicefield.
                Route::resource('devicefield', 'DeviceCustomFieldController')->parameters(['devicefield' => 'customdevicefield']);
            });
        });
    });
});
