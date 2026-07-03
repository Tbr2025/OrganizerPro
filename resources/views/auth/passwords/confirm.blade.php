@extends('layouts.auth')

@section('title', 'Confirm password')
@section('heading', 'Confirm your password')
@section('subtitle', 'Please confirm your password before continuing')

@section('content')
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="btn">Confirm password</button>
    </form>
@endsection

@if (Route::has('password.request'))
    @section('foot')
        <a class="link" href="{{ route('password.request') }}">Forgot your password?</a>
    @endsection
@endif
