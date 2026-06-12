<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Commands\LogTailCommand;
use Tests\Support\CommandTestCase;

final class LogTailCommandTest extends CommandTestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logDir = WRITEPATH . 'logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up mock logs if any
        $files = glob($this->logDir . '/log-*.log');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function testRunShowsErrorMessageIfFileNotFound()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();
        $command = new class($logger, $runner) extends LogTailCommand {
            public $mockOptions = ['date' => '1999-01-01'];
            protected function getOption(string $name) { return $this->mockOptions[$name] ?? null; }
        };
        
        $command->run([]);
        
        $this->assertStringContainsString('Log file not found: log-1999-01-01.log', $this->getBuffer());
    }

    public function testRunTailsFileAndFiltersByLevel()
    {
        $date = date('Y-m-d');
        $logFile = $this->logDir . "/log-{$date}.log";
        
        $content = "ERROR - {$date} 12:00:00 --> This is an error\n";
        $content .= "INFO - {$date} 12:00:01 --> This is info\n";
        file_put_contents($logFile, $content);

        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();

        // We use a mock class to avoid infinite loop
        $command = new class($logger, $runner) extends LogTailCommand {
            protected bool $once = true;
            public $mockOptions = ['level' => 'error'];
            protected function getOption(string $name) { return $this->mockOptions[$name] ?? null; }
        };
        
        $command->run([]);

        $output = $this->getBuffer();
        $this->assertStringContainsString('ERROR', $output);
        $this->assertStringContainsString('This is an error', $output);
        $this->assertStringNotContainsString('INFO', $output);
        $this->assertStringNotContainsString('This is info', $output);
    }

    public function testRunFiltersBySearchKeyword()
    {
        $date = date('Y-m-d');
        $logFile = $this->logDir . "/log-{$date}.log";
        
        $content = "ERROR - {$date} 12:00:00 --> Critical failure in database\n";
        $content .= "ERROR - {$date} 12:00:01 --> Minor glitch in ui\n";
        file_put_contents($logFile, $content);

        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();

        $command = new class($logger, $runner) extends LogTailCommand {
            protected bool $once = true;
            public $mockOptions = ['search' => 'database'];
            protected function getOption(string $name) { return $this->mockOptions[$name] ?? null; }
        };

        $command->run([]);

        $output = $this->getBuffer();
        $this->assertStringContainsString('Critical failure in database', $output);
        $this->assertStringNotContainsString('Minor glitch in ui', $output);
    }

    public function testResolveDateYesterday()
    {
        $logger = \Config\Services::logger();
        $runner = \Config\Services::commands();

        $command = new class($logger, $runner) extends LogTailCommand {
            public $mockOptions = ['yesterday' => true];
            protected function getOption(string $name) { return $this->mockOptions[$name] ?? null; }
            public function testResolveDate() { return $this->resolveDate(); }
        };

        $this->assertSame(date('Y-m-d', strtotime('yesterday')), $command->testResolveDate());
    }

    private function getBuffer(): string
    {
        return $this->io->getOutput();
    }
}
