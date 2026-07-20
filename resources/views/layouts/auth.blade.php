<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Account') — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ config('settings.site_favicon') ?? asset('favicon.ico') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @php
        $brand = config('settings.app_name') ?: config('app.name');
        $logoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $logo = \Illuminate\Support\Str::startsWith($logoRaw, ['http://', 'https://']) ? $logoRaw : asset(ltrim($logoRaw, '/'));
    @endphp
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; min-height: 100dvh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0b0f1a;
            display: flex;
            color: #1f2937;
        }

        /* ── Two-column wrapper ── */
        .auth-wrapper {
            display: flex; width: 100%; min-height: 100vh; min-height: 100dvh;
        }

        /* ── LEFT PANEL — Cricket branding ── */
        .auth-brand {
            flex: 0 0 46%; display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            position: relative; overflow: hidden;
            background: linear-gradient(160deg, #0b1120 0%, #0f1d3d 40%, #162a56 70%, #1a3366 100%);
            padding: 48px 40px;
        }
        .auth-brand::before {
            content: ''; position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 600px 600px at 20% 80%, rgba(234,179,8,0.08) 0%, transparent 70%),
                radial-gradient(ellipse 400px 400px at 80% 20%, rgba(59,130,246,0.06) 0%, transparent 70%);
        }
        .auth-brand::after {
            content: ''; position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.015'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .brand-content { position: relative; z-index: 2; text-align: center; max-width: 380px; }
        .brand-logo { margin-bottom: 32px; }
        .brand-logo img {
            height: 64px; max-width: 220px; object-fit: contain;
            filter: brightness(0) invert(1); opacity: 0.95;
        }

        /* Cricket ball SVG illustration */
        .cricket-visual {
            margin: 0 auto 36px; width: 200px; height: 200px;
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-12px) rotate(5deg); }
        }

        .brand-tagline {
            font-size: 26px; font-weight: 800; line-height: 1.3;
            color: #ffffff; letter-spacing: -0.02em; margin-bottom: 12px;
        }
        .brand-tagline span { color: #eab308; }
        .brand-desc {
            font-size: 14px; color: rgba(255,255,255,0.5); line-height: 1.6; margin-bottom: 36px;
        }

        .brand-stats { display: flex; gap: 32px; justify-content: center; }
        .brand-stat { text-align: center; }
        .brand-stat-value {
            font-size: 24px; font-weight: 800; color: #eab308;
            line-height: 1;
        }
        .brand-stat-label {
            font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase;
            letter-spacing: 0.08em; margin-top: 4px;
        }

        /* Decorative stumps */
        .stumps-deco {
            position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
            opacity: 0.04; width: 300px; height: 120px;
        }

        /* ── RIGHT PANEL — Form ── */
        .auth-form-panel {
            flex: 1; display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 48px 40px;
            background: #ffffff;
            position: relative;
            overflow-y: auto;
        }

        .auth-card {
            width: 100%; max-width: 400px;
            animation: fadeUp .4s ease-out;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: none; }
        }

        .auth-card h1 {
            font-size: 24px; font-weight: 800; color: #111827;
            margin-bottom: 6px; letter-spacing: -0.02em;
        }
        .auth-card .sub {
            font-size: 14px; color: #6b7280; margin-bottom: 28px;
        }

        /* Fields */
        .field { margin-bottom: 18px; }
        .field label {
            display: block; font-size: 13px; font-weight: 600;
            color: #374151; margin-bottom: 6px;
        }
        .field .input-wrap {
            position: relative;
        }
        .field input, .field select {
            width: 100%; padding: 12px 14px; font-size: 15px; color: #111827;
            border: 1.5px solid #e5e7eb; border-radius: 10px; outline: none;
            transition: border-color .2s, box-shadow .2s;
            background: #f9fafb;
            font-family: inherit;
        }
        .field input:focus, .field select:focus {
            border-color: #1a3366; box-shadow: 0 0 0 3px rgba(26,51,102,0.1);
            background: #ffffff;
        }
        .field input::placeholder { color: #9ca3af; }

        /* Password toggle */
        .field .input-wrap input { padding-right: 44px; }
        .pwd-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #9ca3af; padding: 4px; display: flex; align-items: center;
            transition: color .15s;
        }
        .pwd-toggle:hover { color: #4b5563; }
        .pwd-toggle svg { width: 20px; height: 20px; }

        /* Remember + forgot row */
        .row-between {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; gap: 10px; flex-wrap: wrap;
        }
        .remember {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: #4b5563; cursor: pointer; user-select: none;
        }
        .remember input[type="checkbox"] {
            width: 16px; height: 16px; accent-color: #1a3366;
            border-radius: 4px; cursor: pointer;
        }
        .link {
            color: #1a3366; text-decoration: none; font-size: 13px; font-weight: 600;
            transition: color .15s;
        }
        .link:hover { color: #eab308; text-decoration: underline; }

        /* Button */
        .btn {
            width: 100%; border: none; cursor: pointer; padding: 13px; border-radius: 10px;
            font-size: 15px; font-weight: 700; color: #ffffff;
            background: linear-gradient(135deg, #0f1d3d 0%, #1a3366 50%, #234080 100%);
            transition: transform .12s, box-shadow .12s;
            box-shadow: 0 8px 24px rgba(15,29,61,0.25);
            font-family: inherit; letter-spacing: 0.01em;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(15,29,61,0.35);
        }
        .btn:active { transform: translateY(0); }

        /* Alerts */
        .alert {
            background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
            padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 20px;
        }
        .alert ul { margin: 0; padding-left: 18px; }
        .status {
            background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;
            padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 20px;
        }

        /* Footer link */
        .foot {
            text-align: center; margin-top: 28px; font-size: 14px; color: #6b7280;
            padding-top: 20px; border-top: 1px solid #f3f4f6;
        }

        /* Divider */
        .auth-divider {
            display: flex; align-items: center; gap: 12px;
            margin: 20px 0; color: #9ca3af; font-size: 12px;
        }
        .auth-divider::before, .auth-divider::after {
            content: ''; flex: 1; height: 1px; background: #e5e7eb;
        }

        /* Back to home */
        .back-home {
            position: absolute; top: 24px; right: 28px;
            font-size: 13px; color: #9ca3af; text-decoration: none;
            display: flex; align-items: center; gap: 6px; transition: color .15s;
        }
        .back-home:hover { color: #1a3366; }
        .back-home svg { width: 16px; height: 16px; }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .auth-wrapper { flex-direction: column; }
            .auth-brand {
                flex: none; min-height: 220px; padding: 36px 24px 28px;
            }
            .cricket-visual { width: 120px; height: 120px; margin-bottom: 20px; }
            .brand-tagline { font-size: 20px; }
            .brand-desc { display: none; }
            .brand-stats { gap: 24px; }
            .brand-stat-value { font-size: 20px; }
            .auth-form-panel { padding: 32px 24px; }
            .back-home { top: 16px; right: 16px; }
        }
        @media (max-width: 480px) {
            .auth-brand { min-height: 180px; padding: 28px 20px 20px; }
            .cricket-visual { width: 90px; height: 90px; margin-bottom: 16px; }
            .brand-tagline { font-size: 18px; }
            .brand-stats { gap: 16px; }
            .auth-form-panel { padding: 24px 20px; }
            .auth-card h1 { font-size: 20px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="auth-wrapper">
        {{-- ── LEFT: Cricket branding panel ── --}}
        <div class="auth-brand">
            <div class="brand-content">
                <div class="brand-logo">
                    <img src="{{ $logo }}" alt="{{ $brand }}">
                </div>

                {{-- Cricket ball illustration --}}
                <div class="cricket-visual">
                    <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                        {{-- Ball body --}}
                        <circle cx="100" cy="100" r="85" fill="#c0392b" opacity="0.9"/>
                        <circle cx="100" cy="100" r="85" fill="url(#ballGrad)"/>
                        {{-- Shine --}}
                        <ellipse cx="72" cy="68" rx="30" ry="20" fill="white" opacity="0.12" transform="rotate(-25 72 68)"/>
                        {{-- Seam (stitching line) --}}
                        <path d="M40 85 C60 45, 95 25, 130 35 C155 42, 170 65, 168 95 C165 130, 140 160, 110 170 C80 178, 50 165, 38 140 C28 118, 30 100, 40 85Z"
                              stroke="#eab308" stroke-width="2.5" fill="none" opacity="0.7" stroke-linecap="round"/>
                        {{-- Stitch marks --}}
                        <g stroke="#eab308" stroke-width="1.5" opacity="0.5" stroke-linecap="round">
                            <line x1="43" y1="80" x2="50" y2="75"/>
                            <line x1="52" y1="60" x2="59" y2="55"/>
                            <line x1="68" y1="43" x2="76" y2="39"/>
                            <line x1="88" y1="32" x2="96" y2="30"/>
                            <line x1="110" y1="31" x2="118" y2="33"/>
                            <line x1="133" y1="38" x2="140" y2="44"/>
                            <line x1="152" y1="54" x2="157" y2="62"/>
                            <line x1="163" y1="74" x2="166" y2="83"/>
                            <line x1="168" y1="100" x2="166" y2="110"/>
                            <line x1="162" y1="125" x2="157" y2="133"/>
                            <line x1="148" y1="146" x2="140" y2="153"/>
                            <line x1="127" y1="162" x2="118" y2="166"/>
                            <line x1="105" y1="171" x2="95" y2="172"/>
                            <line x1="80" y1="173" x2="70" y2="169"/>
                            <line x1="57" y1="162" x2="50" y2="155"/>
                            <line x1="42" y1="145" x2="38" y2="136"/>
                            <line x1="34" y1="120" x2="33" y2="110"/>
                            <line x1="34" y1="96" x2="37" y2="88"/>
                        </g>
                        <defs>
                            <radialGradient id="ballGrad" cx="0.35" cy="0.3" r="0.65">
                                <stop offset="0%" stop-color="#e74c3c" stop-opacity="0.3"/>
                                <stop offset="100%" stop-color="#7f1d1d" stop-opacity="0.4"/>
                            </radialGradient>
                        </defs>
                    </svg>
                </div>

                <div class="brand-tagline">
                    Manage your <span>cricket</span><br>tournaments with ease
                </div>
                <div class="brand-desc">
                    Organize tournaments, manage teams, run auctions, and track every match — all in one platform.
                </div>

                <div class="brand-stats">
                    <div class="brand-stat">
                        <div class="brand-stat-value">500+</div>
                        <div class="brand-stat-label">Players</div>
                    </div>
                    <div class="brand-stat">
                        <div class="brand-stat-value">50+</div>
                        <div class="brand-stat-label">Teams</div>
                    </div>
                    <div class="brand-stat">
                        <div class="brand-stat-value">20+</div>
                        <div class="brand-stat-label">Tournaments</div>
                    </div>
                </div>
            </div>

            {{-- Decorative stumps at bottom --}}
            <svg class="stumps-deco" viewBox="0 0 300 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="110" y="0" width="8" height="110" rx="3" fill="white"/>
                <rect x="135" y="0" width="8" height="110" rx="3" fill="white"/>
                <rect x="160" y="0" width="8" height="110" rx="3" fill="white"/>
                <rect x="105" y="0" width="70" height="6" rx="2" fill="white"/>
                <rect x="105" y="14" width="70" height="6" rx="2" fill="white"/>
            </svg>
        </div>

        {{-- ── RIGHT: Form panel ── --}}
        <div class="auth-form-panel">
            <a href="{{ route('index') }}" class="back-home">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Back to home
            </a>

            <div class="auth-card">
                @hasSection('heading')
                    <h1>@yield('heading')</h1>
                @endif
                @hasSection('subtitle')
                    <p class="sub">@yield('subtitle')</p>
                @endif

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

                @yield('content')

                @hasSection('foot')
                    <div class="foot">@yield('foot')</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Password toggle script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.pwd-toggle').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var input = this.parentElement.querySelector('input');
                    var eyeOpen = this.querySelector('.eye-open');
                    var eyeClosed = this.querySelector('.eye-closed');
                    if (input.type === 'password') {
                        input.type = 'text';
                        eyeOpen.style.display = 'none';
                        eyeClosed.style.display = 'block';
                    } else {
                        input.type = 'password';
                        eyeOpen.style.display = 'block';
                        eyeClosed.style.display = 'none';
                    }
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
