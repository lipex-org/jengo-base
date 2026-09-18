<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

use CodeIgniter\CLI\CLI;

class ApiSetup extends AbstractSetup
{
    public static function name(): string
    {
        return 'api';
    }

    public static function title(): string
    {
        return 'JENGO API';
    }

    public static function description(): string
    {
        return 'Install and configure the first-party Jengo API package';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());

        // 1. Ensure jengo/api is installed
        if (!$this->ensurePackage('jengo/api')) {
            return;
        }

        // 2. Trigger Jengo API setup
        CLI::newLine();
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Triggering Jengo API setup...");
        CLI::write("    " . str_repeat('┈', 40), 'dark_gray');

        $force = CLI::getOption('force') ? ['--force'] : [];
        $this->command('jengo:api setup', $force);

        CLI::newLine();
        CLI::write("  " . CLI::color('✔', 'green') . " Jengo API package configured successfully.");
    }
}
