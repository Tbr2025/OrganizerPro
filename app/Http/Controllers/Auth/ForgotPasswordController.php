<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ValidatesTurnstile;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;
    use ValidatesTurnstile;

    protected function validateEmail(Request $request)
    {
        $this->validateTurnstile($request);

        $request->validate(['email' => 'required|email']);
    }
}
