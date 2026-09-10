<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Entities\BaseEntity;
use Jengo\Base\Mapping\Mapper;

class ItemEntity extends BaseEntity
{
}

final class CollectionMappingTest extends CIUnitTestCase
{
    public function testCollectFromArrays(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
            ['id' => 3, 'name' => 'Third'],
        ];

        $items = ItemEntity::collect($rows);

        $this->assertCount(3, $items);
        $this->assertInstanceOf(ItemEntity::class, $items[0]);
        $this->assertSame(1, $items[0]->id);
        $this->assertSame('First', $items[0]->name);
        $this->assertSame(3, $items[2]->id);
    }

    public function testCollectViaPendingMappingFluent(): void
    {
        $rows = [
            'k1' => ['id' => 10, 'name' => 'K1'],
            'k2' => ['id' => 20, 'name' => 'K2'],
        ];

        $items = Mapper::from($rows)->collectTo(ItemEntity::class);

        $this->assertArrayHasKey('k1', $items);
        $this->assertArrayHasKey('k2', $items);
        $this->assertSame(10, $items['k1']->id);
        $this->assertSame(20, $items['k2']->id);
    }
}
