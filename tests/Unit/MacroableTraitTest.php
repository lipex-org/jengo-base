<?php

declare(strict_types=1);

namespace Tests\Unit;

use BadMethodCallException;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Entities\BaseEntity;
use Jengo\Base\Traits\MacroableTrait;

class MacroableExample
{
    use MacroableTrait;

    public string $name = 'Ian';
}

class MacroableMixinExample
{
    public function getUpperName(): \Closure
    {
        return function () {
            /** @var MacroableExample $this */
            return strtoupper($this->name);
        };
    }
}

class TestUserEntity extends BaseEntity
{
    protected $attributes = [
        'id'       => 1,
        'username' => 'ian_dev',
        'active'   => true,
    ];
}


class MacroableTraitTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        MacroableExample::flushMacros();
        TestUserEntity::flushMacros();
        parent::tearDown();
    }

    public function testRegisterAndInvokeInstanceMacro(): void
    {
        MacroableExample::macro('sayHello', function (string $greeting) {
            /** @var MacroableExample $this */
            return "{$greeting}, {$this->name}!";
        });

        $obj = new MacroableExample();
        $this->assertSame('Hello, Ian!', $obj->sayHello('Hello'));
    }

    public function testRegisterAndInvokeStaticMacro(): void
    {
        MacroableExample::macro('version', function () {
            return '2.0.0';
        });

        $this->assertSame('2.0.0', MacroableExample::version());
    }

    public function testMixinRegistration(): void
    {
        MacroableExample::mixin(new MacroableMixinExample());

        $obj = new MacroableExample();
        $this->assertSame('IAN', $obj->getUpperName());
    }

    public function testThrowsBadMethodCallOnUnregisteredMacro(): void
    {
        $this->expectException(BadMethodCallException::class);
        $obj = new MacroableExample();
        $obj->nonExistentMethod();
    }

    public function testBaseEntityInheritsMacros(): void
    {
        TestUserEntity::macro('hasCompletedOnboarding', function () {
            /** @var TestUserEntity $this */
            return $this->active === true && $this->username !== '';
        });

        $user = new TestUserEntity();
        $this->assertTrue($user->hasCompletedOnboarding());
    }
}
