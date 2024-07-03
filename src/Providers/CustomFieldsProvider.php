<?php

namespace DotMike\NmsCustomFields\Providers;


use DotMike\NmsCustomFields\Hooks\MenuHook;
use DotMike\NmsCustomFields\Hooks\DeviceHook;

use App\Plugins\Hooks\MenuEntryHook;
use App\Plugins\Hooks\DeviceOverviewHook;
use App\Plugins\PluginManager;
use App\Models\Device;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;

class CustomFieldsProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerDynamicRelations();
        $this->registerBindings();
    }

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(PluginManager $manager): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'nmscustomfields');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'nmscustomfields');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $manager->publishHook('nmscustomfields', MenuEntryHook::class, MenuHook::class);
        $manager->publishHook('nmscustomfields', DeviceOverviewHook::class, DeviceHook::class);
    }

    protected function registerDynamicRelations(): void
    {

        Device::resolveRelationUsing('customFields', function ($device) {
            return $device->belongsToMany(
                \DotMike\NmsCustomFields\Models\CustomField::class,
                'custom_field_device',
                'device_id',
                'custom_field_id'
            )->withPivot('device_id');
        });

        Device::resolveRelationUsing('customFieldDevices', function ($device) {
            return $device->hasMany(
                \DotMike\NmsCustomFields\Models\CustomFieldDevice::class,
                'device_id',
                'device_id'
            );
        });

        Device::resolveRelationUsing('customFieldValues', function ($device) {
            return $device->hasManyThrough(
                \DotMike\NmsCustomFields\Models\CustomFieldValue::class,
                \DotMike\NmsCustomFields\Models\CustomFieldDevice::class,
                'device_id', // Foreign key on custom_field_device table...
                'custom_field_device_id', // Foreign key on custom_field_values table...
                'device_id', // Local key on devices table...
                'id'  // Local key on custom_field_device table...
            );
        });

        Device::resolveRelationUsing('customFieldValuesWithNames', function ($device) {
            return $device->customFieldValues()
                ->join('custom_field_device as cfd', 'custom_field_values.custom_field_device_id', '=', 'cfd.id')
                ->join('custom_fields', 'cfd.custom_field_id', '=', 'custom_fields.id')
                ->select('custom_fields.name as field_name', 'custom_field_values.value as field_value');
        });
    }

    protected function registerBindings(): void
    {
        Route::middlewareGroup('resolve.device', [
            \DotMike\NmsCustomFields\Http\Middleware\ResolveDevice::class,
        ]);

        View::composer('nmscustomfields::*', function ($view) {
            $view->with('nmscustomfields_version', $this->getVersion());
        });
    }

    protected function getVersion(): string
    {
        $composerFile = __DIR__ . '/../../composer.json';
        $composerData = json_decode(file_get_contents($composerFile), true);
        return $composerData['version'] ?? 'unknown';
    }
}
