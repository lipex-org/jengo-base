<?php

declare(strict_types=1);

namespace Jengo\Base\Testing;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

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
     * Static slot registry mapping an integer slot index to its configuration.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $slots = [];

    /**
     * Monotonically increasing slot index counter.
     */
    private static int $slotCounter = 0;

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
    protected string $namespace = '';

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
    // Slot registry accessor
    // -----------------------------------------------------------------------

    /**
     * Retrieve the configuration array for a given slot index.
     *
     * @return array<string, mixed>
     */
    public static function getSlot(int $slot): array
    {
        return self::$slots[$slot] ?? [];
    }

    // -----------------------------------------------------------------------
    // Terminal method
    // -----------------------------------------------------------------------

    /**
     * Stores the current configuration in the slot registry and registers a
     * Pest extend() so that every test file inside $directory inherits a
     * CodeIgniter database test-case base class pre-configured with this
     * builder's settings.
     */
    public function in(string $directory): void
    {
        $slot = self::$slotCounter;
        self::$slots[$slot] = $this->buildConfig();

        $anon = new class('test') extends CIUnitTestCase {
            use DatabaseTestTrait;

            public static int $slotId = 0;

            protected function setUp(): void
            {
                $config = PestDatabaseBuilder::getSlot(static::$slotId);

                $this->seed    = $config['seed'] ?? '';
                $this->migrate = $config['migrate'] ?? true;
                $this->DBGroup = $config['dbGroup'] ?? 'tests';

                if (!empty($config['basePath'])) {
                    $this->basePath = $config['basePath'];
                }

                if (!empty($config['namespace'])) {
                    $this->namespace = $config['namespace'];
                }

                parent::setUp();
            }
        };

        $anon::$slotId = $slot;
        self::$slotCounter++;

        pest()->extend(get_class($anon))->in($directory);
    }

    // -----------------------------------------------------------------------
    // Internal helper
    // -----------------------------------------------------------------------

    /**
     * Build the raw configuration array to store in the slot registry.
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
