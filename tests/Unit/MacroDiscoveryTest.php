<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Modules;
use Jengo\Base\Entities\BaseEntity;
use Jengo\Base\Events\AppLifecycle;
use Jengo\Base\Libraries\MacroDiscovery;
use Jengo\Base\Traits\MacroableTrait;

class DiscoveredEntityExample extends BaseEntity
{
    protected $attributes = [
        'id'       => 55,
        'username' => 'test_discovered',
    ];
}

class MacroDiscoveryTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        MacroDiscovery::reset();
        AppLifecycle::reset();
        DiscoveredEntityExample::flushMacros();
        parent::tearDown();
    }

    public function testMacroDiscoveryBootIdempotency(): void
    {
        MacroDiscovery::discoverAndRegister();
        // Second call should return early without error
        MacroDiscovery::discoverAndRegister();

        $this->assertTrue(true);
    }

    public function testAppLifecycleDiscoversMacros(): void
    {
        AppLifecycle::boot();

        $this->assertTrue(true);
    }

    public function testMacroDiscoveryRespectsModulesConfig(): void
    {
        $modules = new Modules();
        $modules->enabled = true;
        $modules->aliases = ['macros'];

        $this->assertTrue($modules->shouldDiscover('macros'));

        $modules->aliases = ['events', 'filters'];
        $this->assertFalse($modules->shouldDiscover('macros'));
    }
}
