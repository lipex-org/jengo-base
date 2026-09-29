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

    public function testModulesPatchCommand(): void
    {
        $bootFile = SYSTEMPATH . 'Boot.php';
        $eventsFile = APPPATH . 'Config/Events.php';

        $bootBackup = is_file($bootFile) ? file_get_contents($bootFile) : null;
        $eventsBackup = is_file($eventsFile) ? file_get_contents($eventsFile) : null;

        try {
            // Run check option
            command('jengo:modules patch --check');
            $outputCheck = $this->io->getOutput();
            $this->assertStringContainsString('Checking CodeIgniter 4 Autoloader integration', $outputCheck);

            // Run patch variant
            command('jengo:modules patch');
            $output = $this->io->getOutput();
            $this->assertStringContainsString('Autoloader mitigation completed successfully', $output);

            // Verify Boot.php contains trigger
            $bootContent = file_get_contents($bootFile);
            $this->assertStringContainsString("Events::trigger('autoloader_initialized')", $bootContent);

            // Verify Events.php contains listener
            $eventsContent = file_get_contents($eventsFile);
            $this->assertStringContainsString('autoloader_initialized', $eventsContent);
            $this->assertStringContainsString('ModuleDiscovery::discoverAndRegister', $eventsContent);

            // Run check again to verify Patched and Subscribed status
            command('jengo:modules patch --check');
            $outputCheckAfter = $this->io->getOutput();
            $this->assertStringContainsString('Patched', $outputCheckAfter);
            $this->assertStringContainsString('Subscribed', $outputCheckAfter);
        } finally {
            // Restore original files
            if ($bootBackup !== null) {
                file_put_contents($bootFile, $bootBackup);
            }
            if ($eventsBackup !== null) {
                file_put_contents($eventsFile, $eventsBackup);
            }
        }
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
