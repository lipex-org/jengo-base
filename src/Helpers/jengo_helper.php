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
     * Retrieve the last validated FormHandler instance, or resolve it from the caller's #[Validate] attribute.
     *
     * @template T of FormHandler
     * @param class-string<T>|null $handlerClass
     * @return T|FormHandler|null
     */
    function form(?string $handlerClass = null): ?FormHandler
    {
        $last = FormHandler::getLastInstance();

        if ($handlerClass !== null) {
            if ($last instanceof $handlerClass) {
                return $last;
            }

            if (class_exists($handlerClass)) {
                /** @var FormHandler $handler */
                $handler = new $handlerClass();
                $handler->validate();
                FormHandler::setLastInstance($handler);

                return $handler;
            }
        }

        // 2. Check caller's #[Validate] attribute to ensure $last matches the caller method's expected handler
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($trace as $frame) {
            if (!empty($frame['class']) && !empty($frame['function']) && method_exists($frame['class'], $frame['function'])) {
                $refMethod = new \ReflectionMethod($frame['class'], $frame['function']);
                $attributes = $refMethod->getAttributes(\Jengo\Base\Attributes\Validate::class);
                if (!empty($attributes)) {
                    $args = $attributes[0]->getArguments();
                    $targetClass = $args[0] ?? $args['handlerClass'] ?? null;
                    if ($targetClass && class_exists($targetClass)) {
                        if ($last instanceof $targetClass) {
                            return $last;
                        }

                        /** @var FormHandler $handler */
                        $handler = new $targetClass();
                        $handler->validate();
                        FormHandler::setLastInstance($handler);

                        return $handler;
                    }
                }
            }
        }

        return $last;
    }
}

if (!function_exists('sqids_instance')) {
    /**
     * Returns the shared Sqids instance.
     * @return \Sqids\Sqids
     */
    function sqids_instance(): \Sqids\Sqids
    {
        static $instance = null;
        if ($instance === null) {
            $config = config('Sqids') ?? new \Jengo\Base\Config\Sqids();
            $instance = new \Sqids\Sqids($config->alphabet, $config->minLength);
        }
        return $instance;
    }
}

if (!function_exists('sqids_hash')) {
    /**
     * Obfuscates an integer ID using Sqids.
     * @param int|null $id
     * @return string|null
     */
    function sqids_hash(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }
        return sqids_instance()->encode([$id]);
    }
}

if (!function_exists('sqids_unhash')) {
    /**
     * Decodes a Sqids hash back to an integer ID.
     * @param string|null $hash
     * @return int|null
     */
    function sqids_unhash(?string $hash): ?int
    {
        if ($hash === null || $hash === '') {
            return null;
        }
        $decoded = sqids_instance()->decode($hash);
        return empty($decoded) ? null : (int) $decoded[0];
    }
}

if (!function_exists('response_handler')) {
    /**
     * Retrieve the shared ResponseHandler instance.
     */
    function response_handler(): \Jengo\Base\Support\ResponseHandler
    {
        return \Config\Services::responseHandler();
    }
}

