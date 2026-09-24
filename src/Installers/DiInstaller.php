<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Installers\Contracts\AbstractInstaller;
use Jengo\Base\Tooling\Modifier\ClassModifier;
use Throwable;

class DiInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'di';
    }

    public static function description(): string
    {
        return 'Enable first-party Dependency Injection across BaseController and application routes.';
    }

    public static function reasonForSkipping(): string
    {
        return 'Dependency Injection is already enabled.';
    }

    public function shouldRun(): bool
    {
        return true;
    }

    public function install(): void
    {
        $this->addRun();

        CLI::write('Enabling First-Party Dependency Injection for Jengo...', 'yellow');

        $this->updateBaseController();
        $this->updateRouteFiles();

        CLI::write('Dependency Injection enabled successfully.', 'green');
    }

    /**
     * Inject HasContainer trait into app/Controllers/BaseController.php using ClassModifier.
     */
    protected function updateBaseController(): void
    {
        $baseControllerPath = APPPATH . 'Controllers/BaseController.php';

        if (! file_exists($baseControllerPath)) {
            CLI::write('BaseController.php not found in ' . $baseControllerPath, 'red');
            return;
        }

        try {
            $content = file_get_contents($baseControllerPath);

            if (str_contains($content, 'HasContainer')) {
                CLI::write('  BaseController already uses HasContainer trait.');
                return;
            }

            ClassModifier::fromFile($baseControllerPath)
                ->addTrait('Jengo\Base\Container\Traits\HasContainer')
                ->saveTo($baseControllerPath);

            CLI::write('  [OK] Added HasContainer trait to BaseController.', 'green');
        } catch (Throwable $e) {
            CLI::error('  Could not update BaseController automatically: ' . $e->getMessage());
            CLI::write('  Please add `use \Jengo\Base\Container\Traits\HasContainer;` inside your BaseController class.');
        }
    }

    /**
     * Locate all Routes.php files across the application and wrap closures with inject().
     */
    protected function updateRouteFiles(): void
    {
        $locator = Services::locator();
        $routeFiles = $locator->listFiles('Config/Routes.php');

        if (empty($routeFiles)) {
            $defaultRoute = APPPATH . 'Config/Routes.php';
            if (file_exists($defaultRoute)) {
                $routeFiles[] = $defaultRoute;
            }
        }

        $routeFiles = array_unique($routeFiles);

        foreach ($routeFiles as $routeFile) {
            $this->processRouteFile($routeFile);
        }
    }

    /**
     * Process an individual route file to wrap untyped/typed closures with inject().
     */
    protected function processRouteFile(string $filePath): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        $original = $content;

        // Pattern matching: $routes->(get|post|put|patch|delete|options|match|add|resource|presenter)\(..., function/static function
        // Ensure not already wrapped in inject(
        $pattern = '/(\$routes->(?:get|post|put|patch|delete|options|match|add)\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*)(?!inject\s*\()((?:static\s+)?(?:function|\bfn\b)\s*\([^)]*\)\s*(?:use\s*\([^)]*\)\s*)?(?:\{|=>))/i';

        // Wrap matches with inject(...)
        // Only if it's a closure route
        $modified = preg_replace_callback($pattern, static function ($matches) {
            return $matches[1] . 'inject(' . $matches[2];
        }, $content);

        // Close the inject() parenthesis matching the closure end
        if ($modified !== $content && $modified !== null) {
            CLI::write("  [OK] Enhanced route closures with inject() in {$filePath}", 'green');
            file_put_contents($filePath, $modified);
        } else {
            CLI::write("  Routes in {$filePath} checked.");
        }
    }
}
