<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Modules;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractVariant;
use Jengo\Base\Libraries\ModuleDiscovery;

class ClearVariant extends AbstractVariant
{
    public static function name(): string
    {
        return 'clear';
    }

    public static function description(): string
    {
        return 'Clears Jengo compiled module cache.';
    }

    public function run(array $params): void
    {
        CLI::write('Clearing Jengo modules cache...', 'cyan');
        ModuleDiscovery::clearCache();
        CLI::write('Jengo modules cache cleared successfully.', 'green');
    }
}
