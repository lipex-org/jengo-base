<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Test\MockInputOutput;

class AuthSetup extends AbstractSetup
{
    public static function name(): string
    {
        return 'auth';
    }

    public static function title(): string
    {
        return 'THE GATEKEEPER';
    }

    public static function description(): string
    {
        return 'Setup CodeIgniter Shield with Jengo Blueprint styling';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());
        $isInertia = CLI::getOption('inertia');

        // 1. Ensure Shield is installed
        if (!$this->ensurePackage('codeigniter4/shield')) {
            return;
        }

        // Publish Shield Config
        $this->copy([
            VENDORPATH . 'codeigniter4/shield/src/Config/Auth.php' => 'app/Config/Auth.php',
            VENDORPATH . 'codeigniter4/shield/src/Config/AuthToken.php' => 'app/Config/AuthToken.php',
        ]);


        // 3. Publish Jengo Auth Stubs
        CLI::write('  ' . CLI::color('●', 'light_cyan') . ' Publishing Jengo Auth components...');

        if ($isInertia) {
            // Publish authentication actions
            $this->publish(__DIR__ . '/../Publisher/Stubs/Auth/Authentication/', 'app/Authentication');

            // Configs
            $this->publish(__DIR__ . '/../Publisher/Stubs/Auth/Config/Inertia', 'app/Config');

            // Controllers
            $this->publish(__DIR__ . '/../Publisher/Stubs/Auth/Controllers', 'app/Controllers');
        } else {
            $this->publish(__DIR__ . '/../Publisher/Stubs/Auth/Config/Default', 'app/Config');
        }

        // 4. Update Configs
        $this->updateAuthConfig();
        $this->updateAuthTokenConfig();
        $this->updateEmailConfig();
        $this->updateSecurityConfig();
        $this->addHelperToAutoload([
            'CodeIgniter\Settings\Helpers\setting',
            'CodeIgniter\Shield\Helpers\auth',
        ]);
        $this->updateFilters();

        CLI::newLine();
        CLI::write('  ' . CLI::color('✔', 'green') . ' Auth suite configured successfully.');
        CLI::write('  ' . CLI::color('●', 'yellow') . ' Note: Remember to run migrations to set up Shield tables.');
    }

    protected function updateAuthConfig(): void
    {
        $path = APPPATH . 'Config/Auth.php';
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        // Fix Namespace and Extension
        $content = str_replace('namespace CodeIgniter\Shield\Config;', "namespace Config;\n\nuse CodeIgniter\Shield\Config\Auth as ShieldAuth;", $content);
        $content = str_replace('class Auth extends BaseConfig', 'class Auth extends ShieldAuth', $content);

        // Update Redirects
        $redirects = "public array \$redirects = [
        'register'          => 'dashboard',
        'login'             => 'dashboard',
        'logout'            => 'login',
        'force_reset'       => '/',
        'permission_denied' => '/',
        'group_denied'      => '/',
        'registerOnDisable' => '/'
    ];";

        $content = preg_replace('/public array \$redirects = \[.*?\];/s', $redirects, $content);

        // Add registerRedirectOnDisable method
        $method = "\n    /**\n     * Returns the URL the user should be redirected to\n     * if registration is disabled.\n     */\n    public function registerRedirectOnDisable(): string\n    {\n        \$url = setting('Auth.redirects')['registerOnDisable'];\n\n        return \$this->getUrl(\$url);\n    }\n";

        if (!str_contains($content, 'function registerRedirectOnDisable')) {
            $content = str_replace('protected function getUrl', $method . "\n    protected function getUrl", $content);
        }

        $this->writeFile($path, $content);

        CLI::write('  ' . CLI::color('●', 'cyan') . ' Updated Config/Auth.php redirects and views.', 'dark_gray');
    }

    protected function updateAuthTokenConfig(): void
    {
        $path = APPPATH . 'Config/AuthToken.php';
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        // Fix Namespace and Extension
        $content = str_replace('namespace CodeIgniter\Shield\Config;', "namespace Config;\n\nuse CodeIgniter\Shield\Config\AuthToken as ShieldAuthToken;", $content);
        $content = str_replace('class AuthToken extends BaseAuthToken', 'class AuthToken extends ShieldAuthToken', $content);

        $this->writeFile($path, $content);
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Updated Config/AuthToken.php namespace and extension.', 'dark_gray');
    }

    protected function updateEmailConfig(): void
    {
        $path = APPPATH . 'Config/Email.php';
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        $content = preg_replace('/(\$fromEmail\s*=\s*)([\'"].*?[\'"])/', '$1\'test@jengo.com\'', $content);
        $content = preg_replace('/(\$fromName\s*=\s*)([\'"].*?[\'"])/', '$1\'Jengo\'', $content);

        $this->writeFile($path, $content);
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Updated Config/Email.php fromEmail and fromName.', 'dark_gray');
    }

    protected function updateSecurityConfig(): void
    {
        $path = APPPATH . 'Config/Security.php';
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        $content = preg_replace('/(\$csrfProtection\s*=\s*)([\'"].*?[\'"])/', '$1\'session\'', $content);

        $this->writeFile($path, $content);
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Updated Config/Security.php csrfProtection to session.', 'dark_gray');
    }

    protected function updateFilters(): void
    {
        $path = APPPATH . 'Config/Filters.php';

        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        // 1. Add the alias to the $aliases array if it doesn't exist
        if (!str_contains($content, "'inertia' => \\App\\Filters\\Inertia::class")) {
            // Find the public $aliases = [ line
            $aliasPattern = '/(public\s+array\s+\$aliases\s*=\s*\[)/';
            $aliasReplacement = "$1\n        'inertia' => \\App\\Filters\\Inertia::class,";
            $content = preg_replace($aliasPattern, $aliasReplacement, $content);
        }

        // 2. Add 'inertia' to the globals -> before array
        // Looks for 'before' => [ and ensures 'inertia' isn't already added
        if (preg_match('/\'before\'\s*=>\s*\[([^\]]*)/s', $content, $matches)) {
            if (!str_contains($matches[1], "'inertia'")) {
                $content = preg_replace(
                    '/(\'before\'\s*=>\s*\[)/',
                    "$1\n            'inertia',",
                    $content
                );
            }
        }

        // 3. Add 'inertia' to the globals -> after array
        // Looks for 'after' => [ and ensures 'inertia' isn't already added
        if (preg_match('/\'after\'\s*=>\s*\[([^\]]*)/s', $content, $matches)) {
            if (!str_contains($matches[1], "'inertia'")) {
                $content = preg_replace(
                    '/(\'after\'\s*=>\s*\[)/',
                    "$1\n            'inertia',",
                    $content
                );
            }
        }

        // Save the updated configuration back to the file
        file_put_contents($path, $content);
    }
}
