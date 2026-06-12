<?php

declare(strict_types=1);

namespace Jengo\Base\Vite\Repositories;

use Jengo\Base\Vite\Config\ViteConfig;
use Jengo\Base\Vite\ViteEntryPointScanner;

class ViteRepository
{
    protected ViteConfig $config;

    public function __construct()
    {
        helper('Jengo\Base\Helpers\jengo');
        $this->config = config('ViteConfig');
    }

    protected string $cachePath = ROOTPATH . '.jengo/vite_entrypoints.json';

    public function getFullConfig(bool $reset = false): ViteConfig
    {
        $this->config->entrypoints = array_unique([
            ...$this->loadEntrypoints($reset),
            ...$this->config->entrypoints
        ]);

        $this->config->searchPaths = $this->loadSearchPaths();

        return $this->config;
    }

    protected function loadEntrypoints(bool $reset = false): array
    {
        if (isProduction() && file_exists($this->cachePath) && !$reset) {
            return json_decode(file_get_contents($this->cachePath), true) ?? [];
        }

        // In dev or if cache is missing, scan fresh
        return (new ViteEntryPointScanner())->scan();
    }

    public function cacheEntrypoints(array $data): void
    {
        if (!is_dir(dirname($this->cachePath))) {
            mkdir(dirname($this->cachePath), 0755, true);
        }

        // delete cache file if exists
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }

        file_put_contents($this->cachePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function scan(bool $reset = false): ViteConfig
    {
        $config = $this->getFullConfig($reset);

        $this->cacheEntrypoints($config->entrypoints);

        return $config;
    }

    public function loadSearchPaths(): array
    {
        return array_unique([
            APPPATH,
            APPPATH . 'Client',
            ROOTPATH . 'client',
            ROOTPATH . 'resources',
            ...$this->config->searchPaths,
        ]);
    }
}