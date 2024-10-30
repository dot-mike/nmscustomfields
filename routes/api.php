<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['resolve.device', 'resolve.customdevicefield', 'api']], function () {
    Route::prefix('api')->namespace('DotMike\NmsCustomFields\Http\Controllers')->group(function () {
        Route::prefix('v0')->group(function () {
            Route::middleware(['can:admin'])->group(function () {
                Route::get('customfields', 'CustomFieldController@api_index');
                Route::get('customfields/query', 'CustomFieldController@api_query');

                Route::prefix('devices')->group(function () {
                    Route::get('{device}/customfields', 'DeviceCustomFieldController@index');
                    Route::get('{device}/customfields/{customdevicefield}', 'DeviceCustomFieldController@show');
                    Route::match(['put', 'post'], '{device}/customfields', 'DeviceCustomFieldController@upsert');
                    Route::delete('{device}/customfields/{customdevicefield}', 'DeviceCustomFieldController@destroy');
                    Route::patch('{device}/customfields/{customdevicefield}', 'DeviceCustomFieldController@update');
                });
            });
        });
    });
});
