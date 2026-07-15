<?php

declare(strict_types=1);

namespace Jengo\Base\Attributes;

use Attribute;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\Attributes\RouteAttributeInterface;
use Jengo\Base\Validation\FormHandler;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Validate implements RouteAttributeInterface
{
    /**
     * @param class-string<FormHandler> $handlerClass
     */
    public function __construct(
        private readonly string $handlerClass
    ) {
    }

    /**
     * Called before the controller method executes.
     */
    public function before(RequestInterface $request): RequestInterface|ResponseInterface|null
    {
        if (! is_subclass_of($this->handlerClass, FormHandler::class)) {
            throw new \RuntimeException(sprintf(
                'The validation handler [%s] must extend [%s].',
                $this->handlerClass,
                FormHandler::class
            ));
        }

        /** @var FormHandler $handler */
        $handler = new $this->handlerClass();

        if (! $handler->validate($request)) {
            return $handler->redirectOrJson($handler->getErrors(), $request);
        }

        FormHandler::setLastInstance($handler);

        // Deobfuscate router parameters so the controller receives the real integer IDs directly
        $routeParams = $handler->getRouteParams();
        $obfuscatedFields = $handler->getObfuscatedFields();
        if (!empty($routeParams) && !empty($obfuscatedFields)) {
            $router = \Config\Services::router();
            $params = $router->params();
            $updated = false;

            foreach ($routeParams as $key => $index) {
                if (in_array($key, $obfuscatedFields, true) && isset($params[$index])) {
                    $validatedData = $handler->validated();
                    $val = $validatedData->any($key);
                    if ($val !== null) {
                        $params[$index] = $val;
                        $updated = true;
                    }
                }
            }

            if ($updated) {
                $ref = new \ReflectionClass($router);
                if ($ref->hasProperty('params')) {
                    $prop = $ref->getProperty('params');
                    $prop->setAccessible(true);
                    $prop->setValue($router, $params);
                }
            }
        }

        return null;
    }

    /**
     * Called after the controller method executes.
     */
    public function after(RequestInterface $request, ResponseInterface $response): ?ResponseInterface
    {
        return $response;
    }
}
