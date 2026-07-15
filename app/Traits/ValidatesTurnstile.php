<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

trait ValidatesTurnstile
{
    protected function validateTurnstile(Request $request): void
    {
        if (!config('turnstile.secret_key')) {
            return;
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('turnstile.secret_key'),
            'response' => $request->input('cf-turnstile-response', ''),
            'remoteip' => $request->ip(),
        ]);

        if (!$response->json('success')) {
            throw ValidationException::withMessages([
                'captcha' => __('CAPTCHA verification failed. Please try again.'),
            ]);
        }
    }
}
