<?php

namespace Jengo\Base\Testing;

/**
 * Fluent builder that registers a Pest extend() for database tests without
 * requiring per-file beforeEach() boilerplate.
 *
 * Usage in tests/Pest.php:
 *
 *   \Jengo\Base\Testing\PestDatabaseBuilder::make()
 *       ->seed(\Tests\Support\Database\Seeds\ExampleSeeder::class)
 *       ->migrate(true)
 *       ->group('tests')
 *       ->in('feature/database');
 */
class PestDatabaseBuilder
{
    // -----------------------------------------------------------------------
    // Static slot registry
    // -----------------------------------------------------------------------

    /** @var array<int, array<string, mixed>> */
    private static array $slots = [];

    private static int $slotCounter = 0;

    // -----------------------------------------------------------------------
    // Instance configuration
    // -----------------------------------------------------------------------

    private string $seedClass  = '';
    private bool   $migrate    = true;
    private string $dbGroup    = 'tests';
    private string $basePath   = '';
    private string $nameSpace  = '';

    // -----------------------------------------------------------------------
    // Factory
    // -----------------------------------------------------------------------

    public static function make(): self
    {
        return new self();
    }

    // -----------------------------------------------------------------------
    // Fluent configuration methods
    // -----------------------------------------------------------------------

    public function seed(string $seederClass): self
    {
        $this->seedClass = $seederClass;

        return $this;
    }

    public function migrate(bool $migrate = true): self
    {
        $this->migrate = $migrate;

        return $this;
    }

    public function group(string $dbGroup): self
    {
        $this->dbGroup = $dbGroup;

        return $this;
    }

    public function namespace(string $namespace): self
    {
        $this->nameSpace = $namespace;

        return $this;
    }

    public function basePath(string $basePath): self
    {
        $this->basePath = $basePath;

        return $this;
    }

    // -----------------------------------------------------------------------
    // Slot access (called from within the anonymous class setUp)
    // -----------------------------------------------------------------------

    /**
     * Returns the configuration stored for the given slot index.
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

        $anon = new class($slot) extends \CodeIgniter\Test\CIUnitTestCase {
            use \CodeIgniter\Test\DatabaseTestTrait;

            private static int $slot;

            public function __construct(int $slot = -1)
            {
                if ($slot !== -1) {
                    static::$slot = $slot;
                }
            }

            protected function setUp(): void
            {
                $config = \Jengo\Base\Testing\PestDatabaseBuilder::getSlot(static::$slot);

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

        pest()->extend(get_class($anon))->in($directory);

        self::$slotCounter++;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Builds the configuration array from the current instance properties.
     *
     * @return array<string, mixed>
     */
    private function buildConfig(): array
    {
        return [
            'seed'      => $this->seedClass,
            'migrate'   => $this->migrate,
            'dbGroup'   => $this->dbGroup,
            'basePath'  => $this->basePath,
            'namespace' => $this->nameSpace,
        ];
    }
}
