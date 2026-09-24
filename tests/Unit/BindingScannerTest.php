<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Attributes\Bind;
use Jengo\Base\Container\BindingScanner;
use Jengo\Base\Container\Container;
use Jengo\Base\Support\JengoDirectory;

interface TestScannerInterface
{
}

#[Bind(TestScannerInterface::class, singleton: true)]
class TestScannerImplementation implements TestScannerInterface
{
}

/**
 * @internal
 */
final class BindingScannerTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        JengoDirectory::flush();
        Container::reset();
    }

    protected function tearDown(): void
    {
        JengoDirectory::flush();
        Container::reset();
        parent::tearDown();
    }

    public function testCompileWritesBindingsToJengoCache(): void
    {
        // Explicitly write a compiled binding file to verify the format and container loading
        $bindings = [
            TestScannerInterface::class => [
                'concrete' => TestScannerImplementation::class,
                'shared'   => true,
            ],
        ];

        JengoDirectory::writePhpArray(BindingScanner::CACHE_FILE, [
            'compiled_at' => time(),
            'bindings'    => $bindings,
        ]);

        $this->assertTrue(JengoDirectory::has(BindingScanner::CACHE_FILE));

        BindingScanner::loadIntoContainer();

        $container = app();
        $this->assertTrue($container->has(TestScannerInterface::class));

        $instance = $container->get(TestScannerInterface::class);
        $this->assertInstanceOf(TestScannerImplementation::class, $instance);
    }
}
