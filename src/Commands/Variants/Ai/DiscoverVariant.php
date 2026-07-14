<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Variants\Ai;

use CodeIgniter\CLI\CLI;
use Config\Services;
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
        $namespaces = Services::autoloader()->getNamespace();

        // 1. Scan for manifest files
        $checkedPaths = [];

        // Add root path first
        $rootManifest = ROOTPATH . '.jengo/ai-manifest.json';
        if (file_exists($rootManifest)) {
            $content = json_decode(file_get_contents($rootManifest), true);
            if (is_array($content) && $this->validateManifest($content)) {
                $manifests['app'] = $content;
            }
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
                            if ($this->validateManifest($content)) {
                                $name = $content['name'];
                                $manifests[$name] = $content;
                            } else {
                                CLI::write("Warning: Skipping invalid AI manifest at [{$path}]", 'yellow');
                            }
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

        $outputDir = ROOTPATH . '.jengo/ai';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputJsonPath = $outputDir . '/manifest.json';
        file_put_contents($outputJsonPath, json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        CLI::write("Compiled manifest saved to: [{$outputJsonPath}]", 'green');

        // 4. Generate rules.md
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

                if (!empty($data['helpers']) && is_array($data['helpers'])) {
                    $markdown .= "#### Helper Functions\n";
                    foreach ($data['helpers'] as $helper) {
                        $name = $helper['name'] ?? 'unknown';
                        $sig = $helper['signature'] ?? '';
                        $desc = $helper['description'] ?? '';
                        $markdown .= "- **`{$name}`**" . ($sig ? ": `{$sig}`" : "") . ($desc ? " — {$desc}" : "") . "\n";
                    }
                    $markdown .= "\n";
                }

                if (!empty($data['facades']) && is_array($data['facades'])) {
                    $markdown .= "#### Facades & Static Helpers\n";
                    foreach ($data['facades'] as $facade) {
                        $class = $facade['class'] ?? 'unknown';
                        $desc = $facade['description'] ?? '';
                        $markdown .= "- **`{$class}`**" . ($desc ? " — {$desc}" : "") . "\n";
                        if (!empty($facade['methods']) && is_array($facade['methods'])) {
                            foreach ($facade['methods'] as $methodName => $methodDesc) {
                                $markdown .= "  - `{$methodName}`: {$methodDesc}\n";
                            }
                        }
                    }
                    $markdown .= "\n";
                }

                if (!empty($data['classes']) && is_array($data['classes'])) {
                    $markdown .= "#### Core Classes\n";
                    foreach ($data['classes'] as $cls) {
                        $class = $cls['class'] ?? 'unknown';
                        $desc = $cls['description'] ?? '';
                        $markdown .= "- **`{$class}`**" . ($desc ? " — {$desc}" : "") . "\n";
                        if (!empty($cls['methods']) && is_array($cls['methods'])) {
                            foreach ($cls['methods'] as $methodName => $methodDesc) {
                                $markdown .= "  - `{$methodName}`: {$methodDesc}\n";
                            }
                        }
                    }
                    $markdown .= "\n";
                }

                if (!empty($data['middleware']) && is_array($data['middleware'])) {
                    $markdown .= "#### Filters & Middleware\n";
                    foreach ($data['middleware'] as $mw) {
                        $class = $mw['class'] ?? 'unknown';
                        $desc = $mw['description'] ?? '';
                        $markdown .= "- **`{$class}`**" . ($desc ? " — {$desc}" : "") . "\n";
                    }
                    $markdown .= "\n";
                }

                if (!empty($data['models']) && is_array($data['models'])) {
                    $markdown .= "#### Models & Entities\n";
                    foreach ($data['models'] as $model) {
                        $class = $model['class'] ?? 'unknown';
                        $desc = $model['description'] ?? '';
                        $markdown .= "- **`{$class}`**: {$desc}\n";
                        if (!empty($model['fields']) && is_array($model['fields'])) {
                            $markdown .= "  - Fields: `" . implode('`, `', $model['fields']) . "`\n";
                        }
                    }
                    $markdown .= "\n";
                }

                if (!empty($data['events']) && is_array($data['events'])) {
                    $markdown .= "#### Dispatched Events\n";
                    foreach ($data['events'] as $event) {
                        $name = $event['name'] ?? 'unknown';
                        $desc = $event['description'] ?? '';
                        $markdown .= "- **`{$name}`**: {$desc}\n";
                    }
                    $markdown .= "\n";
                }

                if (!empty($data['usage']) && is_array($data['usage'])) {
                    $markdown .= "#### Usage Examples\n";
                    foreach ($data['usage'] as $key => $example) {
                        $markdown .= "- **{$key}**:\n  ```php\n  {$example}\n  ```\n";
                    }
                    $markdown .= "\n";
                }
            }
        }

        $outputRulesPath = $outputDir . '/rules.md';
        file_put_contents($outputRulesPath, $markdown);
        CLI::write("AI development rules saved to: [{$outputRulesPath}]", 'green');

        // 5. IDE & Agent Integrations
        $integrations = [
            ROOTPATH . '.agents/AGENTS.md',
            ROOTPATH . '.cursorrules',
            ROOTPATH . '.clinerules',
            ROOTPATH . '.copilotrules',
        ];

        foreach ($integrations as $path) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, $markdown);
            CLI::write("IDE/Agent integration saved to: [" . (basename($dir) === '.' ? '' : basename($dir) . '/') . basename($path) . "]", 'green');
        }
    }

    private function validateManifest(array $content): bool
    {
        if (empty($content['name']) || !is_string($content['name'])) {
            return false;
        }

        // Check models if present
        if (isset($content['models']) && is_array($content['models'])) {
            foreach ($content['models'] as $model) {
                if (empty($model['class']) || !is_string($model['class'])) {
                    return false;
                }
            }
        }

        // Check events if present
        if (isset($content['events']) && is_array($content['events'])) {
            foreach ($content['events'] as $event) {
                if (empty($event['name']) || !is_string($event['name'])) {
                    return false;
                }
            }
        }

        return true;
    }
}
