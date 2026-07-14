<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Installers\Libraries\InstallerTracker;
use Tests\Support\CommandTestCase;

final class InstallCommandTest extends CommandTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFileSystem();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanFileSystem();
    }
    public function testCommand(): void
    {
        $this->io->setInputs([
            'n',
            'pnpm',
            'n'
        ]);

        command('jengo:install vite');

        $tracker = new InstallerTracker();

        $this->assertTrue($tracker->isInstalled('vite'));
    }

    private function cleanFileSystem(): void
    {
        $baseDir = ROOTPATH;
        $configDir = "$baseDir.jengo";

        $files = [
            'package.json',
            'vite.config.js',
        ];

        helper('filesystem');

        delete_files($configDir, true);
        delete_files("{$baseDir}node_modules", true);

        foreach ($files as $file) {
            $path = "$baseDir$file";
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
