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

        $obfuscatedId = $data['id'];
        $obfuscatedUserId = $data['user_id'];

        // Test decoding when setting properties
        $newEntity = new TestEntity();
        $newEntity->setObfuscatedFields(['id', 'user_id']);

        $newEntity->id = $obfuscatedId;
        $newEntity->user_id = $obfuscatedUserId;

        // Attributes should be decoded back to integers
        $this->assertSame(456, $newEntity->id);
        $this->assertSame(789, $newEntity->user_id);
    }
}
