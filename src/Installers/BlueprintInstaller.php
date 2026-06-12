<?php

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class BlueprintInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'blueprint';
    }

    public static function description(): string
    {
        return 'Sets up the core UI architecture (Layouts, Partials, Home Page)';
    }

    public static function reasonForSkipping(): string
    {
        return 'Blueprint has already been established.';
    }

    public function shouldRun(): bool
    {
        return !is_dir(APPPATH . 'Views/layouts');
    }

    public function install(): void
    {
        $this->addRun();

        $stubsDir = __DIR__ . '/../Publisher/Stubs/Blueprint/';

        // 1. Publish Layouts & Partials
        $this->publish($stubsDir . 'Views/layouts', 'app/Views/layouts');

        // 2. Publish Pages
        $this->publish($stubsDir . 'Views/pages', 'app/Views/pages');

        // 3. Publish Controllers
        $this->publish($stubsDir . 'Controllers', 'app/Controllers');

        // 4. Update Routes
        $this->updateRoutes();

        // Remove the default welcome_message.php if it exists
        $welcomePagePath = APPPATH . "Views/welcome_message.php";
        if (file_exists($welcomePagePath)) {
            unlink($welcomePagePath);
        }

        CLI::write('Blueprint installed successfully.', 'green');
    }

    private function updateRoutes(): void
    {
        $routesPath = APPPATH . 'Config/Routes.php';
        if (!file_exists($routesPath)) {
            return;
        }

        $content = file_get_contents($routesPath);

        // Define Dashboard Route (with session filter)
        $dashboardRoute = "\n// Jengo Dashboard Route\n\$routes->get('dashboard', 'Dashboard::index', ['filter' => 'session']);\n";

        if (!str_contains($content, "get('dashboard'")) {
            $content .= $dashboardRoute;
            $this->writeFile($routesPath, $content);
        }
    }
}
