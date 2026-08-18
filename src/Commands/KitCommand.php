<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class KitCommand extends BaseCommand
{
    protected $group       = 'Jengo';
    protected $name        = 'jengo:kit';
    protected $description = 'Inspect and manage Jengo Ecosystem packages & modules.';
    protected $usage       = 'jengo:kit [options]';

    protected $options = [
        '--status' => 'Print installation status of all Jengo packages only.',
    ];

    public function run(array $params)
    {
        CLI::newLine();
        $this->renderHeader("JENGO ECOSYSTEM CONTROL", "Active Modules & Integrations");

        $modules = [
            'api' => [
                'name' => 'jengo/api',
                'description' => 'A robust API Resource engine featuring auto-routing and standard payloads.',
                'composer' => 'jengo/api',
                'class' => 'Jengo\\Api\\Config\\Services',
            ],
            'inertia' => [
                'name' => 'jengo/inertia',
                'description' => 'Zero-config Inertia.js bridge supporting React, Vue, & Svelte templates.',
                'composer' => 'jengo/inertia',
                'class' => 'Jengo\\Inertia\\Inertia',
            ],
            'schema' => [
                'name' => 'jengo/schema',
                'description' => 'Declarative, type-safe database schemas and code generator.',
                'composer' => 'jengo/schema',
                'class' => 'Jengo\\Schema\\Schema',
            ],
            'vite-plugin' => [
                'name' => 'jengo/vite-plugin',
                'description' => 'High-performance hot-module replacement Vite bundler configuration.',
                'composer' => 'jengo/vite-plugin',
                'class' => null, // Checked via node_modules or configuration
            ],
        ];

        if (CLI::getOption('status') !== null) {
            $this->printStatusOnly($modules);
            return;
        }

        $this->interactiveDashboard($modules);
    }

    private function renderHeader(string $title, string $subtitle): void
    {
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::write("    " . CLI::color($title, 'light_cyan'));
        CLI::write("    " . CLI::color($subtitle, 'dark_gray'));
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::newLine();
    }

    private function isModuleInstalled(string $name, ?string $class): bool
    {
        if ($name === 'jengo/vite-plugin') {
            return file_exists(ROOTPATH . 'vite.config.js') || file_exists(ROOTPATH . 'vite.config.ts');
        }
        return $class !== null && class_exists($class);
    }

    private function printStatusOnly(array $modules): void
    {
        foreach ($modules as $key => $mod) {
            $installed = $this->isModuleInstalled($mod['name'], $mod['class']);
            $statusStr = $installed ? CLI::color('INSTALLED', 'green') : CLI::color('AVAILABLE', 'yellow');
            CLI::write("  " . str_pad($mod['name'], 25) . " [{$statusStr}]");
        }
        CLI::newLine();
    }

    private function interactiveDashboard(array $modules): void
    {
        CLI::write("  Active Modules Status:", 'cyan');
        CLI::write("  " . str_repeat('┈', 60), 'dark_gray');

        $choices = [];
        $actions = [];

        foreach ($modules as $key => $mod) {
            $installed = $this->isModuleInstalled($mod['name'], $mod['class']);
            $statusStr = $installed ? CLI::color('✔ Installed', 'green') : CLI::color('○ Available', 'dark_gray');
            
            CLI::write("  " . str_pad($mod['name'], 22) . " [{$statusStr}]");
            CLI::write("    " . CLI::color($mod['description'], 'dark_gray'));
            CLI::newLine();

            if ($installed) {
                $choices[] = "Manage {$mod['name']} (Already Installed)";
                $actions[] = ['type' => 'installed', 'module' => $mod];
            } else {
                $choices[] = "Install {$mod['name']}";
                $actions[] = ['type' => 'install', 'module' => $mod];
            }
        }

        $choices[] = "Exit Dashboard";
        $actions[] = ['type' => 'exit'];

        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        $choice = CLI::prompt("  Choose a module action", $choices);

        $index = array_search($choice, $choices, true);
        if ($index === false || $actions[$index]['type'] === 'exit') {
            CLI::write("  Goodbye!", 'light_cyan');
            CLI::newLine();
            return;
        }

        $action = $actions[$index];
        if ($action['type'] === 'install') {
            CLI::newLine();
            CLI::write("  To install {$action['module']['name']}, run:", 'yellow');
            CLI::write("  composer require {$action['module']['composer']}", 'cyan');
            CLI::newLine();
        } else {
            CLI::newLine();
            CLI::write("  {$action['module']['name']} is active and running in your workspace.", 'green');
            CLI::newLine();
        }
    }
}
