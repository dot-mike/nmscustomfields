<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['resolve.device', 'api']], function () {
    Route::prefix('api')->namespace('DotMike\NmsCustomFields\Http\Controllers')->group(function () {
        Route::prefix('v0')->group(function () {
            Route::middleware(['can:admin'])->group(function () {
                Route::prefix('devices')->group(function () {
                    Route::get('{device}/customfields', 'DeviceCustomFieldController@index');
                    Route::post('{device}/customfields', 'DeviceCustomFieldController@store');
                    Route::delete('{device}/customfields/{customdevicefield}', 'DeviceCustomFieldController@destroy');
                });
            });
        });
    });
});
