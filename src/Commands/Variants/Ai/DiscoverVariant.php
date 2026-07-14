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

        // 4. Generate rules.md (Dynamic Documentation Engine)
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

                // Compile all other topics dynamically
                foreach ($data as $topicName => $items) {
                    if (in_array($topicName, ['name', 'description', 'usage'], true)) {
                        continue;
                    }

                    if (is_array($items)) {
                        $title = ucwords(str_replace(['_', '-'], ' ', $topicName));
                        $markdown .= "#### {$title}\n";

                        foreach ($items as $item) {
                            if (is_string($item)) {
                                $markdown .= "- {$item}\n";
                                continue;
                            }

                            if (!is_array($item)) {
                                continue;
                            }

                            $identifier = $item['name'] ?? $item['class'] ?? $item['signature'] ?? null;
                            if ($identifier) {
                                $markdown .= "- **`{$identifier}`**";

                                $sig = $item['signature'] ?? null;
                                if ($sig && $identifier !== $sig) {
                                    $markdown .= ": `{$sig}`";
                                }

                                $desc = $item['description'] ?? null;
                                if ($desc) {
                                    $markdown .= " — {$desc}";
                                }
                                $markdown .= "\n";
                            } else {
                                $markdown .= "- Item:\n";
                            }

                            // Dynamic property mapping
                            foreach ($item as $propKey => $propVal) {
                                if (in_array($propKey, ['name', 'class', 'signature', 'description'], true)) {
                                    continue;
                                }

                                if (empty($propVal)) {
                                    continue;
                                }

                                if (is_array($propVal)) {
                                    if (self::isAssociative($propVal)) {
                                        foreach ($propVal as $k => $v) {
                                            $markdown .= "  - `{$k}`: {$v}\n";
                                        }
                                    } else {
                                        $markdown .= "  - " . ucwords($propKey) . ": `" . implode('`, `', $propVal) . "`\n";
                                    }
                                } else {
                                    $markdown .= "  - " . ucwords($propKey) . ": `{$propVal}`\n";
                                }
                            }
                        }
                        $markdown .= "\n";
                    }
                }

                if (!empty($data['usage'])) {
                    $markdown .= "#### Usage Examples\n";
                    if (is_array($data['usage'])) {
                        foreach ($data['usage'] as $key => $example) {
                            $markdown .= "- **{$key}**:\n  ```php\n  {$example}\n  ```\n";
                        }
                    } else {
                        $markdown .= "```php\n" . $data['usage'] . "\n```\n";
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

        $allowedItemKeys = [
            'name',
            'class',
            'signature',
            'description',
            'usage',
            'arguments',
            'options',
            'methods',
            'fields',
            'target',
            'relation',
            'foreignKey',
            'from',
            'to',
            'select',
            'many',
            'helpers',
            'facades',
            'middleware',
            'commands',
            'packages'
        ];

        foreach ($content as $key => $value) {
            if ($key === 'name' || $key === 'description') {
                if (!is_string($value)) {
                    return false;
                }
                continue;
            }

            if ($key === 'usage') {
                if (!is_string($value) && !is_array($value)) {
                    return false;
                }
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        foreach ($item as $itemKey => $itemVal) {
                            if (!in_array($itemKey, $allowedItemKeys, true)) {
                                CLI::write("Manifest validation warning: key '{$itemKey}' in topic '{$key}' is not recognized.", 'yellow');
                                return false;
                            }
                        }
                    }
                }
            } else {
                return false;
            }
        }

        return true;
    }

    private static function isAssociative(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
