<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\View\View;
use Config\App;
use Config\Services;
use Jengo\Base\Inertia\CI4RequestAdapter;
use Jengo\Base\Inertia\Inertia;
use Jengo\Base\Inertia\InertiaResponse;

final class InertiaBridgeTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('Jengo\Base\Helpers\jengo');
        Inertia::flushShared();
        Inertia::version('');
    }

    private function createRequest(string $method = 'GET', string $url = 'http://example.com/dashboard', array $headers = []): IncomingRequest
    {
        $config = new App();
        $uri = new URI($url);
        $userAgent = new UserAgent();
        $request = new IncomingRequest($config, $uri, 'php://input', $userAgent);
        $request->setMethod($method);

        foreach ($headers as $key => $val) {
            $request->setHeader($key, $val);
        }

        return $request;
    }

    public function testInertiaRenderReturnsJsonResponseOnInertiaRequest(): void
    {
        Inertia::version('v1.0');
        $request = $this->createRequest('GET', 'http://example.com/dashboard', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'v1.0',
        ]);

        $response = Inertia::render('Dashboard/Home', ['user' => 'Alice'])->toResponse($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Inertia'));
        $this->assertSame('true', $response->getHeaderLine('X-Inertia'));
        $this->assertSame('X-Inertia', $response->getHeaderLine('Vary'));

        $data = json_decode($response->getBody(), true);
        $this->assertSame('Dashboard/Home', $data['component']);
        $this->assertSame('Alice', $data['props']['user']);
        $this->assertArrayHasKey('errors', $data['props']);
        $this->assertSame('/dashboard', $data['url']);
        $this->assertSame('v1.0', $data['version']);
    }

    public function testInertiaRenderReturnsHtmlViewOnStandardRequest(): void
    {
        $request = $this->createRequest('GET', 'http://example.com/welcome');

        $response = Inertia::render('Welcome', ['title' => 'Hello World'])->toResponse($request);

        $this->assertTrue($response instanceof ResponseInterface || $response instanceof View);
    }

    public function testInertiaVersionMismatchReturns409Conflict(): void
    {
        Inertia::version('v2.0');
        $request = $this->createRequest('GET', 'http://example.com/dashboard', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'v1.0',
        ]);

        $response = Inertia::render('Dashboard/Home')->toResponse($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Inertia-Location'));
        $this->assertSame('/dashboard', $response->getHeaderLine('X-Inertia-Location'));
    }

    public function testInertiaLocationRedirectReturns409Conflict(): void
    {
        $response = Inertia::location('https://billing.example.com/checkout');

        $this->assertSame(409, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Inertia-Location'));
        $this->assertSame('https://billing.example.com/checkout', $response->getHeaderLine('X-Inertia-Location'));
    }

    public function testInertiaRedirectWithFragmentReturns409Conflict(): void
    {
        $response = Inertia::redirectWithFragment('http://example.com/faq#billing');

        $this->assertSame(409, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Inertia-Redirect'));
        $this->assertSame('http://example.com/faq#billing', $response->getHeaderLine('X-Inertia-Redirect'));
    }

    public function testInertiaPrecognitionSuccessReturns204(): void
    {
        $response = Inertia::precognitionSuccess();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Precognition-Success'));
        $this->assertSame('true', $response->getHeaderLine('Precognition-Success'));
    }

    public function testInertiaGlobalHelperFunction(): void
    {
        $res = inertia('Auth/Login', ['appName' => 'Jengo']);

        $this->assertInstanceOf(InertiaResponse::class, $res);

        $request = $this->createRequest('GET', 'http://example.com/login', [
            'X-Inertia' => 'true',
        ]);

        $ci4Response = $res->toResponse($request);
        $this->assertInstanceOf(ResponseInterface::class, $ci4Response);
        $this->assertSame(200, $ci4Response->getStatusCode());
        $data = json_decode($ci4Response->getBody(), true);
        $this->assertSame('Auth/Login', $data['component']);
        $this->assertSame('Jengo', $data['props']['appName']);
    }

    public function testPartialReloadAndLazyProps(): void
    {
        $request = $this->createRequest('GET', 'http://example.com/users', [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Users/Index',
            'X-Inertia-Partial-Data' => 'users',
        ]);

        $evaluatedLazy = false;

        $response = Inertia::render('Users/Index', [
            'users' => ['Alice', 'Bob'],
            'stats' => Inertia::lazy(function () use (&$evaluatedLazy) {
                $evaluatedLazy = true;
                return ['count' => 2];
            }),
        ])->toResponse($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertFalse($evaluatedLazy);
        $data = json_decode($response->getBody(), true);
        $this->assertSame(['Alice', 'Bob'], $data['props']['users']);
    }

    public function testDeferAndMergeProps(): void
    {
        $request = $this->createRequest('GET', 'http://example.com/posts', [
            'X-Inertia' => 'true',
        ]);

        $response = Inertia::render('Posts/Index', [
            'posts' => Inertia::merge(['Post 1', 'Post 2']),
            'comments' => Inertia::defer(fn() => ['Comment 1'], 'comments-group'),
        ])->toResponse($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $data = json_decode($response->getBody(), true);
        $this->assertSame(['Post 1', 'Post 2'], $data['props']['posts']);
        $this->assertArrayNotHasKey('comments', $data['props']);
        $this->assertContains('comments', $data['deferredProps']['comments-group']);
        $this->assertSame(['posts'], $data['mergeProps']);
    }
}
