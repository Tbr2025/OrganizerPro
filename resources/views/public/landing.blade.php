<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Sportzley') }} - Manage Your Cricket Tournaments Like a Pro</title>
    <meta name="description" content="The all-in-one platform for cricket tournament management. Live scoring, auctions, team management, and more.">
    <meta property="og:title" content="{{ config('app.name', 'Sportzley') }}">
    <meta property="og:description" content="Manage your cricket tournaments like a pro.">

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        body { font-family: 'Inter', 'Roboto', sans-serif; }
        h1, h2, h3 { font-family: 'Oswald', sans-serif; }

        .gradient-gold {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .glass-nav {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.4s ease;
        }
        .glass-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(251, 191, 36, 0.3);
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.15;
            animation: orbFloat 8s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes orbFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-40px) scale(1.08); }
        }

        /* Scroll reveal */
        .reveal, .reveal-left, .reveal-right, .reveal-scale {
            opacity: 0;
            transition: opacity 0.7s ease, transform 0.7s ease;
        }
        .reveal { transform: translateY(40px); }
        .reveal-left { transform: translateX(-40px); }
        .reveal-right { transform: translateX(40px); }
        .reveal-scale { transform: scale(0.85); }
        .revealed {
            opacity: 1 !important;
            transform: translateY(0) translateX(0) scale(1) !important;
        }
        .stagger-children > * {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .stagger-children.revealed > *:nth-child(1) { transition-delay: 0.05s; }
        .stagger-children.revealed > *:nth-child(2) { transition-delay: 0.15s; }
        .stagger-children.revealed > *:nth-child(3) { transition-delay: 0.25s; }
        .stagger-children.revealed > *:nth-child(4) { transition-delay: 0.35s; }
        .stagger-children.revealed > * { opacity: 1; transform: translateY(0); }

        .count-up { font-variant-numeric: tabular-nums; }

        .btn-primary {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #0f172a;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            box-shadow: 0 8px 30px rgba(251, 191, 36, 0.4);
            transform: translateY(-2px);
        }
        .btn-outline {
            border: 2px solid rgba(251, 191, 36, 0.5);
            color: #fbbf24;
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            background: rgba(251, 191, 36, 0.1);
            border-color: #fbbf24;
        }

        .pricing-card-popular {
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.5), 0 0 40px rgba(251, 191, 36, 0.1);
        }

        [x-cloak] { display: none !important; }

        /* Smooth scroll */
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 overflow-x-hidden">

    {{-- ═══════════ STICKY HEADER ═══════════ --}}
    <header x-data="{ scrolled: false, mobileOpen: false }"
            @scroll.window="scrolled = (window.scrollY > 50)"
            :class="scrolled ? 'glass-nav shadow-lg' : 'bg-transparent'"
            class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16 md:h-20">
                {{-- Logo --}}
                <a href="/" class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center">
                        <i class="fas fa-trophy text-gray-900 text-sm"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight" style="font-family:'Oswald',sans-serif;">
                        <span class="gradient-gold">Sportzley</span>
                    </span>
                </a>

                {{-- Desktop Nav --}}
                <nav class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-sm text-gray-300 hover:text-yellow-400 transition">Features</a>
                    <a href="#pricing" class="text-sm text-gray-300 hover:text-yellow-400 transition">Pricing</a>
                    <a href="#about" class="text-sm text-gray-300 hover:text-yellow-400 transition">About</a>
                    <a href="#contact" class="text-sm text-gray-300 hover:text-yellow-400 transition">Contact</a>
                </nav>

                {{-- Auth Buttons --}}
                <div class="hidden md:flex items-center gap-3">
                    <a href="{{ route('admin.login') }}" class="px-5 py-2 text-sm font-medium text-gray-300 hover:text-white transition">
                        Login
                    </a>
                    <a href="{{ route('register') }}" class="btn-primary px-5 py-2.5 rounded-lg text-sm font-bold">
                        Get Started Free
                    </a>
                </div>

                {{-- Mobile menu button --}}
                <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 text-gray-300">
                    <i x-show="!mobileOpen" class="fas fa-bars text-lg"></i>
                    <i x-show="mobileOpen" x-cloak class="fas fa-times text-lg"></i>
                </button>
            </div>

            {{-- Mobile Menu --}}
            <div x-show="mobileOpen" x-cloak x-transition
                 class="md:hidden pb-4 border-t border-white/10 mt-2">
                <nav class="flex flex-col gap-1 pt-3">
                    <a href="#features" @click="mobileOpen=false" class="px-4 py-3 text-gray-300 hover:text-yellow-400 hover:bg-white/5 rounded-lg transition">Features</a>
                    <a href="#pricing" @click="mobileOpen=false" class="px-4 py-3 text-gray-300 hover:text-yellow-400 hover:bg-white/5 rounded-lg transition">Pricing</a>
                    <a href="#about" @click="mobileOpen=false" class="px-4 py-3 text-gray-300 hover:text-yellow-400 hover:bg-white/5 rounded-lg transition">About</a>
                    <a href="#contact" @click="mobileOpen=false" class="px-4 py-3 text-gray-300 hover:text-yellow-400 hover:bg-white/5 rounded-lg transition">Contact</a>
                </nav>
                <div class="flex gap-3 mt-4 px-4">
                    <a href="{{ route('admin.login') }}" class="flex-1 text-center px-4 py-2.5 rounded-lg border border-gray-700 text-gray-300 text-sm font-medium">Login</a>
                    <a href="{{ route('register') }}" class="flex-1 text-center btn-primary px-4 py-2.5 rounded-lg text-sm font-bold">Register</a>
                </div>
            </div>
        </div>
    </header>

    {{-- ═══════════ HERO SECTION ═══════════ --}}
    <section class="relative min-h-screen flex items-center justify-center overflow-hidden pt-20">
        {{-- Animated orbs --}}
        <div class="orb" style="width:500px;height:500px;background:#fbbf24;top:-10%;left:-5%;animation-delay:0s;"></div>
        <div class="orb" style="width:400px;height:400px;background:#3b82f6;bottom:5%;right:-5%;animation-delay:-3s;"></div>
        <div class="orb" style="width:300px;height:300px;background:#f59e0b;top:40%;left:50%;animation-delay:-5s;"></div>

        <div class="relative z-10 max-w-5xl mx-auto px-4 text-center">
            <div class="reveal">
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 text-sm font-semibold mb-6">
                    <i class="fas fa-cricket-bat-ball"></i> The #1 Cricket Tournament Platform
                </span>
            </div>

            <h1 class="text-5xl md:text-7xl lg:text-8xl font-extrabold leading-tight mb-6 reveal" style="transition-delay:0.1s;">
                Manage Your Cricket<br>
                Tournaments <span class="gradient-gold">Like a Pro</span>
            </h1>

            <p class="text-lg md:text-xl text-gray-400 max-w-2xl mx-auto mb-10 reveal" style="transition-delay:0.2s;">
                From fixtures to live scoring, auctions to team management — everything you need to organize a world-class cricket tournament in one platform.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 reveal" style="transition-delay:0.3s;">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 rounded-xl text-lg font-bold flex items-center gap-2">
                    <i class="fas fa-rocket"></i> Get Started Free
                </a>
                <a href="#features" class="btn-outline px-8 py-4 rounded-xl text-lg font-bold flex items-center gap-2">
                    <i class="fas fa-play-circle"></i> Explore Features
                </a>
            </div>

            {{-- Quick stats --}}
            <div class="mt-16 flex flex-wrap items-center justify-center gap-8 md:gap-16 reveal" style="transition-delay:0.4s;">
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-extrabold text-white count-up" data-count="500">0</p>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mt-1">Tournaments Hosted</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-extrabold text-white count-up" data-count="5000">0</p>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mt-1">Players Registered</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-extrabold text-white count-up" data-count="10000">0</p>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mt-1">Matches Scored</p>
                </div>
            </div>
        </div>

        {{-- Scroll indicator --}}
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
            <i class="fas fa-chevron-down text-gray-600 text-lg"></i>
        </div>
    </section>

    {{-- ═══════════ ABOUT SECTION ═══════════ --}}
    <section id="about" class="py-24 relative overflow-hidden">
        <div class="max-w-6xl mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="reveal-left">
                    <span class="inline-block px-4 py-2 bg-yellow-500/10 text-yellow-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-info-circle mr-2"></i>About Sportzley
                    </span>
                    <h2 class="text-4xl md:text-5xl font-extrabold text-white mb-6 leading-tight">
                        Built for Cricket,<br>
                        <span class="gradient-gold">Ready for Every Sport</span>
                    </h2>
                    <p class="text-gray-400 text-lg leading-relaxed mb-6">
                        Sportzley was born from a simple idea — organizing a cricket tournament shouldn't require spreadsheets, WhatsApp groups, and sleepless nights. We built the platform we wished existed.
                    </p>
                    <p class="text-gray-400 leading-relaxed">
                        Whether you're running a weekend T20 bash or a full league season, Sportzley handles scheduling, scoring, auctions, and everything in between — so you can focus on the game.
                    </p>
                </div>
                <div class="reveal-right">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="glass-card rounded-xl p-6 text-center">
                            <p class="text-3xl font-extrabold gradient-gold count-up" data-count="500">0</p>
                            <p class="text-sm text-gray-400 mt-1">Tournaments</p>
                        </div>
                        <div class="glass-card rounded-xl p-6 text-center">
                            <p class="text-3xl font-extrabold gradient-gold count-up" data-count="2000">0</p>
                            <p class="text-sm text-gray-400 mt-1">Teams</p>
                        </div>
                        <div class="glass-card rounded-xl p-6 text-center">
                            <p class="text-3xl font-extrabold gradient-gold count-up" data-count="5000">0</p>
                            <p class="text-sm text-gray-400 mt-1">Players</p>
                        </div>
                        <div class="glass-card rounded-xl p-6 text-center">
                            <p class="text-3xl font-extrabold gradient-gold count-up" data-count="99">0</p>
                            <p class="text-sm text-gray-400 mt-1">% Uptime</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════ FEATURES SECTION ═══════════ --}}
    <section id="features" class="py-24 relative">
        <div class="max-w-6xl mx-auto px-4">
            <div class="text-center mb-16 reveal">
                <span class="inline-block px-4 py-2 bg-yellow-500/10 text-yellow-400 rounded-full text-sm font-semibold mb-4">
                    <i class="fas fa-star mr-2"></i>Features
                </span>
                <h2 class="text-4xl md:text-5xl font-extrabold text-white mb-4">
                    Everything You Need to <span class="gradient-gold">Dominate</span>
                </h2>
                <p class="text-gray-400 text-lg max-w-2xl mx-auto">
                    Powerful tools built specifically for cricket tournament organizers.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 stagger-children">
                {{-- Tournament Management --}}
                <div class="glass-card rounded-xl p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-xl bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center">
                        <i class="fas fa-trophy text-2xl text-gray-900"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2" style="font-family:'Oswald',sans-serif;">Tournament Management</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">
                        Create tournaments with groups, knockouts, fixtures, and automatic point tables. Publish beautiful public pages instantly.
                    </p>
                </div>

                {{-- Live Auctions --}}
                <div class="glass-card rounded-xl p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                        <i class="fas fa-gavel text-2xl text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2" style="font-family:'Oswald',sans-serif;">Live Auctions</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">
                        Run IPL-style live auctions with real-time bidding, LED wall displays, and automatic squad management.
                    </p>
                </div>

                {{-- Live Scoring --}}
                <div class="glass-card rounded-xl p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-xl bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center">
                        <i class="fas fa-baseball-ball text-2xl text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2" style="font-family:'Oswald',sans-serif;">Live Scoring</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">
                        Ball-by-ball scoring with automatic stats, wagon wheels, and real-time scorecard updates for fans.
                    </p>
                </div>

                {{-- Team Management --}}
                <div class="glass-card rounded-xl p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-xl bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center">
                        <i class="fas fa-users text-2xl text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2" style="font-family:'Oswald',sans-serif;">Team Management</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">
                        Manage squads, player registrations, team logos, and jerseys. Let players and teams self-register online.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════ PRICING SECTION ═══════════ --}}
    <section id="pricing" class="py-24 relative">
        <div class="max-w-6xl mx-auto px-4">
            <div class="text-center mb-16 reveal">
                <span class="inline-block px-4 py-2 bg-yellow-500/10 text-yellow-400 rounded-full text-sm font-semibold mb-4">
                    <i class="fas fa-tag mr-2"></i>Pricing
                </span>
                <h2 class="text-4xl md:text-5xl font-extrabold text-white mb-4">
                    Simple, <span class="gradient-gold">Transparent</span> Pricing
                </h2>
                <p class="text-gray-400 text-lg">Start free. Upgrade when you're ready.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 stagger-children">
                {{-- Starter --}}
                <div class="glass-card rounded-2xl p-8 flex flex-col">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-700/50 text-gray-300 mb-4 w-fit">STARTER</span>
                    <div class="flex items-baseline gap-1 mb-2">
                        <span class="text-4xl font-extrabold text-white">Free</span>
                    </div>
                    <p class="text-sm text-gray-400 mb-6">Perfect for getting started.</p>

                    <ul class="space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Up to 2 tournaments
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Team management
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Live scoring
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Public tournament page
                        </li>
                    </ul>

                    <a href="{{ route('register') }}" class="block w-full text-center py-3 rounded-xl border border-gray-600 text-white font-semibold hover:bg-white/5 transition">
                        Get Started
                    </a>
                </div>

                {{-- Premium --}}
                <div class="glass-card pricing-card-popular rounded-2xl p-8 flex flex-col relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="inline-block px-4 py-1 rounded-full text-xs font-bold bg-yellow-500 text-gray-900 shadow-lg">MOST POPULAR</span>
                    </div>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-yellow-500/20 text-yellow-400 mb-4 mt-2 w-fit">PREMIUM</span>
                    <div class="flex items-baseline gap-1 mb-2">
                        <span class="text-4xl font-extrabold text-white">Custom</span>
                    </div>
                    <p class="text-sm text-gray-400 mb-6">For growing organizations.</p>

                    <ul class="space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Up to 10 tournaments
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Everything in Starter
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Live & closed bid auctions
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Poster generation
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> LED wall display
                        </li>
                    </ul>

                    <a href="mailto:sportzley@gmail.com" class="block w-full text-center py-3 rounded-xl btn-primary font-bold transition">
                        Contact Us
                    </a>
                </div>

                {{-- Enterprise --}}
                <div class="glass-card rounded-2xl p-8 flex flex-col">
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-300 mb-4 w-fit">ENTERPRISE</span>
                    <div class="flex items-baseline gap-1 mb-2">
                        <span class="text-4xl font-extrabold text-white">Custom</span>
                    </div>
                    <p class="text-sm text-gray-400 mb-6">For large organizations.</p>

                    <ul class="space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Unlimited tournaments
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Everything in Premium
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Custom branding
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 w-4"></i> Dedicated support
                        </li>
                    </ul>

                    <a href="mailto:sportzley@gmail.com" class="block w-full text-center py-3 rounded-xl border border-gray-600 text-white font-semibold hover:bg-white/5 transition">
                        Contact Sales
                    </a>
                </div>
            </div>

            <p class="text-center text-sm text-gray-500 mt-8">
                <a href="{{ route('public.pricing') }}" class="text-yellow-400 hover:underline">View full feature comparison <i class="fas fa-arrow-right ml-1"></i></a>
            </p>
        </div>
    </section>

    {{-- ═══════════ CTA / CONTACT SECTION ═══════════ --}}
    <section id="contact" class="py-24 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-yellow-500/5 via-transparent to-blue-500/5 pointer-events-none"></div>
        <div class="relative max-w-4xl mx-auto px-4 text-center">
            <div class="reveal">
                <h2 class="text-4xl md:text-5xl font-extrabold text-white mb-6">
                    Ready to Organize Your<br>
                    <span class="gradient-gold">Next Tournament?</span>
                </h2>
                <p class="text-lg text-gray-400 mb-10 max-w-2xl mx-auto">
                    Join hundreds of organizers who trust Sportzley to run their cricket tournaments. Start free — no credit card required.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-12 reveal" style="transition-delay:0.1s;">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 rounded-xl text-lg font-bold flex items-center gap-2">
                    <i class="fas fa-rocket"></i> Get Started Free
                </a>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-6 reveal" style="transition-delay:0.2s;">
                <a href="mailto:sportzley@gmail.com" class="flex items-center gap-2 text-gray-400 hover:text-yellow-400 transition">
                    <i class="fas fa-envelope"></i> sportzley@gmail.com
                </a>
                <a href="https://wa.me/919876543210" target="_blank" class="flex items-center gap-2 text-gray-400 hover:text-green-400 transition">
                    <i class="fab fa-whatsapp"></i> WhatsApp Us
                </a>
            </div>
        </div>
    </section>

    {{-- ═══════════ FOOTER ═══════════ --}}
    <footer class="border-t border-white/10 py-10">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center">
                        <i class="fas fa-trophy text-gray-900 text-xs"></i>
                    </div>
                    <span class="text-sm text-gray-500">&copy; {{ date('Y') }} Sportzley. All rights reserved.</span>
                </div>
                <div class="flex items-center gap-6 text-sm">
                    <a href="#features" class="text-gray-500 hover:text-yellow-400 transition">Features</a>
                    <a href="{{ route('public.pricing') }}" class="text-gray-500 hover:text-yellow-400 transition">Pricing</a>
                    <a href="{{ route('admin.login') }}" class="text-gray-500 hover:text-yellow-400 transition">Login</a>
                    <a href="{{ route('register') }}" class="text-gray-500 hover:text-yellow-400 transition">Register</a>
                </div>
                <p class="text-xs text-gray-600">
                    Powered by <span class="text-yellow-400">Sportzley</span>
                </p>
            </div>
        </div>
    </footer>

    {{-- Scroll reveal JS --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const els = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale, .stagger-children');
            if (!els.length) return;
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });
            els.forEach(function (el) { observer.observe(el); });

            // Count-up
            document.querySelectorAll('.count-up').forEach(function (el) {
                const cObs = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            cObs.unobserve(entry.target);
                            const target = parseInt(entry.target.getAttribute('data-count'), 10);
                            if (isNaN(target)) return;
                            const dur = 1200, start = performance.now();
                            function tick(now) {
                                const p = Math.min((now - start) / dur, 1);
                                const ease = 1 - Math.pow(1 - p, 3);
                                entry.target.textContent = Math.round(ease * target).toLocaleString();
                                if (p < 1) requestAnimationFrame(tick);
                            }
                            requestAnimationFrame(tick);
                        }
                    });
                }, { threshold: 0.3 });
                cObs.observe(el);
            });
        });
    </script>
</body>
</html>
