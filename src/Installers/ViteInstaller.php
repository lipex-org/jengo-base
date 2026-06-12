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
        $dependecies = $this->getDependencies();

        $canInstallDependecies = $this->wantsToInstallDependencies();

        if ($canInstallDependecies) {
            $pm = $this->whichPackageMangerToUse();

            CLI::write("Using package manager: {$pm}", 'cyan');
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

        if (!$withTailwind) {
            unset($dependecies['tailwind'], $dependecies['tailwind_vite_plugin']);
        }

        // Install deps
        if ($canInstallDependecies) {
            $this->run(
                $this->buildPackageManagerInstallCommand($pm, $dependecies)
            );
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

        $injection = "\n<?php\nuse function Jengo\Base\\vite_tags;\n?>\n\n<?= vite_tags() ?>\n";

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

    protected function whichPackageMangerToUse(): string
    {
        $pm = CLI::getOption('pm');

        if ($pm && in_array($pm, ['pnpm', 'npm', 'yarn'])) {
            return $pm;
        }

        return CLI::prompt(
            'Which package manager do you want to use?',
            ['pnpm', 'npm', 'yarn'],
            "in_list[pnpm,npm,yarn]"
        );
    }

    protected function wantsToInstallDependencies(): bool
    {
        if (CLI::getOption('yes')) {
            return true;
        }

        return CLI::prompt(
            'Should we install the dependencies?',
            ['y', 'n'],
            'in_list[y,n]'
        ) === 'y';
    }

    private function getDependencies(): array
    {
        return [
            'vite' => 'vite',
            'tailwind' => 'tailwindcss',
            'tailwind_vite_plugin' => '@tailwindcss/vite',
            'jengo_vite_plugin' => '@jengo/vite',
        ];
    }

    private function devDependencies(): array
    {
        return [
            'vite',
            'tailwind',
            'tailwind_vite_plugin',
            'jengo_vite_plugin'
        ];
    }

    /**
     * Build the package manager install command
     * @param string $pm
     * @param array<string, string> $dependencies
     * @return string
     */
    private function buildPackageManagerInstallCommand(string $pm, array $dependencies): string
    {
        $deps = [];
        $devDeps = $this->devDependencies();

        foreach ($dependencies as $key => $name) {
            // check isDev too
            if (in_array($key, $devDeps)) {
                $deps[] = "-D {$name}";
            } else {
                $deps[] = $name;
            }
        }

        $deps = implode(' ', $deps);

        $baseCommand = $this->packageMangerInstallCommand($pm);

        return "{$baseCommand} {$deps}";
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