<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

use CodeIgniter\CLI\CLI;

class VaultSetup extends AbstractSetup
{
    public static function name(): string
    {
        return 'api';
    }

    public static function title(): string
    {
        return 'THE VAULT';
    }

    public static function description(): string
    {
        return 'Setup professional API suite with JWT and standardized responses';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());

        // 1. Install JWT
        if (!$this->ensurePackage('firebase/php-jwt')) {
            return;
        }

        // 2. Publish Base API Controller
        $this->publishAPIController();

        CLI::newLine();
        CLI::write("  " . CLI::color('✔', 'green') . " API Vault has been established.");
        CLI::write("    You can now use " . CLI::color('--api', 'cyan') . " with jengo:make-module.");
    }

    private function publishAPIController(): void
    {
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Publishing Base API Controller...");

        $dest = APPPATH . 'Controllers/APIController.php';
        $stub = __DIR__ . '/../Publisher/Stubs/Vault/APIController.php';

        if (file_exists($dest)) {
            CLI::write("  " . CLI::color('○', 'yellow') . " APIController.php already exists. Skipping.");
            return;
        }

        copy($stub, $dest);
        CLI::write("  " . CLI::color('✔', 'green') . " APIController published to App/Controllers.");
    }
}
