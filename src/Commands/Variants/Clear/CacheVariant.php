<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Clear;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractVariant;
use Jengo\Base\Libraries\ModuleDiscovery;

class CacheVariant extends AbstractVariant
{
    public static function name(): string
    {
        return 'cache';
    }

    public static function description(): string
    {
        return 'Clears Jengo compiled module cache.';
    }

    public function run(array $params): void
    {
        CLI::write('Clearing Jengo module cache...', 'cyan');
        ModuleDiscovery::clearCache();
        CLI::write('Jengo module cache cleared successfully.', 'green');
    }
}
