<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use CodeIgniter\Events\Events;
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

        // Reset factories to prevent config state leakage between tests
        \CodeIgniter\Config\Factories::reset('config');

        // Turn off event simulation so event listeners actually execute
        \CodeIgniter\Events\Events::simulate(false);
    }

    public function testRunEmptyCommandsReturnsWarning()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        // Inject empty dev config
        $config = new DevConfig();
        $config->commands = [];
        \CodeIgniter\Config\Factories::injectMock('config', 'Dev', $config);

        $command->run([]);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('No dev commands registered or enabled', $output);
    }

    public function testRunsCustomDevCommandsConcurrentlyWithPrefixLabels()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        // Register and inject two fast-exiting mock commands
        $config = new DevConfig();
        $config->commands = [
            [
                'command' => 'echo "custom output 1"',
                'label' => 'MockTask1',
                'color' => '32', // Green
            ],
            [
                'command' => 'echo "custom output 2"',
                'label' => 'MockTask2',
                'color' => '35', // Magenta
            ]
        ];
        \CodeIgniter\Config\Factories::injectMock('config', 'Dev', $config);

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

    public function testRunsEventRegisteredDevCommands()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        // Inject empty dev config
        $config = new DevConfig();
        $config->commands = [];
        \CodeIgniter\Config\Factories::injectMock('config', 'Dev', $config);

        // Register a listener for jengo.dev.register
        $listener = static function (\Jengo\Base\Events\DevCommandsCollector $collector) {
            $collector->register('echo "event output 1"', 'EventMockTask');
        };
        Events::on('jengo.dev.register', $listener);

        // Capture standard output
        ob_start();
        $command->run([]);
        $captured = ob_get_clean();

        $this->assertStringContainsString('[EventMockTask]', $captured);
        $this->assertStringContainsString('event output 1', $captured);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('exited with code 0', $output);

        // Remove listener to clean up event registry
        Events::removeListener('jengo.dev.register', $listener);
    }
}
