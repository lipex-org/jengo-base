<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

use CodeIgniter\CLI\CLI;

class InertiaSetup extends AbstractSetup
{
    public static function name(): string
    {
        return 'inertia';
    }

    public static function title(): string
    {
        return 'THE BRIDGE';
    }

    public static function description(): string
    {
        return 'Setup Inertia.js with the jengo/inertia adapter';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());

        if (!$this->ensurePackage('jengo/inertia')) {
            return;
        }

        $framework = CLI::getOption('framework');
        $yes = CLI::getOption('yes') ? '--yes' : '';
        $pm = CLI::getOption('pm') ?? 'npm';

        CLI::newLine();
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Triggering Inertia Installer...");
        CLI::write("    " . str_repeat('┈', 40), 'dark_gray');

        $args = ['inertia'];
        if ($framework) {
            $args[] = "--framework={$framework}";
        }

        if ($yes) {
            $args[] = $yes;
        }

        if ($pm) {
            $args[] = "--pm={$pm}";
        }

        $this->call('jengo:install', $args);
    }
}
