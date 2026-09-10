<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use CodeIgniter\Entity\Entity;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Entities\BaseEntity;
use Jengo\Base\Mapping\Exceptions\MappingException;
use Jengo\Base\Mapping\Mapper;
use stdClass;

// A 3rd-party CI4 entity that does NOT extend Jengo BaseEntity
class ForeignUserEntity extends Entity
{
    protected $attributes = [
        'id' => null,
        'username' => null,
        'email_address' => null,
        'secret_token' => null,
    ];
}

// A Jengo entity that extends BaseEntity and utilizes Sqids & hidden fields
class AppUserEntity extends BaseEntity
{
    protected array $hidden = ['secret_token'];
    protected array $obfuscatedFields = ['id'];
}

final class MapperTest extends CIUnitTestCase
{
    public function testMapFromArrayToBaseEntity(): void
    {
        $data = [
            'id' => 42,
            'username' => 'johndoe',
            'email_address' => 'john@example.com',
            'secret_token' => 'top-secret',
        ];

        $appUser = Mapper::map($data, AppUserEntity::class);

        $this->assertInstanceOf(AppUserEntity::class, $appUser);
        $this->assertSame(42, $appUser->id);
        $this->assertSame('johndoe', $appUser->username);
        $this->assertSame('john@example.com', $appUser->email_address);

        // Verify BaseEntity features apply: hidden field removed and id obfuscated in JSON
        $json = $appUser->jsonSerialize();
        $this->assertArrayNotHasKey('secret_token', $json);
        $this->assertNotSame(42, $json['id']);
        $this->assertIsString($json['id']);
    }

    public function testMapFromForeignCI4EntityToBaseEntity(): void
    {
        $foreign = new ForeignUserEntity([
            'id' => 999,
            'username' => 'shield_user',
            'email_address' => 'shield@example.com',
            'secret_token' => 'pass123',
        ]);

        $appUser = AppUserEntity::from($foreign);

        $this->assertInstanceOf(AppUserEntity::class, $appUser);
        $this->assertSame(999, $appUser->id);
        $this->assertSame('shield_user', $appUser->username);
        $this->assertSame('shield@example.com', $appUser->email_address);

        // JSON serialization utilizes BaseEntity rules
        $serialized = $appUser->jsonSerialize();
        $this->assertArrayNotHasKey('secret_token', $serialized);
        $this->assertIsString($serialized['id']);
    }

    public function testMapFromStdClassAndGenericObject(): void
    {
        $obj = new stdClass();
        $obj->id = 77;
        $obj->username = 'std_user';
        $obj->email_address = 'std@example.com';

        $appUser = Mapper::map($obj, AppUserEntity::class);

        $this->assertSame(77, $appUser->id);
        $this->assertSame('std_user', $appUser->username);
    }

    public function testFluentPendingMapping(): void
    {
        $source = ['id' => 10, 'username' => 'fluent_user', 'email_address' => 'fluent@example.com'];

        $appUser = Mapper::from($source)
            ->with(['extra_role' => 'editor'])
            ->only(['id', 'username', 'extra_role'])
            ->to(AppUserEntity::class);

        $this->assertSame(10, $appUser->id);
        $this->assertSame('fluent_user', $appUser->username);
        $this->assertSame('editor', $appUser->extra_role);
        $this->assertNull($appUser->email_address);
    }

    public function testMapIntoExistingInstance(): void
    {
        $existing = new AppUserEntity(['id' => 1, 'username' => 'initial']);

        Mapper::from(['username' => 'updated_name'])->into($existing);

        $this->assertSame('updated_name', $existing->username);
        $this->assertSame(1, $existing->id);
    }

    public function testCustomProfileRegistry(): void
    {
        Mapper::register(ForeignUserEntity::class, AppUserEntity::class, function ($source, $target, $data) {
            $target->id = $source->id;
            $target->username = strtoupper((string) $source->username);
            $target->email_address = 'custom_' . $source->email_address;
        });

        $foreign = new ForeignUserEntity([
            'id' => 15,
            'username' => 'lowercase',
            'email_address' => 'user@mail.com',
        ]);

        $target = Mapper::map($foreign, AppUserEntity::class);

        $this->assertSame('LOWERCASE', $target->username);
        $this->assertSame('custom_user@mail.com', $target->email_address);

        Mapper::clearRegistry();
    }

    public function testThrowsExceptionForNonExistentTarget(): void
    {
        $this->expectException(MappingException::class);
        Mapper::map(['id' => 1], 'NonExistent\\Class\\Name');
    }

    public function testThrowsExceptionForUnsupportedSource(): void
    {
        $this->expectException(MappingException::class);
        Mapper::map(12345, AppUserEntity::class);
    }
}
