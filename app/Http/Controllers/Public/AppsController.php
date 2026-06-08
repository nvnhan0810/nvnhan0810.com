<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AppsController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('public/AppsPage');
    }
}
