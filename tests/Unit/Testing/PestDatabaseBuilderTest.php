<?php

declare(strict_types=1);

namespace Tests\Unit\Testing;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Testing\PestDatabaseBuilder;
use ReflectionClass;

/**
 * @internal
 */
final class PestDatabaseBuilderTest extends CIUnitTestCase
{
    public function testFluentBuilderSetsAllDatabaseFields(): void
    {
        $builder = PestDatabaseBuilder::make()
            ->seed('Tests\Support\Database\Seeds\ExampleSeeder')
            ->seedOnce(true)
            ->migrate(true)
            ->migrateOnce(true)
            ->refresh(false)
            ->group('custom_tests')
            ->namespace(['App', 'Tests\Support'])
            ->basePath('custom/path/to/database');

        $ref = new ReflectionClass($builder);

        $buildConfigMethod = $ref->getMethod('buildConfig');
        $buildConfigMethod->setAccessible(true);
        $config = $buildConfigMethod->invoke($builder);

        $this->assertSame('Tests\Support\Database\Seeds\ExampleSeeder', $config['seed']);
        $this->assertTrue($config['seedOnce']);
        $this->assertTrue($config['migrate']);
        $this->assertTrue($config['migrateOnce']);
        $this->assertFalse($config['refresh']);
        $this->assertSame('custom_tests', $config['dbGroup']);
        $this->assertSame(['App', 'Tests\Support'], $config['namespace']);
        $this->assertSame('custom/path/to/database', $config['basePath']);
    }

    public function testGenerateClassCodeIncludesAllFields(): void
    {
        $builder = PestDatabaseBuilder::make()
            ->seed(['SeederA', 'SeederB'])
            ->seedOnce(true)
            ->migrate(true)
            ->migrateOnce(true)
            ->refresh(true)
            ->group('tests')
            ->namespace('Tests\Support')
            ->basePath('database/files');

        $ref = new ReflectionClass($builder);
        $generateMethod = $ref->getMethod('generateClassCode');
        $generateMethod->setAccessible(true);

        $buildConfigMethod = $ref->getMethod('buildConfig');
        $buildConfigMethod->setAccessible(true);
        $config = $buildConfigMethod->invoke($builder);

        $code = $generateMethod->invoke($builder, 'Tests\Support\TestCases\Generated', 'DatabaseTestCase_test123', $config);

        $this->assertStringContainsString('protected $seed = array (', $code);
        $this->assertStringContainsString('protected $seedOnce = true;', $code);
        $this->assertStringContainsString('protected $migrate = true;', $code);
        $this->assertStringContainsString('protected $migrateOnce = true;', $code);
        $this->assertStringContainsString('protected $refresh = true;', $code);
        $this->assertStringContainsString("protected \$DBGroup = 'tests';", $code);
        $this->assertStringContainsString("protected \$basePath = 'database/files';", $code);
        $this->assertStringContainsString("protected \$namespace = 'Tests\\\\Support';", $code);
    }
}
