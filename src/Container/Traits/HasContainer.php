<?php

declare(strict_types=1);

namespace Jengo\Base\Container\Traits;

use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Services;
use Jengo\Base\Container\Container;
use Jengo\Base\Container\ContainerInterface;

trait HasContainer
{
    /**
     * Get the container instance.
     */
    protected function app(?string $abstract = null, array $parameters = []): mixed
    {
        $container = Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract, $parameters);
    }

    /**
     * Resolve an instance from the container.
     */
    protected function make(string $abstract, array $parameters = []): mixed
    {
        return Container::getInstance()->make($abstract, $parameters);
    }

    /**
     * Call a method / callable with auto-wired dependencies.
     */
    protected function call(callable|array|string $callable, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        return Container::getInstance()->call($callable, $parameters, $defaultMethod);
    }

    /**
     * Intercept CodeIgniter 4 controller method execution to provide full
     * Dependency Injection for route actions, parameters, and form requests.
     *
     * @param string $method
     * @param mixed ...$params
     * @return mixed
     *
     * @throws PageNotFoundException
     */
    public function _remap(string $method, ...$params): mixed
    {
        if (! method_exists($this, $method) || ($method[0] === '_' && $method !== '__invoke')) {
            throw PageNotFoundException::forMethodNotFound($method);
        }

        $this->ensureControllerInitialized();

        return Container::getInstance()->call([$this, $method], $params);
    }

    /**
     * Ensure controller properties (request, response, logger) are populated
     * even when constructed directly via DI Container without initController().
     */
    protected function ensureControllerInitialized(): void
    {
        if (property_exists($this, 'request') && (! isset($this->request) || $this->request === null)) {
            $this->request = Services::request();
        }

        if (property_exists($this, 'response') && (! isset($this->response) || $this->response === null)) {
            $this->response = Services::response();
        }

        if (property_exists($this, 'logger') && (! isset($this->logger) || $this->logger === null)) {
            $this->logger = Services::logger();
        }
    }
}
