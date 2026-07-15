<?php

declare(strict_types=1);

namespace Jengo\Base\Validation;

class ValidatedData
{
    private readonly \stdClass $get;
    private readonly \stdClass $post;
    private readonly \stdClass $json;
    private readonly \stdClass $router;

    public function __construct(
        array $get = [],
        array $post = [],
        array $json = [],
        array $router = []
    ) {
        $this->get = (object) $get;
        $this->post = (object) $post;
        $this->json = (object) $json;
        $this->router = (object) $router;
    }

    /**
     * Retrieve a GET parameter object or specific field.
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }
        return property_exists($this->get, $key) ? $this->get->{$key} : $default;
    }

    /**
     * Retrieve a POST parameter object or specific field.
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return property_exists($this->post, $key) ? $this->post->{$key} : $default;
    }

    /**
     * Retrieve a JSON body parameter object or specific field.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->json;
        }
        return property_exists($this->json, $key) ? $this->json->{$key} : $default;
    }

    /**
     * Retrieve a Router parameter object or specific field.
     */
    public function router(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->router;
        }
        return property_exists($this->router, $key) ? $this->router->{$key} : $default;
    }

    /**
     * Retrieve a value from anywhere, prioritizing: router, json, post, get.
     */
    public function any(string $key, mixed $default = null): mixed
    {
        if (property_exists($this->router, $key)) {
            return $this->router->{$key};
        }
        if (property_exists($this->json, $key)) {
            return $this->json->{$key};
        }
        if (property_exists($this->post, $key)) {
            return $this->post->{$key};
        }
        if (property_exists($this->get, $key)) {
            return $this->get->{$key};
        }
        return $default;
    }

    /**
     * Convert all grouped inputs to a flat merged array.
     */
    public function toArray(): array
    {
        return array_merge(
            (array) $this->get,
            (array) $this->post,
            (array) $this->json,
            (array) $this->router
        );
    }
}
