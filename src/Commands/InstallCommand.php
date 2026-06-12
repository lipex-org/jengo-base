<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Libraries\InstallerRunner;
use Jengo\Base\Installers\Libraries\InstallerTracker;
use Jengo\Base\Installers\Repositories\InstallerRepository;

class InstallCommand extends BaseCommand
{
    private InstallerRunner $runner;
    protected $group = 'Jengo';
    protected $name = 'jengo:install';
    protected $description = 'Run Jengo installers';
    protected $usage = 'jengo:install <installers> <options>';

    protected $arguments = [
        'installers' => 'Installers to run or skip'
    ];

    protected $options = [
        '--show' => 'List all installers',
        '--status' => 'Show install status',
        '--no-check' => 'Skip tracker',
        '--force' => 'Force installation even if already installed',
        '--except' => 'Run all installers except the specified. Separate them with commas i.e vite,maizzle',
        '--framework' => 'The framework to use (vue, react, svelte)',
        '--kit' => 'The starter kit to use',
        '--client-dir' => 'The directory for client files',
        '--pm' => 'The package manager to use (pnpm, npm, yarn)',
        '--tailwind' => 'Include Tailwind CSS (y/n)',
        '--yes' => 'Answer yes to all prompts',
    ];

    public function run(array $params)
    {
        $isNoCheck = CLI::getOption('no-check') !== null;
        $isForce = CLI::getOption('force') !== null;
        $isShow = CLI::getOption('show') !== null;
        $isStatus = CLI::getOption('status') !== null;

        $exceptOption = CLI::getOption('except');
        $except = $exceptOption
            ? array_filter(array_map('trim', explode(',', $exceptOption)))
            : [];

        $ranInstallers = [];
        $tracker = new InstallerTracker();

        if ($isNoCheck) {
            CLI::write(
                'WARNING: --no-check disables installer tracking and may overwrite files.',
                'yellow'
            );

            if (CLI::prompt('Continue?', ['y', 'n']) !== 'y') {
                CLI::newLine();
                CLI::write('Aborted.', 'light_gray');
                return;
            }
        }

        if ($isShow) {
            foreach (InstallerRepository::all() as $installer) {
                CLI::write(sprintf(
                    '%-12s %s',
                    $installer::name(),
                    $installer::description()
                ));
            }
            return;
        }

        if ($isStatus) {
            foreach (InstallerRepository::all() as $installer) {
                $installed = $tracker->isInstalled($installer::name());

                CLI::write(
                    sprintf('%-12s %s', $installer::name(), $installed ? '✔ installed' : '⏳ pending'),
                    $installed ? 'green' : 'yellow'
                );
            }

            return;
        }

        $this->runner = new InstallerRunner(null, $isNoCheck, $isForce);

        // Handle --except
        if ($except) {
            foreach (InstallerRepository::all() as $installer) {
                $name = $installer::name();
                if (in_array($name, $except, true)) {
                    continue;
                }

                $ranInstallers[] = $name;
                $this->runner->run($installer);
            }

            $this->showSuccess($ranInstallers, $except);
            return;
        }

        $installers = [];

        foreach ($params as $option => $name) {
            // skip indices and any null option values
            if (!$name) {
                continue;
            }

            // skip any options
            if (!is_int($option) && CLI::getOption($option)) {
                continue;
            }

            $installers[] = $name;
        }

        // Handle explicit installer names
        if ($installers) {
            foreach ($installers as $name) {
                $installer = InstallerRepository::find($name);

                if (!$installer) {
                    CLI::error("Installer [$name] not found.");
                    continue;
                }

                $ranInstallers[] = $name;
                $this->runner->run($installer);
            }

            $this->showSuccess($ranInstallers, $except);
            return;
        }

        // No installers specified, trigger the wizard
        $availableInstallers = InstallerRepository::all();
        $options = [];
        $installerMap = [];

        CLI::newLine();
        CLI::write("  " . str_repeat('━', 50), 'dark_gray');
        CLI::write("    JENGO INSTALLER WIZARD", 'light_cyan');
        CLI::write("    Select the modules you wish to install", 'dark_gray');
        CLI::write("  " . str_repeat('━', 50), 'dark_gray');
        CLI::newLine();

        foreach ($availableInstallers as $index => $installer) {
            $name = $installer::name();
            $isInstalled = $tracker->isInstalled($name);
            
            $statusIcon = $isInstalled ? CLI::color('✔', 'green') : CLI::color('○', 'yellow');
            $statusText = $isInstalled ? CLI::color('installed', 'dark_gray') : CLI::color('pending', 'yellow');
            
            // Align columns
            $nameCol = str_pad($name, 15);
            $descCol = str_pad($installer::description(), 40);

            $options[$index] = sprintf(
                "%s  %s  %s  [%s]",
                CLI::color($nameCol, 'cyan'),
                CLI::color('·', 'dark_gray'),
                $descCol,
                $statusIcon . ' ' . $statusText
            );
            $installerMap[$index] = $installer;
        }

        if (empty($options)) {
            CLI::write('    No installers found.', 'yellow');
            return;
        }

        foreach ($options as $index => $option) {
            CLI::write(sprintf('    [%s] %s', CLI::color((string)$index, 'light_green'), $option));
        }

        CLI::newLine();
        $input = CLI::prompt(CLI::color('  Choose installers (ID)', 'light_cyan'));
        $selections = array_filter(array_map('trim', explode(',', $input)), 'strlen');

        if (empty($selections)) {
            CLI::newLine();
            CLI::write('  Selection cancelled. No changes made.', 'light_gray');
            return;
        }

        CLI::newLine();
        CLI::write("  " . str_repeat('┈', 50), 'dark_gray');
        CLI::newLine();

        foreach ($selections as $index) {
            if (! isset($installerMap[$index])) {
                continue;
            }

            $installer = $installerMap[$index];
            $ranInstallers[] = $installer::name();
            $this->runner->run($installer);
        }

        $this->showSuccess($ranInstallers, $except);
    }

    public function showSuccess(array $ran, array $skipped): void
    {
        $runnerReport = $this->runner->report();

        $ran = array_unique(
            [
                ...array_diff($ran, $runnerReport['skipped']),
                ...$runnerReport['ran']
            ],
        );

        $skipped = array_unique([
            ...$skipped,
            ...$runnerReport['skipped']
        ]);

        sort($skipped);
        sort($ran);

        CLI::write("  " . str_repeat('━', 50), 'dark_gray');
        CLI::write("    INSTALLATION SUMMARY", 'light_cyan');
        CLI::write("  " . str_repeat('━', 50), 'dark_gray');
        CLI::newLine();

        if ($ran) {
            CLI::write("    " . CLI::color('COMPLETE', 'green'));
            foreach ($ran as $name) {
                CLI::write("    " . CLI::color('✔', 'green') . " " . ucfirst(str_replace(['_', '-'], ' ', $name)));
            }
            CLI::newLine();
        }

        if ($skipped) {
            CLI::write("    " . CLI::color('SKIPPED', 'yellow'));
            foreach ($skipped as $name) {
                CLI::write("    " . CLI::color('○', 'yellow') . " " . ucfirst(str_replace(['_', '-'], ' ', $name)));
            }
            CLI::newLine();
        }

        CLI::write("  " . CLI::color('Installation sequence finished.', 'light_cyan'));
        CLI::newLine();
    }
}
