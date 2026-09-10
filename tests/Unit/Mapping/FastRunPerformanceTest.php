<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Entities\BaseEntity;

class BenchEntity extends BaseEntity
{
}

final class FastRunPerformanceTest extends CIUnitTestCase
{
    public function testFastRunBatchMapping(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
            ];
        }

        $startTime = microtime(true);
        $mapped = BenchEntity::collect($rows);
        $elapsed = microtime(true) - $startTime;

        $this->assertCount(1000, $mapped);
        $this->assertSame(1, $mapped[0]->id);
        $this->assertSame('User 1', $mapped[0]->name);
        $this->assertSame(1000, $mapped[999]->id);

        // Fast-run execution: 1,000 entities should hydrate in less than 1.0 second
        $this->assertLessThan(1.0, $elapsed, "Expected 1,000 entity mappings to take < 1s, took {$elapsed}s");
    }
}
