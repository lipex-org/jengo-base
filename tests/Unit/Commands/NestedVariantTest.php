<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Core\AbstractMasterCommand;
use Jengo\Base\Commands\Core\AbstractNestedVariant;
use Jengo\Base\Commands\Core\AbstractVariant;
use Jengo\Base\Commands\Repositories\VariantRepository;
use Tests\Support\CommandTestCase;

final class NestedVariantTest extends CommandTestCase
{
    public function testNestedVariantRouting(): void
    {
        // Define an in-memory leaf variant
        $leaf = new class extends AbstractVariant {
            public static bool $executed = false;
            public static array $receivedParams = [];

            public static function name(): string
            {
                return 'child-action';
            }

            public static function description(): string
            {
                return 'Sample leaf variant';
            }

            public function run(array $params): void
            {
                self::$executed = true;
                self::$receivedParams = $params;
            }
        };

        // Define a nested variant parent
        $parent = new class extends AbstractNestedVariant {
            public static function name(): string
            {
                return 'parent-group';
            }

            public static function description(): string
            {
                return 'Parent group with nested variants';
            }

            public function variantPath(): string
            {
                return 'Tests/CustomVariants';
            }
        };

        $this->assertSame('parent-group', $parent::name());
        $this->assertSame('Parent group with nested variants', $parent::description());
    }

    public function testShowHelpRendersForVariants(): void
    {
        $command = new class (service('logger'), service('commands')) extends AbstractMasterCommand {
            protected $group = 'Test';
            protected $name = 'test:master';
            protected $description = 'Test master command.';
            protected string $variantPath = 'Commands/Variants/Make';
        };

        // Root help
        $command->showHelp([]);
        $output = $this->io->getOutput();
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('test:master <variant>', $output);

        // Specific leaf variant help
        $command->showHelp(['action']);
        $leafOutput = $this->io->getOutput();
        $this->assertStringContainsString('Specific Usage:', $leafOutput);
        $this->assertStringContainsString('test:master action', $leafOutput);
        $this->assertStringContainsString('Arguments:', $leafOutput);
    }
}
