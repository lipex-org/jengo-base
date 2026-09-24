<?php

if (! defined('ROOTPATH')) {
    define('ROOTPATH', realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR);
}
if (! defined('TESTPATH')) {
    define('TESTPATH', ROOTPATH . 'tests' . DIRECTORY_SEPARATOR);
}
if (! defined('SUPPORTPATH')) {
    define('SUPPORTPATH', TESTPATH . '_support' . DIRECTORY_SEPARATOR);
}

use Tests\TestCase;
use Tests\Support\Database\Seeds\ExampleSeeder;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)->in('feature');

/*
|--------------------------------------------------------------------------
| Database Test Case (Option A: DatabaseTestCase base class)
|--------------------------------------------------------------------------
|
| Uncomment the line below to apply the DatabaseTestCase to all tests
| in the feature/database directory. This sets up migrations and seeding
| automatically without any beforeEach() boilerplate.
|
*/

// uses(Tests\TestCases\DatabaseTestCase::class)->in('feature/database');

/*
|--------------------------------------------------------------------------
| Database Test Case (Option B: PestDatabaseBuilder for on-the-fly config)
|--------------------------------------------------------------------------
|
| Use this when you need to configure different seeders per directory,
| or when you want to configure without creating a named class.
|
*/

// \Jengo\Base\Testing\PestDatabaseBuilder::make()
//     ->seed(ExampleSeeder::class)
//     ->migrate(true)
//     ->group('tests')
//     ->in('feature/database');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function something()
{
    // ..
}