if (!function_exists('inertia')) {
    /**
     * Creates an Inertia response instance.
     *
     * @param string $component JavaScript page component name
     * @param array<string, mixed> $props Page props
     * @return mixed
     */
    function inertia(string $component, array $props = [])
    {
        if (class_exists(\Jengo\Inertia\Inertia::class) && class_exists(\Jengo\Inertia\Config\Services::class)) {
            return \Jengo\Inertia\Inertia::render($component, $props);
        }

        return \Jengo\Base\Inertia\Inertia::render($component, $props);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     */
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if ($callback === null) {
            return new \Jengo\Base\Support\HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (!function_exists('rescue')) {
    /**
     * Catch a potential exception and return a default value.
     */
    function rescue(callable $callback, mixed $rescue = null, bool $report = true): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            if ($report) {
                try {
                    log_message('error', $e->getMessage(), ['exception' => $e]);
                } catch (\Throwable) {
                    // Ignore reporting failure
                }
            }

            return value($rescue, $e);
        }
    }
}

if (!function_exists('retry')) {
    /**
     * Retry an operation a given number of times.
     */
    function retry(int $times, callable $callback, int $sleepMilliseconds = 0, ?callable $when = null): mixed
    {
        $attempts = 0;
        $backoff = $sleepMilliseconds;

        while ($attempts < $times) {
            $attempts++;

            try {
                return $callback($attempts);
            } catch (\Throwable $e) {
                if ($attempts >= $times || ($when !== null && !$when($e))) {
                    throw $e;
                }

                if ($backoff > 0) {
                    usleep($backoff * 1000);
                }
            }
        }

        throw new \RuntimeException('Retry attempts exhausted.');
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     */
    function blank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof \Countable) {
            return count($value) === 0;
        }

        if ($value instanceof \Stringable) {
            return trim((string) $value) === '';
        }

        return empty($value);
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if a value is not "blank".
     */
    function filled(mixed $value): bool
    {
        return !blank($value);
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     */
    function data_get(mixed $target, string|array|int|null $key, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return $target;
        }

        $segments = is_array($key) ? $key : explode('.', (string) $key);

        if (empty($segments)) {
            return $target;
        }

        foreach ($segments as $i => $segment) {
            unset($segments[$i]);

            if ($segment === null) {
                return $target;
            }

            if ($segment === '*') {
                if (!is_iterable($target)) {
                    return value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = data_get($item, $segments);
                }

                return in_array('*', $segments, true) ? \Jengo\Base\Libraries\Arr::set($result)->collapse()->toArray() : $result;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target)) {
                if ($target instanceof \ArrayAccess && $target->offsetExists($segment)) {
                    $target = $target[$segment];
                } elseif (isset($target->{$segment})) {
                    $target = $target->{$segment};
                } elseif (property_exists($target, (string) $segment)) {
                    $target = $target->{$segment};
                } elseif (method_exists($target, (string) $segment)) {
                    $target = $target->{$segment}();
                } else {
                    return value($default);
                }
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('data_set')) {
    /**
     * Set an item on an array or object using dot notation.
     */
    function data_set(mixed &$target, string|array $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', (string) $key);

        if (empty($segments)) {
            return $target;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (!is_array($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$item) {
                    data_set($item, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$item) {
                    $item = $value;
                }
            }
        } elseif (is_array($target)) {
            if ($segments) {
                if (!array_key_exists($segment, $target) || !is_array($target[$segment])) {
                    $target[$segment] = [];
                }

                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !array_key_exists($segment, $target)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment}) || !is_object($target->{$segment})) {
                    $target->{$segment} = new \stdClass();
                }

                data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                $target[$segment] = [];
                data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }
}

if (!function_exists('head')) {
    /**
     * Get the first element of an array.
     */
    function head(array $array): mixed
    {
        return reset($array);
    }
}

if (!function_exists('last')) {
    /**
     * Get the last element of an array.
     */
    function last(array $array): mixed
    {
        return end($array);
    }
}

if (!function_exists('str')) {
    /**
     * Create a fluent String instance.
     */
    function str(?string $string = null): \Jengo\Base\Libraries\Str
    {
        return \Jengo\Base\Libraries\Str::set($string ?? '');
    }
}

if (!function_exists('arr')) {
    /**
     * Create a fluent Array instance.
     */
    function arr(array|\CodeIgniter\Entity\Entity $data = []): \Jengo\Base\Libraries\Arr
    {
        return \Jengo\Base\Libraries\Arr::set($data);
    }
}

if (!function_exists('now')) {
    /**
     * Create a new Time instance for the current date and time.
     */
    function now(?string $timezone = null): \CodeIgniter\I18n\Time
    {
        return \CodeIgniter\I18n\Time::now($timezone);
    }
}

if (!function_exists('today')) {
    /**
     * Create a new Time instance for the start of today.
     */
    function today(?string $timezone = null): \CodeIgniter\I18n\Time
    {
        return \CodeIgniter\I18n\Time::today($timezone);
    }
}

if (!function_exists('flash')) {
    /**
     * Retrieve or set session flash data.
     */
    function flash(?string $key = null, mixed $value = null): mixed
    {
        $session = \Config\Services::session();

        if ($key === null) {
            return $session;
        }

        if ($value !== null) {
            $session->setFlashdata($key, $value);
            return null;
        }

        return $session->getFlashdata($key);
    }
}

if (!function_exists('logger')) {
    /**
     * Log a message or retrieve the logger service instance.
     */
    function logger(?string $message = null, string $level = 'info', array $context = []): mixed
    {
        if ($message === null) {
            return \Config\Services::logger();
        }

        log_message($level, $message, $context);
        return null;
    }
}

if (!function_exists('info')) {
    /**
     * Write an informational log message.
     */
    function info(string $message, array $context = []): void
    {
        log_message('info', $message, $context);
    }
}

if (!function_exists('warning')) {
    /**
     * Write a warning log message.
     */
    function warning(string $message, array $context = []): void
    {
        log_message('warning', $message, $context);
    }
}

if (!function_exists('error')) {
    /**
     * Write an error log message.
     */
    function error(string $message, array $context = []): void
    {
        log_message('error', $message, $context);
    }
}

