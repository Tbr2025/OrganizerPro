@extends('layouts.auth')

@section('title', 'Register')
@section('heading', 'Create your account')
@section('subtitle', 'Sign up to get started')

@section('content')
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="field">
            <label for="name">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="Your name">
        </div>
        <div class="field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@example.com">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="••••••••">
        </div>
        <div class="field">
            <label for="password-confirm">Confirm password</label>
            <input id="password-confirm" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
        </div>
        @if(config('turnstile.site_key') && !app()->environment('local'))
            <div class="cf-turnstile" data-sitekey="{{ config('turnstile.site_key') }}" style="margin-bottom: 16px;"></div>
        @endif
        <button type="submit" class="btn">Create account</button>
    </form>
    @if(config('turnstile.site_key') && !app()->environment('local'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endsection

@section('foot')
    Already have an account? <a class="link" href="{{ route('login') }}">Sign in</a>
@endsection
