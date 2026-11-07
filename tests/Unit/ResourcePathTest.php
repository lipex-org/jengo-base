<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Traits\HasClientAssets;

final class ResourcePathTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('Jengo\Base\Helpers\jengo');
    }

    public function testResourcepathConstantIsDefined(): void
    {
        $this->assertTrue(defined('RESOURCEPATH'));
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR, RESOURCEPATH);
        $this->assertStringEndsWith('resources' . DIRECTORY_SEPARATOR, RESOURCEPATH);
    }

    public function testResourcePathFunctionWithoutArgs(): void
    {
        $expected = rtrim(RESOURCEPATH, '\\/');

        $this->assertSame($expected, resource_path());
        $this->assertSame($expected, resource_path(''));
        $this->assertSame($expected, resource_path(null));
    }

    public function testResourcePathFunctionWithSubpath(): void
    {
        $expected = rtrim(RESOURCEPATH, '\\/') . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js';

        $this->assertSame($expected, resource_path('js/app.js'));
        $this->assertSame($expected, resource_path('/js/app.js'));
    }

    public function testResourcePathFunctionWithDirectory(): void
    {
        $expected = rtrim(RESOURCEPATH, '\\/') . DIRECTORY_SEPARATOR . 'css';

        $this->assertSame($expected, resource_path('css'));
        $this->assertSame($expected, resource_path('/css'));
    }

    public function testHasClientAssetsTraitUsesResourcePath(): void
    {
        $class = new class {
            use HasClientAssets;

            public array $writtenFiles = [];

            public function testAddJS(string $filename, string $content): void
            {
                $this->addJSFile($filename, $content);
            }

            public function testAddCSS(string $filename, string $content): void
            {
                $this->addCSSFile($filename, $content);
            }

            protected function writeFile(string $path, string $content): void
            {
                $this->writtenFiles[$path] = $content;
            }
        };

        $class->testAddJS('app.js', 'console.log("hello");');
        $expectedJsPath = resource_path('js/app.js');
        $this->assertArrayHasKey($expectedJsPath, $class->writtenFiles);
        $this->assertSame('console.log("hello");', $class->writtenFiles[$expectedJsPath]);

        $class->testAddCSS('app.css', 'body { color: red; }');
        $expectedCssPath = resource_path('css/app.css');
        $this->assertArrayHasKey($expectedCssPath, $class->writtenFiles);
        $this->assertSame('body { color: red; }', $class->writtenFiles[$expectedCssPath]);
    }
}
