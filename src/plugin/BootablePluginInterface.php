<?php

namespace greenmeteor\native\plugin;

use greenmeteor\native\Native;

/**
 * Optional plugin lifecycle hook, called once by {@see PluginManager::boot()}.
 */
interface BootablePluginInterface
{
    public function boot(Native $native): void;
}
