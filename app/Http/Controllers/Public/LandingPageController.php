<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;

class LandingPageController extends Controller
{
    public function index()
    {
        return view('public.landing');
    }
}
