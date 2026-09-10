<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use CodeIgniter\Entity\Entity;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Attributes\Mapping\MapFrom;
use Jengo\Base\Attributes\Mapping\MapTo;
use Jengo\Base\Attributes\Mapping\MapWith;
use Jengo\Base\Entities\BaseEntity;
use Jengo\Base\Mapping\Mapper;

// 3rd-party user entity
class ThirdPartyUser extends Entity
{
    protected $attributes = [
        'user_id' => null,
        'user_email' => null,
        'full_name' => null,
        'is_active' => 0,
    ];
}

// Mapped Jengo entity
class CustomerEntity extends BaseEntity
{
    #[MapFrom('user_id')]
    #[MapTo('user_id')]
    public ?int $id = null;

    #[MapFrom('user_email')]
    #[MapTo('user_email')]
    public ?string $email = null;

    #[MapFrom('full_name')]
    public ?string $name = null;

    #[MapFrom('is_active')]
    #[MapTo('is_active')]
    public ?int $status = null;

    #[MapWith(UpperTransformer::class)]
    public ?string $code = null;
}

final class BidirectionalSyncTest extends CIUnitTestCase
{
    public function testSyncBackToProvidedForeignEntity(): void
    {
        $foreign = new ThirdPartyUser([
            'user_id' => 100,
            'user_email' => 'old@example.com',
            'full_name' => 'Old Name',
            'is_active' => 1,
            'code' => 'init',
        ]);

        $customer = CustomerEntity::from($foreign);

        // Verify initial mapping
        $this->assertSame(100, $customer->id);
        $this->assertSame('old@example.com', $customer->email);
        $this->assertSame('Old Name', $customer->name);
        $this->assertSame('INIT', $customer->code);

        // Mutate customer
        $customer->email = 'new@example.com';
        $customer->name = 'New Name';
        $customer->code = 'UPDATED';

        // Sync back
        $customer->syncTo($foreign);

        // Verify foreign entity has updated attributes under foreign column names
        $this->assertSame('new@example.com', $foreign->user_email);
        $this->assertSame('New Name', $foreign->full_name);
        $this->assertSame('updated', $foreign->code); // Reversed by UpperTransformer to lowercase!

        // Verify CI4 change tracking on foreign entity
        $this->assertTrue($foreign->hasChanged('user_email'));
        $this->assertTrue($foreign->hasChanged('full_name'));
        $this->assertTrue($foreign->hasChanged('code'));
    }

    public function testSyncWithoutArgumentsUsesCapturedOrigin(): void
    {
        $foreign = new ThirdPartyUser([
            'user_id' => 200,
            'user_email' => 'captured@example.com',
            'full_name' => 'Original Capture',
            'is_active' => 1,
        ]);

        $customer = CustomerEntity::from($foreign);
        $customer->name = 'Modified In Memory';

        // Calling syncTo() with no arguments syncs directly to $foreign!
        $customer->syncTo();

        $this->assertSame('Modified In Memory', $foreign->full_name);
    }

    public function testToOriginalReconstructsForeignEntity(): void
    {
        $foreign = new ThirdPartyUser([
            'user_id' => 300,
            'user_email' => 'reconstruct@example.com',
            'full_name' => 'Reconstruct Test',
            'is_active' => 1,
        ]);

        $customer = CustomerEntity::from($foreign);
        $customer->email = 'changed_email@example.com';

        /** @var ThirdPartyUser $reconstructed */
        $reconstructed = $customer->toOriginal();

        $this->assertInstanceOf(ThirdPartyUser::class, $reconstructed);
        $this->assertSame(300, $reconstructed->user_id);
        $this->assertSame('changed_email@example.com', $reconstructed->user_email);
    }

    public function testToOriginalWithArraySource(): void
    {
        $sourceArray = [
            'user_id' => 400,
            'user_email' => 'array@example.com',
            'full_name' => 'Array Source',
            'is_active' => 1,
            'code' => 'test',
        ];

        $customer = CustomerEntity::from($sourceArray);
        $customer->email = 'updated_array@example.com';

        $reversedArray = $customer->toOriginal();

        $this->assertIsArray($reversedArray);
        $this->assertSame('updated_array@example.com', $reversedArray['user_email']);
        $this->assertSame(400, $reversedArray['user_id']);
        $this->assertSame('Array Source', $reversedArray['full_name']);
    }

    public function testSyncOnlyChangedAttributes(): void
    {
        $foreign = new ThirdPartyUser([
            'user_id' => 500,
            'user_email' => 'untouched@example.com',
            'full_name' => 'Untouched Name',
            'is_active' => 1,
        ]);

        $customer = CustomerEntity::from($foreign);

        // Only modify one field
        $customer->name = 'Only Name Changed';

        // Sync only changed
        $customer->syncTo($foreign, onlyChanged: true);

        $this->assertSame('Only Name Changed', $foreign->full_name);
    }
}
