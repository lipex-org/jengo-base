<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Events\AppLifecycle;

/**
 * @internal
 */
final class InitEventTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AppLifecycle::reset();
    }

    protected function tearDown(): void
    {
        AppLifecycle::reset();
        parent::tearDown();
    }

    public function testInitEventFiresOnPreSystem(): void
    {
        $fired = false;
        Events::on('init', static function () use (&$fired) {
            $fired = true;
        });

        // Trigger pre_system (HTTP Web entrypoint)
        Events::trigger('pre_system');

        $this->assertTrue($fired);
    }

    public function testInitEventFiresOnPreCommand(): void
    {
        $fired = false;
        Events::on('init', static function () use (&$fired) {
            $fired = true;
        });

        // Trigger pre_command (Spark / CLI entrypoint)
        Events::trigger('pre_command');

        $this->assertTrue($fired);
    }
}
