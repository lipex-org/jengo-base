<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Support\JengoDirectory;

/**
 * @internal
 */
final class JengoDirectoryTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        JengoDirectory::flush();
    }

    protected function tearDown(): void
    {
        JengoDirectory::flush();
        parent::tearDown();
    }

    public function testPathReturnsCorrectSubpaths(): void
    {
        $base = JengoDirectory::path();
        $this->assertStringEndsWith('.jengo', $base);

        $sub = JengoDirectory::path('cache/bindings.php');
        $this->assertStringEndsWith('.jengo' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'bindings.php', $sub);
    }

    public function testEnsureExistsCreatesDirectoryAndGitignore(): void
    {
        $path = JengoDirectory::ensureExists('cache');
        $this->assertDirectoryExists($path);
        $this->assertFileExists(JengoDirectory::path('.gitignore'));

        $gitignoreContent = file_get_contents(JengoDirectory::path('.gitignore'));
        $this->assertStringContainsString('*', $gitignoreContent);
        $this->assertStringContainsString('!.gitignore', $gitignoreContent);
    }

    public function testPutAndGetAndHas(): void
    {
        $this->assertFalse(JengoDirectory::has('test_file.txt'));
        $this->assertNull(JengoDirectory::get('test_file.txt'));

        JengoDirectory::put('test_file.txt', 'hello jengo');

        $this->assertTrue(JengoDirectory::has('test_file.txt'));
        $this->assertSame('hello jengo', JengoDirectory::get('test_file.txt'));
    }

    public function testWriteAndReadPhpArray(): void
    {
        $data = [
            'service' => 'auth',
            'enabled' => true,
            'count'   => 42,
        ];

        JengoDirectory::writePhpArray('config/test_array.php', $data);

        $this->assertTrue(JengoDirectory::has('config/test_array.php'));

        $read = JengoDirectory::readPhpArray('config/test_array.php');
        $this->assertSame($data, $read);

        $default = JengoDirectory::readPhpArray('non_existent.php', ['fallback' => true]);
        $this->assertSame(['fallback' => true], $default);
    }

    public function testWriteAndReadJson(): void
    {
        $payload = [
            'name'    => 'jengo',
            'version' => '1.0.0',
            'tags'    => ['framework', 'codeigniter'],
        ];

        JengoDirectory::writeJson('manifest.json', $payload);

        $this->assertTrue(JengoDirectory::has('manifest.json'));

        $read = JengoDirectory::readJson('manifest.json');
        $this->assertSame($payload, $read);

        $default = JengoDirectory::readJson('non_existent.json', ['default' => true]);
        $this->assertSame(['default' => true], $default);
    }

    public function testDeleteFile(): void
    {
        JengoDirectory::put('to_delete.txt', 'temp');
        $this->assertTrue(JengoDirectory::has('to_delete.txt'));

        $deleted = JengoDirectory::delete('to_delete.txt');
        $this->assertTrue($deleted);
        $this->assertFalse(JengoDirectory::has('to_delete.txt'));
    }

    public function testFlushCleansAllExceptGitignore(): void
    {
        JengoDirectory::put('file1.txt', '1');
        JengoDirectory::put('cache/file2.txt', '2');

        $this->assertTrue(JengoDirectory::has('file1.txt'));
        $this->assertTrue(JengoDirectory::has('cache/file2.txt'));

        JengoDirectory::flush();

        $this->assertFalse(JengoDirectory::has('file1.txt'));
        $this->assertFalse(JengoDirectory::has('cache/file2.txt'));
        $this->assertTrue(JengoDirectory::has('.gitignore'));
    }
}
