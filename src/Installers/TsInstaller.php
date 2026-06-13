<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class TsInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'typescript';
    }

    public static function description(): string
    {
        return 'Configure TypeScript with Vite support for CI4';
    }

    public static function dependencies(): array
    {
        return ['vite'];
    }

    public function shouldRun(): bool
    {
        return !file_exists(ROOTPATH . 'tsconfig.json');
    }

    public function install(): void
    {
        $this->addRun();

        $framework = $this->detectFramework();

        // Dependencies to install as dev
        $devDependencies = [
            'typescript',
            '@types/node',
        ];

        // Framework specific dependencies
        if ($framework === 'react') {
            $devDependencies[] = '@types/react';
            $devDependencies[] = '@types/react-dom';
        } elseif ($framework === 'vue') {
            $devDependencies[] = 'vue-tsc';
        }

        // Publish base TS stubs
        $this->publish(
            __DIR__ . '/../Publisher/Stubs/TS'
        );

        // Framework specific configurations
        if ($framework === 'react') {
            $this->updateTsConfigForReact();
        } elseif ($framework === 'vue') {
            $this->updateTsConfigForVue();
        } elseif ($framework === 'svelte') {
            $this->updateTsConfigForSvelte();
        }

        if ($this->wantsToInstallDependencies()) {
            $pm = $this->selectNodeManager();
            $this->run($pm->getAddCommand($devDependencies, true));
        }

        CLI::write('TypeScript configured successfully for ' . ($framework ?: 'standard JS') . '.', 'green');
    }

    protected function detectFramework(): ?string
    {
        $packageJsonPath = ROOTPATH . 'package.json';
        if (!file_exists($packageJsonPath)) {
            return null;
        }

        $packageJson = json_decode(file_get_contents($packageJsonPath), true);
        $deps = array_merge($packageJson['dependencies'] ?? [], $packageJson['devDependencies'] ?? []);

        if (isset($deps['react'])) return 'react';
        if (isset($deps['vue'])) return 'vue';
        if (isset($deps['svelte'])) return 'svelte';

        return null;
    }

    protected function updateTsConfigForReact(): void
    {
        $path = ROOTPATH . 'tsconfig.json';
        if (!file_exists($path)) return;

        $config = json_decode(file_get_contents($path), true);
        
        $config['compilerOptions']['jsx'] = 'react-jsx';
        
        $this->writeFile($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function updateTsConfigForVue(): void
    {
        $path = ROOTPATH . 'tsconfig.json';
        if (!file_exists($path)) return;

        $config = json_decode(file_get_contents($path), true);
        
        $config['compilerOptions']['jsx'] = 'preserve';
        $config['compilerOptions']['moduleResolution'] = 'bundler';
        
        $this->writeFile($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function updateTsConfigForSvelte(): void
    {
        $path = ROOTPATH . 'tsconfig.json';
        if (!file_exists($path)) return;

        $config = json_decode(file_get_contents($path), true);
        
        $config['compilerOptions']['moduleResolution'] = 'bundler';
        $config['compilerOptions']['verbatimModuleSyntax'] = true;
        
        $this->writeFile($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
