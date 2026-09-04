<?php

declare(strict_types=1);

namespace Jengo\Base\Contracts;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

interface ResponseModifierInterface
{
    /**
     * Format a validation failure response.
     *
     * @param array<string, string> $errors Validation error messages
     * @param RequestInterface $request Incoming HTTP request
     * @param array<string, mixed> $options Optional context (e.g. form handler class, action name)
     */
    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface;
}
