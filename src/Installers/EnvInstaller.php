<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class EnvInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'env';
    }

    public static function description(): string
    {
        return 'Install Jengo opinionated .env configuration file';
    }

    public static function reasonForSkipping(): string
    {
        return '.env file already exists.';
    }

    public function shouldRun(): bool
    {
        return !file_exists(ROOTPATH . '.env');
    }

    public function install(): void
    {
        $this->addRun();

        // Generate secure 32-byte encryption key
        $key = 'hex2bin:' . bin2hex(random_bytes(32));

        $envContent = <<<EOT
#--------------------------------------------------------------------
# Jengo Environment Configuration
#--------------------------------------------------------------------

# Environment: development, production, testing
CI_ENVIRONMENT = development

# App Configuration
app.baseURL = 'http://localhost:8080'
app.forceGlobalSecureRequests = false
app.indexPage = ''

# Database Configuration
database.default.DBDriver = SQLite3
database.default.database = writable/database.db

# Encryption Key
encryption.key = '{$key}'

# Security
security.tokenName = 'csrf_token'
security.headerName = 'X-CSRF-TOKEN'
security.cookieName = 'csrf_cookie'
security.expires = 7200
security.regenerate = true
security.redirect = true

# Session
session.driver = 'CodeIgniter\Session\Handlers\FileHandler'
session.savePath = 'writable/session'
EOT;

        $this->writeFile(ROOTPATH . '.env', $envContent);

        CLI::write('Jengo opinionated .env file successfully created with secure encryption key.', 'green');
    }
}
