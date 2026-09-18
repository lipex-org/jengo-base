<?php

namespace Jengo\Base\Facades;

use CodeIgniter\HTTP\Response;
use Jengo\Base\Exceptions\InterruptExecutionException;

/**
 * Class Request
 *
 * A static proxy for CodeIgniter's IncomingRequest instance, allowing intuitive
 * static access to HTTP request data and metadata using method overloading.
 *
 * This class wraps the global `request()` helper and provides access to all common
 * HTTP input methods such as `getPost()`, `getGet()`, `getJSON()`, `getCookie()`, etc.
 * It simplifies calling request methods in a static context while preserving full
 * IDE autocompletion via PHPDoc annotations.
 *
 * Example:
 *     Request::getPost('email');
 *     Request::getJSON(true);
 *     Request::getHeaderLine('Content-Type');
 *
 * It also includes a static `validate()` method that integrates CodeIgniter's
 * validation service with auto-detection of input sources (JSON or POST),
 * and returns either a boolean or a RedirectResponse with error flashdata.
 *
 * @package Jengo\Facades
 * 
 * @method static mixed getGet(string|null $index = null, int|null $filter = null, $flags = null)
 * @method static mixed getPost(string|null $index = null, int|null $filter = null, $flags = null)
 * @method static mixed getGetPost($index = null, $filter = null, $flags = null)
 * @method static mixed getPostGet($index = null, $filter = null, $flags = null)
 * @method static mixed getVar(string|null $index = null, int|null $filter = null, $flags = null)
 * @method static array getRawInput()
 * @method static array|bool|float|int|stdClass|null getJSON(bool $assoc = false, int $depth = 512, int $options = 0)
 * @method static mixed getJsonVar($index = null, bool $assoc = false, ?int $filter = null, $flags = null)
 * @method static string|null getServer(string $index)
 * @method static string|null getHeaderLine(string $name)
 * @method static string getUserAgent()
 * @method static string getIPAddress()
 * @method static bool isAJAX()
 * @method static bool isSecure()
 * @method static string getMethod(bool $upper = false)
 * @method static bool is(string $type)
 * @method static \CodeIgniter\HTTP\URI getUri()
 * @method static string getBody()
 * @method static string getLocale()
 * @method static string getPath()
 * @method static bool isCLI()
 * @method static string negotiate(string $type, array $supported, bool $strictMatch = false)
 * @method static string setPath(string $path, \Config\App|null $config = null)
 * @method static string getDefaultLocale()
 * @method static \Codeigniter\HTTP\IncomingRequest setValidLocales(array $locales)
 * @method static \Codeigniter\HTTP\IncomingRequest setLocale(string $locale)
 * @method static mixed getRawInputVar($index = null, ?int $filter = null, $flags = null)
 * @method static array|bool|float|int|object|string|null getCookie($index = null, $filter = null, $flags = null)
 * @method static \CodeIgniter\HTTP\Files\UploadedFile|null getFile(string $fileID)
 * @method static array|null getFileMultiple(string $fileID)
 * @method static array getFiles()
 * @method static array|string|null getOldInput(string $key)
 */

class Request
{
    public static function __callStatic($method, $args)
    {
        return request()->$method(...$args);
    }

    /**
     * Validates incoming request data against the provided validation rules.
     *
     * Automatically pulls JSON or POST data depending on the request content type.
     * If validation fails, throws an InterruptExecutionException with a redirect
     * response back to the previous page, attaching validation errors to the session.
     *
     * @param array $rules Validation rules in the format accepted by CodeIgniter's validator.
     * @param bool $redirect Flag whether to perform a redirect after validation fails.
     *
     * @throws \Jengo\Base\Exceptions\InterruptExecutionException If validation fails and redirect is permitted.
     *
     * @return bool|array
     *         Returns true if validation passes.
     *         If an InterruptExecutionException is thrown, execution is halted.
     *         If validation fails, returns an array of validation errors depeding on redirect flag.
     */
    public static function validate(array $rules, bool $redirect = true): bool|array
    {
        /** @var \CodeIgniter\Validation\ValidationInterface */
        $validator = service("validation");
        $request = request();

        $validator->setRules($rules);

        $data = match (true) {
            !empty($request->getJSON(true)) => $request->getJSON(true),
            !empty($request->getPost()) => $request->getPost(),
            default => [],
        };

        $success = $validator->run($data);

        if (!$success && $redirect) {
            throw new InterruptExecutionException(redirect()
                ->back()
                ->with("errors", $validator->getErrors()));
        } else if (!$success) {
            return $validator->getErrors();
        }

        return $success;
    }

    /**
     * Retrieves all input values or a single input value from the current request.
     *
     * @param string|null $key The input key to retrieve.
     * @param mixed $default Fallback value if key is not found.
     * @return mixed The value associated with the input key, or all inputs.
     */
    public static function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return static::all();
        }

        $val = request()->getVar($key);

        if ($val !== null) {
            return $val;
        }

        return data_get(static::all(), $key, $default);
    }

    /**
     * Get all of the input and files for the request.
     */
    public static function all(): array
    {
        $request = request();
        $json = $request->getJSON(true);

        if (is_array($json) && !empty($json)) {
            return array_merge($request->getGet(), $json);
        }

        return array_merge($request->getGet(), $request->getPost());
    }

    /**
     * Retrieve input as a boolean value.
     */
    public static function boolean(string $key, bool $default = false): bool
    {
        $val = static::input($key);

        if ($val === null) {
            return $default;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Retrieve input as an integer value.
     */
    public static function integer(string $key, int $default = 0): int
    {
        $val = static::input($key);

        return $val !== null ? (int) $val : $default;
    }

    /**
     * Retrieve input as a float value.
     */
    public static function float(string $key, float $default = 0.0): float
    {
        $val = static::input($key);

        return $val !== null ? (float) $val : $default;
    }

    /**
     * Retrieve input as a CodeIgniter Time date instance.
     */
    public static function date(string $key, ?string $format = null, ?string $timezone = null): ?\CodeIgniter\I18n\Time
    {
        $val = static::input($key);

        if (blank($val)) {
            return null;
        }

        if ($format !== null) {
            return \CodeIgniter\I18n\Time::createFromFormat($format, (string) $val, $timezone);
        }

        return \CodeIgniter\I18n\Time::parse((string) $val, $timezone);
    }

    /**
     * Retrieve the Bearer token from the request Authorization header.
     */
    public static function bearerToken(): ?string
    {
        $header = request()->getHeaderLine('Authorization');

        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return null;
    }

    /**
     * Get a subset containing the provided keys with values from the input data.
     */
    public static function only(array|string ...$keys): array
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        $all = static::all();
        $results = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $results[$key] = $all[$key];
            }
        }

        return $results;
    }

    /**
     * Get all of the input except for a specified array of items.
     */
    public static function except(array|string ...$keys): array
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        $all = static::all();

        foreach ($keys as $key) {
            unset($all[$key]);
        }

        return $all;
    }

    /**
     * Determine if the request contains a given input item key.
     */
    public static function has(string|array $keys): bool
    {
        $keys = (array) $keys;
        $all = static::all();

        foreach ($keys as $key) {
            if (!array_key_exists($key, $all) && request()->getVar($key) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the request contains a non-empty value for an input item.
     */
    public static function filled(string|array $keys): bool
    {
        $keys = (array) $keys;

        foreach ($keys as $key) {
            if (blank(static::input($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the request is missing a given input item key.
     */
    public static function missing(string|array $keys): bool
    {
        return !static::has($keys);
    }
}
