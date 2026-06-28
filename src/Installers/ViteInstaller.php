<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class ViteInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'vite';
    }

    public static function description(): string
    {
        return 'Install Vite with CI4 defaults';
    }

    public static function reasonForSkipping(): string
    {
        return 'package.json already exists. If you want to install Vite, please remove package.json first.';
    }

    public function shouldRun(): bool
    {
        return !file_exists(ROOTPATH . 'package.json');
    }

    public function install(): void
    {
        $this->addRun();
        $this->ensureClientDirectory();

        $canInstallDependencies = $this->wantsToInstallDependencies();
        $pm = null;

        if ($canInstallDependencies) {
            $pm = $this->selectNodeManager();
            CLI::write("Using package manager: {$pm->getManager()}", 'cyan');
        }

        $withTailwind = $this->wantsTailwind();

        // Publish base Vite stubs
        $this->publish(
            __DIR__ . '/../Publisher/Stubs/Vite'
        );

        $jsDir = 'resources/js';
        $cssDir = 'resources/css';

        $this->publish(
            __DIR__ . '/../Publisher/Stubs/Client',
            $jsDir
        );

        $this->publish(
            __DIR__ . '/../Publisher/Stubs/CSS',
            $cssDir
        );

        if ($withTailwind) {
            $this->publish(
                __DIR__ . '/../Publisher/Stubs/Tailwind',
                $cssDir
            );
        }

        // Env integration
        $this->env()
            ->addTitle('Vite')
            ->set('VITE_ENABLED', 'true')
            ->set('VITE_DEV_SERVER', 'http://localhost:5173')
            ->save();

        // Install deps
        if ($canInstallDependencies && $pm) {
            $dependencies = $this->getDependencies($withTailwind);
            $devDependencies = $this->getDevDependencies($withTailwind);

            if (!empty($dependencies)) {
                $this->run($pm->getAddCommand($dependencies));
            }
            if (!empty($devDependencies)) {
                $this->run($pm->getAddCommand($devDependencies, true));
            }
        }

        if ($withTailwind) {
            $this->updateViteConfig();
        }

        $this->injectViteTags();

        CLI::write('Vite installed successfully.', 'green');
    }

    private function injectViteTags(): void
    {
        $headerPath = APPPATH . 'Views/layouts/partials/header.layout.partial.php';

        if (!file_exists($headerPath)) {
            return;
        }

        $content = file_get_contents($headerPath);

        // Check if already injected
        if (str_contains($content, 'vite_tags()')) {
            return;
        }

        $injection = "\n<?= Jengo\Base\\vite_tags() ?>\n";

        $this->writeFile($headerPath, $content . $injection);

        CLI::write('  ' . CLI::color('●', 'cyan') . ' Injected vite_tags into header partial.', 'dark_gray');
    }

    protected function wantsTailwind(): bool
    {
        $tailwind = CLI::getOption('tailwind');
        if ($tailwind !== null) {
            return $tailwind === 'y' || $tailwind === true;
        }

        if (CLI::getOption('yes')) {
            return true;
        }

        return CLI::prompt(
            'Include Tailwind CSS?',
            ['y', 'n'],
            'in_list[y,n]'
        ) === 'y';
    }

    private function getDependencies(bool $withTailwind = true): array
    {
        $deps = [];
        // Production dependencies if any
        return $deps;
    }

    private function getDevDependencies(bool $withTailwind = true): array
    {
        $deps = [
            'vite',
            '@jengo/vite',
        ];

        if ($withTailwind) {
            $deps[] = 'tailwindcss';
            $deps[] = '@tailwindcss/vite';
            $deps[] = 'daisyui@latest';
        }

        return $deps;
    }

    private function updateViteConfig(): void
    {
        $configFile = ROOTPATH . 'vite.config.ts';
        if (!file_exists($configFile)) {
            $configFile = ROOTPATH . 'vite.config.js';
        }

        if (!file_exists($configFile)) {
            return;
        }

        $content = file_get_contents($configFile);

        // Uncomment import
        $content = str_replace(
            "// import tailwindcss from '@tailwindcss/vite'",
            "import tailwindcss from '@tailwindcss/vite'",
            $content
        );

        // Uncomment plugin usage
        $content = str_replace(
            "// tailwindcss(),",
            "tailwindcss(),",
            $content
        );

        $this->writeFile($configFile, $content);
    }
}
