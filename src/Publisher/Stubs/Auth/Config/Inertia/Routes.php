<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->get('dashboard', 'Dashboard::index', ['filter' => 'session']);

$routes->post('logout', 'Auth\LoginController::logoutAction', ['as' => 'logout']);

service('auth')->routes($routes, [
    'namespace' => 'App\Controllers\Auth',
    'except' => ['logout']
]);