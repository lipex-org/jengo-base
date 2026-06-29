<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class MaizzleInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'maizzle';
    }

    public static function description(): string
    {
        return 'Install Maizzle for email template development';
    }

    public static function reasonForSkipping(): string
    {
        return 'Maizzle is already installed.';
    }

    public static function dependencies(): array
    {
        return ['vite'];
    }

    public function shouldRun(): bool
    {
        return !is_dir(ROOTPATH . 'resources/js/emails');
    }

    public function install(): void
    {
        $this->addRun();
        
        $pm = $this->node();
        $dependencies = $this->getDependencies();

        CLI::write("Using package manager: {$pm->getManager()}", 'cyan');

        // Publish Maizzle stubs
        $this->publish(
            __DIR__ . '/../Publisher/Stubs/Maizzle',
            'resources/js'
        );

        // Install dependencies (all dev)
        $this->run($pm->getAddCommand($dependencies, true));

        $this->updateViteConfig();
        $this->registerDecorator();

        CLI::write('Maizzle installed successfully.', 'green');
    }

    private function registerDecorator(): void
    {
        $configPath = APPPATH . 'Config/View.php';
        if (!file_exists($configPath)) {
            return;
        }

        $content = file_get_contents($configPath);
        $decoratorClass = '\\Jengo\\Base\\View\\Decorators\\EmailTemplateDecorator::class';

        if (str_contains($content, 'EmailTemplateDecorator::class')) {
            return;
        }

        // Inject inside $decorators array
        $content = preg_replace(
            '/(public\s+array\s+\$decorators\s*=\s*\[)/',
            "$1\n        {$decoratorClass},",
            $content,
            1
        );

        $this->writeFile($configPath, $content);
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Registered EmailTemplateDecorator in Config/View.php.', 'dark_gray');
    }

    private function getDependencies(): array
    {
        return [
            '@maizzle/framework',
            'vue',
            '@vitejs/plugin-vue',
        ];
    }

    private function updateViteConfig(): void
    {
        $configFile = ROOTPATH . 'vite.config.ts';
        if (!file_exists($configFile)) {
            $configFile = ROOTPATH . 'vite.config.js';
        }

        if (!file_exists($configFile)) {
            CLI::error('vite.config.ts or vite.config.js not found.');
            return;
        }

        $content = file_get_contents($configFile);

        // Add imports
        if (!str_contains($content, "import vue from '@vitejs/plugin-vue'")) {
            $content = "import vue from '@vitejs/plugin-vue'\n" . $content;
        }
        if (!str_contains($content, "import { maizzle } from '@maizzle/framework'")) {
            $content = "import { maizzle } from '@maizzle/framework'\n" . $content;
        }

        // Add plugins
        $maizzleConfig = "        vue(),\n        maizzle({\n            root: 'resources/js/emails',\n            output: {\n                path: 'app/Views/emails',\n                extension: 'php',\n            },\n            static: {\n                source: ['resources/js/emails/images'],\n            },\n        }),";

        if (!str_contains($content, 'maizzle(')) {
            $content = preg_replace(
                '/plugins:\s*\[/',
                "plugins: [\n{$maizzleConfig}",
                $content
            );
        }

        $this->writeFile($configFile, $content);
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Updated vite config with Maizzle plugin.', 'dark_gray');
    }
}
