<?php

declare(strict_types=1);

namespace Jengo\Base\View\Decorators;

use CodeIgniter\View\ViewDecoratorInterface;

class EmailTemplateDecorator implements ViewDecoratorInterface
{
    /**
     * Decorates the rendered HTML by replacing % key % and % key.nested % placeholders.
     */
    public static function decorate(string $html): string
    {
        $renderer = service('renderer');

        // Check if the current view being rendered is an email template
        $isEmail = false;
        try {
            $ref = new \ReflectionClass($renderer);
            if ($ref->hasProperty('renderVars')) {
                $prop = $ref->getProperty('renderVars');
                $prop->setAccessible(true);
                $renderVars = $prop->getValue($renderer);
                $view = $renderVars['view'] ?? '';
                if (str_contains($view, 'emails/')) {
                    $isEmail = true;
                }
            }
        } catch (\Throwable $e) {
            // Failsafe: fall back to checking if the HTML contains Maizzle placeholders
        }

        if (!$isEmail && !preg_match('/%\s*[a-zA-Z0-9_\-\.]+\s*%/', $html)) {
            return $html;
        }

        $viewData = $renderer->getData();
        
        // Auto-load email helper if it exists to make it available for templates
        if (function_exists('helper')) {
            try {
                helper('email');
            } catch (\Throwable $e) {
                // Ignore if helper doesn't exist
            }
        }

        return preg_replace_callback('/%\s*([a-zA-Z0-9_\-\.]+)(?:\s*\|\s*([^%]+?))?\s*%/', function ($matches) use ($viewData) {
            $key = trim($matches[1]);
            
            // Search using our robust object/array resolver
            $value = self::resolveValue($key, $viewData);

            if ($value === null) {
                return '';
            }

            // Apply filters if defined
            if (isset($matches[2]) && trim($matches[2]) !== '') {
                $filters = preg_split('/\|(?![^(]*\))/', $matches[2]);
                foreach ($filters as $filter) {
                    $value = self::applyFilter($value, $filter);
                }
            }

            if ($value !== null) {
                return is_scalar($value) ? (string) $value : json_encode($value);
            }

            return '';
        }, $html);
    }

    /**
     * Navigates dot notation path supporting both nested arrays and object properties/methods.
     */
    private static function resolveValue(string $path, array $data)
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_object($current)) {
                if (isset($current->{$segment})) {
                    $current = $current->{$segment};
                } elseif (method_exists($current, $segment)) {
                    $current = $current->{$segment}();
                } elseif (method_exists($current, 'get' . ucfirst($segment))) {
                    $current = $current->{'get' . ucfirst($segment)}();
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Applies a single filter function, parsing any parameters.
     */
    private static function applyFilter($value, string $filterStr)
    {
        if (preg_match('/^([a-zA-Z0-9_]+)(?:\((.*)\))?$/', trim($filterStr), $matches)) {
            $funcName = $matches[1];
            if (!function_exists($funcName)) {
                return $value;
            }

            $args = [];
            if (isset($matches[2]) && trim($matches[2]) !== '') {
                $rawArgs = str_getcsv($matches[2], ',', "'");
                foreach ($rawArgs as $arg) {
                    $arg = trim($arg);
                    if (str_starts_with($arg, '"') && str_ends_with($arg, '"')) {
                        $arg = substr($arg, 1, -1);
                    }
                    if ($arg === 'true') {
                        $arg = true;
                    } elseif ($arg === 'false') {
                        $arg = false;
                    } elseif ($arg === 'null') {
                        $arg = null;
                    }
                    $args[] = $arg;
                }
            }

            // Value is passed as the first parameter to the function
            array_unshift($args, $value);

            try {
                return call_user_func_array($funcName, $args);
            } catch (\Throwable $e) {
                return $value;
            }
        }

        return $value;
    }
}
