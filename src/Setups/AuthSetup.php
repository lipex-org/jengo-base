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
        $this->publishBlueprintAuth();
        $this->configureShield();
    }

    private function runShieldSetup(): void
    {
        CLI::write("  " . CLI::color('●', 'light_cyan') . " Running Shield Setup...");
        $this->call('shield:setup');
        CLI::write("  " . CLI::color('✔', 'green') . " Shield setup completed.");
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
