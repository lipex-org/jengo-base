<?php

declare(strict_types=1);

namespace Jengo\Base\Traits;

use Jengo\Base\Libraries\PackageManager;

trait HasClientAssets
{
    /**
     * Ensure the resources/js and resources/css directories exist.
     */
    protected function ensureClientDirectory(): void
    {
        $paths = [
            resource_path('js'),
            resource_path('css'),
        ];

        foreach ($paths as $fullPath) {
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0777, true);
            }
        }

        $this->ensureNodeModulesIgnored();
    }

    /**
     * Ensure node_modules/ is added to .gitignore.
     */
    protected function ensureNodeModulesIgnored(?string $path = null): void
    {
        PackageManager::ensureGitignore($path);
    }

    /**
     * Add a JS file to resources/js.
     */
    protected function addJSFile(string $filename, string $content): void
    {
        $this->ensureClientDirectory();

        $path = resource_path('js/' . ltrim($filename, '/'));
        $this->ensureDirectory(dirname($path));

        $this->writeFile($path, $content);
    }

    /**
     * Add a CSS file to resources/css.
     */
    protected function addCSSFile(string $filename, string $content): void
    {
        $this->ensureClientDirectory();

        $path = resource_path('css/' . ltrim($filename, '/'));
        $this->ensureDirectory(dirname($path));

        $this->writeFile($path, $content);
    }

    /**
     * Ensure a directory exists.
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
