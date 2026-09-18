<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

use CodeIgniter\CLI\CLI;

class AuthSetup extends AbstractSetup
{
    public static function name(): string
    {
        return 'auth';
    }

    public static function title(): string
    {
        return 'JENGO AUTH';
    }

    public static function description(): string
    {
        return 'Install and configure the first-party Jengo Auth package';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());

        // 1. Ensure jengo/auth is installed
        if (!$this->ensurePackage('jengo/auth')) {
            return;
        }

        // 2. Trigger Jengo Auth setup
        CLI::newLine();
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Triggering Jengo Auth setup...");
        CLI::write("    " . str_repeat('┈', 40), 'dark_gray');

        $force = CLI::getOption('overwrite') || CLI::getOption('force') ? ['--overwrite'] : [];
        $this->command('jengo:auth setup', $force);

        CLI::newLine();
        CLI::write("  " . CLI::color('✔', 'green') . " Jengo Auth package configured successfully.");
    }
}
