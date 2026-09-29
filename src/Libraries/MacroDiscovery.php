<?php

declare(strict_types=1);

namespace Jengo\Base\Libraries;

use CodeIgniter\Autoloader\FileLocator;
use Config\Modules;
use Config\Services;

class MacroDiscovery
{
    private static bool $booted = false;

    /**
     * Discover and register all macros across app/Config/Macros.php and module/package configurations.
     */
    public static function discoverAndRegister(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        /** @var Modules $modulesConfig */
        $modulesConfig = config('Modules') ?? new Modules();

        // 1. Always load app-level Config/Macros.php if present
        $appMacroConfig = APPPATH . 'Config/Macros.php';
        if (is_file($appMacroConfig)) {
            $class = 'Config\\Macros';
            if (class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, 'register')) {
                    $instance->register();
                }
            } else {
                require_once $appMacroConfig;
            }
        }

        // 2. Discover module & package macros if enabled in Modules config
        if ($modulesConfig->shouldDiscover('macros')) {
            /** @var FileLocator $locator */
            $locator = Services::locator();
            $files = $locator->search('Config/Macros.php');

            foreach ($files as $file) {
                if ($file === $appMacroConfig) {
                    continue; // Already processed
                }

                $className = $locator->getClassname($file);
                if ($className !== '' && class_exists($className)) {
                    $instance = new $className();
                    if (method_exists($instance, 'register')) {
                        $instance->register();
                    }
                } else {
                    require_once $file;
                }
            }
        }
    }

    /**
     * Reset discovery status (used for testing and worker resets).
     */
    public static function reset(): void
    {
        self::$booted = false;
    }
}
