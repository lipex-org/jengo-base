<?php

declare(strict_types=1);

namespace Jengo\Base\Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Router\RouteCollection;
use Config\Modules;
use Config\Routing;
use Jengo\Base\Container\Container;
use Jengo\Base\Container\ContainerInterface;
use Jengo\Base\Libraries\ModuleDiscovery;
use Jengo\Base\Support\ResponseHandler;
use Config\Services as AppServices;
class Services extends BaseService
{
    public static function container(bool $getShared = true): ContainerInterface
    {
        if ($getShared) {
            return Container::getInstance();
        }

        return new Container();
    }

    public static function responseHandler(bool $getShared = true): ResponseHandler
    {
        if ($getShared) {
            return static::getSharedInstance('responseHandler');
        }

        return new ResponseHandler();
    }
}
