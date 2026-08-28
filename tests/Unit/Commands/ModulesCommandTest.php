<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Libraries\ModuleDiscovery;
use Tests\Support\CommandTestCase;

final class ModulesCommandTest extends CommandTestCase
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

    public function testModulesDiscoverCommand(): void
    {
        // Setup a dummy module directory to discover
        $dummyModuleDir = ROOTPATH . 'modules/TestDummyModule/Config';
        if (!is_dir($dummyModuleDir)) {
            mkdir($dummyModuleDir, 0777, true);
        }

        // Run discover variant
        command('jengo:modules discover');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('Modules\\TestDummyModule', $output);

        // Run cache variant
        command('jengo:modules cache');
        $this->assertFileExists(ROOTPATH . '.jengo/cache/modules.php');

        // Run clear variant
        command('jengo:modules clear');
        $this->assertFileDoesNotExist(ROOTPATH . '.jengo/cache/modules.php');
    }

    private function cleanFileSystem(): void
    {
        $dummyModuleDir = ROOTPATH . 'modules/TestDummyModule';
        if (is_dir($dummyModuleDir)) {
            helper('filesystem');
            delete_files($dummyModuleDir, true);
            rmdir($dummyModuleDir);
        }

        $cacheFile = ROOTPATH . '.jengo/cache/modules.php';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}
