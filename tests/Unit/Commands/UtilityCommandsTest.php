<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Tests\Support\CommandTestCase;

final class UtilityCommandsTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanCacheFile();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanCacheFile();
    }

    private function cleanCacheFile(): void
    {
        $cacheFile = ROOTPATH . '.jengo/cache/modules.php';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    public function testOptimizeCommandCompilesModuleCache(): void
    {
        command('jengo:optimize');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('Scanning modules directory...', $output);
        $this->assertStringContainsString('Jengo optimization complete!', $output);
        $this->assertFileExists(ROOTPATH . '.jengo/cache/modules.php');
    }

    public function testClearCacheCommand(): void
    {
        // Generate cache first
        command('jengo:optimize');
        $this->assertFileExists(ROOTPATH . '.jengo/cache/modules.php');

        // Clear cache via variant
        command('jengo:clear cache');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('Jengo module cache cleared successfully.', $output);
        $this->assertFileDoesNotExist(ROOTPATH . '.jengo/cache/modules.php');
    }
}
