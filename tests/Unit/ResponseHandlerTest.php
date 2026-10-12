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
use Jengo\Base\Contracts\ResponseModifierInterface;
use Jengo\Base\Validation\Modifiers\UniversalModifier;
use Jengo\Base\Validation\Modifiers\JsonModifier;
use Jengo\Base\Validation\Modifiers\StandardViewModifier;
use Jengo\Base\Validation\Modifiers\InertiaModifier;
use Jengo\Base\Support\ResponseHandler;
use Jengo\Base\Validation\FormHandler;

class CustomApiModifier implements ResponseModifierInterface
{
    public function modifyValidationFailed(array $errors, \CodeIgniter\HTTP\RequestInterface $request, array $options = []): ResponseInterface
    {
        return Services::response()
            ->setStatusCode(422)
            ->setJSON([
                'success' => false,
                'api_errors' => $errors,
                'form' => $options['handler'] ?? null,
            ]);
    }
}

if (!class_exists(TestFormHandler::class, false)) {
    class TestFormHandler extends FormHandler
    {
        protected array $rules = [
            'name' => 'required|min_length[3]',
            'email' => 'required|valid_email',
        ];

        protected array $messages = [
            'name' => [
                'required' => 'The name is required.',
            ],
        ];
    }
}

final class ResponseHandlerTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('Jengo\Base\Helpers\jengo');
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    private function createRequest(array $headers = []): IncomingRequest
    {
        $config = new App();
        $uri = Services::uri();
        $userAgent = new UserAgent();
        $request = new IncomingRequest($config, $uri, 'php://input', $userAgent);

        foreach ($headers as $key => $val) {
            $request->setHeader($key, $val);
        }

        return $request;
    }

    public function testDefaultModifierIsUniversalModifier(): void
    {
        $handler = new ResponseHandler();
        $modifier = $handler->getModifier();

        $this->assertInstanceOf(UniversalModifier::class, $modifier);
    }

    public function testSetModifierAtRuntime(): void
    {
        $handler = new ResponseHandler();
        $handler->setModifier(new JsonModifier());

        $this->assertInstanceOf(JsonModifier::class, $handler->getModifier());
    }

    public function testUniversalModifierReturnsJsonForAjaxOrJsonHeaders(): void
    {
        $request = $this->createRequest(['Accept' => 'application/json']);
        $handler = new ResponseHandler();

        $response = $handler->validationFailed(['field' => 'Required'], $request);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('error', $body['status']);
        $this->assertSame(['field' => 'Required'], $body['errors']);
    }

    public function testFormHandlerCustomModifierApplied(): void
    {
        $request = $this->createRequest(); // Standard web request without JSON headers

        $form = new class ($request) extends FormHandler {
            protected array $rules = ['email' => 'required|valid_email'];
            protected ?string $modifier = CustomApiModifier::class;
        };

        $this->assertFalse($form->validate());
        $response = $form->redirectOrJson($form->getErrors(), $request);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('email', $body['api_errors']);
    }

    public function testValidateAttributeAppliesEndpointModifierOverride(): void
    {
        $request = $this->createRequest(); // Standard web request without JSON headers

        // Route specifies CustomApiModifier even though request didn't send JSON headers
        $attribute = new Validate(TestFormHandler::class, CustomApiModifier::class);
        $response = $attribute->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(422, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('name', $body['api_errors']);
    }

    public function testInertiaModifierThrowsWhenJengoInertiaNotInstalled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The jengo/inertia package is required to use InertiaModifier.');

        $request = $this->createRequest();
        $modifier = new InertiaModifier();
        $modifier->modifyValidationFailed(['email' => 'Invalid email'], $request);
    }

    public function testUniversalModifierDetectsInertiaHeaderAndDelegates(): void
    {
        $request = $this->createRequest(['X-Inertia' => 'true']);
        $handler = new ResponseHandler();

        // InertiaModifier will be selected and will throw because jengo/inertia is not in base's composer dependencies
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The jengo/inertia package is required to use InertiaModifier.');

        $handler->validationFailed(['name' => 'Required'], $request);
    }

    public function testConfigValidationResponseModifierRespected(): void
    {
        $config = config('Jengo');
        $originalModifier = $config->validationResponseModifier;
        $config->validationResponseModifier = CustomApiModifier::class;

        $request = $this->createRequest();
        $handler = new ResponseHandler();

        $this->assertInstanceOf(CustomApiModifier::class, $handler->getModifier());

        $config->validationResponseModifier = $originalModifier;
    }

    public function testRequestValidateThrowsInterruptExecutionException(): void
    {
        $this->expectException(\Jengo\Base\Exceptions\InterruptExecutionException::class);

        \Jengo\Base\Facades\Request::validate([
            'required_field' => 'required',
        ], true);
    }
}
