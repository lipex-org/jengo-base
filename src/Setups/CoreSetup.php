<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

use CodeIgniter\CLI\CLI;

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

        $this->addHelperToAutoload();
    }

    private function addHelperToAutoload(): void
    {
        $path = APPPATH . 'Config/Autoload.php';
        
        if (!file_exists($path)) {
            CLI::error("Config/Autoload.php not found.");
            return;
        }

        $content = file_get_contents($path);
        
        if (str_contains($content, 'Jengo\Base\Helpers\jengo')) {
            CLI::write("  " . CLI::color('✔', 'green') . " Jengo helper is already configured.");
            return;
        }

        $content = preg_replace(
            '/(public\s+\$helpers\s*=\s*\[)/',
            "$1\n        'Jengo\\\Base\\\Helpers\\\jengo',",
            $content,
            1
        );
        
        file_put_contents($path, $content);
        CLI::write("  " . CLI::color('✔', 'green') . " Jengo helper added to Autoload config.");
    }
}
