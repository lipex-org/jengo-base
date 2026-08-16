<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Commands\DevCommand;
use Tests\Support\CommandTestCase;

final class DevCommandTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure Vite is disabled by default for tests
        $_ENV['VITE_ENABLED'] = 'false';
        $_ENV['vite.enabled'] = 'false';

        DevCommand::reset();
    }

    protected function tearDown(): void
    {
        DevCommand::reset();
        parent::tearDown();
    }

    public function testRunEmptyCommandsReturnsWarning()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        // Pass format option in CodeIgniter's CLI option array parsing format (name => value)
        \CodeIgniter\Config\Factories::reset('config');
        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['format' => 'default']);

        $command->run([]);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('No dev commands registered or enabled', $output);
    }

    public function testRunsCustomDevCommandsConcurrentlyWithPrefixLabels()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['format' => 'default']);

        DevCommand::register('echo "custom output 1"', 'MockTask1', '32');
        DevCommand::register('echo "custom output 2"', 'MockTask2', '35');

        // Capture standard output
        ob_start();
        $command->run([]);
        $captured = ob_get_clean();

        // The console output should contain the prepended colored labels and output lines
        $this->assertStringContainsString('[MockTask1]', $captured);
        $this->assertStringContainsString('custom output 1', $captured);
        $this->assertStringContainsString('[MockTask2]', $captured);
        $this->assertStringContainsString('custom output 2', $captured);

        $output = $this->io->getOutput() . $captured;
        $this->assertStringContainsString('exited with code 0', $output);
    }

    public function testRunsCustomDevCommandsJsonFormat()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['format' => 'json']);

        DevCommand::register('echo "custom output json"', 'JSONTask', '32');

        ob_start();
        $command->run([]);
        $captured = ob_get_clean();

        $this->assertStringContainsString('"process":"JSONTask"', $captured);
        $this->assertStringContainsString('"message":"custom output json"', $captured);
    }

    public function testRunsCustomDevCommandsCompactFormat()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['format' => 'compact']);

        DevCommand::register('echo "custom output compact"', 'CompactTask', '32');

        ob_start();
        $command->run([]);
        $captured = ob_get_clean();

        // Compact hides general output, so it shouldn't contain the custom output
        $this->assertStringNotContainsString('custom output compact', $captured);
    }

    public function testExceptFiltersDefaultCommands()
    {
        $_ENV['VITE_ENABLED'] = 'true';

        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['format' => 'default']);

        // Exclude Vite, Server, and Logs
        DevCommand::except('vite', 'server', 'logs');

        $command->run([]);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('No dev commands registered or enabled', $output);
    }

    public function testOnlyFiltersDefaultCommands()
    {
        $_ENV['VITE_ENABLED'] = 'true';

        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['format' => 'default']);

        // Limit to only 'non_existent_default' (excludes vite, server, and logs)
        DevCommand::only('non_existent_default');

        $command->run([]);

        $output = $this->io->getOutput();
        $this->assertStringContainsString('No dev commands registered or enabled', $output);
    }

    public function testRunsSequentialStartupTasks()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['format' => 'default']);

        // Register a sequential task fluently
        DevCommand::register('echo "sequential test output"', 'SeqTask')->sequential()->register();

        ob_start();
        $command->run([]);
        $captured = ob_get_clean();

        $output = $this->io->getOutput() . $captured;

        $this->assertStringContainsString('Running [SeqTask]', $output);
        $this->assertStringContainsString('sequential test output', $output);
        $this->assertStringContainsString('Completed [SeqTask]', $output);
    }

    public function testRunsCustomDevCommandsWithFluentAPI()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new DevCommand($logger, $runner);

        $reflection = new \ReflectionClass(\CodeIgniter\CLI\CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, ['format' => 'default']);

        // Register custom command via fluent chaining API
        DevCommand::spark('custom:command', 'FluentTask')->green()->autoRestart()->watch('app')->register();

        ob_start();
        $command->run([]);
        $captured = ob_get_clean();

        $output = $this->io->getOutput() . $captured;
        $this->assertNotNull($output);
    }
}
