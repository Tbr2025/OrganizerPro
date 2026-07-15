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
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
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
        @if(config('turnstile.site_key'))
            <div class="cf-turnstile" data-sitekey="{{ config('turnstile.site_key') }}" style="margin-bottom: 16px;"></div>
        @endif
        <button type="submit" class="btn">Sign in</button>
    </form>
    @if(config('turnstile.site_key'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endsection

@if (Route::has('register'))
    @section('foot')
        Don't have an account? <a class="link" href="{{ route('register') }}">Create one</a>
    @endsection
@endif
