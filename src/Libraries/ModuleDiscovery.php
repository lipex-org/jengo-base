<?php

declare(strict_types=1);

namespace Jengo\Base\Libraries;

use CodeIgniter\CLI\CLI;
use Config\Services;

class ModuleDiscovery
{
    private static ?array $cachedModules = null;

    /**
     * Discover modules and register their PSR-4 namespaces on the CodeIgniter autoloader.
     */
    public static function discoverAndRegister(): void
    {
        $modules = self::discover();

        $autoloader = Services::autoloader();
        foreach ($modules as $namespace => $path) {
            $ns = rtrim($namespace, '\\') . '\\';
            $autoloader->addNamespace($ns, $path);
        }

        // Register these modules to the CodeIgniter Autoload config at runtime
        $autoloadConfig = config('Autoload');
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
        $cachePath = ROOTPATH . '.jengo/cache/modules.php';

        // 1. Production Mode: check cache first
        if ($env === 'production') {
            if (file_exists($cachePath)) {
                self::$cachedModules = require $cachePath;
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
        $cacheDir = ROOTPATH . '.jengo/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // Add gitignore inside .jengo to prevent cache tracking
        $gitignorePath = ROOTPATH . '.jengo/.gitignore';
        if (!file_exists($gitignorePath)) {
            file_put_contents($gitignorePath, "cache/\n");
        }

        $cacheFile = $cacheDir . '/modules.php';
        $exported = var_export($modules, true);
        $content = <<<PHP
<?php
// Generated automatically by Jengo - Do not edit manually
return {$exported};
PHP;

        // Atomic file write using a temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'jengo_modules_cache_');
        file_put_contents($tempFile, $content);
        rename($tempFile, $cacheFile);
    }

    /**
     * Deletes the compiled modules cache file.
     */
    public static function clearCache(): void
    {
        $cacheFile = ROOTPATH . '.jengo/cache/modules.php';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}
