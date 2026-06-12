<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Libraries\EnvHandler;
use Jengo\Base\Setups\Contracts\SetupInterface;

abstract class AbstractSetup implements SetupInterface
{
    protected function env(): EnvHandler
    {
        return new EnvHandler(ROOTPATH . '.env');
    }
    protected function renderHeader(string $title, string $subtitle): void
    {
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::write("    " . CLI::color($title, 'light_cyan'));
        CLI::write("    " . CLI::color($subtitle, 'dark_gray'));
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::newLine();
    }

    protected function isPackageInstalled(string $package): bool
    {
        $composerJson = json_decode(file_get_contents(ROOTPATH . 'composer.json'), true);
        $dependencies = array_merge($composerJson['require'] ?? [], $composerJson['require-dev'] ?? []);
        return isset($dependencies[$package]);
    }

    protected function ensurePackage(string $package): bool
    {
        if ($this->isPackageInstalled($package)) {
            CLI::write("  " . CLI::color('✔', 'green') . " Package " . CLI::color($package, 'cyan') . " is already present.");
            return true;
        }

        CLI::write("  " . CLI::color('●', 'light_cyan') . " Package " . CLI::color($package, 'cyan') . " is missing. Installing...");
        CLI::newLine();

        passthru("composer require {$package}", $returnVar);

        if ($returnVar !== 0) {
            CLI::newLine();
            CLI::error("  Failed to install {$package}.");
            return false;
        }

        CLI::newLine();
        CLI::write("  " . CLI::color('✔', 'green') . " Package " . CLI::color($package, 'cyan') . " installed successfully.");
        return true;
    }

    /**
     * Call another spark command.
     */
    protected function call(string $command, array $params = []): void
    {
        command($command . ' ' . implode(' ', $params));
    }
}
