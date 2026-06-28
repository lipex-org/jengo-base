<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Make;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractVariant;
use Jengo\Base\Libraries\ModuleDiscovery;

class ModuleVariant extends AbstractVariant
{
    public static function name(): string
    {
        return 'module';
    }

    public static function description(): string
    {
        return 'Generates a new Jengo module structure.';
    }

    public function arguments(): array
    {
        return [
            'name' => 'Name of the module to create',
        ];
    }

    public function options(): array
    {
        return [
            '--group' => 'The module group (e.g. Core, Financial)',
            '--force' => 'Overwrite existing files',
        ];
    }

    public function run(array $params): void
    {
        $name = array_shift($params);

        if (!$name) {
            CLI::error('Module name is required.');
            return;
        }

        // Handle group option
        $group = CLI::getOption('group');
        $force = CLI::getOption('force') !== null;

        // Normalize group slashes for target directory and namespace
        $groupPath = $group ? str_replace('\\', '/', (string)$group) : '';
        $groupNamespace = $group ? str_replace('/', '\\', (string)$group) : '';

        // Build target path
        $targetDir = ROOTPATH . 'modules/';
        if ($groupPath) {
            $targetDir .= trim($groupPath, '/') . '/';
        }
        $targetDir .= trim($name, '/\\') . '/';

        // Calculate expected namespace
        $namespace = 'Modules\\';
        if ($groupNamespace) {
            $namespace .= trim($groupNamespace, '\\') . '\\';
        }
        $namespace .= trim($name, '\\');

        // Checkpoint 4: Namespace Duplication Prevention
        $existing = ModuleDiscovery::scanModulesDirectory();
        if (isset($existing[$namespace]) && !$force) {
            CLI::error("Namespace [{$namespace}] already exists or folder matches an existing module.");
            return;
        }

        CLI::write("Generating module inside: {$targetDir}", 'cyan');

        // Create directory structure
        $dirs = [
            'Config',
            'Controllers',
            'Models',
            'Views',
            'Database/Migrations',
        ];

        foreach ($dirs as $dir) {
            $fullDir = $targetDir . $dir;
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0777, true);
            }
        }

        // Scaffold files
        
        // 1. Config/Routes.php
        $routesPath = $targetDir . 'Config/Routes.php';
        $routesContent = <<<PHP
<?php

/**
 * Routes for Jengo Module: {$name}
 */
\$routes->group(strtolower('{$name}'), ['namespace' => '{$namespace}\Controllers'], static function (\$routes) {
    \$routes->get('/', 'Home::index');
});
PHP;
        if (!file_exists($routesPath) || $force) {
            file_put_contents($routesPath, $routesContent);
        }

        // 2. Controllers/Home.php
        $controllerPath = $targetDir . 'Controllers/Home.php';
        $controllerContent = <<<PHP
<?php

namespace {$namespace}\Controllers;

use CodeIgniter\Controller;

class Home extends Controller
{
    public function index()
    {
        return view('{$namespace}\Views\index');
    }
}
PHP;
        if (!file_exists($controllerPath) || $force) {
            file_put_contents($controllerPath, $controllerContent);
        }

        // 3. Views/index.php
        $viewPath = $targetDir . 'Views/index.php';
        $viewContent = <<<HTML
<h1>Welcome to Jengo Module: {$name}</h1>
<p>Generated automatically by Jengo Modules Ecosystem.</p>
HTML;
        if (!file_exists($viewPath) || $force) {
            file_put_contents($viewPath, $viewContent);
        }

        // 4. Module.php (Marker file)
        $markerPath = $targetDir . 'Module.php';
        $markerContent = <<<PHP
<?php

namespace {$namespace};

class Module
{
    // Jengo Module registration class
}
PHP;
        if (!file_exists($markerPath) || $force) {
            file_put_contents($markerPath, $markerContent);
        }

        CLI::write("Module [{$name}] successfully scaffolded!", 'green');
    }
}
