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
        return 'THE GATEKEEPER';
    }

    public static function description(): string
    {
        return 'Setup CodeIgniter Shield with Jengo Blueprint styling';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());

        if (!$this->ensurePackage('codeigniter4/shield')) {
            return;
        }

        $this->runShieldSetup();
        
        $isInertiaInstalled = file_exists(ROOTPATH . 'vendor/jengo/inertia/composer.json') || class_exists('\Jengo\Inertia\Inertia');

        if ($isInertiaInstalled) {
            $this->publishInertiaAuth();
        } else {
            $this->publishBlueprintAuth();
            $this->configureShield();
        }
    }

    private function runShieldSetup(): void
    {
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Running Manual Shield Setup...");

        $sourceDir = ROOTPATH . 'vendor/codeigniter4/shield/src/Config/';
        $destDir = APPPATH . 'Config/';

        // 1. Publish Configs
        $this->copyAndReplace($sourceDir . 'Auth.php', $destDir . 'Auth.php', [
            'namespace CodeIgniter\Shield\Config'  => 'namespace Config',
            'use CodeIgniter\Config\BaseConfig;'   => 'use CodeIgniter\Shield\Config\Auth as ShieldAuth;',
            'extends BaseConfig'                   => 'extends ShieldAuth',
        ]);

        $this->copyAndReplace($sourceDir . 'AuthGroups.php', $destDir . 'AuthGroups.php', [
            'namespace CodeIgniter\Shield\Config'  => 'namespace Config',
            'use CodeIgniter\Config\BaseConfig;'   => 'use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;',
            'extends BaseConfig'                   => 'extends ShieldAuthGroups',
        ]);

        $this->copyAndReplace($sourceDir . 'AuthToken.php', $destDir . 'AuthToken.php', [
            'namespace CodeIgniter\Shield\Config;' => "namespace Config;\n\nuse CodeIgniter\Shield\Config\AuthToken as ShieldAuthToken;",
            'extends BaseAuthToken'                => 'extends ShieldAuthToken',
        ]);

        // 2. Autoload Helpers
        $autoloadPath = $destDir . 'Autoload.php';
        if (file_exists($autoloadPath)) {
            $content = file_get_contents($autoloadPath);
            $pattern = '/^    public \$helpers = \[(.*?)\];/msu';
            if (preg_match($pattern, $content, $matches)) {
                $helpers = array_map('trim', explode(',', str_replace(["'", '"'], '', $matches[1])));
                $helpers = array_filter($helpers);
                $newHelpers = array_unique(array_merge($helpers, ['auth', 'setting']));
                $replace = '    public $helpers = [\'' . implode("', '", $newHelpers) . '\'];';
                $content = preg_replace($pattern, $replace, $content);
                file_put_contents($autoloadPath, $content);
            }
        }

        // 3. Setup Routes
        $routesPath = $destDir . 'Routes.php';
        if (file_exists($routesPath)) {
            $content = file_get_contents($routesPath);
            if (!str_contains($content, "service('auth')->routes(\$routes);")) {
                $content .= "\nservice('auth')->routes(\$routes);\n";
                file_put_contents($routesPath, $content);
            }
        }

        // 4. Security CSRF
        $securityPath = $destDir . 'Security.php';
        if (file_exists($securityPath)) {
            $content = file_get_contents($securityPath);
            $content = str_replace("\$csrfProtection = 'cookie';", "\$csrfProtection = 'session';", $content);
            file_put_contents($securityPath, $content);
        }

        CLI::write("  " . CLI::color('✔', 'green') . " Shield setup completed.");
    }

    private function copyAndReplace(string $source, string $dest, array $replacements): void
    {
        if (!file_exists($source)) return;
        if (file_exists($dest)) return; // Don't overwrite if it exists
        
        $content = file_get_contents($source);
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0777, true);
        }
        
        file_put_contents($dest, $content);
    }

    private function publishInertiaAuth(): void
    {
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Configuring Shield for Inertia SPA...");

        // Publish Auth Controller
        $stubsDir = __DIR__ . '/../Publisher/Stubs/Auth/';
        if (!is_dir(APPPATH . 'Controllers/Auth')) {
            mkdir(APPPATH . 'Controllers/Auth', 0777, true);
        }
        copy($stubsDir . 'Controllers/AuthController.php', APPPATH . 'Controllers/Auth/AuthController.php');

        // Update Routes
        $routesPath = APPPATH . 'Config/Routes.php';
        if (file_exists($routesPath)) {
            $content = file_get_contents($routesPath);
            $inertiaRoutes = "\n// Jengo Inertia Auth Routes\n\$routes->get('login', '\App\Controllers\Auth\AuthController::loginView');\n\$routes->get('register', '\App\Controllers\Auth\AuthController::registerView');\n";
            
            if (!str_contains($content, "AuthController::loginView")) {
                $content .= $inertiaRoutes;
                file_put_contents($routesPath, $content);
            }
        }

        CLI::write("  " . CLI::color('✔', 'green') . " Inertia Auth configuration completed.");
    }

    private function publishBlueprintAuth(): void
    {
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Applying Jengo Blueprint styling to Auth views...");

        $stubsDir = __DIR__ . '/../Publisher/Stubs/Auth/';
        
        // 1. Publish Layout
        $layoutDest = APPPATH . 'Views/layouts/auth.layout.php';
        if (!is_dir(dirname($layoutDest))) {
            mkdir(dirname($layoutDest), 0777, true);
        }
        copy($stubsDir . 'layouts/auth.layout.php', $layoutDest);

        // 2. Publish Views
        $viewsDest = APPPATH . 'Views/Shield/';
        if (!is_dir($viewsDest)) {
            mkdir($viewsDest, 0777, true);
        }
        
        copy($stubsDir . 'Views/login.php', $viewsDest . 'login.php');
        copy($stubsDir . 'Views/register.php', $viewsDest . 'register.php');

        CLI::write("  " . CLI::color('✔', 'green') . " Blueprint Auth views published.");
    }

    private function configureShield(): void
    {
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Configuring Shield to use Jengo views...");

        $path = APPPATH . 'Config/AuthView.php';
        if (!file_exists($path)) {
            CLI::write("  " . CLI::color('○', 'yellow') . " Config/AuthView.php not found. Skipping config update.");
            return;
        }

        $content = file_get_contents($path);

        $replacements = [
            "'login'             => 'CodeIgniter\Shield\Views\login'" => "'login'             => 'Shield\login'",
            "'register'          => 'CodeIgniter\Shield\Views\register'" => "'register'          => 'Shield\register'",
        ];

        foreach ($replacements as $old => $new) {
            $content = str_replace($old, $new, $content);
        }

        file_put_contents($path, $content);
        CLI::write("  " . CLI::color('✔', 'green') . " Config/AuthView.php updated.");
    }
}
