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
use Jengo\Base\Modifiers\AutoDetectModifier;
use Jengo\Base\Modifiers\JsonModifier;
use Jengo\Base\Modifiers\StandardViewModifier;
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

final class ResponseHandlerTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('Jengo\Base\Helpers\jengo');
    }

    private function createRequest(array $headers = []): IncomingRequest
    {
        $config = new App();
        $uri = new URI('http://example.com/test');
        $userAgent = new UserAgent();
        $request = new IncomingRequest($config, $uri, 'php://input', $userAgent);

        foreach ($headers as $key => $val) {
            $request->setHeader($key, $val);
        }

        return $request;
    }

    public function testDefaultModifierIsAutoDetect(): void
    {
        $handler = new ResponseHandler();
        $modifier = $handler->getModifier();

        $this->assertInstanceOf(AutoDetectModifier::class, $modifier);
    }

    public function testSetModifierAtRuntime(): void
    {
        $handler = new ResponseHandler();
        $handler->setModifier(new JsonModifier());

        $this->assertInstanceOf(JsonModifier::class, $handler->getModifier());
    }

    public function testAutoDetectModifierReturnsJsonForAjaxOrJsonHeaders(): void
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
}
