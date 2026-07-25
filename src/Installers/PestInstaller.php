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

        return !$hasPest || !$hasPestInit;
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

        // Trust pest plugins
        $composer['config']['allow-plugins']['pestphp/pest-plugin'] = true;

        $this->writeFile($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Require pestphp/pest package
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Requiring pestphp/pest via Composer...', 'dark_gray');
        $this->run('composer require pestphp/pest --dev --no-interaction');

        // Check if we need to run pest --init
        if (!file_exists(ROOTPATH . 'tests/Pest.php')) {
            CLI::write('  ' . CLI::color('●', 'cyan') . ' Initializing Pest PHP...', 'dark_gray');
            $pestBin = ROOTPATH . 'vendor/bin/pest';
            if (file_exists($pestBin)) {
                $this->run($pestBin . ' --init --no-interaction');
            }
        }

        CLI::write('Pest PHP configured successfully.', 'green');
    }
}
