<?php

declare(strict_types=1);

namespace Jengo\Base\Inertia;

use CodeIgniter\HTTP\RequestInterface as CI4RequestInterface;
use Inertia\Protocol\Contracts\RequestInterface as ProtocolRequestInterface;

class CI4RequestAdapter implements ProtocolRequestInterface
{
    public function __construct(protected CI4RequestInterface $request)
    {
    }

    public function getHeaderLine(string $name): string
    {
        return (string) $this->request->getHeaderLine($name);
    }

    public function hasHeader(string $name): bool
    {
        return $this->request->hasHeader($name) && $this->request->getHeaderLine($name) !== '';
    }

    public function getMethod(): string
    {
        return strtoupper((string) $this->request->getMethod());
    }

    public function getUrl(): string
    {
        $uri = $this->request->getUri();
        $path = '/' . ltrim($uri->getPath(), '/');
        $query = $uri->getQuery();
        $fragment = $uri->getFragment();

        return $path . ($query ? '?' . $query : '') . ($fragment ? '#' . $fragment : '');
    }

    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->request->getGet($name) ?? $default;
    }

    public function getCi4Request(): CI4RequestInterface
    {
        return $this->request;
    }
}
