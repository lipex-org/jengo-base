<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

class CoreSetup extends AbstractSetup
{
    public static function name(): string
    {
        return 'core';
    }

    public static function title(): string
    {
        return 'THE FOUNDATION';
    }

    public static function description(): string
    {
        return 'Configure Jengo core helpers and autoloading';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());

        $this->addHelperToAutoload(['Jengo\Base\Helpers\jengo']);

        // Create modules ecosystem directories
        $modulesDir = ROOTPATH . 'modules';
        $groupDirs = [
            $modulesDir . '/Core',
            $modulesDir . '/Financial',
        ];

        if (!is_dir($modulesDir)) {
            mkdir($modulesDir, 0777, true);
        }

        foreach ($groupDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            // Put .gitkeep inside
            $gitkeepPath = $dir . '/.gitkeep';
            if (!file_exists($gitkeepPath)) {
                file_put_contents($gitkeepPath, '');
            }
        }

        // Register Modules namespace in composer.json
        $composerJsonPath = ROOTPATH . 'composer.json';
        if (file_exists($composerJsonPath)) {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);
            
            if (!isset($composerJson['autoload'])) {
                $composerJson['autoload'] = [];
            }
            if (!isset($composerJson['autoload']['psr-4'])) {
                $composerJson['autoload']['psr-4'] = [];
            }

            if (!isset($composerJson['autoload']['psr-4']['Modules\\'])) {
                $composerJson['autoload']['psr-4']['Modules\\'] = 'modules/';
                file_put_contents(
                    $composerJsonPath,
                    json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
                
                \CodeIgniter\CLI\CLI::write('  ' . \CodeIgniter\CLI\CLI::color('●', 'cyan') . ' Added Modules namespace to composer.json.', 'dark_gray');
                
                $composer = $this->composer();
                $composer->run('composer dump-autoload', ROOTPATH);
            }
        }
    }
}
