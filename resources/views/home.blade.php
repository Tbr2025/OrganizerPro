<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ config('settings.site_favicon') ?? asset('favicon.ico') }}" type="image/x-icon">
    @php
        $brand = config('settings.app_name') ?: config('app.name');
        $logoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $logo = \Illuminate\Support\Str::startsWith($logoRaw, ['http://', 'https://']) ? $logoRaw : asset(ltrim($logoRaw, '/'));
        $user = auth()->user();
        $canAdmin = $user && method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['Superadmin', 'Admin', 'Team Manager']) : false;
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f3f4f6; color: #1f2937; }
        .topbar { background: linear-gradient(135deg, #1a1a2e, #16213e); color: #fff; padding: 14px 22px; display: flex; align-items: center; justify-content: space-between; }
        .topbar .left { display: flex; align-items: center; gap: 12px; }
        .topbar img { height: 34px; max-width: 150px; object-fit: contain; background:#fff; border-radius:6px; padding:4px; }
        .topbar .brand { font-weight: 700; font-size: 16px; }
        .topbar .right { display: flex; align-items: center; gap: 14px; font-size: 14px; }
        .logout-btn { background: rgba(255,255,255,0.14); color:#fff; border:none; cursor:pointer; padding:8px 14px; border-radius:8px; font-size:13px; font-weight:600; }
        .logout-btn:hover { background: rgba(255,255,255,0.24); }
        .wrap { max-width: 820px; margin: 32px auto; padding: 0 20px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:28px; box-shadow: 0 8px 24px rgba(0,0,0,0.05); }
        .hello { font-size: 24px; font-weight: 800; margin: 0 0 6px; color:#111827; }
        .muted { color:#6b7280; margin: 0 0 20px; }
        .status { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:10px 14px; border-radius:10px; font-size:14px; margin-bottom:20px; }
        .actions { display:flex; flex-wrap:wrap; gap:12px; }
        .btn { display:inline-flex; align-items:center; gap:8px; padding:12px 18px; border-radius:10px; font-size:14px; font-weight:700; text-decoration:none; }
        .btn-primary { background: linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; box-shadow:0 10px 24px rgba(79,70,229,0.25); }
        .btn-secondary { background:#fff; color:#374151; border:1px solid #d1d5db; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="left">
            <img src="{{ $logo }}" alt="{{ $brand }}">
            <span class="brand">{{ $brand }}</span>
        </div>
        <div class="right">
            <span>{{ $user->name ?? '' }}</span>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <div class="wrap">
        <div class="card">
            <h1 class="hello">Welcome, {{ $user->name ?? 'there' }} 👋</h1>
            <p class="muted">You're signed in to {{ $brand }}.</p>

            @if (session('status'))
                <div class="status">{{ session('status') }}</div>
            @endif

            <div class="actions">
                @if($canAdmin)
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
                @endif
                <a href="{{ url('/') }}" class="btn btn-secondary">Browse site</a>
            </div>
        </div>
    </div>
</body>
</html>
