<?php

declare(strict_types=1);

namespace Jengo\Base\Commands\Core;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Repositories\VariantRepository;

/**
 * Base class for all Master Commands that route to Variants.
 */
abstract class AbstractMasterCommand extends BaseCommand
{
    /**
     * The relative path where this command's variants are stored.
     * e.g., 'Commands/Variants/Make'
     */
    protected string $variantPath;

    /**
     * Orchestrates the routing to the appropriate variant.
     * Supports arbitrary nesting levels (e.g. `jengo:pesa mpesa register-c2b` or `jengo:pesa mpesa:register-c2b`).
     */
    public function run(array $params)
    {
        $variantName = array_shift($params);

        if (!$variantName || $variantName === 'list') {
            $this->showHelp();
            return;
        }

        $variant = VariantRepository::find($this->variantPath, $variantName);

        // If not found directly, check if the next segment forms a compound name (e.g., 'mpesa:register-c2b')
        if (!$variant && !empty($params) && !str_starts_with((string) $params[0], '-')) {
            $nextSegment = array_shift($params);
            $compoundName = $variantName . ':' . $nextSegment;
            $variant = VariantRepository::find($this->variantPath, $compoundName);

            // If still not found, put the segment back for fallback error reporting
            if (!$variant) {
                array_unshift($params, $nextSegment);
            }
        }

        if (!$variant) {
            CLI::error("Variant [{$variantName}] not found for command [{$this->name}].");
            CLI::newLine();
            $this->showHelp();
            return;
        }

        $variant->run($params);
    }

    public function showAvailableVariants(): void
    {
        $variants = VariantRepository::all($this->variantPath);

        CLI::write("Available Variants:", 'yellow');

        if (empty($variants)) {
            CLI::write("  (No variants found in path: {$this->variantPath})", 'dark_gray');
            return;
        }

        $maxlen = 0;
        foreach ($variants as $variant) {
            $maxlen = max($maxlen, strlen($variant::name()));
        }

        foreach ($variants as $variant) {
            CLI::write("  " . CLI::color(str_pad($variant::name(), $maxlen + 2), 'green') . $variant::description());

            $args = $variant->arguments();
            if (!empty($args)) {
                $maxArgLen = 0;
                foreach ($args as $name => $desc) {
                    $maxArgLen = max($maxArgLen, strlen($name));
                }

                foreach ($args as $name => $desc) {
                    CLI::write("    " . str_pad($name, $maxArgLen + 2) . CLI::color($desc, 'dark_gray'));
                }
            }
        }
    }

