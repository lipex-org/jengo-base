<?php

declare(strict_types=1);

namespace Jengo\Base\Vite;

/**
 * Recursively resolves dependencies (CSS, imported chunks) from the manifest.
 */
class AssetResolver
{
    protected Manifest $manifest;
    protected string $buildDirectory;

    protected array $processedChunks = [];
    protected array $scripts = [];
    protected array $styles = [];
    protected array $preloads = [];

    public function __construct(Manifest $manifest, string $buildDirectory = 'dist/')
    {
        $this->manifest = $manifest;
        $this->buildDirectory = rtrim($buildDirectory, '/') . '/';
    }

    /**
     * Resolves all assets for the given entrypoints.
     */
    public function resolve(array $entrypoints): array
    {
        $this->reset();

        foreach ($entrypoints as $entrypoint) {
            $this->resolveChunk($entrypoint, true);
        }

        return [
            'scripts' => array_values(array_unique($this->scripts)),
            'styles' => array_values(array_unique($this->styles)),
            'preloads' => array_values(array_unique($this->preloads)),
        ];
    }

    /**
     * Recursively resolves a chunk and its dependencies.
     */
    protected function resolveChunk(string $entrypoint, bool $isEntry = false): void
    {
        if (isset($this->processedChunks[$entrypoint])) {
            return;
        }

        $this->processedChunks[$entrypoint] = true;

        $chunk = $this->manifest->getChunk($entrypoint);

        if (!$chunk) {
            return;
        }

        $assetPath = base_url($this->buildDirectory . $chunk['file']);

        if (!empty($chunk['isEntry']) || $isEntry) {
            if (str_ends_with($chunk['file'], '.js')) {
                $this->scripts[] = $assetPath;
            } elseif (str_ends_with($chunk['file'], '.css')) {
                $this->styles[] = $assetPath;
            }
        } else {
            // It's an imported chunk, we should preload it
             if (str_ends_with($chunk['file'], '.js')) {
                $this->preloads[] = $assetPath;
             }
        }

        // Resolve CSS
        if (!empty($chunk['css'])) {
            foreach ($chunk['css'] as $cssFile) {
                $this->styles[] = base_url($this->buildDirectory . $cssFile);
            }
        }

        // Recursively resolve imports
        if (!empty($chunk['imports'])) {
            foreach ($chunk['imports'] as $importedChunk) {
                $this->resolveChunk($importedChunk, false);
            }
        }
    }

    /**
     * Resets the resolver state.
     */
    protected function reset(): void
    {
        $this->processedChunks = [];
        $this->scripts = [];
        $this->styles = [];
        $this->preloads = [];
    }
}
