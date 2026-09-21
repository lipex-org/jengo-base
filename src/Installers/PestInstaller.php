<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class PestInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'pest';
    }

    public static function description(): string
    {
        return 'Install and configure Pest PHP testing framework';
    }

    public static function reasonForSkipping(): string
    {
        return 'Pest PHP is already installed.';
    }

    public function shouldRun(): bool
    {
        $composerPath = ROOTPATH . 'composer.json';
        if (!file_exists($composerPath)) {
            return false;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        $hasPest = isset($composer['require-dev']['pestphp/pest']);
        $hasPestInit = file_exists(ROOTPATH . 'tests/Pest.php');

        return !$hasPest && !$hasPestInit;
    }

    public function install(): void
    {
        $this->addRun();

        CLI::write('  ' . CLI::color('●', 'cyan') . ' Configuring composer.json to trust Pest plugins...', 'dark_gray');

        $composerPath = ROOTPATH . 'composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        // Ensure config and allow-plugins exist
        if (!isset($composer['config'])) {
            $composer['config'] = [];
        }
        if (!isset($composer['config']['allow-plugins'])) {
            $composer['config']['allow-plugins'] = [];
        }

        if (!isset($composer['autoload-dev'])) {
            $composer['autoload-dev'] = [];
        }

        if (!isset($composer['autoload-dev']['psr-4'])) {
            $composer['autoload-dev']['psr-4'] = [];
        }

        // Trust pest plugins
        $composer['config']['allow-plugins']['pestphp/pest-plugin'] = true;

        // add tests to be autoloaded and psr4 compliant
        if (!isset($composer['autoload-dev']['psr-4']['Tests\\'])) {
            $composer['autoload-dev']['psr-4']['Tests\\'] = 'tests/';
        }

        // remove phpunit if it exists
        if (isset($composer['require-dev']['phpunit/phpunit'])) {
            unset($composer['require-dev']['phpunit/phpunit']);
        }

        $this->writeFile($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // run composer update to apply changes
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Running composer update to apply changes...', 'dark_gray');
        $this->run('composer update --no-interaction');

        // Require pestphp/pest package
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Requiring pestphp/pest via Composer...', 'dark_gray');
        $this->run('composer require pestphp/pest --dev --with-all-dependencies --no-interaction');

        // publish Pest test stubs from Publisher/Stubs/Pest to completley overwrite the tests folder in the current project
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Publishing Pest test stubs to tests folder...', 'dark_gray');
        $source = __DIR__ . '/../Publisher/Stubs/Pest';
        $destination = ROOTPATH . 'tests';

        helper('filesystem');

        if (is_dir($destination)) {
            delete_files($destination, true);
        }

        $this->publish($source);

        CLI::write('Pest PHP configured successfully.', 'green');
    }
}
