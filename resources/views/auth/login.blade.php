<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ config('settings.site_favicon') ?? asset('favicon.ico') }}" type="image/x-icon">
    @php
        $brand = config('settings.app_name') ?: config('app.name');
        $logoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $logo = \Illuminate\Support\Str::startsWith($logoRaw, ['http://', 'https://']) ? $logoRaw : asset(ltrim($logoRaw, '/'));
    @endphp
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; min-height: 100dvh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(150deg, #1a1a2e 0%, #16213e 55%, #0f3460 100%);
            display: flex; align-items: center; justify-content: center; padding: 20px;
            color: #1f2937;
        }
        .auth-card {
            width: 100%; max-width: 420px; background: #ffffff;
            border-radius: 18px; box-shadow: 0 25px 60px rgba(0,0,0,0.35);
            padding: 34px 30px; animation: rise .35s ease-out;
        }
        @keyframes rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        .auth-logo { text-align: center; margin-bottom: 18px; }
        .auth-logo img { height: 54px; max-width: 200px; object-fit: contain; }
        .auth-logo .brand { font-size: 20px; font-weight: 800; color: #1a1a2e; margin-top: 8px; }
        h1 { font-size: 22px; font-weight: 700; text-align: center; margin: 0 0 4px; color: #111827; }
        .sub { text-align: center; color: #6b7280; font-size: 14px; margin: 0 0 24px; }
        .field { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        input[type=email], input[type=password] {
            width: 100%; padding: 12px 14px; font-size: 15px; color: #111827;
            border: 1px solid #d1d5db; border-radius: 10px; outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        input[type=email]:focus, input[type=password]:focus {
            border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.18);
        }
        .row-between { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .remember { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4b5563; cursor: pointer; }
        .remember input { width: 16px; height: 16px; accent-color: #6366f1; }
        .link { color: #4f46e5; text-decoration: none; font-size: 13px; font-weight: 600; }
        .link:hover { text-decoration: underline; }
        .btn {
            width: 100%; border: none; cursor: pointer; padding: 13px; border-radius: 10px;
            font-size: 15px; font-weight: 700; color: #ffffff;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            transition: transform .12s, box-shadow .12s; box-shadow: 0 10px 24px rgba(79,70,229,0.3);
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(79,70,229,0.4); }
        .foot { text-align: center; margin-top: 22px; font-size: 14px; color: #6b7280; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; }
        .alert ul { margin: 0; padding-left: 18px; }
        .status { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; }
        .err { color: #dc2626; font-size: 12px; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-logo">
            <img src="{{ $logo }}" alt="{{ $brand }}">
            <div class="brand">{{ $brand }}</div>
        </div>
        <h1>Welcome back</h1>
        <p class="sub">Sign in to your account to continue</p>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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
            <button type="submit" class="btn">Sign in</button>
        </form>

        @if (Route::has('register'))
            <div class="foot">
                Don't have an account?
                <a class="link" href="{{ route('register') }}">Create one</a>
            </div>
        @endif
    </div>
</body>
</html>
