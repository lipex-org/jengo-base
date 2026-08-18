<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Commands\KitCommand;
use Tests\Support\CommandTestCase;

final class KitCommandTest extends CommandTestCase
{
    public function testKitCommandPrintsEcosystemHeader()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new KitCommand($logger, $runner);

        // Run with --status option to avoid interactive prompt in test
        \CodeIgniter\Config\Factories::reset('config');
        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['status' => true]);

        $command->run([]);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('JENGO ECOSYSTEM CONTROL', $output);
        $this->assertStringContainsString('jengo/api', $output);
        $this->assertStringContainsString('jengo/inertia', $output);
        $this->assertStringContainsString('jengo/schema', $output);
    }
}
