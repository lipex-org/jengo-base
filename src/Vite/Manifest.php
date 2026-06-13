<?php

declare(strict_types=1);

namespace Jengo\Base\Vite;

use RuntimeException;

/**
 * Parses and caches the Vite manifest file.
 */
class Manifest
{
    protected array $manifest = [];
    protected string $manifestPath;

    public function __construct(string $manifestPath)
    {
        $this->manifestPath = $manifestPath;
        $this->loadManifest();
    }

    /**
     * Loads the manifest file and decodes it.
     */
    protected function loadManifest(): void
    {
        if (!is_file($this->manifestPath)) {
            throw new RuntimeException("Vite manifest not found at: {$this->manifestPath}. Did you run the build command?");
        }

        $content = file_get_contents($this->manifestPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read Vite manifest at: {$this->manifestPath}");
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in Vite manifest at: {$this->manifestPath}");
        }

        $this->manifest = $decoded;
    }

    /**
     * Returns the chunk information for a given entrypoint.
     */
    public function getChunk(string $entrypoint): ?array
    {
        return $this->manifest[$entrypoint] ?? null;
    }

    /**
     * Checks if an entrypoint exists in the manifest.
     */
    public function has(string $entrypoint): bool
    {
        return isset($this->manifest[$entrypoint]);
    }

    /**
     * Returns the entire manifest array.
     */
    public function all(): array
    {
        return $this->manifest;
    }
}
