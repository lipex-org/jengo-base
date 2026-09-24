<?php

declare(strict_types=1);

namespace Jengo\Base\Libraries;

use Config\Autoload;
use Config\Services;

class ModuleDiscovery
{
    private static ?array $cachedModules = null;

    public function discoverAndRegisterInTesting(Autoload $autoloadConfig): void
    {
        helper('Jengo\Base\Helpers\jengo');

        if (isTesting()) {
            self::discoverAndRegister($autoloadConfig);
        }
    }


    /**
     * Discover modules and register their PSR-4 namespaces on the CodeIgniter autoloader.
     */
    public static function discoverAndRegister(?Autoload $autoloadConfig = null): void
    {
        $modules = self::discover();

        $autoloader = Services::autoloader();
        foreach ($modules as $namespace => $path) {
            $ns = rtrim($namespace, '\\') . '\\';
            $autoloader->addNamespace($ns, $path);
        }

        // Register these modules to the CodeIgniter Autoload config at runtime
        $autoloadConfig ??= config('Autoload');
        if ($autoloadConfig) {
            foreach ($modules as $namespace => $path) {
                $ns = rtrim($namespace, '\\') . '\\';
                $autoloadConfig->psr4[$ns] = $path;
            }
        }
    }

    /**
     * Retrieve discovered modules list (cached or live depending on environment).
     */
    public static function discover(): array
    {
        if (self::$cachedModules !== null) {
            return self::$cachedModules;
        }

        $env = env('CI_ENVIRONMENT', 'production');
        $cacheFile = 'cache/modules.php';

        // 1. Production Mode: check cache first
        if ($env === 'production') {
            if (\Jengo\Base\Support\JengoDirectory::has($cacheFile)) {
                self::$cachedModules = \Jengo\Base\Support\JengoDirectory::readPhpArray($cacheFile);
                return self::$cachedModules;
            }

            // Fallback to scanning, load namespaces, and trigger cache compilation
            $modules = self::scanModulesDirectory();
            self::compileCache($modules);
            self::$cachedModules = $modules;
            return $modules;
        }

        // 2. Development Mode: always scan live
        $modules = self::scanModulesDirectory();
        self::$cachedModules = $modules;
        return $modules;
    }

    /**
     * Scan the modules/ directory recursively for valid standalone and grouped modules.
     */
    public static function scanModulesDirectory(): array
    {
        $modulesDir = ROOTPATH . 'modules';
        if (!is_dir($modulesDir)) {
            return [];
        }

        $modules = [];

        try {
            self::scanRecursive($modulesDir, $modulesDir, $modules);
        } catch (\Throwable $e) {
            // Fail-Safe Processing: log rather than crashing
            log_message('error', '[Jengo Module Discovery] ' . $e->getMessage());
        }

        return $modules;
    }

    /**
     * Recursively scan directories to find module roots.
     */
    private static function scanRecursive(string $baseDir, string $currentDir, array &$modules): void
    {
        if (self::isValidModule($currentDir)) {
            // Calculate relative path from baseDir
            $relativePath = ltrim(substr($currentDir, strlen($baseDir)), '/\\');
            // Convert path slashes to namespace backslashes
            $namespacePart = str_replace(['/', '\\'], '\\', $relativePath);
            $modules["Modules\\{$namespacePart}"] = $currentDir;
            return;
        }

        $iterator = new \DirectoryIterator($currentDir);
        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }
            self::scanRecursive($baseDir, $item->getRealPath(), $modules);
        }
    }

    /**
     * Checks if a directory contains standard module folders or a marker file.
     */
    private static function isValidModule(string $path): bool
    {
        return is_dir($path . '/Config') ||
            is_dir($path . '/Controllers') ||
            file_exists($path . '/Module.php');
    }

    /**
     * Programmatically compile namespace mapping and write it to .jengo/cache/modules.php.
     */
    public static function compileCache(array $modules): void
    {
        \Jengo\Base\Support\JengoDirectory::writePhpArray('cache/modules.php', $modules);
    }

    /**
     * Deletes the compiled modules cache file.
     */
    public static function clearCache(): void
    {
        \Jengo\Base\Support\JengoDirectory::delete('cache/modules.php');
    }
}
