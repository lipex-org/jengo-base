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
}
