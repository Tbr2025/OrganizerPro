<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;

class PricingController extends Controller
{
    public function index()
    {
        return view('public.pricing');
    }
}
