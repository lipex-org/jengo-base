<?php

declare(strict_types=1);

namespace Jengo\Base\Testing;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Support\JengoDirectory;

/**
 * Fluent builder for configuring database-dependent Pest tests on the fly.
 *
 * Usage in tests/Pest.php:
 *
 *   \Jengo\Base\Testing\PestDatabaseBuilder::make()
 *       ->seed(\Tests\Support\Database\Seeds\ExampleSeeder::class)
 *       ->migrate(true)
 *       ->group('tests')
 *       ->in('feature/database');
 *
 * This eliminates the need for beforeEach() boilerplate inside individual test files.
 */
class PestDatabaseBuilder
{
    /**
     * Cache file in .jengo/ for tracking generated test cases.
     */
    public const CACHE_FILE = 'cache/pest_test_cases.json';

    /**
     * The seeder class to run before each test.
     */
    protected string $seed = '';

    /**
     * Whether to run migrations before each test.
     */
    protected bool $migrate = true;

    /**
     * The database group name to use for tests.
     */
    protected string $dbGroup = 'tests';

    /**
     * The base path for database files (migrations/seeds).
     */
    protected string $basePath = '';

    /**
     * The namespace for database classes.
     */
    protected string $namespace = 'Tests\\Support';

    // -----------------------------------------------------------------------
    // Factory
    // -----------------------------------------------------------------------

    /**
     * Create a new builder instance.
     */
    public static function make(): self
    {
        return new self();
    }

    // -----------------------------------------------------------------------
    // Fluent configuration
    // -----------------------------------------------------------------------

    /**
     * Set the seeder class to execute before each test.
     */
    public function seed(string $seederClass): self
    {
        $this->seed = $seederClass;

        return $this;
    }

    /**
     * Enable or disable running migrations before tests.
     */
    public function migrate(bool $migrate = true): self
    {
        $this->migrate = $migrate;

        return $this;
    }

    /**
     * Set the database connection group name.
     */
    public function group(string $dbGroup): self
    {
        $this->dbGroup = $dbGroup;

        return $this;
    }

    /**
     * Set the namespace for database files.
     */
    public function namespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Set the base path for database files.
     */
    public function basePath(string $basePath): self
    {
        $this->basePath = $basePath;

        return $this;
    }

    // -----------------------------------------------------------------------
    // Class Generation & Pest Registration
    // -----------------------------------------------------------------------

    /**
     * Generates a physical test case class in tests/_support/TestCases/Generated/
     * and registers it with Pest for the given directory.
     */
    public function in(string ...$directories): void
    {
        $className = $this->ensureTestCaseClass($directories);

        if (function_exists('pest')) {
            pest()->extend($className)->in(...$directories);
        }
    }

    /**
     * Generates a physical test case class and applies it to the current test file via uses().
     */
    public function use(): void
    {
        $className = $this->ensureTestCaseClass();

        if (function_exists('uses')) {
            uses($className);
        }
    }

    /**
     * Ensure the physical test case class exists on disk and is included.
     *
     * @param string[] $directories
     * @return class-string<CIUnitTestCase>
     */
    public function ensureTestCaseClass(array $directories = []): string
    {
        $config = $this->buildConfig();
        $hash = substr(md5(serialize($config) . implode(',', $directories)), 0, 10);
        $shortName = 'DatabaseTestCase_' . $hash;
        $namespace = 'Tests\\Support\\TestCases\\Generated';
        $fqcn = $namespace . '\\' . $shortName;

        if (class_exists($fqcn, false)) {
            return $fqcn;
        }

        $supportDir = defined('SUPPORTPATH') ? SUPPORTPATH : (defined('TESTPATH') ? TESTPATH . '_support' . DIRECTORY_SEPARATOR : (getcwd() . '/tests/_support/'));
        $targetDir = rtrim($supportDir, '/\\') . DIRECTORY_SEPARATOR . 'TestCases' . DIRECTORY_SEPARATOR . 'Generated';
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $shortName . '.php';

        $cache = JengoDirectory::readJson(self::CACHE_FILE, []);
        $cachedHash = $cache[$shortName]['hash'] ?? null;
        $currentHash = md5(serialize($config));

        if (! file_exists($targetFile) || $cachedHash !== $currentHash) {
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $classCode = $this->generateClassCode($namespace, $shortName, $config);
            file_put_contents($targetFile, $classCode, LOCK_EX);

            $cache[$shortName] = [
                'file'        => $targetFile,
                'hash'        => $currentHash,
                'directories' => $directories,
                'created_at'  => date('c'),
            ];
            JengoDirectory::writeJson(self::CACHE_FILE, $cache);
        }

        if (file_exists($targetFile)) {
            require_once $targetFile;
        }

        return $fqcn;
    }

    /**
     * Generate PHP code for the physical test case class.
     *
     * @param array<string, mixed> $config
     */
    protected function generateClassCode(string $namespace, string $shortName, array $config): string
    {
        $seedVal = var_export($config['seed'], true);
        $migrateVal = var_export($config['migrate'], true);
        $dbGroupVal = var_export($config['dbGroup'], true);
        $basePathVal = var_export($config['basePath'], true);
        $namespaceVal = var_export($config['namespace'], true);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Auto-generated by Jengo PestDatabaseBuilder.
 * Do not edit directly.
 */
class {$shortName} extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected \$seed = {$seedVal};
    protected \$migrate = {$migrateVal};
    protected \$DBGroup = {$dbGroupVal};
    protected \$basePath = {$basePathVal};
    protected \$namespace = {$namespaceVal};
}

PHP;
    }

    /**
     * Build the raw configuration array.
     *
     * @return array<string, mixed>
     */
    private function buildConfig(): array
    {
        return [
            'seed'      => $this->seed,
            'migrate'   => $this->migrate,
            'dbGroup'   => $this->dbGroup,
            'basePath'  => $this->basePath,
            'namespace' => $this->namespace,
        ];
    }
}
