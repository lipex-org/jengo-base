<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Base\Facades\ModelFacade;
use Jengo\Base\Exceptions\InterruptExecutionException;
use Jengo\Base\Events\AbstractEvent;
use Jengo\Base\Validation\FormHandler;

if (!function_exists('model_of')) {
    /**
     * Returns the model facade instance of the model provided
     * @param class-string $model Valid model class name
     * @return ModelFacade
     */
    function model_of(string $model): ModelFacade
    {
        return new ModelFacade($model);
    }
}

if (!function_exists('interrupt_response')) {
    /**
     * Stops execution of program and sneds the response given to the client(CLI or Browser)
     * @param ResponseInterface $response
     * @throws InterruptExecutionException
     * @return never
     */
    function interrupt_response(ResponseInterface $response): void
    {
        throw new InterruptExecutionException($response);
    }
}

if (!function_exists('register_events')) {
    /**
     * Registers one or more event classes with the CodeIgniter Events system.
     * The event class must be a string and extend AbstractEvent.
     * @param class-string[] $events
     * @throws InvalidArgumentException
     * @return void
     */
    function register_events(...$events): void
    {
        foreach ($events as $event) {
            if (!is_string($event)) {
                throw new InvalidArgumentException("Event must be a class name string");
            }

            if (!class_exists($event)) {
                throw new InvalidArgumentException("Event must be a valid class");
            }

            // 1. Check inheritance by creating an instance (Required for instanceof)
            $instance = new $event();

            if (!$instance instanceof AbstractEvent) {
                throw new InvalidArgumentException("Event class must extend AbstractEvent");
            }

            Events::on($event::NAME, [$event, 'event']);
        }
    }
}


if (!function_exists('trigger_event')) {
    /**
     * Triggers an event implemented using jengo base
     * @param string $event
     * @param array $arguments
     * @throws InvalidArgumentException
     * @return void
     */
    function trigger_event(string $event, ...$arguments): void
    {
        $instance = new $event();

        if (!$instance instanceof AbstractEvent) {
            throw new InvalidArgumentException("Event class must extend AbstractEvent");
        }

        Events::trigger($event::NAME, ...$arguments);
    }
}

if (!function_exists('controller_url')) {
    /**
     * Produces a string based on the controller and method provided
     * @param string $controller
     * @param string $method
     * @param int|string[] $args
     * @return string
     */
    function controller_url(string $controller, string $method, int|string ...$args)
    {
        return url_to("\\$controller::$method", ...$args);
    }
}

if (!function_exists('page')) {
    function page(string $name, array $data = [], array $options = [])
    {
        return view("pages/$name.page.php", $data, $options);
    }
}

if (!function_exists('isProduction')) {
    /**
     * Checks if ENVIRONMENT is production
     * @return bool
     */
    function isProduction(): bool
    {
        return ENVIRONMENT === 'production';
    }
}

if (!function_exists('isDevelopment')) {
    /**
     * Checks if ENVIRONMENT is development
     * @return bool
     */
    function isDevelopment(): bool
    {
        return ENVIRONMENT === 'development';
    }
}

if (!function_exists('isStaging')) {
    /**
     * Checks if ENVIRONMENT is in staging
     * @return bool
     */
    function isStaging(): bool
    {
        return ENVIRONMENT === 'staging';
    }
}

if (!function_exists('isTesting')) {
    /**
     * Checks if ENVIRONMENT is in testing
     * @return bool
     */
    function isTesting(): bool
    {
        return ENVIRONMENT === 'testing';
    }
}

if (!function_exists('form')) {
    /**
     * Retrieve the last validated FormHandler instance, or a new instance of a specific class.
     *
     * @template T of FormHandler
     * @param class-string<T>|null $handlerClass
     * @return T|FormHandler|null
     */
    function form(?string $handlerClass = null): ?FormHandler
    {
        if ($handlerClass === null) {
            return FormHandler::getLastInstance();
        }

        return new $handlerClass();
    }
}