<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class DevInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'dev';
    }

    public static function description(): string
    {
        return 'Configure development environment (Concurrently, .env, Composer scripts)';
    }

    public static function reasonForSkipping(): string
    {
        return 'Development environment already configured.';
    }

    public static function dependencies(): array
    {
        return ['vite'];
    }

    public function shouldRun(): bool
    {
        $composer = json_decode(file_get_contents(ROOTPATH . 'composer.json'), true);
        return !isset($composer['scripts']['dev']);
    }

    public function install(): void
    {
        $this->addRun();

        // 1. Update .env to development
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Setting environment to development...', 'dark_gray');
        $this->env()
            ->set('CI_ENVIRONMENT', 'development')
            ->save();

        // 2. Install concurrently
        $pm = $this->detectPackageManager();
        CLI::write("  " . CLI::color('●', 'cyan') . " Installing concurrently via {$pm}...", 'dark_gray');
        
        $installCmd = match ($pm) {
            'pnpm' => 'pnpm add -D concurrently',
            'yarn' => 'yarn add -D concurrently',
            default => 'npm install --save-dev concurrently',
        };

        $this->run($installCmd);

        // 3. Update composer.json
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Updating composer.json with dev script and high timeout...', 'dark_gray');
        
        $composerPath = ROOTPATH . 'composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        // Ensure scripts and config exist
        if (!isset($composer['scripts'])) {
            $composer['scripts'] = [];
        }
        if (!isset($composer['config'])) {
            $composer['config'] = [];
        }

        // Add the dev script
        $composer['scripts']['dev'] = 'concurrently "php spark serve" "npm run dev" --kill-others';
        
        // Set a very high process timeout (0 = infinite)
        $composer['config']['process-timeout'] = 0;

        $this->writeFile($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        CLI::write('Development environment configured successfully.', 'green');
    }
}
