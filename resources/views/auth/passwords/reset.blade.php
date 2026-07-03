@extends('layouts.auth')

@section('title', 'Reset password')
@section('heading', 'Set a new password')
@section('subtitle', 'Choose a new password for your account')

@section('content')
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus placeholder="you@example.com">
        </div>
        <div class="field">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="••••••••">
        </div>
        <div class="field">
            <label for="password-confirm">Confirm password</label>
            <input id="password-confirm" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
        </div>
        <button type="submit" class="btn">Reset password</button>
    </form>
@endsection

@section('foot')
    <a class="link" href="{{ route('login') }}">Back to sign in</a>
@endsection
