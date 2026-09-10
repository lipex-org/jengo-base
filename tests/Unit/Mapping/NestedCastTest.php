<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Attributes\Mapping\MapCast;
use Jengo\Base\Entities\BaseEntity;

class AddressEntity extends BaseEntity
{
}

class TagEntity extends BaseEntity
{
}

class CompanyEntity extends BaseEntity
{
    #[MapCast(AddressEntity::class)]
    public ?AddressEntity $address = null;

    #[MapCast(TagEntity::class, isCollection: true)]
    public array $tags = [];
}

final class NestedCastTest extends CIUnitTestCase
{
    public function testNestedEntityAndCollectionCast(): void
    {
        $payload = [
            'name' => 'Acme Corporation',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Metropolis',
            ],
            'tags' => [
                ['name' => 'enterprise'],
                ['name' => 'saas'],
            ],
        ];

        $company = CompanyEntity::from($payload);

        $this->assertInstanceOf(CompanyEntity::class, $company);
        $this->assertSame('Acme Corporation', $company->name);

        // Nested single entity
        $this->assertInstanceOf(AddressEntity::class, $company->address);
        $this->assertSame('123 Main St', $company->address->street);
        $this->assertSame('Metropolis', $company->address->city);

        // Nested collection of entities
        $this->assertCount(2, $company->tags);
        $this->assertInstanceOf(TagEntity::class, $company->tags[0]);
        $this->assertSame('enterprise', $company->tags[0]->name);
        $this->assertSame('saas', $company->tags[1]->name);
    }
}
