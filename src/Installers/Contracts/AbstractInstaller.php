<?php

declare(strict_types=1);

namespace Jengo\Base\Installers\Contracts;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Publisher\Publisher;
use Jengo\Base\Installers\Libraries\EnvHandler;
use Jengo\Base\Installers\Traits\HasClientAssets;
use RuntimeException;

abstract class AbstractInstaller implements InstallerInterface
{
    use HasClientAssets;

    protected string $root;

    public int $runs = 0;
    protected string $workingDirectory;

    public function __construct()
    {
        $this->root = ROOTPATH;
        $this->workingDirectory = $this->root;
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

        $destDir = "{$this->root}$destination";

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
     * Change working directory for shell commands.
     * Path is relative to ROOTPATH.
     */
    protected function workIn(string $relativePath): static
    {
        $this->workingDirectory = $this->root . trim($relativePath, '/\\') . DIRECTORY_SEPARATOR;
        return $this;
    }

    protected function run(string $command): void
    {
        $cwd = getcwd();

        CLI::newLine();
        CLI::write("Running command [$command]...", 'light_purple');

        // we can add a loader here to run in the background and stopped once command completes

        chdir($this->workingDirectory);
        passthru($command);
        chdir($cwd);
    }

    /**
     * Safely write or update a value in .env
     */
    protected function env(): EnvHandler
    {
        $path = $this->root . ".env";

        if (!file_exists($path)) {
            $template = $this->root . "env";

            if (file_exists($template)) {
                copy($template, $path);
            }
        }

        return new EnvHandler($path);
    }

    protected function escapeEnvValue(string $value): string
    {
        // Quote if needed (spaces, special chars)
        if (preg_match('/\s|"|\'/', $value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return $value;
    }

    private function isPathAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[a-z]:\\\/i', $path));
    }

    protected function addRun(): void
    {
        ++$this->runs;
    }

    public static function dependencies(): array
    {
        return [];
    }

    protected function detectPackageManager(): string
    {
        if (file_exists($this->root . 'pnpm-lock.yaml')) {
            return 'pnpm';
        }

        if (file_exists($this->root . 'yarn.lock')) {
            return 'yarn';
        }

        return 'npm';
    }

        protected function packageMangerInstallCommand(string $pm): string
    {
        return match ($pm) {
            'pnpm' => 'pnpm install',
            'yarn' => 'yarn add',
            default => 'npm install',
        };
    }
}
