<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Psy\Configuration;
use Psy\Shell;
use Psy\VersionUpdater\Checker;

class JengoShell extends Shell
{
    protected function getHeader(): string
    {
        return '';
    }

    protected function writeVersionInfo()
    {
        // Suppress version info check
    }

    protected function writeManualUpdateInfo()
    {
        // Suppress manual update notice
    }
}

class TinkerCommand extends BaseCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:tinker';
    protected $description = 'Interactive PHP REPL preloaded with Jengo CodeIgniter context.';
    protected $usage = 'jengo:tinker';

    public function run(array $params)
    {
        CLI::newLine();
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::write("    " . CLI::color('JENGO TINKER SHELL', 'light_cyan'));
        CLI::write("    " . CLI::color('Interactive REPL - Type "exit" or "quit" to leave.', 'dark_gray'));
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::newLine();

        // 1. Configure PsySH shell
        $config = new Configuration();
        $config->setUpdateCheck(Checker::NEVER);
        $config->setStartupMessage('');

        $shell = new JengoShell($config);

        // 2. Preload Jengo helpers & variables into the REPL scope
        $scope = [
            'app' => service('request'),
            'db' => \Config\Database::connect(),
        ];
        $shell->setScopeVariables($scope);

        // 3. Start REPL execution loop
        $shell->run();
    }
}
