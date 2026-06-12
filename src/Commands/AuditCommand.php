<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditCommand extends BaseCommand
{
    protected $group       = 'Jengo';
    protected $name        = 'jengo:audit';
    protected $description = 'Audit the project for security misconfigurations and best practices.';
    protected $usage       = 'jengo:audit';

    public function run(array $params)
    {
        CLI::write("  " . str_repeat('━', 50), 'dark_gray');
        CLI::write("    JENGO SECURITY AUDITOR", 'light_cyan');
        CLI::write("  " . str_repeat('━', 50), 'dark_gray');
        CLI::newLine();

        $vulnerabilities = 0;

        // 1. Check CSRF
        if (!$this->checkCSRF()) $vulnerabilities++;

        // 2. Check CSP
        if (!$this->checkCSP()) $vulnerabilities++;

        // 3. Check Writable Folders
        if (!$this->checkPermissions()) $vulnerabilities++;

        // 4. Check Environment
        if (!$this->checkEnv()) $vulnerabilities++;

        CLI::newLine();
        if ($vulnerabilities === 0) {
            CLI::write("✔ No critical security issues found. Stay vigilant!", 'green');
        } else {
            CLI::write("⚠ Found {$vulnerabilities} potential security risk(s).", 'yellow');
        }
        CLI::newLine();
    }

    private function checkCSRF(): bool
    {
        CLI::print("  " . str_pad("CSRF Protection", 30));
        
        $config = config('Security');
        if ($config->csrfProtection !== 'none') {
            CLI::write(" [ PASS ]", 'green');
            return true;
        }

        CLI::write(" [ FAIL ]", 'red');
        CLI::write("    ↳ CSRF protection is disabled. Enable it in Config/Security.php", 'dark_gray');
        return false;
    }

    private function checkCSP(): bool
    {
        CLI::print("  " . str_pad("Content Security Policy", 30));
        
        $config = config('ContentSecurityPolicy');
        if ($config->reportOnly === false) {
            CLI::write(" [ PASS ]", 'green');
            return true;
        }

        CLI::write(" [ WARN ]", 'yellow');
        CLI::write("    ↳ CSP is in 'reportOnly' mode. Set \$reportOnly = false in Config/ContentSecurityPolicy.php", 'dark_gray');
        return true; // Just a warning
    }

    private function checkPermissions(): bool
    {
        CLI::print("  " . str_pad("Public Folder Safety", 30));
        
        $publicWrite = is_writable(FCPATH);
        if (!$publicWrite) {
            CLI::write(" [ PASS ]", 'green');
            return true;
        }

        CLI::write(" [ FAIL ]", 'red');
        CLI::write("    ↳ The public/ directory is writable. This is a severe risk.", 'dark_gray');
        return false;
    }

    private function checkEnv(): bool
    {
        CLI::print("  " . str_pad("Environment Mode", 30));
        
        $env = env('CI_ENVIRONMENT', 'production');
        if ($env === 'production') {
            CLI::write(" [ PASS ]", 'green');
            return true;
        }

        CLI::write(" [ INFO ]", 'cyan');
        CLI::write("    ↳ Running in '{$env}' mode. Ensure production uses 'production'.", 'dark_gray');
        return true;
    }
}
