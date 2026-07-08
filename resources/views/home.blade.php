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
        $isPlayer = $user && method_exists($user, 'hasRole') ? $user->hasRole('Player') : false;
        $player = $user?->player;
        $approvedCount = $player ? $player->registrations()->where('status', 'approved')->count() : 0;
        $pendingCount = $player ? $player->registrations()->where('status', 'pending')->count() : 0;
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
        .nav-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-top:22px; }
        .nav-card { display:flex; gap:14px; align-items:flex-start; text-decoration:none; background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:18px; transition:box-shadow .15s, transform .1s; }
        .nav-card:hover { box-shadow:0 12px 28px rgba(0,0,0,0.08); transform:translateY(-1px); }
        .nav-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; background:#eef2ff; color:#4f46e5; }
        .nav-icon svg { width:22px; height:22px; }
        .nav-title { font-weight:700; color:#111827; font-size:15px; }
        .nav-sub { color:#6b7280; font-size:13px; margin-top:2px; }
        .badge { display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; }
        .badge-green { background:#dcfce7; color:#166534; }
        .badge-amber { background:#fef3c7; color:#92400e; }
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

            @if($isPlayer && ($approvedCount || $pendingCount))
                <p class="muted" style="margin:-8px 0 0;">
                    @if($approvedCount)<span class="badge badge-green">✔ {{ $approvedCount }} accepted</span>@endif
                    @if($pendingCount)<span class="badge badge-amber">⏳ {{ $pendingCount }} pending review</span>@endif
                </p>
            @endif

            <div class="actions" style="margin-top:16px;">
                @if($canAdmin)
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
                @endif
                <a href="{{ url('/') }}" class="btn btn-secondary">Browse site</a>
            </div>

            {{-- Player navigation --}}
            @if($isPlayer)
            <div class="nav-grid">
                <a href="{{ route('profileplayers.edit') }}" class="nav-card">
                    <span class="nav-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span>
                    <span>
                        <span class="nav-title">My Registration Details</span>
                        <span class="nav-sub">View & edit the details you submitted (per tournament). Un-verified fields are editable; changes go for admin approval.</span>
                    </span>
                </a>
                <a href="{{ route('profile.edit') }}" class="nav-card">
                    <span class="nav-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.657 1.343-3 3-3m-6 3a3 3 0 11-6 0 3 3 0 016 0zM15 7a2 2 0 100-4 2 2 0 000 4zM7 20h10a2 2 0 002-2v-1a4 4 0 00-4-4H9a4 4 0 00-4 4v1a2 2 0 002 2z"/></svg></span>
                    <span>
                        <span class="nav-title">Account &amp; Password</span>
                        <span class="nav-sub">Update your login email and password. Also shows your full registration summary.</span>
                    </span>
                </a>
            </div>
            @endif
        </div>
    </div>
</body>
</html>
