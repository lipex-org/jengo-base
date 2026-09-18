<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Base\Installers\Libraries\InstallerRunner;
use Jengo\Base\Installers\Libraries\InstallerTracker;
use Tests\Support\CommandTestCase;
use Tests\Support\Installers\FakeInstaller;

final class InstallerTest extends CommandTestCase
{
    public function testPublishCopiesFiles()
    {
        $installer = new FakeInstaller();

        $installer->install();

        $this->assertFileExists(
            ROOTPATH . 'vite-test/vite.config.js'
        );
    }

    public function testEnvUpdatesExistingKey()
    {
        file_put_contents(ROOTPATH . '.env', "FOO=bar\n");

        $installer = new FakeInstaller();

        $installer->install();

        $this->assertStringContainsString(
            'FOO=baz',
            file_get_contents(ROOTPATH . '.env')
        );
    }

    public function testEnvAppendsMissingKey()
    {
        file_put_contents(ROOTPATH . '.env', "FOO=bar\n");

        $installer = new FakeInstaller();

        $installer->install();

        $this->assertStringContainsString(
            'BAR=baz',
            file_get_contents(ROOTPATH . '.env')
        );
    }

    public function testEnvCreatesFileIfMissing()
    {
        $envPath = ROOTPATH . '.env';
        if (file_exists($envPath)) {
            unlink($envPath);
        }

        $installer = new FakeInstaller();
        $installer->install();

        $this->assertFileExists($envPath);
        $this->assertStringContainsString('FOO=baz', file_get_contents($envPath));
    }

    public function testEnvCopiesTemplateIfMissing()
    {
        $envPath = ROOTPATH . '.env';
        $templatePath = ROOTPATH . 'env';

        if (file_exists($envPath)) {
            unlink($envPath);
        }
        file_put_contents($templatePath, "TEMPLATE=true\n");

        $installer = new FakeInstaller();
        $installer->install();

        $this->assertFileExists($envPath);
        $content = file_get_contents($envPath);
        $this->assertStringContainsString('TEMPLATE=true', $content);
        $this->assertStringContainsString('FOO=baz', $content);

        unlink($templatePath);
    }

    public function testInstallerIsMarkedInstalled()
    {
        $path = TESTPATH . 'installers.php';

        $tracker = new InstallerTracker($path);

        $this->assertFalse($tracker->isInstalled('vite'));

        $tracker->markInstalled('vite');

        $this->assertTrue($tracker->isInstalled('vite'));

        unlink($path);
    }

    public function testRunnerSkipsInstalledInstaller()
    {
        $path = TESTPATH . 'installers.php';

        $tracker = new InstallerTracker($path);

        $installer = new FakeInstaller('vite');
        $runner = new InstallerRunner($tracker);

        $runner->run($installer);
        $runner->run($installer);

        $this->assertSame(1, $installer->runs);

        unlink($path);
    }

    public function testPackageManagerEnsureGitignoreCreatesAndAppendsNodeModules(): void
    {
        $tempDir = sys_get_temp_dir() . '/jengo_test_gitignore_' . uniqid();
        mkdir($tempDir, 0777, true);
        $gitignore = $tempDir . '/.gitignore';

        // 1. Creates when missing
        \Jengo\Base\Libraries\PackageManager::ensureGitignore($tempDir);
        $this->assertFileExists($gitignore);
        $this->assertSame("node_modules/\n", file_get_contents($gitignore));

        // 2. Does not duplicate when already present
        \Jengo\Base\Libraries\PackageManager::ensureGitignore($tempDir);
        $this->assertSame("node_modules/\n", file_get_contents($gitignore));

        // 3. Appends when missing from existing content
        file_put_contents($gitignore, "/vendor/\n.env\n");
        \Jengo\Base\Libraries\PackageManager::ensureGitignore($tempDir);
        $content = file_get_contents($gitignore);
        $this->assertStringContainsString("/vendor/\n", $content);
        $this->assertStringContainsString("node_modules/\n", $content);

        @unlink($gitignore);
        @rmdir($tempDir);
    }
}
