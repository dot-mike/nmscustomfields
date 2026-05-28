<?php

namespace DotMike\NmsCustomFields\Providers;


use DotMike\NmsCustomFields\Hooks\MenuEntry;
use DotMike\NmsCustomFields\Hooks\DeviceOverview;

use LibreNMS\Interfaces\Plugins\PluginManagerInterface;
use LibreNMS\Interfaces\Plugins\Hooks\MenuEntryHook;
use LibreNMS\Interfaces\Plugins\Hooks\DeviceOverviewHook;

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
    public function boot(PluginManagerInterface $pluginManager): void
    {
        $pluginName = 'nmscustomfields';
        $pluginManager->publishHook($pluginName, MenuEntryHook::class, MenuEntry::class);
        $pluginManager->publishHook($pluginName, DeviceOverviewHook::class, DeviceOverview::class);

        // if plugin is disabled, don't boot it
        if (! $pluginManager->pluginEnabled($pluginName)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', $pluginName);
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', $pluginName);
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->loadHelpers();
    }

    protected function registerDynamicRelations(): void
    {
        Device::resolveRelationUsing('customFieldDevices', function ($device) {
            return $device->hasMany(
                \DotMike\NmsCustomFields\Models\CustomFieldDevice::class,
                'device_id',
                'device_id'
            );
        });
    }

    protected function registerBindings(): void
    {
        Route::middlewareGroup('resolve.device', [
            \DotMike\NmsCustomFields\Http\Middleware\ResolveDevice::class,
        ]);

        Route::middlewareGroup('resolve.customdevicefield', [
            \DotMike\NmsCustomFields\Http\Middleware\ResolveCustomField::class,
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

    protected function loadHelpers()
    {
        $helperPath = __DIR__ . '/../Helpers/custom_field_helpers.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
        }
    }
}
