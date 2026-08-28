<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Modules;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractVariant;
use Jengo\Base\Libraries\ModuleDiscovery;

class DiscoverVariant extends AbstractVariant
{
    public static function name(): string
    {
        return 'discover';
    }

    public static function description(): string
    {
        return 'Discovers and lists all Jengo modules in the project.';
    }

    public function run(array $params): void
    {
        $cacheFile = ROOTPATH . '.jengo/cache/modules.php';
        $isCached = file_exists($cacheFile);

        CLI::write('Scanning modules directory...', 'cyan');
        $modules = ModuleDiscovery::scanModulesDirectory();

        if (empty($modules)) {
            CLI::write('No Jengo modules discovered in ROOTPATH/modules.', 'yellow');
            return;
        }

        $cachedModules = [];
        if ($isCached) {
            $cachedModules = require $cacheFile;
        }

        $tbody = [];
        foreach ($modules as $namespace => $path) {
            $inCache = array_key_exists($namespace, $cachedModules) && $cachedModules[$namespace] === $path;
            $tbody[] = [
                $namespace,
                str_replace(ROOTPATH, '', $path),
                $inCache ? CLI::color('Yes', 'green') : CLI::color('No', 'red')
            ];
        }

        CLI::newLine();
        CLI::write('Discovered Modules:', 'yellow');
        CLI::table($tbody, ['Namespace', 'Path (relative to root)', 'Cached']);
        CLI::newLine();

        CLI::write('Cache Status: ' . ($isCached ? CLI::color('Cached', 'green') : CLI::color('Uncached', 'red')));
    }
}
