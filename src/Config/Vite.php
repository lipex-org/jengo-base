<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Tracks entrypoints and core Vite settings.
 */
class Vite extends BaseConfig
{
    public array $entrypoints = [];

    public array $searchPaths = [
        APPPATH,
        ROOTPATH . 'resources',
    ];

    public function toArray(): array
    {
        return [
            'entrypoints' => $this->entrypoints,
            'searchPaths' => $this->searchPaths
        ];
    }
}