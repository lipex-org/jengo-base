<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;
use Jengo\Base\Attributes\Validate;
use Jengo\Base\Validation\FormHandler;

class TestFormHandler extends FormHandler
{
    protected array $rules = [
        'name'  => 'required|min_length[3]',
        'email' => 'required|valid_email',
    ];

    protected array $messages = [
        'name' => [
            'required' => 'The name is required.',
        ]
    ];
}

class InvalidFormHandler {}

class ObfuscatedFormHandler extends FormHandler
{
    protected array $rules = [
        'user_id' => 'required|integer',
    ];
    protected array $obfuscatedFields = ['user_id'];
    protected array $routeParams = [
        'user_id' => 0,
    ];
}

final class FormHandlerTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('Jengo\Base\Helpers\jengo');
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    private function createRequest(array $data = [], array $headers = []): IncomingRequest
    {
        $config = new App();
        $uri = new URI('http://example.com/test');
        $userAgent = new UserAgent();
        $request = new IncomingRequest($config, $uri, 'php://input', $userAgent);

        foreach ($headers as $key => $val) {
            $request->setHeader($key, $val);
        }

        // Set superglobals
        $_GET = $data;
        $_POST = $data;

        // Set CI4 global request arrays
        $request->setGlobal('get', $data);
        $request->setGlobal('post', $data);

        // Set raw body for JSON requests
        $request->setBody(json_encode($data));

        return $request;
    }

    public function testFormHandlerSuccess()
    {
        $request = $this->createRequest([
            'name'  => 'Alice',
            'email' => 'alice@example.com',
            'extra' => 'not-in-rules',
        ]);

        $handler = new TestFormHandler();
        $this->assertTrue($handler->validate($request));
        $this->assertEmpty($handler->getErrors());

        $validated = $handler->validated()->toArray();
        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function testFormHandlerFailure()
    {
        $request = $this->createRequest([
            'name'  => 'Al',
            'email' => 'invalid-email',
        ]);

        $handler = new TestFormHandler();
        $this->assertFalse($handler->validate($request));
        $this->assertNotEmpty($handler->getErrors());
        $this->assertArrayHasKey('name', $handler->getErrors());
        $this->assertArrayHasKey('email', $handler->getErrors());
    }

    public function testFormHelperAndLastInstance()
    {
        $handler = new TestFormHandler();
        FormHandler::setLastInstance($handler);

        $this->assertSame($handler, form());
        $this->assertInstanceOf(TestFormHandler::class, form(TestFormHandler::class));
    }

    public function testValidateAttributeRunsSuccess()
    {
        $request = $this->createRequest([
            'name'  => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $attribute = new Validate(TestFormHandler::class);
        $response = $attribute->before($request);

        $this->assertNull($response);
        $this->assertInstanceOf(TestFormHandler::class, form());
        $this->assertSame('Alice', form()->validated()->any('name'));
    }

    public function testValidateAttributeRunsFailureJson()
    {
        $request = $this->createRequest([
            'name'  => 'Al',
        ], [
            'Accept' => 'application/json',
        ]);

        $attribute = new Validate(TestFormHandler::class);
        $response = $attribute->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertSame('error', $body['status']);
        $this->assertNotEmpty($body['errors']);
    }

    public function testValidateAttributeThrowsOnInvalidHandler()
    {
        $request = $this->createRequest();
        $attribute = new Validate(InvalidFormHandler::class);

        $this->expectException(\RuntimeException::class);
        $attribute->before($request);
    }

    public function testFormHandlerDeobfuscatesValues()
    {
        // Setup sqids hash
        $hash = sqids_hash(12345);

        $request = $this->createRequest([
            'user_id' => $hash,
        ]);

        $handler = new ObfuscatedFormHandler();
        $this->assertTrue($handler->validate($request));
        $this->assertSame(12345, $handler->validated()->any('user_id'));
    }

    public function testValidateAttributeDeobfuscatesRouterParams()
    {
        $hash = sqids_hash(9999);

        $router = Services::router();
        $ref = new \ReflectionClass($router);
        $prop = $ref->getProperty('params');
        $prop->setAccessible(true);
        $prop->setValue($router, [0 => $hash]);

        $request = $this->createRequest();

        $attribute = new Validate(ObfuscatedFormHandler::class);
        $response = $attribute->before($request);

        $this->assertNull($response);
        $this->assertSame(9999, form()->validated()->any('user_id'));
        $this->assertSame(9999, $router->params()[0]);
    }

    public function testFormHandlerGroupsDataBySource()
    {
        // Set distinct request globals
        $_GET = ['id' => 'get-value'];
        $_POST = ['id' => 'post-value'];

        $router = Services::router();
        $ref = new \ReflectionClass($router);
        $prop = $ref->getProperty('params');
        $prop->setAccessible(true);
        $prop->setValue($router, [0 => 'router-value']);

        $request = $this->createRequest();
        $request->setGlobal('get', ['id' => 'get-value']);
        $request->setGlobal('post', ['id' => 'post-value']);

        // Define inline handler
        $handler = new class extends FormHandler {
            protected array $rules = ['id' => 'required'];
            protected array $routeParams = ['id' => 0];
        };

        $this->assertTrue($handler->validate($request));

        $validated = $handler->validated();
        $this->assertSame('get-value', $validated->get('id'));
        $this->assertSame('post-value', $validated->post('id'));
        $this->assertSame('router-value', $validated->router('id'));
        $this->assertSame('router-value', $validated->any('id'));
    }
}
