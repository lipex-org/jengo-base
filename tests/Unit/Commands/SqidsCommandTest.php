<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Commands\SqidsCommand;
use Tests\Support\CommandTestCase;

final class SqidsCommandTest extends CommandTestCase
{
    private SqidsCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        helper('jengo');

        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $this->command = new SqidsCommand($logger, $runner);
    }

    public function testHashCommandSuccess()
    {
        $this->command->run(['hash', '12345']);
        $output = $this->io->getOutput();
        $cleanOutput = $this->cleanCliColors($output);

        $this->assertStringContainsString('ID:   12345', $cleanOutput);
        $this->assertStringContainsString('Hash: ', $cleanOutput);
    }

    public function testHashCommandValidationError()
    {
        $this->command->run(['hash', 'abc']);
        $output = $this->io->getOutput();
        $cleanOutput = $this->cleanCliColors($output);

        $this->assertStringContainsString('The ID must be a non-negative integer.', $cleanOutput);
    }

    public function testHashCommandMissingArg()
    {
        $this->command->run(['hash']);
        $output = $this->io->getOutput();
        $cleanOutput = $this->cleanCliColors($output);

        $this->assertStringContainsString('Please provide an integer ID to hash.', $cleanOutput);
    }

    public function testUnhashCommandSuccess()
    {
        $hash = sqids_hash(98765);

        $this->command->run(['unhash', $hash]);
        $output = $this->io->getOutput();
        $cleanOutput = $this->cleanCliColors($output);

        $this->assertStringContainsString("Hash: {$hash}", $cleanOutput);
        $this->assertStringContainsString('ID:   98765', $cleanOutput);
    }

    public function testUnhashCommandInvalidHash()
    {
        $this->command->run(['unhash', 'invalid_hash_here_123']);
        $output = $this->io->getOutput();
        $cleanOutput = $this->cleanCliColors($output);

        $this->assertStringContainsString('Invalid hash or failed to decode.', $cleanOutput);
    }

    public function testUnhashCommandMissingArg()
    {
        $this->command->run(['unhash']);
        $output = $this->io->getOutput();
        $cleanOutput = $this->cleanCliColors($output);

        $this->assertStringContainsString('Please provide a hash string to decode.', $cleanOutput);
    }

    private function cleanCliColors(string $text): string
    {
        return preg_replace('/\e\[[0-9;]*m/', '', $text);
    }
}
