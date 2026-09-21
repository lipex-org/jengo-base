<?php

declare(strict_types=1);

namespace Jengo\Base\Installers;

use CodeIgniter\CLI\CLI;
use InvalidArgumentException;
use Jengo\Base\Installers\Contracts\AbstractInstaller;
use RuntimeException;

class PestInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'pest';
    }

    public static function description(): string
    {
        return 'Install and configure Pest PHP testing framework';
    }

    public static function reasonForSkipping(): string
    {
        return 'Pest PHP is already installed.';
    }

    public function shouldRun(): bool
    {
        $composerPath = ROOTPATH . 'composer.json';
        if (!file_exists($composerPath)) {
            return false;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        $hasPest = isset($composer['require-dev']['pestphp/pest']);
        $hasPestInit = file_exists(ROOTPATH . 'tests/Pest.php');

        return !$hasPest && !$hasPestInit;
    }

    public function install(): void
    {
        $this->addRun();

        helper(['filesystem', '\Jengo\Base\Helpers\jengo']);


        CLI::write('  ' . CLI::color('●', 'cyan') . ' Configuring composer.json to trust Pest plugins...', 'dark_gray');

        $composerPath = ROOTPATH . 'composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        // Ensure config and allow-plugins exist
        if (!isset($composer['config'])) {
            $composer['config'] = [];
        }
        if (!isset($composer['config']['allow-plugins'])) {
            $composer['config']['allow-plugins'] = [];
        }

        if (!isset($composer['autoload-dev'])) {
            $composer['autoload-dev'] = [];
        }

        if (!isset($composer['autoload-dev']['psr-4'])) {
            $composer['autoload-dev']['psr-4'] = [];
        }

        // Trust pest plugins
        $composer['config']['allow-plugins']['pestphp/pest-plugin'] = true;

        // add tests to be autoloaded and psr4 compliant
        if (!isset($composer['autoload-dev']['psr-4']['Tests\\'])) {
            $composer['autoload-dev']['psr-4']['Tests\\'] = 'tests/';
        }

        // remove phpunit if it exists
        if (isset($composer['require-dev']['phpunit/phpunit'])) {
            unset($composer['require-dev']['phpunit/phpunit']);
        }

        $this->writeFile($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // run composer update to apply changes
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Running composer update to apply changes...', 'dark_gray');
        $this->run('composer update --no-interaction');

        // look for Modules config and add pestphp/pest to the exclude list if it exists
        $modulesPath = APPPATH . 'Config/Modules.php';

        // use a regex to find the composerPackages property and add pestphp/pest to the exclude list
        $isExceptionCaught = rescue(fn() => $this->updateComposerPackagesProperty($modulesPath), true);

        if ($isExceptionCaught) {
            // inform user to add pestphp/pest and pestphp/pest-plugin to Modules as the installer failed
            CLI::write(' ' . CLI::color('●', 'yellow') . 'Ensure to add pestphp/pest and pestphp/èest-plugin to composerPackages exclusion in app/Config/Modules.php config');
        }
        
        // Require pestphp/pest package
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Requiring pestphp/pest via Composer...', 'dark_gray');
        $this->run('composer require pestphp/pest --dev --with-all-dependencies --no-interaction');

        // publish Pest test stubs from Publisher/Stubs/Pest to completley overwrite the tests folder in the current project
        CLI::write('  ' . CLI::color('●', 'cyan') . ' Publishing Pest test stubs to tests folder...', 'dark_gray');
        $source = __DIR__ . '/../Publisher/Stubs/Pest';
        $destination = ROOTPATH . 'tests';

        if (is_dir($destination)) {
            delete_files($destination, true);
        }

        $this->publish($source);

        CLI::write('Pest PHP configured successfully.', 'green');
    }

    private function updateComposerPackagesProperty(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_writable($filePath)) {
            throw new InvalidArgumentException("File does not exist or is not writable: {$filePath}");
        }

        $source = file_get_contents($filePath);
        $tokens = token_get_all($source);
        $totalTokens = count($tokens);

        $propertyTokenIndex = null;

        // 1. Locate the $composerPackages variable token
        for ($i = 0; $i < $totalTokens; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$composerPackages') {
                $propertyTokenIndex = $i;
                break;
            }
        }

        if ($propertyTokenIndex === null) {
            echo "Property '\$composerPackages' was not found in {$filePath}.\n";
            return false;
        }

        // 2. Find the start of the array assignment ('[' or 'array(')
        $arrayStartIndex = null;
        $isShortSyntax = true;

        for ($i = $propertyTokenIndex + 1; $i < $totalTokens; $i++) {
            $token = $tokens[$i];

            if ($token === '[') {
                $arrayStartIndex = $i;
                $isShortSyntax = true;
                break;
            }

            if (is_array($token) && $token[0] === T_ARRAY) {
                // Find the opening '(' for array(...)
                for ($j = $i + 1; $j < $totalTokens; $j++) {
                    if ($tokens[$j] === '(') {
                        $arrayStartIndex = $j;
                        $isShortSyntax = false;
                        break 2;
                    }
                }
            }

            // If we hit a semicolon before an array starts, it's not initialized with an array
            if ($token === ';') {
                echo "Property '\$composerPackages' is declared without an array initializer.\n";
                return false;
            }
        }

        if ($arrayStartIndex === null) {
            echo "Could not find array opening for '\$composerPackages'.\n";
            return false;
        }

        // 3. Find the matching closing bracket
        $depth = 1;
        $openChar = $isShortSyntax ? '[' : '(';
        $closeChar = $isShortSyntax ? ']' : ')';
        $arrayEndIndex = null;

        for ($i = $arrayStartIndex + 1; $i < $totalTokens; $i++) {
            $token = $tokens[$i];

            if ($token === $openChar) {
                $depth++;
            } elseif ($token === $closeChar) {
                $depth--;
                if ($depth === 0) {
                    $arrayEndIndex = $i;
                    break;
                }
            }
        }

        if ($arrayEndIndex === null) {
            throw new RuntimeException("Syntax error: Unmatched closing array bracket in {$filePath}.");
        }

        // 4. Extract and evaluate the existing array contents
        $arrayCodeTokens = array_slice($tokens, $arrayStartIndex, $arrayEndIndex - $arrayStartIndex + 1);
        $arrayCode = '';
        foreach ($arrayCodeTokens as $t) {
            $arrayCode .= is_array($t) ? $t[1] : $t;
        }

        // Evaluate safely into a PHP array
        $currentData = @eval ("return {$arrayCode};");
        if (!is_array($currentData)) {
            throw new RuntimeException("Failed to parse the existing array data inside '\$composerPackages'.");
        }

        // 5. Merge the packages into 'exclude'
        $packagesToAdd = [
            'pestphp/pest',
            'pestphp/pest-plugin',
        ];

        $existingExclude = $currentData['exclude'] ?? [];

        // Avoid unnecessary writes if all packages are already present
        $missingPackages = array_diff($packagesToAdd, $existingExclude);
        if (isset($currentData['exclude']) && empty($missingPackages)) {
            echo "All specified packages are already present in 'exclude'. No updates made.\n";
            return false;
        }

        // Append and eliminate duplicates
        $currentData['exclude'] = array_values(array_unique(array_merge($existingExclude, $packagesToAdd)));

        // 6. Format the replacement array code
        $newArrayCode = $this->formatPhpArray($currentData);

        // 7. Splice the new code back into the file
        $prefixCode = '';
        for ($i = 0; $i < $arrayStartIndex; $i++) {
            $prefixCode .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        }

        $suffixCode = '';
        for ($i = $arrayEndIndex + 1; $i < $totalTokens; $i++) {
            $suffixCode .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        }

        $updatedSource = $prefixCode . $newArrayCode . $suffixCode;

        file_put_contents($filePath, $updatedSource);
        echo "Successfully updated '\$composerPackages' in {$filePath}.\n";
        return true;
    }

    /**
     * Formats a PHP configuration array into clean, indented short-array syntax.
     */
    private function formatPhpArray(array $data, int $baseIndentLevel = 1): string
    {
        if (empty($data)) {
            return '[]';
        }

        $indent = str_repeat('    ', $baseIndentLevel);
        $subIndent = str_repeat('    ', $baseIndentLevel + 1);
        $lines = ["[\n"];

        foreach ($data as $key => $values) {
            $lines[] = "{$indent}\"" . addslashes((string) $key) . "\" => [\n";
            if (is_array($values)) {
                foreach ($values as $val) {
                    $lines[] = "{$subIndent}\"" . addslashes((string) $val) . "\",\n";
                }
            }
            $lines[] = "{$indent}],\n";
        }

        $lines[] = str_repeat('    ', max(0, $baseIndentLevel - 1)) . ']';

        return implode('', $lines);
    }
}
