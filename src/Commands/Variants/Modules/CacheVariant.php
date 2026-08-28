<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Modules;

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
        return 'Compiles Jengo modules configuration map and caches it.';
    }

    public function run(array $params): void
    {
        CLI::write('Scanning modules directory...', 'cyan');
        $modules = ModuleDiscovery::scanModulesDirectory();

        CLI::write('Compiling modules configuration map...', 'cyan');
        ModuleDiscovery::compileCache($modules);

        CLI::write('Jengo modules cached successfully.', 'green');
    }
}
