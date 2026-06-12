<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use Jengo\Base\Attributes\API;
use Jengo\Base\Traits\APIResponseTrait;

/**
 * Base API Controller for the Jengo ecosystem.
 * Provides standardized JSON responses and RESTful utilities.
 */
#[API]
abstract class APIController extends ResourceController
{
    use APIResponseTrait;

    protected $format = 'json';
}
