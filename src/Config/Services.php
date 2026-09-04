<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

use CodeIgniter\Config\BaseService;
use Jengo\Base\Support\ResponseHandler;

class Services extends BaseService
{
    public static function responseHandler(bool $getShared = true): ResponseHandler
    {
        if ($getShared) {
            return static::getSharedInstance('responseHandler');
        }

        return new ResponseHandler();
    }
}
