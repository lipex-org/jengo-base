<?php

declare(strict_types=1);

namespace Jengo\Base\Installers\Traits;

trait HasClientAssets
{
    /**
     * Ensure the resources/js and resources/css directories exist.
     */
    protected function ensureClientDirectory(): void
    {
        $paths = [
            'resources/js',
            'resources/css',
        ];

        foreach ($paths as $path) {
            $fullPath = ROOTPATH . $path;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0777, true);
            }
        }
    }

    /**
     * Add a JS file to resources/js.
     */
    protected function addJSFile(string $filename, string $content): void
    {
        $this->ensureClientDirectory();
        
        $path = ROOTPATH . 'resources/js/' . ltrim($filename, '/');
        $this->ensureDirectory(dirname($path));

        $this->writeFile($path, $content);
    }

    /**
     * Add a CSS file to resources/css.
     */
    protected function addCSSFile(string $filename, string $content): void
    {
        $this->ensureClientDirectory();

        $path = ROOTPATH . 'resources/css/' . ltrim($filename, '/');
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
