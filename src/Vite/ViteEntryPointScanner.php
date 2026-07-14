<?php

declare(strict_types=1);

namespace Jengo\Base\Vite;

use Jengo\Base\Vite\Repositories\ViteRepository;

class ViteEntryPointScanner
{
    public function scan(): array
    {
        $searchPaths = (new ViteRepository())->loadSearchPaths();

        $entrypoints = [];
        foreach ($searchPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $directory = new \RecursiveDirectoryIterator($path);
            $iterator = new \RecursiveIteratorIterator($directory);

            foreach ($iterator as $file) {
                // Match: something.entrypoint.ts or something.entrypoint.scss
                if (preg_match('/^(.+)\.entrypoint\.(ts|tsx|vue|svelte|js|jsx|scss|css)$/', $file->getFilename())) {
                    // Create a relative path from ROOTPATH for Vite to consume
                    $relativePath = str_replace(ROOTPATH, '', $file->getRealPath());
                    $entrypoints[] = $relativePath;
                }
            }
        }
        return $entrypoints;
    }
}