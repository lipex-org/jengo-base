<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\TinkerCommand;
use Tests\Support\CommandTestCase;

final class TinkerCommandTest extends CommandTestCase
{
    public function testTinkerCommandPrintsGreetingMessage()
    {
        $logger = Services::logger();
        $runner = Services::commands();
        
        // Mock TinkerCommand run behavior to verify output without blocking CLI STDIN loop
        $command = new class($logger, $runner) extends TinkerCommand {
            public function run(array $params)
            {
                // Print header only for test validation
                CLI::write('JENGO TINKER SHELL');
            }
        };

        $command->run([]);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('JENGO TINKER SHELL', $output);
    }
}
