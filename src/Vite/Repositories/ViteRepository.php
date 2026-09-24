<?php

declare(strict_types=1);

namespace Jengo\Base\Vite\Repositories;

use Jengo\Base\Config\Vite as ViteConfig;
use Jengo\Base\Support\JengoDirectory;
use Jengo\Base\Vite\ViteEntryPointScanner;

class ViteRepository
{
    protected ViteConfig $config;

    public function __construct()
    {
        helper('Jengo\Base\Helpers\jengo');
        $this->config = config('Vite');
    }

    protected string $cacheFile = 'vite_entrypoints.json';

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
        if (isProduction() && JengoDirectory::has($this->cacheFile) && !$reset) {
            return JengoDirectory::readJson($this->cacheFile, []) ?? [];
        }

        // In dev or if cache is missing, scan fresh
        return (new ViteEntryPointScanner())->scan();
    }

    public function cacheEntrypoints(array $data): void
    {
        JengoDirectory::writeJson($this->cacheFile, $data);
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