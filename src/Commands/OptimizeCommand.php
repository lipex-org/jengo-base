<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Jengo\Base\Libraries\ModuleDiscovery;

class OptimizeCommand extends BaseCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:optimize';
    protected $description = 'Optimize Jengo performance (compile modules mapping)';
    protected $usage = 'jengo:optimize';

    public function run(array $params)
    {
        CLI::write('Scanning modules directory...', 'cyan');
        $modules = ModuleDiscovery::scanModulesDirectory();

        CLI::write('Compiling modules configuration map...', 'cyan');
        ModuleDiscovery::compileCache($modules);

        CLI::write('Jengo optimization complete! Modules mapped successfully.', 'green');
    }
}
