<?php

namespace Jengo\Base\Support;

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