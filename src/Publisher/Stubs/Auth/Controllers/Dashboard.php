<?php

namespace App\Controllers;

use Jengo\Inertia\Inertia;

class Dashboard extends BaseController
{
    public function index()
    {
        return Inertia::render('dashboard');
    }
}
