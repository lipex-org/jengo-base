<?php

declare(strict_types=1);

namespace Jengo\Base\Validation\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Base\Contracts\ResponseModifierInterface;

class UniversalModifier implements ResponseModifierInterface
{
    /**
     * @param ResponseModifierInterface|class-string<ResponseModifierInterface>|null $inertiaModifier
     * @param ResponseModifierInterface|class-string<ResponseModifierInterface>|null $jsonModifier
     * @param ResponseModifierInterface|class-string<ResponseModifierInterface>|null $standardModifier
     */
    public function __construct(
        protected ResponseModifierInterface|string|null $inertiaModifier = null,
        protected ResponseModifierInterface|string|null $jsonModifier = null,
        protected ResponseModifierInterface|string|null $standardModifier = null,
    ) {
    }

    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        $modifier = $this->resolveModifier($request);

        return $modifier->modifyValidationFailed($errors, $request, $options);
    }

    public function resolveModifier(RequestInterface $request): ResponseModifierInterface
    {
        // 1. Inertia SPA transition (X-Inertia header present)
        if ($this->isInertiaRequest($request)) {
            return $this->getInertiaModifier();
        }

        // 2. JSON / API / AJAX request
        if ($this->isJsonRequest($request)) {
            return $this->getJsonModifier();
        }

        // 3. Fallback to standard web view redirect
        return $this->getStandardModifier();
    }

    protected function isInertiaRequest(RequestInterface $request): bool
    {
        if (!$request->hasHeader('X-Inertia')) {
            return false;
        }

        $line = trim((string) $request->getHeaderLine('X-Inertia'));

        return $line !== '' && strtolower($line) !== 'false';
    }

    protected function isJsonRequest(RequestInterface $request): bool
    {
        if (method_exists($request, 'isAJAX') && $request->isAJAX()) {
            return true;
        }

        $accept = (string) $request->getHeaderLine('Accept');
        $contentType = (string) $request->getHeaderLine('Content-Type');

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }

    public function getInertiaModifier(): ResponseModifierInterface
    {
        if ($this->inertiaModifier instanceof ResponseModifierInterface) {
            return $this->inertiaModifier;
        }

        $class = $this->inertiaModifier ?? InertiaModifier::class;

        return $this->inertiaModifier = new $class();
    }

    public function getJsonModifier(): ResponseModifierInterface
    {
        if ($this->jsonModifier instanceof ResponseModifierInterface) {
            return $this->jsonModifier;
        }

        $class = $this->jsonModifier ?? JsonModifier::class;

        return $this->jsonModifier = new $class();
    }

    public function getStandardModifier(): ResponseModifierInterface
    {
        if ($this->standardModifier instanceof ResponseModifierInterface) {
            return $this->standardModifier;
        }

        $class = $this->standardModifier ?? StandardViewModifier::class;

        return $this->standardModifier = new $class();
    }
}
