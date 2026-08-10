@extends('layouts.auth')

@section('title', 'Reset password')
@section('heading', 'Forgot your password?')
@section('subtitle', "Enter your email and we'll send you a reset link")

@section('content')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="you@example.com">
        </div>
        @if(config('turnstile.site_key') && !app()->environment('local'))
            <div class="cf-turnstile" data-sitekey="{{ config('turnstile.site_key') }}" style="margin-bottom: 16px;" data-retry="auto" data-retry-interval="1500" data-refresh-expired="auto"></div>
        @endif
        <button type="submit" class="btn">Send password reset link</button>
    </form>
    @if(config('turnstile.site_key') && !app()->environment('local'))
        <link rel="preconnect" href="https://challenges.cloudflare.com" crossorigin>
        <link rel="dns-prefetch" href="https://challenges.cloudflare.com">
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endsection

@section('foot')
    Remembered it? <a class="link" href="{{ route('login') }}">Back to sign in</a>
@endsection
