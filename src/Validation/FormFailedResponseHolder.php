<?php

declare(strict_types=1);

namespace Jengo\Base\Validation;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class FormFailedResponseHolder
{
    private ?ResponseInterface $response = null;

    public function __construct(
        private readonly array $errors,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Get the validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the current request.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Set the overridden response.
     *
     * @deprecated Use response modifiers via #[Validate(..., modifier: ...)], FormHandler::$modifier, or Config\Jengo::$validationResponseModifier instead.
     */
    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;
    }

    /**
     * Get the overridden response, if any.
     *
     * @deprecated Use response modifiers instead of mutating holder response.
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
