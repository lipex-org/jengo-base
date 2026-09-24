<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Base\Container\Container;
use Jengo\Base\Container\ContainerInterface;
use Jengo\Base\Container\Exceptions\ContainerException;
use Jengo\Base\Container\Exceptions\NotFoundException;
use Jengo\Base\Container\Traits\HasContainer;
use Jengo\Base\Validation\ValidatedData;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class ContainerTest extends CIUnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        Container::setInstance($this->container);
        helper('Jengo\Base\Helpers\jengo');
    }

    protected function tearDown(): void
    {
        $this->container->flush();
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testContainerImplementsPsr11AndJengoInterface(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
        $this->assertInstanceOf(\Psr\Container\ContainerInterface::class, $this->container);
    }

    public function testSingletonInstanceAccess(): void
    {
        $instance1 = Container::getInstance();
        $instance2 = Container::getInstance();
        $this->assertSame($instance1, $instance2);
    }

    public function testBasicBindingAndResolution(): void
    {
        $this->container->bind('sample_string', fn () => 'hello world');
        $this->assertSame('hello world', $this->container->make('sample_string'));
        $this->assertTrue($this->container->bound('sample_string'));
    }

    public function testSingletonBindingReturnsSameInstance(): void
    {
        $this->container->singleton(\stdClass::class, function () {
            $obj = new \stdClass();
            $obj->time = microtime(true);
            return $obj;
        });

        $obj1 = $this->container->make(\stdClass::class);
        $obj2 = $this->container->make(\stdClass::class);

        $this->assertSame($obj1, $obj2);
    }

    public function testInstanceBinding(): void
    {
        $mock = new \stdClass();
        $mock->key = 'custom_val';

        $this->container->instance('custom_key', $mock);

        $this->assertSame($mock, $this->container->make('custom_key'));
        $this->assertTrue($this->container->bound('custom_key'));
        $this->assertTrue($this->container->resolved('custom_key'));
    }

    public function testAutoWiringRecursiveDependencies(): void
    {
        $this->container->bind(DummyRepositoryInterface::class, DummyDatabaseRepository::class);

        /** @var DummyUserService $service */
        $service = $this->container->make(DummyUserService::class);

        $this->assertInstanceOf(DummyUserService::class, $service);
        $this->assertInstanceOf(DummyDatabaseRepository::class, $service->repository);
        $this->assertInstanceOf(DummyBillingService::class, $service->billing);
    }

    public function testMakeWithParameterOverrides(): void
    {
        $this->container->bind(DummyRepositoryInterface::class, DummyDatabaseRepository::class);

        $customBilling = new DummyBillingService();
        $customBilling->driver = 'custom_stripe';

        /** @var DummyUserService $service */
        $service = $this->container->make(DummyUserService::class, [
            'billing' => $customBilling,
        ]);

        $this->assertSame('custom_stripe', $service->billing->driver);
    }

    public function testPsr11GetAndHas(): void
    {
        $this->container->bind('my_key', fn () => 12345);

        $this->assertTrue($this->container->has('my_key'));
        $this->assertSame(12345, $this->container->get('my_key'));

        $this->expectException(NotFoundException::class);
        $this->container->get('non_existent_key_xyz');
    }

    public function testCallClosureWithAutoWiringAndPositionalParams(): void
    {
        $this->container->bind(DummyRepositoryInterface::class, DummyDatabaseRepository::class);

        $result = $this->container->call(function (int $id, DummyUserService $service, string $status = 'active') {
            return [
                'id'         => $id,
                'user'       => $service->findUser($id),
                'status'     => $status,
                'repo_class' => $service->repository::class,
            ];
        }, [42]);

        $this->assertSame(42, $result['id']);
        $this->assertSame('User 42', $result['user']);
        $this->assertSame('active', $result['status']);
        $this->assertSame(DummyDatabaseRepository::class, $result['repo_class']);
    }

    public function testCallClassMethodStringSyntax(): void
    {
        $this->container->bind(DummyRepositoryInterface::class, DummyDatabaseRepository::class);

        $result = $this->container->call(DummyController::class . '@show', [99]);

        $this->assertSame(['id' => 99, 'name' => 'User 99'], $result);
    }

    public function testControllerRemapWithHasContainerTrait(): void
    {
        $this->container->bind(DummyRepositoryInterface::class, DummyDatabaseRepository::class);

        $controller = new DummyController();

        $result = $controller->_remap('update', 77, 'pending');

        $this->assertSame([
            'id'       => 77,
            'status'   => 'pending',
            'user'     => 'User 77',
            'has_data' => true,
        ], $result);
    }

    public function testCommandMethodInvocationWithDI(): void
    {
        $this->container->bind(DummyRepositoryInterface::class, DummyDatabaseRepository::class);

        $command = new DummyCommand(new NullLogger(), new \CodeIgniter\CLI\Commands());

        $output = $this->container->call([$command, 'run'], [['id' => 55]]);

        $this->assertSame('Command dispatched for User 55', $output);
    }

    public function testGlobalAppAndResolveHelpers(): void
    {
        $this->assertSame($this->container, app());

        $this->container->bind('test_val', fn () => 999);
        $this->assertSame(999, app('test_val'));
        $this->assertSame(999, resolve('test_val'));
    }

    public function testCircularDependencyThrowsException(): void
    {
        $this->container->bind(CircularA::class, CircularA::class);
        $this->container->bind(CircularB::class, CircularB::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency');

        $this->container->make(CircularA::class);
    }
}

// Dummy Test Doubles
interface DummyRepositoryInterface {
    public function find(int $id): string;
}

class DummyDatabaseRepository implements DummyRepositoryInterface {
    public function find(int $id): string {
        return "User {$id}";
    }
}

class DummyBillingService {
    public string $driver = 'default_gateway';
}

class DummyUserService {
    public function __construct(
        public DummyRepositoryInterface $repository,
        public DummyBillingService $billing
    ) {}

    public function findUser(int $id): string {
        return $this->repository->find($id);
    }
}

class DummyController {
    use HasContainer;

    public function show(int $id, DummyUserService $users): array {
        return ['id' => $id, 'name' => $users->findUser($id)];
    }

    public function update(int $id, DummyUserService $users, ValidatedData $data, string $status = 'active'): array {
        return [
            'id'       => $id,
            'status'   => $status,
            'user'     => $users->findUser($id),
            'has_data' => $data instanceof ValidatedData,
        ];
    }
}

class DummyCommand extends BaseCommand {
    use HasContainer;

    protected $group = 'Testing';
    protected $name = 'test:dummy';
    protected $description = 'Test command';

    public function run(array $params = [], ?DummyUserService $users = null) {
        $id = $params['id'] ?? 1;
        $user = $users?->findUser((int) $id) ?? 'Unknown';
        return "Command dispatched for {$user}";
    }
}

class CircularA {
    public function __construct(public CircularB $b) {}
}

class CircularB {
    public function __construct(public CircularA $a) {}
}
