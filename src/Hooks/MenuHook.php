<?php

namespace DotMike\NmsCustomFields\Hooks;

use App\Plugins\Hooks\MenuEntryHook;

class MenuHook extends MenuEntryHook
{
    public string $view = 'nmscustomfields::menu.main';
}
