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

        // 4. Generate rules.md (Generic JSON-to-Markdown Compiler)
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

                // Compile all other topics recursively and dynamically
                foreach ($data as $topicName => $items) {
                    if (in_array($topicName, ['name', 'description'], true)) {
                        continue;
                    }

                    $title = ucwords(str_replace(['_', '-'], ' ', $topicName));
                    $markdown .= "#### {$title}\n";
                    $markdown .= $this->compileJsonToMarkdown($items, 2);
                    $markdown .= "\n";
                }
            }
        }

        $outputRulesPath = $outputDir . '/rules.md';
        file_put_contents($outputRulesPath, $markdown);
        CLI::write("AI development rules saved to: [{$outputRulesPath}]", 'green');

        // 5. Ask for target IDEs/agents if empty and interactive
        $targets = $config->aiTargets ?? [];
        if (empty($targets) && !defined('PHPUNIT_COMPOSER_INSTALL') && !defined('__PHPUNIT_PHAR__') && PHP_SAPI === 'cli') {
            CLI::newLine();
            CLI::write("No AI targets configured in Config/Jengo.php. Let's configure them now:", 'cyan');
            if (CLI::prompt('Do you use Antigravity / Gemini CLI?', ['y', 'n']) === 'y') {
                $targets[] = 'antigravity';
            }
            if (CLI::prompt('Do you use Cursor?', ['y', 'n']) === 'y') {
                $targets[] = 'cursor';
            }
            if (CLI::prompt('Do you use Cline / Roo-Cline?', ['y', 'n']) === 'y') {
                $targets[] = 'cline';
            }
            if (CLI::prompt('Do you use GitHub Copilot?', ['y', 'n']) === 'y') {
                $targets[] = 'copilot';
            }
            $config->aiTargets = $targets;
            $this->saveConfig($targets);
        }

        // 6. IDE & Agent Integrations
        foreach ($targets as $target) {
            switch ($target) {
                case 'antigravity':
                    // Write to isolated skill file
                    $skillDir = ROOTPATH . '.agents/skills/jengo';
                    if (!is_dir($skillDir)) {
                        mkdir($skillDir, 0755, true);
                    }
                    $skillMd = "---\nname: jengo\ndescription: Jengo framework developer rules and package specifications\n---\n\n" . $markdown;
                    file_put_contents($skillDir . '/SKILL.md', $skillMd);
                    CLI::write("Published Jengo capability skill to: [.agents/skills/jengo/SKILL.md]", 'green');
                    break;
                case 'cursor':
                    $this->writeRulesSafely(ROOTPATH . '.cursorrules', $markdown);
                    break;
                case 'cline':
                    $this->writeRulesSafely(ROOTPATH . '.clinerules', $markdown);
                    break;
                case 'copilot':
                    $this->writeRulesSafely(ROOTPATH . '.copilotrules', $markdown);
                    break;
            }
        }
    }

    private function compileJsonToMarkdown(mixed $data, int $depth = 1): string
    {
        $markdown = "";
        $indent = str_repeat("  ", max(0, $depth - 2));

        if (is_array($data)) {
            if ($this->isAssociative($data)) {
                foreach ($data as $key => $value) {
                    $formattedKey = ucwords(str_replace(['_', '-'], ' ', (string) $key));

                    if (is_array($value)) {
                        if ($depth === 1) {
                            $markdown .= str_repeat("#", min(6, $depth + 2)) . " {$formattedKey}\n";
                            $markdown .= $this->compileJsonToMarkdown($value, $depth + 1);
                        } else {
                            $markdown .= "{$indent}- **{$formattedKey}**:\n";
                            $markdown .= $this->compileJsonToMarkdown($value, $depth + 1);
                        }
                    } else {
                        $strValue = $this->formatValue($value);
                        if ($depth === 1) {
                            $markdown .= "**{$formattedKey}**: {$strValue}\n\n";
                        } else {
                            $markdown .= "{$indent}- **{$formattedKey}**: {$strValue}\n";
                        }
                    }
                }
            } else {
                // Sequential list
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $markdown .= $this->compileItemMarkdown($item, $depth);
                    } else {
                        $strValue = $this->formatValue($item);
                        $markdown .= "{$indent}- {$strValue}\n";
                    }
                }
                if ($depth === 1) {
                    $markdown .= "\n";
                }
            }
        } else {
            $strValue = $this->formatValue($data);
            $markdown .= "{$indent}- {$strValue}\n";
        }

        return $markdown;
    }

    private function compileItemMarkdown(array $item, int $depth): string
    {
        $markdown = "";
        $indent = str_repeat("  ", max(0, $depth - 2));

        $primaryKeys = ['name', 'class', 'signature', 'id'];
        $labelKey = null;
        foreach ($primaryKeys as $pk) {
            if (array_key_exists($pk, $item)) {
                $labelKey = $pk;
                break;
            }
        }

        if ($labelKey === null && !empty($item)) {
            $keys = array_keys($item);
            $labelKey = $keys[0];
        }

        if ($labelKey !== null) {
            $labelVal = $this->formatValue($item[$labelKey]);
            $formattedKey = ucwords(str_replace(['_', '-'], ' ', (string) $labelKey));

            $markdown .= "{$indent}- **`{$labelVal}`** ({$formattedKey})\n";

            foreach ($item as $k => $v) {
                if ($k === $labelKey) {
                    continue;
                }

                $formattedSubKey = ucwords(str_replace(['_', '-'], ' ', (string) $k));
                if (is_array($v)) {
                    $markdown .= "{$indent}  - **{$formattedSubKey}**:\n";
                    $markdown .= $this->compileJsonToMarkdown($v, $depth + 2);
                } else {
                    $strVal = $this->formatValue($v);
                    $markdown .= "{$indent}  - **{$formattedSubKey}**: {$strVal}\n";
                }
            }
        } else {
            $markdown .= $this->compileJsonToMarkdown($item, $depth);
        }

        return $markdown;
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return (string) $value;
    }

    private function writeRulesSafely(string $filePath, string $jengoRules): void
    {
        $startMarker = "### JENGO-AI-START";
        $endMarker = "### JENGO-AI-END";

        $wrappedRules = "\n" . $startMarker . "\n" . trim($jengoRules) . "\n" . $endMarker . "\n";

        if (!file_exists($filePath)) {
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($filePath, $wrappedRules);
            CLI::write("Created AI rules file: [" . basename($filePath) . "]", 'green');
            return;
        }

        $existingContent = file_get_contents($filePath);

        $startPos = strpos($existingContent, $startMarker);
        $endPos = strpos($existingContent, $endMarker);

        if ($startPos !== false && $endPos !== false) {
            $newContent = substr($existingContent, 0, $startPos)
                . $wrappedRules
                . substr($existingContent, $endPos + strlen($endMarker));
        } else {
            $newContent = rtrim($existingContent) . "\n" . $wrappedRules;
        }

        file_put_contents($filePath, $newContent);
        CLI::write("Merged Jengo rules into: [" . basename($filePath) . "]", 'green');
    }

    private function saveConfig(array $targets): void
    {
        $appConfigPath = APPPATH . 'Config/Jengo.php';
        if (!file_exists($appConfigPath)) {
            $appConfigPath = ROOTPATH . 'src/Config/Jengo.php';
            if (!file_exists($appConfigPath)) {
                return;
            }
        }

        $content = file_get_contents($appConfigPath);
        $formattedTargets = "['" . implode("', '", $targets) . "']";

        $pattern = '/(public\s+array\s+\$aiTargets\s*=\s*)([^;]+)(;)/i';
        $replacement = '${1}' . $formattedTargets . '${3}';
        $newContent = preg_replace($pattern, $replacement, $content);

        if ($newContent !== null && $newContent !== $content) {
            file_put_contents($appConfigPath, $newContent);
            CLI::write("Updated Jengo config target IDEs/Agents.", 'green');
        }
    }

    private function validateManifest(array $content): bool
    {
        return !empty($content['name']) && is_string($content['name']);
    }

    private static function isAssociative(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
