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

        $options = ['npm', 'pnpm', 'yarn'];
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
            default => 'npm uninstall ' . $packagesString,
        };
    }

    /**
     * Runs a package manager command.
     */
    public function run(string $command, string $cwd): void
    {
        $originalCwd = getcwd();
        chdir($cwd);

        CLI::newLine();
        CLI::write("Running [$command]...", 'light_purple');

        passthru($command);

        chdir($originalCwd);
    }

    public function getManager(): string
    {
        return $this->manager;
    }
}
