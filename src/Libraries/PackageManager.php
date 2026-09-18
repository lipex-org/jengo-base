<?php

declare(strict_types=1);

namespace Jengo\Base\Libraries;

use CodeIgniter\CLI\CLI;

/**
 * Agnostic Package Manager Library.
 * Handles Composer, npm, pnpm, and yarn operations.
 */
class PackageManager
{
    protected string $type; // 'php' or 'node'
    protected string $manager; // 'composer', 'npm', 'pnpm', 'yarn'

    public function __construct(string $manager = 'npm')
    {
        $this->manager = $manager;
        $this->type = ($manager === 'composer') ? 'php' : 'node';

        if ($this->type === 'node') {
            self::ensureGitignore();
        }
    }

    public static function init(string $manager = 'npm'): self
    {
        return new self($manager);
    }

    /**
     * Detects the package manager based on lock files in the root.
     */
    public static function detect(string $root, string $type = 'node'): string
    {
        if ($type === 'php') {
            return 'composer';
        }

        if (file_exists($root . '/pnpm-lock.yaml')) {
            return 'pnpm';
        }

        if (file_exists($root . '/yarn.lock')) {
            return 'yarn';
        }

        if (file_exists($root . '/bun.lock') || file_exists($root . '/bun.lockb')) {
            return 'bun';
        }

        return 'npm';
    }

    /**
     * Prompts the user to choose a package manager.
     */
    public static function prompt(string $type = 'node'): string
    {
        if ($type === 'php') {
            return 'composer';
        }

        $options = ['npm', 'pnpm', 'yarn', 'bun'];
        $pm = CLI::getOption('pm');

        if ($pm && in_array($pm, $options, true)) {
            return $pm;
        }

        return CLI::prompt(
            'Which package manager do you want to use?',
            $options,
            'in_list[' . implode(',', $options) . ']'
        );
    }

    /**
     * Returns the command to install all defined dependencies.
     */
    public function getInstallCommand(): string
    {
        return match ($this->manager) {
            'composer' => 'composer install',
            'pnpm' => 'pnpm install',
            'yarn' => 'yarn install',
            'bun' => 'bun install',
            default => 'npm install',
        };
    }

    /**
     * Returns the command to add/require specific packages.
     *
     * @param array<string> $packages
     */
    public function getAddCommand(array $packages, bool $isDev = false): string
    {
        if (empty($packages)) {
            return '';
        }

        $packagesString = implode(' ', $packages);

        return match ($this->manager) {
            'composer' => 'composer require ' . ($isDev ? '--dev ' : '') . $packagesString,
            'pnpm' => 'pnpm add ' . ($isDev ? '-D ' : '') . $packagesString,
            'yarn' => 'yarn add ' . ($isDev ? '--dev ' : '') . $packagesString,
            'bun' => 'bun add ' . ($isDev ? '-d ' : '') . $packagesString,
            default => 'npm install ' . ($isDev ? '--save-dev ' : '') . $packagesString,
        };
    }

    /**
     * Returns the command to remove specific packages.
     *
     * @param array<string> $packages
     */
    public function getRemoveCommand(array $packages): string
    {
        if (empty($packages)) {
            return '';
        }

        $packagesString = implode(' ', $packages);

        return match ($this->manager) {
            'composer' => 'composer remove ' . $packagesString,
            'pnpm' => 'pnpm remove ' . $packagesString,
            'yarn' => 'yarn remove ' . $packagesString,
            'bun' => 'bun remove ' . $packagesString,
            default => 'npm uninstall ' . $packagesString,
        };
    }

    /**
     * Runs a package manager command.
     */
    public function run(string $command, string $cwd): void
    {
        if ($this->type === 'node') {
            self::ensureGitignore($cwd);
        }

        $originalCwd = getcwd();
        chdir($cwd);

        CLI::newLine();
        CLI::write("Running [$command]...", 'light_purple');

        passthru($command);

        chdir($originalCwd);
    }

    /**
     * Ensure that node_modules/ is included in .gitignore.
     */
    public static function ensureGitignore(?string $root = null): void
    {
        $dir = $root ?: (defined('ROOTPATH') ? ROOTPATH : getcwd());
        $gitignorePath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.gitignore';

        if (!file_exists($gitignorePath)) {
            @file_put_contents($gitignorePath, "node_modules/\n");
            return;
        }

        $content = @file_get_contents($gitignorePath);
        if ($content === false) {
            return;
        }

        if (!preg_match('/(^|\n)\s*\/?node_modules\/?\s*($|\n)/m', $content)) {
            $separator = (str_ends_with($content, "\n") || $content === '') ? '' : "\n";
            @file_put_contents($gitignorePath, $content . $separator . "node_modules/\n");
        }
    }

    public function getManager(): string
    {
        return $this->manager;
    }
}
