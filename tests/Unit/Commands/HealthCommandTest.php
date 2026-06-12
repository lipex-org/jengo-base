<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Commands\HealthCommand;
use Tests\Support\CommandTestCase;

final class HealthCommandTest extends CommandTestCase
{
    public function testRunProducesOutput()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new HealthCommand($logger, $runner);

        $command->run([]);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('THE GUARDIAN', $output);
        $this->assertStringContainsString('ENVIRONMENT', $output);
        $this->assertStringContainsString('FILE SYSTEM', $output);
        $this->assertStringContainsString('DATABASE', $output);
        $this->assertStringContainsString('INFRASTRUCTURE', $output);
    }
}
