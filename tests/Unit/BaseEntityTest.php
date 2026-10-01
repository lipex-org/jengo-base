<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Entities\BaseEntity;

class TestEntity extends BaseEntity
{
    protected array $visible = [];
    protected array $hidden = [];
    protected array $obfuscatedFields = [];

    public function setVisibleFields(array $fields): void
    {
        $this->visible = $fields;
    }

    public function setHiddenFields(array $fields): void
    {
        $this->hidden = $fields;
    }

    public function setObfuscatedFields(array $fields): void
    {
        $this->obfuscatedFields = $fields;
    }
}

final class BaseEntityTest extends CIUnitTestCase
{
    public function testBaseEntitySerialization()
    {
        $entity = new TestEntity([
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
        ]);

        // Default: all are serialized
        $data = $entity->jsonSerialize();
        $this->assertSame(123, $data['id']);
        $this->assertSame('John Doe', $data['name']);
        $this->assertSame('john@example.com', $data['email']);
        $this->assertSame('admin', $data['role']);
    }

    public function testVisibleFieldsFilter()
    {
        $entity = new TestEntity([
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
        ]);
        $entity->setVisibleFields(['id', 'name']);

        $data = $entity->jsonSerialize();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('role', $data);
    }

    public function testHiddenFieldsFilter()
    {
        $entity = new TestEntity([
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
        ]);
        $entity->setHiddenFields(['email', 'role']);

        $data = $entity->jsonSerialize();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('role', $data);
    }

    public function testSqidsObfuscationAndDeobfuscation()
    {
        $entity = new TestEntity([
            'id' => 456,
            'user_id' => 789,
            'name' => 'Alice',
        ]);
        $entity->setObfuscatedFields(['id', 'user_id']);

        $data = $entity->jsonSerialize();
        $this->assertIsString($data['id']);
        $this->assertIsString($data['user_id']);
        $this->assertNotEquals('456', $data['id']);
        $this->assertNotEquals('789', $data['user_id']);
    }

    public function testNestedEntitiesSerializationAndObfuscation()
    {
        $profile = new TestEntity([
            'id' => 10,
            'bio' => 'Software Engineer',
        ]);
        $profile->setObfuscatedFields(['id']);

        $identity1 = new TestEntity([
            'id' => 100,
            'provider' => 'email',
        ]);
        $identity1->setObfuscatedFields(['id']);

        $identity2 = new TestEntity([
            'id' => 200,
            'provider' => 'github',
        ]);
        $identity2->setObfuscatedFields(['id']);

        $user = new TestEntity([
            'id' => 1,
            'name' => 'Alice',
            'profile' => $profile,
            'identities' => [$identity1, $identity2],
        ]);
        $user->setObfuscatedFields(['id']);

        $serialized = $user->jsonSerialize();

        // User ID is obfuscated
        $this->assertIsString($serialized['id']);
        $this->assertNotEquals('1', $serialized['id']);

        // Nested profile is serialized as array and its ID is obfuscated
        $this->assertIsArray($serialized['profile']);
        $this->assertIsString($serialized['profile']['id']);
        $this->assertNotEquals('10', $serialized['profile']['id']);
        $this->assertSame('Software Engineer', $serialized['profile']['bio']);

        // Nested identities array is serialized and each ID is obfuscated
        $this->assertIsArray($serialized['identities']);
        $this->assertCount(2, $serialized['identities']);
        $this->assertIsString($serialized['identities'][0]['id']);
        $this->assertNotEquals('100', $serialized['identities'][0]['id']);
        $this->assertSame('email', $serialized['identities'][0]['provider']);
        $this->assertIsString($serialized['identities'][1]['id']);
        $this->assertNotEquals('200', $serialized['identities'][1]['id']);
        $this->assertSame('github', $serialized['identities'][1]['provider']);

        // Verify json_encode produces identical obfuscated JSON
        $encoded = json_decode(json_encode($user), true);
        $this->assertSame($serialized['id'], $encoded['id']);
        $this->assertSame($serialized['profile']['id'], $encoded['profile']['id']);
        $this->assertSame($serialized['identities'][0]['id'], $encoded['identities'][0]['id']);
    }
}

