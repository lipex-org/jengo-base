<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Vite;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractVariant;
use Jengo\Base\Config\Vite as ViteConfig;
use Jengo\Base\Vite\Repositories\ViteRepository;
use Jengo\Base\Vite\ViteEntryPointScanner;

class ConfigVariant extends AbstractVariant
{
    public static function name(): string
    {
        return 'config';
    }

    public static function description(): string
    {
        return 'Returns the Vite entrypoint configuration as JSON.';
    }

    public function run(array $params): void
    {
        $scanner = new ViteEntryPointScanner();
        $repo = new ViteRepository();

        $config = config('Vite') ?? new ViteConfig();

        $config->entrypoints = [
            ...$config->entrypoints,
            ...$scanner->scan()
        ];
        $config->searchPaths = [
            ...$config->searchPaths,
            ...$repo->loadSearchPaths(),
        ];

        $repo->cacheEntrypoints($config->entrypoints);

        $json = json_encode($config->toArray());

        CLI::write($json);
    }
}
