<?php

namespace Tests\TestCases;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Base test case for database-dependent Pest tests.
 *
 * Usage in tests/Pest.php:
 *   uses(Tests\TestCases\DatabaseTestCase::class)->in('feature/database');
 *
 * Or override properties per-file:
 *   uses(Tests\TestCases\DatabaseTestCase::class);
 *   beforeEach(fn() => $this->seed = MySeeder::class);
 */
abstract class DatabaseTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * Seeder class to run before each test.
     * Override in subclass or set in beforeEach().
     */
    protected $seed = '';

    /**
     * Whether to run migrations before each test.
     */
    protected $migrate = true;

    /**
     * Database group to use.
     */
    protected $DBGroup = 'tests';

    /**
     * Base path for migration and seed files.
     * Defaults to SUPPORTPATH . 'Database/' when empty.
     */
    protected $basePath = '';

    /**
     * Namespace for migration and seed classes.
     */
    protected $namespace = 'Tests\\Support';
}
