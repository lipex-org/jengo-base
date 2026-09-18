<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;
use Jengo\Base\Facades\Request;

final class RequestErgonomicsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
        $_POST = [];
        service('superglobals')->setGetArray([]);
        service('superglobals')->setPostArray([]);
        helper('Jengo\Base\Helpers\jengo');
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        service('superglobals')->setGetArray([]);
        service('superglobals')->setPostArray([]);
        Services::reset(true);
        parent::tearDown();
    }

    private function setMockRequest(array $get = [], array $post = [], array $headers = []): void
    {
        $config = new App();
        $uri = Services::uri();
        $userAgent = new UserAgent();
        $request = new IncomingRequest($config, $uri, 'php://input', $userAgent);

        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);

        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }

        Services::injectMock('request', $request);
    }

    public function testRequestAllMergesGetAndPost(): void
    {
        $this->setMockRequest(['search' => 'jengo'], ['name' => 'Ian']);

        $all = Request::all();

        $this->assertSame('jengo', $all['search']);
        $this->assertSame('Ian', $all['name']);
    }

    public function testRequestBoolean(): void
    {
        $this->setMockRequest([], [
            'is_active' => 'true',
            'is_admin' => '1',
            'is_verified' => 'on',
            'is_subscribed' => 'yes',
            'is_banned' => 'false',
            'is_deleted' => '0',
        ]);

        $this->assertTrue(Request::boolean('is_active'));
        $this->assertTrue(Request::boolean('is_admin'));
        $this->assertTrue(Request::boolean('is_verified'));
        $this->assertTrue(Request::boolean('is_subscribed'));
        $this->assertFalse(Request::boolean('is_banned'));
        $this->assertFalse(Request::boolean('is_deleted'));
        $this->assertTrue(Request::boolean('non_existent', true));
        $this->assertFalse(Request::boolean('non_existent', false));
    }

    public function testRequestIntegerAndFloat(): void
    {
        $this->setMockRequest([], [
            'page' => '15',
            'price' => '29.95',
        ]);

        $this->assertSame(15, Request::integer('page'));
        $this->assertSame(1, Request::integer('missing_page', 1));
        $this->assertSame(29.95, Request::float('price'));
        $this->assertSame(0.0, Request::float('missing_price', 0.0));
    }

    public function testRequestDate(): void
    {
        $this->setMockRequest([], [
            'birthday' => '2026-05-10',
            'custom_date' => '10/05/2026',
            'empty_date' => '',
        ]);

        $birthday = Request::date('birthday');
        $this->assertInstanceOf(Time::class, $birthday);
        $this->assertSame('2026-05-10', $birthday->format('Y-m-d'));

        $custom = Request::date('custom_date', 'd/m/Y');
        $this->assertInstanceOf(Time::class, $custom);
        $this->assertSame('2026-05-10', $custom->format('Y-m-d'));

        $this->assertNull(Request::date('empty_date'));
        $this->assertNull(Request::date('missing_date'));
    }

    public function testRequestBearerToken(): void
    {
        $this->setMockRequest([], [], [
            'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
        ]);

        $token = Request::bearerToken();
        $this->assertSame('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $token);

        $this->setMockRequest([], [], []);
        $this->assertNull(Request::bearerToken());
    }

    public function testRequestOnlyAndExcept(): void
    {
        $this->setMockRequest([], [
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $only = Request::only('name', 'email');
        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $only);

        $except = Request::except('password');
        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $except);
    }

    public function testRequestHasFilledAndMissing(): void
    {
        $this->setMockRequest([], [
            'name' => 'John',
            'blank_field' => '   ',
            'zero_field' => '0',
        ]);

        $this->assertTrue(Request::has('name'));
        $this->assertTrue(Request::has(['name', 'blank_field']));
        $this->assertFalse(Request::has('missing_field'));

        $this->assertTrue(Request::filled('name'));
        $this->assertTrue(Request::filled('zero_field'));
        $this->assertFalse(Request::filled('blank_field'));
        $this->assertFalse(Request::filled('missing_field'));

        $this->assertTrue(Request::missing('missing_field'));
        $this->assertFalse(Request::missing('name'));
    }
}