    /**
     * Displays a dynamic help screen based on discovered variants and supports nested variants.
     *
     * @param array|null $segments Optional segments to resolve nested help. Defaults to CLI segments.
     */
    public function showHelp(?array $segments = null): void
    {
        $params = $segments ?? CLI::getSegments();

        // If the first segment is the command name (e.g. 'jengo:pesa'), remove it
        if (!empty($params) && $params[0] === $this->name) {
            array_shift($params);
        }

        // Filter out 'help' or 'list' keywords if provided as the trailing or leading token
        $searchSegments = array_values(array_filter($params, static fn($s) => $s !== 'help' && $s !== 'list' && !str_starts_with((string) $s, '-')));

        if (empty($searchSegments)) {
            CLI::write("Usage:", 'yellow');
            CLI::write("  {$this->name} <variant> [arguments] [options]");
            CLI::newLine();
            $this->showAvailableVariants();
            return;
        }

        // Recursively find the target variant across nesting levels
        $currentPath = $this->variantPath;
        $matchedVariant = null;
        $matchedChain = [];

        while (!empty($searchSegments)) {
            $seg = array_shift($searchSegments);
            $matchedChain[] = $seg;

            // 1. Try finding direct segment in current path
            $found = VariantRepository::find($currentPath, $seg);

            // 2. If not found and more segments remain, try compound lookup (e.g. 'mpesa:register-c2b')
            if (!$found && !empty($searchSegments)) {
                $nextSeg = array_shift($searchSegments);
                $compound = $seg . ':' . $nextSeg;
                $found = VariantRepository::find($currentPath, $compound);
                if ($found) {
                    $matchedChain[] = $nextSeg;
                } else {
                    array_unshift($searchSegments, $nextSeg);
                }
            }

            if (!$found) {
                CLI::error("Variant [" . implode(' ', $matchedChain) . "] not found for command [{$this->name}].");
                CLI::newLine();
                $this->showAvailableVariants();
                return;
            }

            $matchedVariant = $found;

            // If found variant is a nested parent and more segments remain, descend deeper
            if ($matchedVariant instanceof \Jengo\Base\Commands\Contracts\NestedCommandVariantInterface && !empty($searchSegments)) {
                $currentPath = $matchedVariant->variantPath();
            } else {
                break;
            }
        }

        if (!$matchedVariant) {
            $this->showAvailableVariants();
            return;
        }

        // If the resolved variant is a nested parent and no further sub-variant was requested, display its sub-variants
        if ($matchedVariant instanceof \Jengo\Base\Commands\Contracts\NestedCommandVariantInterface) {
            CLI::write("Usage:", 'yellow');
            CLI::write("  {$this->name} " . implode(' ', $matchedChain) . " <sub-variant> [arguments] [options]");
            CLI::newLine();

            CLI::write("Description:", 'yellow');
            CLI::write("  " . $matchedVariant::description());
            CLI::newLine();

            $childVariants = VariantRepository::all($matchedVariant->variantPath());
            CLI::write("Available Sub-Variants:", 'yellow');

            if (empty($childVariants)) {
                CLI::write("  (No sub-variants found in path: {$matchedVariant->variantPath()})", 'dark_gray');
                return;
            }

            $maxlen = 0;
            foreach ($childVariants as $cv) {
                $maxlen = max($maxlen, strlen($cv::name()));
            }

            foreach ($childVariants as $cv) {
                CLI::write("  " . CLI::color(str_pad($cv::name(), $maxlen + 2), 'green') . $cv::description());

                $args = $cv->arguments();
                if (!empty($args)) {
                    $maxArgLen = 0;
                    foreach ($args as $name => $desc) {
                        $maxArgLen = max($maxArgLen, strlen($name));
                    }
                    foreach ($args as $name => $desc) {
                        CLI::write("    " . str_pad($name, $maxArgLen + 2) . CLI::color($desc, 'dark_gray'));
                    }
                }
            }
            return;
        }

        // Render leaf variant specific usage details
        CLI::write("Specific Usage:", 'yellow');
        CLI::write("  {$this->name} " . implode(' ', $matchedChain) . " [arguments] [options]");
        CLI::newLine();

        CLI::write("Description:", 'yellow');
        CLI::write("  " . $matchedVariant::description());
        CLI::newLine();

        $args = $matchedVariant->arguments();
        if (!empty($args)) {
            CLI::write("Arguments:", 'yellow');
            $maxArgLen = 0;
            foreach ($args as $name => $desc) {
                $maxArgLen = max($maxArgLen, strlen($name));
            }
            foreach ($args as $name => $desc) {
                CLI::write("  " . CLI::color(str_pad($name, $maxArgLen + 2), 'green') . $desc);
            }
            CLI::newLine();
        }

        $opts = $matchedVariant->options();
        if (!empty($opts)) {
            CLI::write("Options:", 'yellow');
            $maxOptLen = 0;
            foreach ($opts as $name => $desc) {
                $maxOptLen = max($maxOptLen, strlen($name));
            }
            foreach ($opts as $name => $desc) {
                CLI::write("  " . CLI::color(str_pad($name, $maxOptLen + 2), 'green') . $desc);
            }
            CLI::newLine();
        }
    }
}
