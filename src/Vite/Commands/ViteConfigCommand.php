<?php

declare(strict_types=1);

namespace Jengo\Base\Vite\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Jengo\Base\Vite\Config\ViteConfig;
use Jengo\Base\Vite\Repositories\ViteRepository;
use Jengo\Base\Vite\ViteEntryPointScanner;

class ViteConfigCommand extends BaseCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:vite-config';
    protected $description = 'Returns the Vite entrypoint configuration as JSON.';

    public function run(array $params)
    {
        $scanner = new ViteEntryPointScanner();
        $repo = new ViteRepository();

        // scan 
        $config = new ViteConfig();

        $config->entrypoints = $scanner->scan();
        $config->searchPaths = $repo->loadSearchPaths();

        $repo->cacheEntrypoints($config->entrypoints);

        // Output raw JSON so the JS plugin can parse it
        $json = json_encode($config->toArray());

        CLI::write($json);
    }
}