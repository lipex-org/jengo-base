<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Models\UserModel;

final class ModelFacadeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        helper('Jengo\Base\Helpers\jengo');
        $this->migrateDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->regressDatabase();
        $this->migrateDatabase();
    }

    public function testFindOrFailFindsExistingRecord(): void
    {
        $model = new UserModel();
        $id = $model->insert(['name' => 'Alice']);

        $user = model_of(UserModel::class)->findOrFail($id);

        $this->assertNotNull($user);
        $this->assertSame('Alice', is_array($user) ? $user['name'] : $user->name);
    }

    public function testFindOrFailThrowsPageNotFoundException(): void
    {
        $this->expectException(PageNotFoundException::class);

        model_of(UserModel::class)->findOrFail(999999);
    }

    public function testFirstOrFailFindsRecord(): void
    {
        $model = new UserModel();
        $model->insert(['name' => 'Bob']);

        $user = model_of(UserModel::class)->firstOrFail();

        $this->assertNotNull($user);
    }

    public function testFirstOrFailThrowsPageNotFoundExceptionOnEmpty(): void
    {
        $this->expectException(PageNotFoundException::class);

        model_of(UserModel::class)->where('name', 'NonExistentPerson')->firstOrFail();
    }

    public function testWhenAppliesConditionalCallback(): void
    {
        $model = new UserModel();
        $model->insert(['name' => 'Charlie']);
        $model->insert(['name' => 'Dana']);

        $applied = false;
        $users = model_of(UserModel::class)
            ->when(true, function ($m) use (&$applied) {
                $applied = true;
                $m->where('name', 'Charlie');
            })
            ->when(false, function ($m) {
                $m->where('name', 'ShouldNotRun');
            })
            ->findAll();

        $this->assertTrue($applied);
        $this->assertCount(1, $users);
    }

    public function testUnlessAppliesConditionalCallback(): void
    {
        $model = new UserModel();
        $model->insert(['name' => 'Eve']);

        $applied = false;
        $users = model_of(UserModel::class)
            ->unless(false, function ($m) use (&$applied) {
                $applied = true;
                $m->where('name', 'Eve');
            })
            ->unless(true, function ($m) {
                $m->where('name', 'ShouldNotRun');
            })
            ->findAll();

        $this->assertTrue($applied);
        $this->assertCount(1, $users);
    }

    public function testTapAndPipe(): void
    {
        $tapped = false;
        $result = model_of(UserModel::class)
            ->tap(function ($m) use (&$tapped) {
                $tapped = true;
            })
            ->pipe(function ($m) {
                return 'piped-result';
            });

        $this->assertTrue($tapped);
        $this->assertSame('piped-result', $result);
    }
}
