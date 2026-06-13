<?php

declare(strict_types=1);

namespace Jengo\Base\Vite;

use RuntimeException;

/**
 * Orchestrates Vite tag generation for development and production.
 */
class ViteService
{
    protected TagGenerator $tags;

    public function __construct()
    {
        $this->tags = new TagGenerator();
    }

    /**
     * Generates all required HTML tags for the given entrypoints.
     */
    public function generateTags(array $entrypoints, bool $isDevelopment, string $devServerUrl, string $manifestPath, string $buildDirectory = 'dist/'): string
    {
        if (empty($entrypoints)) {
            return '';
        }

        if ($isDevelopment) {
            return $this->generateDevelopmentTags($entrypoints, $devServerUrl);
        }

        return $this->generateProductionTags($entrypoints, $manifestPath, $buildDirectory);
    }

    /**
     * Generates tags for development mode.
     */
    protected function generateDevelopmentTags(array $entrypoints, string $devServerUrl): string
    {
        $devServerUrl = rtrim($devServerUrl, '/') . '/';
        $output = [];

        // Determine if React Refresh is needed
        $needsReactRefresh = false;
        foreach ($entrypoints as $entrypoint) {
            if (str_ends_with($entrypoint, '.tsx') || str_ends_with($entrypoint, '.jsx')) {
                $needsReactRefresh = true;
                break;
            }
        }

        if ($needsReactRefresh) {
            $output[] = $this->tags->makeReactRefreshPreamble($devServerUrl);
        }

        // Output Vite client
        $output[] = $this->tags->makeScriptTag($devServerUrl . '@vite/client');

        // Output entrypoints
        foreach ($entrypoints as $entrypoint) {
            // Remove leading slashes to prevent double slashes
            $cleanEntrypoint = ltrim($entrypoint, '/');
            $output[] = $this->tags->makeScriptTag($devServerUrl . $cleanEntrypoint);
        }

        return implode(PHP_EOL, $output);
    }

    /**
     * Generates tags for production mode using the manifest.
     */
    protected function generateProductionTags(array $entrypoints, string $manifestPath, string $buildDirectory): string
    {
        try {
            $manifest = new Manifest($manifestPath);
        } catch (RuntimeException $e) {
            log_message('error', $e->getMessage());
            return '<!-- Vite Manifest Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
        }

        $resolver = new AssetResolver($manifest, $buildDirectory);
        $assets = $resolver->resolve($entrypoints);

        $output = [];

        // 1. Preloads
        foreach ($assets['preloads'] as $preload) {
            $output[] = $this->tags->makePreloadTag($preload);
        }

        // 2. CSS
        foreach ($assets['styles'] as $style) {
            $output[] = $this->tags->makeStyleTag($style);
        }

        // 3. JS Scripts
        foreach ($assets['scripts'] as $script) {
            $output[] = $this->tags->makeScriptTag($script);
        }

        return implode(PHP_EOL, $output);
    }
}
