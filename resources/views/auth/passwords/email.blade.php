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
        <button type="submit" class="btn">Send password reset link</button>
    </form>
@endsection

@section('foot')
    Remembered it? <a class="link" href="{{ route('login') }}">Back to sign in</a>
@endsection
