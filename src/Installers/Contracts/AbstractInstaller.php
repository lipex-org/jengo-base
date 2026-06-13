<?php

declare(strict_types=1);

namespace Jengo\Base\Installers\Contracts;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Traits\HasClientAssets;
use Jengo\Base\Traits\IsSetupOrInstaller;

abstract class AbstractInstaller implements InstallerInterface
{
    use HasClientAssets;
    use IsSetupOrInstaller;

    protected string $root;

    public int $runs = 0;
    protected string $workingDirectory;

    public function __construct()
    {
        $this->root = ROOTPATH;
        $this->workingDirectory = $this->root;
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

        chdir($this->workingDirectory);
        passthru($command);
        chdir($cwd);
    }

    protected function addRun(): void
    {
        ++$this->runs;
    }

    public static function dependencies(): array
    {
        return [];
    }

    protected function wantsToInstallDependencies(): bool
    {
        if (CLI::getOption('yes')) {
            return true;
        }

        return CLI::prompt(
            'Should we install the dependencies?',
            ['y', 'n'],
            'in_list[y,n]'
        ) === 'y';
    }
}
