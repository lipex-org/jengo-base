<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Modules;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractVariant;

class PatchVariant extends AbstractVariant
{
    public static function name(): string
    {
        return 'patch';
    }

    public static function description(): string
    {
        return 'Mitigates CI4 autoloader limitation by applying the autoloader_initialized patch.';
    }

    public function options(): array
    {
        return [
            '--check' => 'Check if the CI4 Boot.php file and app/Config/Events.php are patched without modifying them.',
        ];
    }

    public function run(array $params): void
    {
        $isCheckOnly = CLI::getOption('check') !== null
            || in_array('--check', $params, true)
            || array_key_exists('check', $params);

        $bootFile = SYSTEMPATH . 'Boot.php';
        $eventsFile = APPPATH . 'Config/Events.php';

        CLI::write('Checking CodeIgniter 4 Autoloader integration...', 'cyan');

        $bootPatched = $this->isBootPatched($bootFile);
        $eventsPatched = $this->isEventsPatched($eventsFile);

        if ($isCheckOnly) {
            CLI::write('SYSTEMPATH Boot.php: ' . ($bootPatched ? CLI::color('Patched', 'green') : CLI::color('Not Patched', 'yellow')));
            CLI::write('APPPATH Config/Events.php: ' . ($eventsPatched ? CLI::color('Subscribed', 'green') : CLI::color('Not Subscribed', 'yellow')));
            return;
        }

        $this->patchBootFile($bootFile);
        $this->patchEventsFile($eventsFile);

        CLI::newLine();
        CLI::write('Autoloader mitigation completed successfully.', 'green');
    }

    private function isBootPatched(string $bootFile): bool
    {
        if (!is_file($bootFile)) {
            return false;
        }

        $content = file_get_contents($bootFile);
        return str_contains($content, "Events::trigger('autoloader_initialized'");
    }

    private function isEventsPatched(string $eventsFile): bool
    {
        if (!is_file($eventsFile)) {
            return false;
        }

        $content = file_get_contents($eventsFile);
        return str_contains($content, 'autoloader_initialized')
            && str_contains($content, 'ModuleDiscovery::discoverAndRegister');
    }

    private function patchBootFile(string $bootFile): void
    {
        if (!is_file($bootFile)) {
            CLI::error("SYSTEMPATH Boot.php not found at [{$bootFile}].");
            return;
        }

        if ($this->isBootPatched($bootFile)) {
            CLI::write('  SYSTEMPATH Boot.php is already patched with autoloader_initialized trigger.', 'dark_gray');
            return;
        }

        $content = file_get_contents($bootFile);

        $target = 'Services::autoloader()->initialize(new Autoload(), new Modules())->register();';
        $replacement = "Services::autoloader()->initialize(new Autoload(), new Modules())->register();\n\n        \\CodeIgniter\\Events\\Events::trigger('autoloader_initialized');";

        if (!str_contains($content, $target)) {
            CLI::error('  Unable to locate autoloader initialization target in SYSTEMPATH Boot.php.');
            return;
        }

        $newContent = str_replace($target, $replacement, $content);
        file_put_contents($bootFile, $newContent);

        CLI::write('  ' . CLI::color('✔', 'green') . ' Patched SYSTEMPATH Boot.php with autoloader_initialized trigger.', 'white');
    }

    private function patchEventsFile(string $eventsFile): void
    {
        if (!is_file($eventsFile)) {
            CLI::error("APPPATH Config/Events.php not found at [{$eventsFile}].");
            return;
        }

        if ($this->isEventsPatched($eventsFile)) {
            CLI::write('  APPPATH Config/Events.php is already subscribed to autoloader_initialized.', 'dark_gray');
            return;
        }

        $content = file_get_contents($eventsFile);

        $snippet = "\n// Jengo Module Discovery Autoloader Hook\n"
            . "\\CodeIgniter\\Events\\Events::on('autoloader_initialized', static function (): void {\n"
            . "    \\Jengo\\Base\\Libraries\\ModuleDiscovery::discoverAndRegister();\n"
            . "});\n";

        $newContent = rtrim($content) . "\n" . $snippet;
        file_put_contents($eventsFile, $newContent);

        CLI::write('  ' . CLI::color('✔', 'green') . ' Added autoloader_initialized listener to APPPATH Config/Events.php.', 'white');
    }
}
