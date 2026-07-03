@extends('layouts.auth')

@section('title', 'Verify email')
@section('heading', 'Verify your email address')
@section('subtitle', 'Check your inbox for a verification link')

@section('content')
    @if (session('resent'))
        <div class="status">A fresh verification link has been sent to your email address.</div>
    @endif
    <p style="color:#4b5563; font-size:14px; text-align:center; margin:0 0 20px;">
        Before proceeding, please check your email for a verification link. If you didn't receive it, request another below.
    </p>
    <form method="POST" action="{{ route('verification.resend') }}">
        @csrf
        <button type="submit" class="btn">Resend verification email</button>
    </form>
@endsection

@section('foot')
    <a class="link" href="{{ route('login') }}">Back to sign in</a>
@endsection
