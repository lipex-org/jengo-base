<?php

namespace Jengo\Base\Traits;

use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\InputOutput;
use CodeIgniter\Publisher\Publisher;
use CodeIgniter\Test\Mock\MockInputOutput;
use Jengo\Base\Installers\Libraries\EnvHandler;
use Jengo\Base\Libraries\PackageManager;
use RuntimeException;

trait IsSetupOrInstaller
{
    /**
     * Returns a PackageManager instance for Node-based operations.
     */
    protected function node(): PackageManager
    {
        return PackageManager::init(PackageManager::detect(ROOTPATH, 'node'));
    }

    /**
     * Returns a PackageManager instance for PHP-based operations.
     */
    protected function composer(): PackageManager
    {
        return PackageManager::init('composer');
    }

    /**
     * Prompts for and returns a Node-based PackageManager.
     */
    protected function selectNodeManager(): PackageManager
    {
        $manager = PackageManager::prompt('node');
        return PackageManager::init($manager);
    }

    protected function isPathAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[a-z]:\\\/i', $path));
    }

    protected function escapeEnvValue(string $value): string
    {
        // Quote if needed (spaces, special chars)
        if (preg_match('/\s|"|\'/', $value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return $value;
    }

    /**
     * Publish files using CI4 Publisher.
     *
     * @param string $source Absolute path to source directory or file
     * @param string $destination Relative to ROOTPATH
     */
    protected function publish(string $source, string $destination = ''): void
    {
        if (!$this->isPathAbsolute($source)) {
            throw new RuntimeException("Publish source must be an absolute path.");
        }

        $destDir = ROOTPATH . $destination;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        $publisher = new Publisher($source, $destDir);

        // Default behavior:
        // - overwrite existing files
        // - preserve directory structure
        $publisher->publish();
    }

    protected function writeFile(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
    }


    /**
     * Safely write or update a value in .env
     */
    protected function env(): EnvHandler
    {
        $path = ROOTPATH . ".env";

        if (!file_exists($path)) {
            $template = ROOTPATH . "env";

            if (file_exists($template)) {
                copy($template, $path);
            }
        }

        return new EnvHandler($path);
    }


    /**
     * Call another spark command.
     */
    protected function command(string $command, array $params = [], array $inputs = []): MockInputOutput
    {
        $io = new MockInputOutput();
        CLI::setInputOutput($io);

        $io->setInputs($inputs);

        command($command . ' ' . implode(' ', $params));

        CLI::resetInputOutput();

        return $io;
    }

    protected function copy(string|array $source, ?string $destination = null): void
    {
        if (is_array($source)) {
            foreach ($source as $src => $dest) {
                $this->copy($src, $dest);
            }

            return;
        }

        $src = $this->isPathAbsolute($source) ? $source : ROOTPATH . $source;
        $dest = $this->isPathAbsolute($destination) ? $destination : ROOTPATH . $destination;

        if (file_exists($dest)) {
            return;
        }

        if (!file_exists($src)) {
            return;
        }

        $directory = dirname($dest);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        copy($src, $dest);
    }

    protected function addHelperToAutoload(array $helpers): void
    {
        $path = APPPATH . 'Config/Autoload.php';

        if (!file_exists($path)) {
            CLI::error("Config/Autoload.php not found.");
            return;
        }

        $content = file_get_contents($path);
        $updated = false;

        foreach ($helpers as $helper) {
            // Escape backslashes for standard string comparison
            $normalizedHelper = str_replace('\\', '\\\\', $helper);

            // Check if this specific helper is already configured
            if (str_contains($content, $helper) || str_contains($content, $normalizedHelper)) {
                CLI::write("  " . CLI::color('✔', 'green') . " Helper '{$helper}' is already configured.");
                continue;
            }

            // Properly escape backslashes for the regular expression replacement string
            $regexReplacement = str_replace('\\', '\\\\\\\\', $helper);

            // Inject the helper right after the opening bracket of the public $helpers array
            $content = preg_replace(
                '/(public\s+\$helpers\s*=\s*\[)/',
                "$1\n        '{$regexReplacement}',",
                $content,
                1
            );

            $updated = true;
            CLI::write("  " . CLI::color('✔', 'green') . " Helper '{$helper}' added to Autoload config.");
        }

        // Only write back to the file if changes were actually made
        if ($updated) {
            file_put_contents($path, $content);
        }
    }
}
