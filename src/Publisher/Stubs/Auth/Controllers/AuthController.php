<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Jengo\Inertia\Inertia;

class AuthController extends BaseController
{
    public function loginView()
    {
        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        return Inertia::render('Auth/Login', [
            'error' => session('error'),
        ]);
    }

    public function registerView()
    {
        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        return Inertia::render('Auth/Register');
    }
}
