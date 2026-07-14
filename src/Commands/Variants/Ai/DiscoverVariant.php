<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Ai;

use CodeIgniter\CLI\CLI;
use Jengo\Base\AI\AiCapableInterface;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Jengo\Base\Config\Jengo as JengoConfig;

class DiscoverVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'discover';
    }

    public static function description(): string
    {
        return 'Discovers and compiles AI capabilities of Jengo packages.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            '--force' => 'Force regeneration of rule files',
        ];
    }

    public function run(array $params): void
    {
        CLI::write('Scanning Jengo ecosystem for AI capabilities...', 'cyan');

        $manifests = [];
        $namespaces = config('Autoload')->psr4;

        // 1. Scan for manifest files
        $checkedPaths = [];

        // Add root path first
        $rootManifest = ROOTPATH . '.jengo/ai-manifest.json';
        if (file_exists($rootManifest)) {
            $manifests['app'] = json_decode(file_get_contents($rootManifest), true);
        }

        foreach ($namespaces as $namespace => $dirs) {
            $dirs = (array) $dirs;
            foreach ($dirs as $dir) {
                $dir = realpath($dir);
                if (!$dir) {
                    continue;
                }

                // Check directory and its parent (package root)
                $pathsToCheck = [
                    $dir . '/.jengo/ai-manifest.json',
                    dirname($dir) . '/.jengo/ai-manifest.json',
                ];

                foreach ($pathsToCheck as $path) {
                    $path = realpath($path);
                    if ($path && !in_array($path, $checkedPaths, true) && file_exists($path)) {
                        $checkedPaths[] = $path;
                        $content = json_decode(file_get_contents($path), true);
                        if (is_array($content)) {
                            $name = $content['name'] ?? basename(dirname($path, 2));
                            $manifests[$name] = $content;
                        }
                    }
                }
            }
        }

        // 2. Discover classes implementing AiCapableInterface in configuration
        $config = config('Jengo') ?? new JengoConfig();
        $capabilityClasses = $config->aiCapabilities ?? [];
        $dynamicCapabilities = [];

        foreach ($capabilityClasses as $class) {
            if (class_exists($class) && is_subclass_of($class, AiCapableInterface::class)) {
                /** @var AiCapableInterface $instance */
                $instance = new $class();
                $dynamicCapabilities[$class] = $instance->getCapabilities();
            }
        }

        // 3. Compile output
        $compiled = [
            'generated_at' => date('c'),
            'packages' => $manifests,
            'dynamic_capabilities' => $dynamicCapabilities,
        ];

        $outputJsonPath = ROOTPATH . '.jengo-ai.json';
        file_put_contents($outputJsonPath, json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        CLI::write("Compiled manifest saved to: [{$outputJsonPath}]", 'green');

        // 4. Generate rules.md
        $rulesDir = ROOTPATH . '.jengo-ai';
        if (!is_dir($rulesDir)) {
            mkdir($rulesDir, 0755, true);
        }

        $markdown = "# Jengo AI Coding Rules & Context\n";
        $markdown .= "> Generated on " . date('Y-m-d H:i:s') . "\n\n";
        $markdown .= "This document provides context for AI assistants working on this Jengo project.\n\n";

        if (!empty($manifests)) {
            $markdown .= "## Discovered Packages & APIs\n\n";
            foreach ($manifests as $packageName => $data) {
                $markdown .= "### Package: {$packageName}\n";
                if (!empty($data['description'])) {
                    $markdown .= "*Description:* {$data['description']}\n\n";
                }

                if (!empty($data['models'])) {
                    $markdown .= "#### Models & Entities\n";
                    foreach ($data['models'] as $model) {
                        $markdown .= "- **`{$model['class']}`**: {$model['description']}\n";
                        if (!empty($model['fields'])) {
                            $markdown .= "  - Fields: `" . implode('`, `', $model['fields']) . "`\n";
                        }
                    }
                    $markdown .= "\n";
                }

                if (!empty($data['events'])) {
                    $markdown .= "#### Dispatched Events\n";
                    foreach ($data['events'] as $event) {
                        $markdown .= "- **`{$event['name']}`**: {$event['description']}\n";
                    }
                    $markdown .= "\n";
                }

                if (!empty($data['usage'])) {
                    $markdown .= "#### Usage Examples\n";
                    foreach ($data['usage'] as $key => $example) {
                        $markdown .= "- **{$key}**:\n  ```php\n  {$example}\n  ```\n";
                    }
                    $markdown .= "\n";
                }
            }
        }

        $outputRulesPath = $rulesDir . '/rules.md';
        file_put_contents($outputRulesPath, $markdown);
        CLI::write("AI development rules saved to: [{$outputRulesPath}]", 'green');
    }
}
