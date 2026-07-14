<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\CommandTestCase;

final class AiCommandTest extends CommandTestCase
{
    private string $tempManifestPath;
    private string $outputJsonPath;
    private string $outputRulesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempManifestPath = ROOTPATH . '.jengo/ai-manifest.json';
        $this->outputJsonPath = ROOTPATH . '.jengo-ai.json';
        $this->outputRulesDir = ROOTPATH . '.jengo-ai';

        if (!is_dir(dirname($this->tempManifestPath))) {
            mkdir(dirname($this->tempManifestPath), 0755, true);
        }

        // Write a mock manifest
        $mockData = [
            'name' => 'jengo/test-package',
            'description' => 'A test package for AI discovery',
            'models' => [
                [
                    'class' => 'App\\Models\\TestModel',
                    'description' => 'Test model description',
                    'fields' => ['id', 'name'],
                ]
            ],
            'usage' => [
                'test' => 'echo "hello";'
            ]
        ];

        file_put_contents($this->tempManifestPath, json_encode($mockData));
    }

    protected function tearDown(): void
    {
        helper('filesystem');

        delete_files($this->tempManifestPath, true);

        if (file_exists($this->outputJsonPath)) {
            unlink($this->outputJsonPath);
        }

        if (is_dir($this->outputRulesDir)) {
            $rulesFile = $this->outputRulesDir . '/rules.md';
            if (file_exists($rulesFile)) {
                unlink($rulesFile);
            }
            rmdir($this->outputRulesDir);
        }

        parent::tearDown();
    }

    public function testAiDiscoverCommand()
    {
        // Run command jengo:ai discover
        command('jengo:ai discover');

        $this->assertFileExists($this->outputJsonPath);
        $this->assertFileExists($this->outputRulesDir . '/rules.md');

        $json = json_decode(file_get_contents($this->outputJsonPath), true);
        $this->assertArrayHasKey('packages', $json);
        $this->assertArrayHasKey('jengo/test-package', $json['packages']);
        $this->assertSame('A test package for AI discovery', $json['packages']['jengo/test-package']['description']);

        $markdown = file_get_contents($this->outputRulesDir . '/rules.md');
        $this->assertStringContainsString('# Jengo AI Coding Rules & Context', $markdown);
        $this->assertStringContainsString('Package: jengo/test-package', $markdown);
        $this->assertStringContainsString('App\\Models\\TestModel', $markdown);
    }
}
