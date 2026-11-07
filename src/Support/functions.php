<?php

namespace {
    if (!defined('RESOURCEPATH')) {
        define(
            'RESOURCEPATH',
            (defined('ROOTPATH') ? rtrim(ROOTPATH, '\\/') : getcwd()) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR
        );
    }

    if (!function_exists('resource_path')) {
        /**
         * Returns the path to the resources directory or a specific file/subfolder within it.
         *
         * @param string|null $path
         * @return string
         */
        function resource_path(?string $path = ''): string
        {
            $base = defined('RESOURCEPATH')
                ? rtrim(RESOURCEPATH, '\\/')
                : (defined('ROOTPATH') ? rtrim(ROOTPATH, '\\/') . DIRECTORY_SEPARATOR . 'resources' : getcwd() . DIRECTORY_SEPARATOR . 'resources');

            return empty($path) ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '\\/');
        }
    }
}

namespace Jengo\Base\Support {
    use CodeIgniter\Entity\Entity;
    use Jengo\Base\Libraries\Arr;
    use Jengo\Base\Libraries\Str;

    if (!function_exists('arr')) {
        function arr(array|Entity $arr): Arr
        {
            return Arr::set($arr);
        }
    }

    if (!function_exists('str')) {
        function str(string $str = ''): Str
        {
            return Str::set($str);
        }
    }
}
