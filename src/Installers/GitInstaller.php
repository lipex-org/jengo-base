<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class GitInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'git';
    }

    public static function description(): string
    {
        return 'Configure Jengo Git Flow architecture (main, staging, dev branches)';
    }

    public static function reasonForSkipping(): string
    {
        return 'A Git repository (.git) is already initialized.';
    }

    public function shouldRun(): bool
    {
        return true;
    }

    public function install(): void
    {
        $this->addRun();

        // Checkpoint 1: Pre-Flight Environment Checks
        
        // 1. Verify Git Installation
        $this->runGit('--version', $exitCode);
        if ($exitCode !== 0) {
            CLI::error('Git installation could not be verified. Please ensure Git is installed and in your PATH.');
            return;
        }

        // 2. Locate Project Root
        if (!file_exists(ROOTPATH . 'spark') || !is_dir(APPPATH)) {
            CLI::error('Must be run from the root of a CodeIgniter 4 application.');
            return;
        }

        // 3. Detect Existing Repository
        if (is_dir(ROOTPATH . '.git')) {
            $overwrite = CLI::getOption('yes') ? 'y' : CLI::prompt(
                'A Git repository (.git) already exists in the project root. Overwrite/reinitialize?',
                ['y', 'n'],
                'in_list[y,n]'
            );

            if ($overwrite !== 'y') {
                CLI::write('Initialization aborted gracefully to preserve commit history.', 'yellow');
                return;
            }

            // Remove existing Git repository directory
            $this->deleteDirectory(ROOTPATH . '.git');
        }

        // Checkpoint 2: Base Repository Initialization
        
        // 1. Initialize Git
        $this->runGit('init');

        // 2. Create Default .gitignore
        $gitignorePath = ROOTPATH . '.gitignore';
        if (!file_exists($gitignorePath)) {
            $gitignoreContent = implode("\n", [
                '/vendor/',
                'writable/cache/',
                'writable/debugbar/',
                'writable/logs/',
                'writable/session/',
                'writable/uploads/',
                '.env',
                '*.local.php',
                '.DS_Store',
                'Thumbs.db',
                'node_modules/',
            ]) . "\n";
            file_put_contents($gitignorePath, $gitignoreContent);
        }

        // 3. Commit Base Files
        $this->runGit('add .');
        $this->runGit('commit -m "chore: initial framework structure by Jengo Installer"');

        // 4. Ensure branch is named 'main'
        $this->runGit('branch -m main');

        // Checkpoint 3: Multi-Branch Infrastructure Setup
        
        // 1. Create staging off main
        $this->runGit('checkout -b staging');

        // 2. Create dev off staging
        $this->runGit('checkout -b dev');

        // Checkpoint 4: Validation & State Verification
        
        // 1. Audit Branches
        $branchOutput = $this->runGit('branch');
        $branches = array_map(function($line) {
            return trim(str_replace('*', '', $line));
        }, $branchOutput);

        $requiredBranches = ['main', 'staging', 'dev'];
        $missingBranches = array_diff($requiredBranches, $branches);

        if (!empty($missingBranches)) {
            CLI::error('Branch verification failed. Missing: ' . implode(', ', $missingBranches));
            return;
        }

        // 2. Confirm Working Directory State
        $statusOutput = $this->runGit('status --porcelain');
        if (!empty($statusOutput)) {
            CLI::warning('Working directory is not clean:');
            foreach ($statusOutput as $line) {
                CLI::write("  $line");
            }
        }

        // Checkpoint 5: CLI Output & Next-Steps Guidance
        CLI::newLine();
        CLI::write('🚀 Jengo Git Architecture Installed Successfully!', 'green');
        CLI::newLine();
        CLI::write('Active Branch: ' . CLI::color('dev', 'light_cyan'));
        CLI::write('Branches Created: ' . CLI::color('main, staging, dev', 'light_cyan'));
        CLI::newLine();
        CLI::write('Next Steps:');
        CLI::write('  1. Link your remote repository: ' . CLI::color('git remote add origin <your-url>', 'light_green'));
        CLI::write('  2. Start building your features on top of the dev branch.');
        CLI::newLine();
    }

    private function runGit(string $args, ?int &$returnCode = null): array
    {
        $cwd = getcwd();
        chdir(ROOTPATH);

        $output = [];
        exec("git {$args} 2>&1", $output, $returnCode);

        chdir($cwd);
        return $output;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
