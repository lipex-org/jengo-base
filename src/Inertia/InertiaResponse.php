<?php

declare(strict_types=1);

namespace Jengo\Base\Inertia;

use CodeIgniter\HTTP\RequestInterface as CI4RequestInterface;
use CodeIgniter\HTTP\ResponsableInterface;
use CodeIgniter\HTTP\ResponseInterface as CI4ResponseInterface;
use CodeIgniter\View\View;
use Config\Services;
use Config\View as ConfigView;
use Inertia\Protocol\InertiaResponse as ProtocolInertiaResponse;

class InertiaResponse implements ResponsableInterface
{
    public function __construct(
        protected ProtocolInertiaResponse $protocolResponse,
        protected ?CI4RequestInterface $request = null
    ) {
    }

    public function with(array|string $key, mixed $value = null): self
    {
        $this->protocolResponse->with($key, $value);

        return $this;
    }

    public function withVersion(mixed $version): self
    {
        $this->protocolResponse->withVersion($version);

        return $this;
    }

    public function encryptHistory(bool $encrypt = true): self
    {
        $this->protocolResponse->encryptHistory($encrypt);

        return $this;
    }

    public function clearHistory(bool $clear = true): self
    {
        $this->protocolResponse->clearHistory($clear);

        return $this;
    }

    public function preserveFragment(bool $preserve = true): self
    {
        $this->protocolResponse->preserveFragment($preserve);

        return $this;
    }

    public function withFlash(array $flash): self
    {
        $this->protocolResponse->withFlash($flash);

        return $this;
    }

    public function getProtocolResponse(): ProtocolInertiaResponse
    {
        return $this->protocolResponse;
    }

    public function toResponse(?CI4RequestInterface $request = null): CI4ResponseInterface|View
    {
        $req = $request ?? $this->request ?? Services::request();
        $adapter = new CI4RequestAdapter($req);

        $decision = $this->protocolResponse->toDecision($adapter);
        $response = Services::response();

        // 1. Version Mismatch or Location / Fragment Conflict (409)
        if ($decision->isConflict()) {
            foreach ($decision->headers as $name => $val) {
                $response->setHeader($name, $val);
            }

            return $response->setStatusCode($decision->statusCode)->setBody((string) $decision->content);
        }

        // 2. Precognition Success (204)
        if ($decision->isPrecognition()) {
            foreach ($decision->headers as $name => $val) {
                $response->setHeader($name, $val);
            }

            return $response->setStatusCode(204)->setBody('');
        }

        // 3. Inertia JSON response (200)
        if ($decision->isJson()) {
            foreach ($decision->headers as $name => $val) {
                $response->setHeader($name, $val);
            }

            return $response->setStatusCode(200)->setJSON($decision->pageObject->toArray());
        }

        // 4. Full HTML View response (200)
        $config = config('Jengo') ?? new \Jengo\Base\Config\Jengo();
        $inertiaConfig = config('Inertia');
        $rootView = $inertiaConfig->rootView ?? $config->inertia['rootView'] ?? 'app';

        $viewData = ['page' => $decision->pageObject->toArray()];

        if (function_exists('view')) {
            try {
                $rendered = view($rootView, $viewData);
                return $response->setStatusCode(200)->setBody($rendered)->setHeader('Content-Type', 'text/html; charset=UTF-8');
            } catch (\Throwable $e) {
                // Fallback View instance
            }
        }

        $view = new View(new ConfigView(), '');
        $view->setData($viewData, 'raw');

        return $view;
    }

    public function getResponse(): CI4ResponseInterface
    {
        $res = $this->toResponse();
        if ($res instanceof View) {
            $config = config('Jengo') ?? new \Jengo\Base\Config\Jengo();
            $inertiaConfig = config('Inertia');
            $rootView = $inertiaConfig->rootView ?? $config->inertia['rootView'] ?? 'app';
            return Services::response()->setBody(view($rootView, $res->getData()))->setHeader('Content-Type', 'text/html; charset=UTF-8');
        }

        return $res;
    }
}
