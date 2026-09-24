<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use CodeIgniter\Config\Factories;
use Jengo\Base\Config\Jengo as JengoConfig;
use Tests\Support\CommandTestCase;

final class ViteConfigCOmmandTest extends CommandTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $config = new JengoConfig();

        $config->vite = [
            'entrypoints' => [
                'app.css',
                'user/book.ts',
                'main.ts',
            ],
            'searchPaths' => [
                APPPATH,
                ROOTPATH . 'resources',
            ],
        ];

        Factories::injectMock('config', 'Jengo', $config);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function test(): void
    {
        command('jengo:vite config');

        $output = $this->io->getOutput();

        $this->assertStringContainsString('app.css', $output);
        $this->assertStringContainsString('user\\/book.ts', $output);
        $this->assertStringContainsString('main.ts', $output);
    }
}
