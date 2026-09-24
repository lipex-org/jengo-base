<?php

declare(strict_types=1);

namespace Jengo\Base\Events;

use CodeIgniter\Events\Events;
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

        // 1. Trigger the unified 'init' event for container bindings & setup
        Events::trigger('init');

        // 2. Discover and register ecosystem modules
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
