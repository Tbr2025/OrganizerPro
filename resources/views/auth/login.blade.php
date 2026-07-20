@extends('layouts.auth')

@section('title', 'Sign in')
@section('heading', 'Welcome back')
@section('subtitle', 'Sign in to your account to continue')

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="you@example.com">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
                <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                <button type="button" class="pwd-toggle" aria-label="Toggle password visibility">
                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="row-between">
            <label class="remember">
                <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a class="link" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>
        @if(config('turnstile.site_key') && !app()->environment('local'))
            <div class="cf-turnstile" data-sitekey="{{ config('turnstile.site_key') }}" style="margin-bottom: 16px;"></div>
        @endif
        <button type="submit" class="btn">Sign in</button>
    </form>
    @if(config('turnstile.site_key') && !app()->environment('local'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endsection

@if (Route::has('register'))
    @section('foot')
        Don't have an account? <a class="link" href="{{ route('register') }}">Create one</a>
    @endsection
@endif
