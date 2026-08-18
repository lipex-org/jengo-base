<?php

declare(strict_types=1);

namespace Jengo\Base\Entities\Casts;

use CodeIgniter\Entity\Cast\BaseCast;

class CastPgBoolean extends BaseCast
{
    #[\Override]
    public static function get($value, array $params = [])
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['t', '1', 1, 'true'], true);
    }

    #[\Override]
    public static function set($value, array $params = [])
    {
        return $value;
    }
}
