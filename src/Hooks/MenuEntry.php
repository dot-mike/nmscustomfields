<?php

namespace DotMike\NmsCustomFields\Hooks;

use LibreNMS\Interfaces\Plugins\Hooks\MenuEntryHook;

class MenuEntry implements MenuEntryHook
{
    // this will determine if the menu entry should be shown to the user
    public function authorize(\Illuminate\Contracts\Auth\Authenticatable $user, array $settings = []): bool
    {
        // Allow users with GLOBAL_READ or better
        //return $user->can('global-read');

        return true;
    }

    /**
     * @return array{0: string, 1: array<string, string[]>}
     */
    public function handle(string $pluginName): array
    {
        return ["$pluginName::menu", []];
    }
}
