<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

use CodeIgniter\Config\BaseConfig;

class Sqids extends BaseConfig
{
    /**
     * The alphabet used by Sqids.
     */
    public string $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * The minimum length of the generated Sqids.
     */
    public int $minLength = 10;
}
