<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Jengo\Base\Setups\ApiSetup;
use Jengo\Base\Setups\AuthSetup;
use Jengo\Base\Setups\CoreSetup;
use Jengo\Base\Setups\InertiaSetup;
use Jengo\Base\Setups\Repositories\SetupRepository;
use Jengo\Base\Setups\ShieldAuthSetup;
use Tests\Support\CommandTestCase;

final class SetupCommandTest extends CommandTestCase
{
    public function testSetupRepositoryDiscoversSetups(): void
    {
        $all = SetupRepository::all();
        $names = array_map(static fn ($setup) => $setup::name(), $all);

        $this->assertContains('api', $names);
        $this->assertContains('auth', $names);
        $this->assertContains('shield-auth', $names);
        $this->assertContains('core', $names);
        $this->assertContains('inertia', $names);
    }

    public function testFindApiSetup(): void
    {
        $setup = SetupRepository::find('api');

        $this->assertInstanceOf(ApiSetup::class, $setup);
        $this->assertSame('api', $setup::name());
        $this->assertSame('JENGO API', $setup::title());
        $this->assertNotEmpty($setup::description());
    }

    public function testFindAuthSetup(): void
    {
        $setup = SetupRepository::find('auth');

        $this->assertInstanceOf(AuthSetup::class, $setup);
        $this->assertSame('auth', $setup::name());
        $this->assertSame('JENGO AUTH', $setup::title());
        $this->assertNotEmpty($setup::description());
    }

    public function testFindShieldAuthSetup(): void
    {
        $setup = SetupRepository::find('shield-auth');

        $this->assertInstanceOf(ShieldAuthSetup::class, $setup);
        $this->assertSame('shield-auth', $setup::name());
        $this->assertSame('THE SHIELD GATEKEEPER', $setup::title());
        $this->assertNotEmpty($setup::description());
    }

    public function testFindReturnsNullForNonExistentSetup(): void
    {
        $setup = SetupRepository::find('non-existent');

        $this->assertNull($setup);
    }
}
