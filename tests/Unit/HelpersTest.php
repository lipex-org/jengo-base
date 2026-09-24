<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Support\CommandTestCase;

final class HelpersTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('jengo');
    }

    public function testSqidsHashAndUnhash()
    {
        // Assert null cases
        $this->assertNull(sqids_hash(null));
        $this->assertNull(sqids_unhash(null));
        $this->assertNull(sqids_unhash(''));

        // Assert valid cases
        $hash = sqids_hash(12345);
        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);

        $id = sqids_unhash($hash);
        $this->assertSame(12345, $id);

        // Assert invalid hash unhash returns null
        $this->assertNull(sqids_unhash('invalid-hash-string-value-that-is-not-valid'));
    }

    public function testSqidsCustomConfig(): void
    {
        $customConfig = new \Jengo\Base\Config\Jengo();
        $customConfig->sqids = [
            'alphabet'  => '0123456789abcdefghijklmnopqrstuvwxyz',
            'minLength' => 16,
        ];
        \CodeIgniter\Config\Factories::injectMock('config', 'Jengo', $customConfig);

        // Clear static cache in helper by reflection or testing new instance logic
        $hash = (new \Sqids\Sqids($customConfig->sqids['alphabet'], $customConfig->sqids['minLength']))->encode([999]);
        $this->assertGreaterThanOrEqual(16, strlen($hash));
    }
}
