<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use CodeIgniter\Entity\Entity;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Attributes\Mapping\MapFrom;
use Jengo\Base\Attributes\Mapping\MapIgnore;
use Jengo\Base\Attributes\Mapping\MapProperty;
use Jengo\Base\Attributes\Mapping\MapSource;
use Jengo\Base\Attributes\Mapping\MapTo;
use Jengo\Base\Attributes\Mapping\MapWith;
use Jengo\Base\Entities\BaseEntity;
use Jengo\Base\Mapping\Contracts\ValueTransformerInterface;
use Jengo\Base\Mapping\Mapper;

class UpperTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, string $sourceKey, object|array $source): mixed
    {
        return is_string($value) ? strtoupper($value) : $value;
    }

    public function reverse(mixed $value, string $targetKey, object|array $target): mixed
    {
        return is_string($value) ? strtolower($value) : $value;
    }
}

// Target entity with property-level attributes
class ProfileEntity extends BaseEntity
{
    #[MapFrom('user_email')]
    #[MapTo('user_email')]
    public ?string $email = null;

    #[MapFrom('account_balance')]
    public ?float $balance = null;

    #[MapWith(UpperTransformer::class)]
    public ?string $badge = null;

    #[MapIgnore]
    public ?string $internalNote = null;

    #[MapIgnore(direction: MapIgnore::DIRECTION_TO_TARGET)]
    public ?string $syncOnlyField = 'default_sync';
}

// Target entity with class-level MapProperty attributes (dynamic CI4 attributes)
#[MapSource(Entity::class)]
#[MapProperty(target: 'display_name', source: 'screen_name')]
#[MapProperty(target: 'contact', source: 'mobile_phone')]
class DynamicEntity extends BaseEntity
{
}

final class AttributesTest extends CIUnitTestCase
{
    public function testMapFromRenamesAttributes(): void
    {
        $source = [
            'user_email' => 'alice@example.com',
            'account_balance' => 125.50,
            'badge' => 'vip_member',
            'internalNote' => 'Do not map this',
        ];

        $profile = ProfileEntity::from($source);

        $this->assertSame('alice@example.com', $profile->email);
        $this->assertSame(125.50, $profile->balance);
        $this->assertSame('VIP_MEMBER', $profile->badge); // Transformed by UpperTransformer
        $this->assertNull($profile->internalNote); // MapIgnore applied
    }

    public function testClassLevelMapProperty(): void
    {
        $source = [
            'screen_name' => 'cyber_samurai',
            'mobile_phone' => '+15550199',
            'regular_field' => 'regular_val',
        ];

        $dynamic = DynamicEntity::from($source);

        $this->assertSame('cyber_samurai', $dynamic->display_name);
        $this->assertSame('+15550199', $dynamic->contact);
        $this->assertSame('regular_val', $dynamic->regular_field);
    }

    public function testClassLevelDefaultMapSource(): void
    {
        $source = ['screen_name' => 'bot'];
        $dynamic = DynamicEntity::from($source);

        $this->assertInstanceOf(Entity::class, $dynamic->toOriginal());
    }
}
