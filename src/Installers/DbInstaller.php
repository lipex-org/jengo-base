<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class DbInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'db';
    }

    public static function description(): string
    {
        return 'Configure default database to SQLite and run migrations';
    }

    public static function reasonForSkipping(): string
    {
        return 'Database is already configured.';
    }

    public function shouldRun(): bool
    {
        return true;
    }

    public function install(): void
    {
        $this->addRun();

        CLI::write('  ' . CLI::color('●', 'cyan') . ' Configuring SQLite as the default database...', 'dark_gray');
        $this->configureDatabase();

        CLI::write('  ' . CLI::color('●', 'cyan') . ' Running database migrations...', 'dark_gray');
        $this->run('php spark migrate --all');

        CLI::write('Database configured successfully.', 'green');
    }

    private function configureDatabase(): void
    {
        $configPath = APPPATH . 'Config/Database.php';

        if (!file_exists($configPath)) {
            CLI::error('Config/Database.php not found.');
            return;
        }

        $content = file_get_contents($configPath);

        // 1. Comment out the active MySQL default connection
        // We look for a public array $default that has 'MySQLi' in it
        $content = preg_replace(
            '/^(\s+)(public array \$default = \[\s+.*?\'DBDriver\'\s*=>\s*\'MySQLi\'.*?\];)/ms',
            '$1/* $2 */',
            $content
        );

        // 2. Uncomment the SQLite connection
        $lines = explode("\n", $content);
        $inSqlite = false;
        $startIdx = -1;

        for ($i = 0; $i < count($lines); $i++) {
            if (str_contains($lines[$i], 'Sample database connection for SQLite3.')) {
                $inSqlite = true;
                // Go backwards to find the start of the docblock
                $j = $i;
                while ($j >= 0 && str_contains($lines[$j], '//')) {
                    if (str_contains($lines[$j], '/**')) {
                        $startIdx = $j;
                        break;
                    }
                    $j--;
                }
            }

            if ($inSqlite && str_contains($lines[$i], '];') && str_contains($lines[$i], '//')) {
                // Uncomment from startIdx to $i
                if ($startIdx !== -1) {
                    for ($k = $startIdx; $k <= $i; $k++) {
                        $lines[$k] = preg_replace('/^\s*\/\/\s?/', '    ', $lines[$k]);
                    }
                }
                $inSqlite = false;
                break;
            }
        }

        $content = implode("\n", $lines);

        file_put_contents($configPath, $content);
    }
}
