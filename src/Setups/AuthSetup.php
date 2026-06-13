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

        // 1. Ensure Shield is installed
        if (!$this->ensurePackage('codeigniter4/shield')) {
            return;
        }

        // 2. Run Shield Setup if not already done
        if (!file_exists(APPPATH . 'Config/Auth.php')) {
            CLI::write('  ' . CLI::color('●', 'light_cyan') . ' Running Shield setup...');
            $this->command('shield:setup', [], ['n', 'n', 'n']);
        }

        // 3. Publish Jengo Auth Stubs
        CLI::write('  ' . CLI::color('●', 'light_cyan') . ' Publishing Jengo Auth components...');

        // Configs
        $this->publish(__DIR__ . '/../Publisher/Stubs/Auth/Config', 'app/Config');

        // Controllers
        $this->publish(__DIR__ . '/../Publisher/Stubs/Auth/Controllers', 'app/Controllers');

        // Layouts
        $this->publish(__DIR__ . '/../Publisher/Stubs/Auth/layouts', 'app/Views/layouts');

        // Views
        $this->publish(__DIR__ . '/../Publisher/Stubs/Auth/Views', 'app/Views/auth');

        // 4. Update Auth Config
        $this->updateAuthConfig();

        // 5. Update Routes
        $this->updateRoutes();

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

        // Update Redirects
        $redirects = "public array \$redirects = [
        'register'          => 'dashboard',
        'login'             => 'dashboard',
        'logout'            => 'login',
        'force_reset'       => '/',
        'permission_denied' => '/',
        'group_denied'      => '/',
    ];";

        $content = preg_replace('/public array \$redirects = \[.*?\];/s', $redirects, $content);

        // Update View mapping to use Jengo views (if not using Inertia)
        // Note: The Jengo controllers use Inertia::render by default, 
        // but Shield's internal actions might still look at this config.
        $views = "public array \$views = [
        'login'                       => 'App\Views\auth\login',
        'register'                    => 'App\Views\auth\register',
        'layout'                      => 'App\Views\layouts\auth.layout',
        'action_show'                 => 'CodeIgniter\Shield\Views\action_show',
        'magic-link-login'            => 'CodeIgniter\Shield\Views\magic_link_form',
        'magic-link-message'          => 'CodeIgniter\Shield\Views\magic_link_message',
        'magic-link-email'            => 'CodeIgniter\Shield\Views\Email\magic_link_email',
        'verification-email'          => 'CodeIgniter\Shield\Views\Email\email_activation_email',
    ];";

        $content = preg_replace('/public array \$views = \[.*?\];/s', $views, $content);

        $this->writeFile($path, $content);
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Updated Config/Auth.php redirects and views.', 'dark_gray');
    }

    protected function updateRoutes(): void
    {
        $path = APPPATH . 'Config/Routes.php';
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        // Check if already updated
        if (str_contains($content, 'Jengo Inertia Auth Routes')) {
            return;
        }

        $stubPath = __DIR__ . '/../Publisher/Stubs/Auth/Config/Routes.php';
        $routesStub = file_get_contents($stubPath);

        // Remove php opening tag and imports from stub for cleaner injection if needed, 
        // but here we might just want to append or replace the auth section.

        // Simple approach: Replace service('auth')->routes($routes); with Jengo routes
        $search = "service('auth')->routes(\$routes);";
        if (str_contains($content, $search)) {
            $replacement = "\n// Jengo Inertia Auth Routes\n" . trim(str_replace('<?php', '', $routesStub));
            $content = str_replace($search, $replacement, $content);
        } else {
            // Append if not found
            $content .= "\n\n// Jengo Inertia Auth Routes\n" . trim(str_replace('<?php', '', $routesStub));
        }

        $this->writeFile($path, $content);
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Injected Jengo Auth routes into Config/Routes.php.', 'dark_gray');
    }
}
