<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockInputOutput;

class CommandTestCase extends CIUnitTestCase
{
    protected MockInputOutput $io;

    protected string $output = "";

    protected function setUp(): void
    {
        parent::setUp();

        $this->io = new MockInputOutput();

        CLI::setInputOutput($this->io);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->output = $this->io->getOutput();

        CLI::resetInputOutput();
    }
}
