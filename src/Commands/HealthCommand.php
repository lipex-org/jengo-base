<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Config\Services;
use Throwable;

class HealthCommand extends BaseCommand
{
    protected $group       = 'Jengo';
    protected $name        = 'jengo:health';
    protected $description = 'Diagnose application health and configuration.';
    protected $usage       = 'jengo:health';

    private int $issues = 0;
    private int $warnings = 0;

    public function run(array $params)
    {
        CLI::newLine();
        $this->renderHeader("THE GUARDIAN", "Application Health & Diagnostics");

        $this->checkEnvironment();
        $this->checkFileSystem();
        $this->checkDatabase();
        $this->checkFrontend();
        $this->checkInfrastructure();

        $this->renderSummary();
    }

    private function checkEnvironment(): void
    {
        $this->startSuite("ENVIRONMENT");

        // .env check
        $envExists = file_exists(ROOTPATH . '.env');
        $this->renderRow("Configuration (.env)", $envExists ? 'PASS' : 'FAIL', $envExists ? "Found" : "Missing file");

        if ($envExists) {
            $baseURL = env('app.baseURL');
            $this->renderRow("Base URL", $baseURL ? 'PASS' : 'WARN', $baseURL ?: "Not set in .env");

            $key = env('encryption.key');
            $this->renderRow("Encryption Key", $key ? 'PASS' : 'FAIL', $key ? "Set" : "Missing encryption.key");
        }

        $this->renderRow("Environment", 'PASS', (string)env('CI_ENVIRONMENT', 'production'));
    }

    private function checkFileSystem(): void
    {
        $this->startSuite("FILE SYSTEM");

        $paths = [
            'Writable Root' => WRITEPATH,
            'Logs'          => WRITEPATH . 'logs',
            'Cache'         => WRITEPATH . 'cache',
            'Sessions'      => WRITEPATH . 'session',
        ];

        foreach ($paths as $label => $path) {
            if (!is_dir($path)) {
                $this->renderRow($label, 'WARN', "Directory missing");
                continue;
            }

            $isWritable = is_writable($path);
            $this->renderRow($label, $isWritable ? 'PASS' : 'FAIL', $isWritable ? "Writable" : "Permission denied");
        }
    }

    private function checkDatabase(): void
    {
        $this->startSuite("DATABASE");

        try {
            $db = Database::connect();
            $db->connect();
            $connected = $db->connID !== false;
            $this->renderRow("Connection", $connected ? 'PASS' : 'FAIL', $connected ? "Connected" : "Failed to connect");

            if ($connected) {
                $migrations = Services::migrations();
                $history = $migrations->getHistory();
                $available = $migrations->findMigrations();
                
                $pending = count($available) - count($history);
                $this->renderRow("Migrations", $pending === 0 ? 'PASS' : 'WARN', $pending === 0 ? "Up to date" : "{$pending} pending");
            }
        } catch (Throwable $e) {
            $this->renderRow("Connection", 'FAIL', "Exception: " . $e->getMessage());
        }
    }

    private function checkFrontend(): void
    {
        $this->startSuite("FRONTEND (VITE)");

        $pkgExists = file_exists(ROOTPATH . 'package.json');
        $this->renderRow("package.json", $pkgExists ? 'PASS' : 'WARN', $pkgExists ? "Found" : "Missing (Vite not installed?)");

        if ($pkgExists) {
            $nodeModules = is_dir(ROOTPATH . 'node_modules');
            $this->renderRow("node_modules", $nodeModules ? 'PASS' : 'FAIL', $nodeModules ? "Installed" : "Missing (run npm install)");

            $viteEnabled = env('VITE_ENABLED');
            $this->renderRow("Vite Enabled", $viteEnabled ? 'PASS' : 'WARN', $viteEnabled ? "Yes" : "Set to false or missing");
        }
    }

    private function checkInfrastructure(): void
    {
        $this->startSuite("INFRASTRUCTURE");

        $this->renderRow("PHP Version", PHP_VERSION_ID >= 80100 ? 'PASS' : 'FAIL', PHP_VERSION);

        $extensions = ['intl', 'mbstring', 'curl', 'json'];
        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $this->renderRow("Ext: {$ext}", $loaded ? 'PASS' : 'FAIL', $loaded ? "Loaded" : "Missing");
        }
    }

    private function renderHeader(string $title, string $subtitle): void
    {
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::write("    " . CLI::color($title, 'light_cyan'));
        CLI::write("    " . CLI::color($subtitle, 'dark_gray'));
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::newLine();
    }

    private function startSuite(string $name): void
    {
        CLI::write("  " . CLI::color($name, 'cyan'));
        CLI::write("  " . str_repeat('┈', 60), 'dark_gray');
    }

    private function renderRow(string $label, string $status, string $message): void
    {
        $icon = '';
        $color = 'white';

        switch ($status) {
            case 'PASS':
                $icon = CLI::color('✔', 'green');
                $color = 'dark_gray';
                break;
            case 'FAIL':
                $icon = CLI::color('✘', 'red');
                $color = 'red';
                $this->issues++;
                break;
            case 'WARN':
                $icon = CLI::color('!', 'yellow');
                $color = 'yellow';
                $this->warnings++;
                break;
        }

        $labelCol = str_pad("    " . $label, 30);
        $statusCol = str_pad($icon, 10);

        CLI::print($labelCol);
        CLI::print($statusCol);
        CLI::write($message, $color);
    }

    private function renderSummary(): void
    {
        CLI::newLine();
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        
        if ($this->issues === 0 && $this->warnings === 0) {
            CLI::write("    " . CLI::color('ALL SYSTEMS GO', 'green') . " - Your architecture is sound.");
        } else {
            $summary = [];
            if ($this->issues > 0) $summary[] = CLI::color("{$this->issues} issues", 'red');
            if ($this->warnings > 0) $summary[] = CLI::color("{$this->warnings} warnings", 'yellow');
            
            CLI::write("    " . CLI::color('DIAGNOSIS COMPLETE', 'light_cyan') . " - Found " . implode(' and ', $summary));
        }
        
        CLI::write("  " . str_repeat('━', 60), 'dark_gray');
        CLI::newLine();
    }
}
