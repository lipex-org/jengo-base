<?php

use App\Controllers\Auth\ActionController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\MagicLinkController;
use App\Controllers\Auth\RegisterController;
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('dashboard', 'Dashboard::index', ['as' => 'dashboard', 'filter' => 'session']);

// Jengo Inertia Auth Routes
$routes->get('login', [LoginController::class, 'loginView'], ['as' => 'login']);
$routes->post('login', [LoginController::class, 'loginAction']);
$routes->get('logout', [LoginController::class, 'logoutAction']);

$routes->get('register', [RegisterController::class, 'registerView'], ['as' => 'register']);
$routes->post('register', [RegisterController::class, 'registerAction']);

$routes->get('login/magic-link', [MagicLinkController::class, 'loginView'], ['as' => 'magic-link']);
$routes->post('login/magic-link', [MagicLinkController::class, 'loginAction']);
$routes->get('login/verify-magic-link', [MagicLinkController::class, 'verify'], ['as' => 'verify-magic-link']);

$routes->group('auth/a', static function ($routes) {
    $routes->get('show', [ActionController::class, 'show'], ['as' => 'auth-action-show']);
    $routes->post('handle', [ActionController::class, 'handle'], ['as' => 'auth-action-handle']);
    $routes->post('verify', [ActionController::class, 'verify'], ['as' => 'auth-action-verify']);
});

service('auth')->routes($routes, ['except' => ['login', 'register', 'logout', 'magic-link']]);
