<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Commands\DevCommand;
use Jengo\Base\Config\Dev as DevConfig;
use Tests\Support\CommandTestCase;

final class DevCommandTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure Vite is disabled by default for tests
        $_ENV['VITE_ENABLED'] = 'false';
        $_ENV['vite.enabled'] = 'false';
    }

    public function testRunEmptyCommandsReturnsWarning()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        // Clear any registered dev commands in config
        $config = config('Dev') ?? new DevConfig();
        $config->commands = [];

        $command->run([]);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('No dev commands registered or enabled', $output);
    }

    public function testRunsCustomDevCommandsConcurrentlyWithPrefixLabels()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        // Register two fast-exiting mock commands
        $config = config('Dev') ?? new DevConfig();
        $config->commands = [
            [
                'command' => 'echo "custom output 1"',
                'label'   => 'MockTask1',
                'color'   => '32', // Green
            ],
            [
                'command' => 'echo "custom output 2"',
                'label'   => 'MockTask2',
                'color'   => '35', // Magenta
            ]
        ];

        // Capture standard output
        ob_start();
        $command->run([]);
        $captured = ob_get_clean();

        // The console output should contain the prepended colored labels and output lines
        $this->assertStringContainsString('[MockTask1]', $captured);
        $this->assertStringContainsString('custom output 1', $captured);
        $this->assertStringContainsString('[MockTask2]', $captured);
        $this->assertStringContainsString('custom output 2', $captured);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('exited with code 0', $output);
    }
}
