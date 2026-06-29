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

        // 1. Process Conditionals (innermost first)
        $condPattern = '/%\s*if\s*([^%]+?)\s*%\s*([^%]*?)\s*(?:%\s*else\s*%\s*([^%]*?)\s*)?%\s*endif\s*%/s';
        while (preg_match($condPattern, $html)) {
            $html = preg_replace_callback($condPattern, function ($matches) use ($viewData) {
                $conditionStr = trim($matches[1]);
                $trueContent = $matches[2];
                $falseContent = $matches[3] ?? '';

                $isTruthy = self::evaluateCondition($conditionStr, $viewData);

                return $isTruthy ? $trueContent : $falseContent;
            }, $html);
        }

        // 2. Process Variables, Function Calls, and Filters
        $varPattern = '/%\s*([a-zA-Z0-9_\-\.]+)(?:\((.*?)\))?(?:\s*\|\s*([^%]+?))?\s*%/';
        return preg_replace_callback($varPattern, function ($matches) use ($viewData) {
            $keyOrFunc = trim($matches[1]);
            $hasParams = isset($matches[2]);
            $filterStr = $matches[3] ?? '';

            $value = null;

            if ($hasParams) {
                // Function call (e.g. base_url() or base_url('path'))
                if (function_exists($keyOrFunc)) {
                    $args = [];
                    $rawParams = trim($matches[2]);
                    if ($rawParams !== '') {
                        $rawArgs = str_getcsv($rawParams, ',', "'");
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
                    try {
                        $value = call_user_func_array($keyOrFunc, $args);
                    } catch (\Throwable $e) {
                        $value = null;
                    }
                }
            } else {
                // Standard variable / path resolution
                $value = self::resolveValue($keyOrFunc, $viewData);
            }

            if ($value === null) {
                return '';
            }

            // Apply filters if defined
            if ($filterStr !== '') {
                $filters = preg_split('/\|(?![^(]*\))/', $filterStr);
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
     * Evaluates a condition string to check if it's truthy, negated, or a comparison.
     */
    private static function evaluateCondition(string $conditionStr, array $viewData): bool
    {
        // 1. Check for comparisons: ==, !=, >=, <=, >, <
        if (preg_match('/^([a-zA-Z0-9_\-\.]+)\s*(==|!=|>=|<=|>|<)\s*(.*?)$/', $conditionStr, $condMatches)) {
            $key = trim($condMatches[1]);
            $operator = $condMatches[2];
            $rawValue = trim($condMatches[3]);

            $leftVal = self::resolveValue($key, $viewData);

            $rightVal = $rawValue;
            if (str_starts_with($rightVal, "'") && str_ends_with($rightVal, "'")) {
                $rightVal = substr($rightVal, 1, -1);
            } elseif (str_starts_with($rightVal, '"') && str_ends_with($rightVal, '"')) {
                $rightVal = substr($rightVal, 1, -1);
            } elseif ($rightVal === 'true') {
                $rightVal = true;
            } elseif ($rightVal === 'false') {
                $rightVal = false;
            } elseif ($rightVal === 'null') {
                $rightVal = null;
            } elseif (is_numeric($rightVal)) {
                $rightVal = $rightVal + 0;
            }

            return match ($operator) {
                '==' => $leftVal == $rightVal,
                '!=' => $leftVal != $rightVal,
                '>'  => $leftVal > $rightVal,
                '<'  => $leftVal < $rightVal,
                '>=' => $leftVal >= $rightVal,
                '<=' => $leftVal <= $rightVal,
                default => false,
            };
        }

        // 2. Simple boolean / truthy checks (supporting optional negation '!')
        $isNegated = str_starts_with($conditionStr, '!');
        $key = $isNegated ? substr($conditionStr, 1) : $conditionStr;
        $key = trim($key);

        $value = self::resolveValue($key, $viewData);
        $isTruthy = !empty($value);

        return $isNegated ? !$isTruthy : $isTruthy;
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
