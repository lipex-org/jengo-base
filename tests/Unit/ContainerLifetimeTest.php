<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Container\Container;
use stdClass;

class TestServiceA
{
    public function __construct(public string $name = 'ServiceA')
    {
    }
}

class TestServiceB
{
    public function __construct(public TestServiceA $serviceA, public string $environment = 'testing')
    {
    }

    public function process(string $suffix): string
    {
        return $this->serviceA->name . ' - ' . $this->environment . ' - ' . $suffix;
    }
}

final class ContainerLifetimeTest extends CIUnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        Container::setInstance($this->container);
    }

    protected function tearDown(): void
    {
        $this->container->flush();
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testTransientBindingAlwaysReturnsNewInstance(): void
    {
        $this->container->bind(TestServiceA::class, fn () => new TestServiceA('Transient-' . uniqid()), false);

        $inst1 = $this->container->make(TestServiceA::class);
        $inst2 = $this->container->make(TestServiceA::class);

        $this->assertNotSame($inst1, $inst2);
        $this->assertNotSame($inst1->name, $inst2->name);
    }

    public function testSingletonBindingReturnsSameInstance(): void
    {
        $this->container->singleton(TestServiceA::class, fn () => new TestServiceA('Singleton'));

        $inst1 = $this->container->make(TestServiceA::class);
        $inst2 = $this->container->make(TestServiceA::class);

        $this->assertSame($inst1, $inst2);
        $this->assertSame('Singleton', $inst1->name);
    }

    public function testAutoWiringRecursiveDependencies(): void
    {
        $this->container->singleton(TestServiceA::class, fn () => new TestServiceA('AutoWiredA'));

        /** @var TestServiceB $serviceB */
        $serviceB = $this->container->make(TestServiceB::class, ['environment' => 'production']);

        $this->assertInstanceOf(TestServiceB::class, $serviceB);
        $this->assertSame('AutoWiredA', $serviceB->serviceA->name);
        $this->assertSame('production', $serviceB->environment);

        $result = $this->container->call([$serviceB, 'process'], ['suffix' => 'done']);
        $this->assertSame('AutoWiredA - production - done', $result);
    }

    public function testInstanceRegistrationAndFlush(): void
    {
        $obj = new stdClass();
        $obj->id = 12345;

        $this->container->instance('shared.object', $obj);
        $this->assertSame($obj, $this->container->make('shared.object'));

        $this->container->flush();
        $this->assertFalse($this->container->bound('shared.object'));
    }
}
