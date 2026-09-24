<?php

declare(strict_types=1);

namespace Jengo\Base\Events;

use CodeIgniter\Events\Events;
use Jengo\Base\Container\BindingScanner;
use Jengo\Base\Libraries\ModuleDiscovery;

class AppLifecycle
{
    private static bool $initialized = false;

    /**
     * Boot the unified Jengo application lifecycle.
     * Fires the 'init' event across both HTTP (pre_system) and CLI (pre_command) environments.
     */
    public static function boot(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // 1. Load compiled #[Bind] attribute bindings into the DI container
        BindingScanner::loadIntoContainer();

        // 2. Trigger the unified 'init' event for manual bindings & setups
        Events::trigger('init');

        // 3. Discover and register ecosystem modules
        ModuleDiscovery::discoverAndRegister();
    }

    /**
     * Reset lifecycle state (used in testing and long-running workers).
     */
    public static function reset(): void
    {
        self::$initialized = false;
    }
}
