<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Tests\Support\CommandTestCase;

final class MakeCommandTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanGeneratedFiles();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanGeneratedFiles();
    }

    private function cleanGeneratedFiles(): void
    {
        $files = [
            APPPATH . 'Actions/ProcessPayment.php',
            APPPATH . 'Forms/UserRegistration.php',
            APPPATH . 'Repositories/OrderRepository.php',
            APPPATH . 'Views/pages/dashboard.page.php',
            APPPATH . 'Events/UserRegistered.php',
            APPPATH . 'Macros/UserMacros.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testMakeMacroGeneratesFile(): void
    {
        command('jengo:make macro UserMacros --target="App\\\\Entities\\\\User"');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('File created:', $output);
        $expectedPath = APPPATH . 'Macros/UserMacros.php';
        $this->assertFileExists($expectedPath);

        $content = (string) file_get_contents($expectedPath);
        $this->assertStringContainsString('class UserMacros', $content);
        $this->assertStringContainsString('App\Entities\User', $content);
    }


    public function testMakeActionGeneratesFile(): void
    {
        command('jengo:make action ProcessPayment');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('File created:', $output);
        $expectedPath = APPPATH . 'Actions/ProcessPayment.php';
        $this->assertFileExists($expectedPath);

        $content = (string) file_get_contents($expectedPath);
        $this->assertStringContainsString('class ProcessPayment', $content);
        $this->assertStringContainsString('namespace App\Actions;', $content);
    }

    public function testMakeFormGeneratesFile(): void
    {
        command('jengo:make form UserRegistration');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('File created:', $output);
        $expectedPath = APPPATH . 'Forms/UserRegistration.php';
        $this->assertFileExists($expectedPath);

        $content = (string) file_get_contents($expectedPath);
        $this->assertStringContainsString('class UserRegistration', $content);
    }

    public function testMakeRepoGeneratesFile(): void
    {
        command('jengo:make repo OrderRepository');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('File created:', $output);
        $expectedPath = APPPATH . 'Repositories/OrderRepository.php';
        $this->assertFileExists($expectedPath);

        $content = (string) file_get_contents($expectedPath);
        $this->assertStringContainsString('class OrderRepository', $content);
    }

    public function testMakePageGeneratesFile(): void
    {
        command('jengo:make page Dashboard');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('File created:', $output);
        $expectedPath = APPPATH . 'Views/pages/dashboard.page.php';
        $this->assertFileExists($expectedPath);
    }

    public function testMakeEventGeneratesFile(): void
    {
        command('jengo:make event UserRegistered');
        $output = $this->io->getOutput();

        $this->assertStringContainsString('File created:', $output);
        $expectedPath = APPPATH . 'Events/UserRegistered.php';
        $this->assertFileExists($expectedPath);

        $content = (string) file_get_contents($expectedPath);
        $this->assertStringContainsString('class UserRegistered', $content);
    }
}
