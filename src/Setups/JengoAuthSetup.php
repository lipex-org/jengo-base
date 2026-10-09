<?php

declare(strict_types=1);

namespace Jengo\Base\Setups;

use CodeIgniter\CLI\CLI;

class JengoAuthSetup extends AbstractSetup
{
    public static function name(): string
    {
        return 'auth';
    }

    public static function title(): string
    {
        return 'THE JENGO AUTH SUITE';
    }

    public static function description(): string
    {
        return 'Setup native Jengo Auth with Vima authorization and Blueprint styling';
    }

    public function setup(): void
    {
        $this->renderHeader(self::title(), self::description());

        // 1. Ensure jengo/auth is installed
        if (!$this->ensurePackage('jengo/auth')) {
            return;
        }

        // 2. Publish JengoAuth Blueprint Views (app.layout.php, home.page.php, dashboard.page.php)
        CLI::write('  ' . CLI::color('●', 'light_cyan') . ' Publishing Jengo Auth Blueprint views...');

        $stubsDir = __DIR__ . '/../Publisher/Stubs/Blueprint/Views/JengoAuth/';
        if (is_dir($stubsDir . 'layouts')) {
            $this->publish($stubsDir . 'layouts', 'app/Views/layouts');
        }
        if (is_dir($stubsDir . 'pages')) {
            $this->publish($stubsDir . 'pages', 'app/Views/pages');
        }

        // 3. Register Jengo Auth Helpers
        $this->addHelperToAutoload([
            'Jengo\Auth\Helpers\auth',
        ]);

        // 4. Run Jengo Auth Installer (publishes Config/Auth.php, Vima setup, and appends service('auth')->routes($routes) to Config/Routes.php)
        $kit = CLI::getOption('kit') ?? CLI::getOption('framework');
        $args = ['auth', '--yes'];
        if ($kit) {
            $args[] = "--kit={$kit}";
        }

        $this->command('jengo:install', $args);

        // 5. Ensure dashboard route uses 'auth:universal' filter
        $this->updateDashboardRouteFilter();

        CLI::newLine();
        CLI::write('  ' . CLI::color('✔', 'green') . ' Jengo Auth suite configured successfully.');
        CLI::write('  ' . CLI::color('●', 'yellow') . ' Note: Run php spark migrate to set up Jengo Auth tables.');
    }

    protected function updateDashboardRouteFilter(): void
    {
        $routesFile = APPPATH . 'Config/Routes.php';
        if (!file_exists($routesFile)) {
            return;
        }

        $content = file_get_contents($routesFile);
        if ($content === false) {
            return;
        }

        // Replace any ['filter' => 'session'] for dashboard route with ['filter' => 'auth:universal']
        $updated = preg_replace(
            "/(\\\$routes->get\s*\(\s*['\"]dashboard['\"]\s*,\s*['\"][^'\"]+['\"]\s*,\s*\[\s*['\"]filter['\"]\s*=>\s*['\"])(session)(['\"]\s*\]\s*\);)/",
            '$1auth:universal$3',
            $content
        );

        if ($updated !== null && $updated !== $content) {
            file_put_contents($routesFile, $updated);
            CLI::write('  ' . CLI::color('●', 'cyan') . ' Updated dashboard route filter to [auth:universal].', 'dark_gray');
        }
    }
}
