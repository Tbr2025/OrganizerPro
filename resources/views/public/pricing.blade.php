<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - {{ config('app.name') }}</title>
    <meta name="description" content="Choose the perfect plan for your cricket tournament management needs.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .gradient-text {
            background: linear-gradient(135deg, #a78bfa, #818cf8, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            mix-blend-mode: multiply;
            animation: float 8s ease-in-out infinite;
            opacity: 0.3;
        }

        .orb-1 {
            width: 400px; height: 400px;
            background: #7c3aed;
            top: -100px; left: -100px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 350px; height: 350px;
            background: #2563eb;
            top: 200px; right: -80px;
            animation-delay: -3s;
        }

        .orb-3 {
            width: 300px; height: 300px;
            background: #7c3aed;
            bottom: -50px; left: 30%;
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }

        .featured-ring {
            box-shadow: 0 0 0 2px rgba(129, 140, 248, 0.5), 0 0 40px rgba(129, 140, 248, 0.15);
        }
    </style>
</head>
<body class="min-h-screen bg-slate-900 text-white overflow-x-hidden">

    {{-- Animated Background Orbs --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="relative z-10">
        {{-- Header --}}
        <header class="pt-8 pb-4 px-6 text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-gray-400 hover:text-white text-sm mb-8 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Home
            </a>
            <h1 class="text-4xl md:text-6xl font-extrabold mb-4">
                <span class="gradient-text">Simple Pricing</span>
            </h1>
            <p class="text-lg text-gray-400 max-w-2xl mx-auto">
                Choose the perfect plan for your cricket tournament management needs. Start free, upgrade as you grow.
            </p>
        </header>

        {{-- Pricing Cards --}}
        <section class="max-w-6xl mx-auto px-6 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                {{-- Starter --}}
                <div class="glass-card rounded-2xl p-8 transition-all duration-300 flex flex-col">
                    <div class="mb-6">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-700/50 text-gray-300 mb-4">STARTER</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold">Free</span>
                        </div>
                        <p class="text-sm text-gray-400 mt-2">Perfect for getting started with small tournaments.</p>
                    </div>

                    <ul class="space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Up to 2 tournaments
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Team management
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Live scoring
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Public tournament page
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-500">
                            <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Auctions
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-500">
                            <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Poster generation
                        </li>
                    </ul>

                    <a href="{{ route('register') }}" class="block w-full text-center py-3 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold transition-all duration-200">
                        Get Started
                    </a>
                </div>

                {{-- Premium (Featured) --}}
                <div class="glass-card featured-ring rounded-2xl p-8 transition-all duration-300 flex flex-col relative">
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                        <span class="inline-block px-4 py-1.5 rounded-full text-xs font-bold bg-indigo-500 text-white shadow-lg shadow-indigo-500/30">MOST POPULAR</span>
                    </div>
                    <div class="mb-6 mt-2">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 mb-4">PREMIUM</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold">Custom</span>
                        </div>
                        <p class="text-sm text-gray-400 mt-2">For growing organizations that need more power.</p>
                    </div>

                    <ul class="space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Up to 10 tournaments
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Everything in Starter
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Open & Closed bid auctions
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Poster generation & templates
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            LED wall display for auctions
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Priority support
                        </li>
                    </ul>

                    <a href="mailto:contact@organizerpro.com" class="block w-full text-center py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition-all duration-200 shadow-lg shadow-indigo-600/30">
                        Contact Us
                    </a>
                </div>

                {{-- Enterprise --}}
                <div class="glass-card rounded-2xl p-8 transition-all duration-300 flex flex-col">
                    <div class="mb-6">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-300 mb-4">ENTERPRISE</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold">Custom</span>
                        </div>
                        <p class="text-sm text-gray-400 mt-2">For large organizations with custom requirements.</p>
                    </div>

                    <ul class="space-y-3 mb-8 flex-1">
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Unlimited tournaments
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Everything in Premium
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            All auction modes (Open, Closed, Offline)
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Custom branding & themes
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Dedicated support
                        </li>
                        <li class="flex items-center gap-3 text-sm text-gray-300">
                            <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            API access & integrations
                        </li>
                    </ul>

                    <a href="mailto:contact@organizerpro.com" class="block w-full text-center py-3 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold transition-all duration-200">
                        Contact Sales
                    </a>
                </div>

            </div>
        </section>

        {{-- Feature Comparison --}}
        <section class="max-w-4xl mx-auto px-6 py-12">
            <h2 class="text-2xl font-bold text-center mb-8">
                <span class="gradient-text">Feature Comparison</span>
            </h2>
            <div class="glass-card rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10">
                            <th class="text-left px-6 py-4 text-gray-400 font-medium">Feature</th>
                            <th class="text-center px-4 py-4 text-gray-400 font-medium">Starter</th>
                            <th class="text-center px-4 py-4 text-indigo-400 font-medium">Premium</th>
                            <th class="text-center px-4 py-4 text-purple-400 font-medium">Enterprise</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $features = [
                                ['Tournaments', '2', '10', 'Unlimited'],
                                ['Team Management', true, true, true],
                                ['Live Scoring', true, true, true],
                                ['Public Pages', true, true, true],
                                ['Player Registration', true, true, true],
                                ['Auctions', false, true, true],
                                ['Offline Auctions', false, false, true],
                                ['LED Wall Display', false, true, true],
                                ['Poster Generation', false, true, true],
                                ['Custom Templates', false, true, true],
                                ['Custom Branding', false, false, true],
                                ['API Access', false, false, true],
                                ['Priority Support', false, true, true],
                                ['Dedicated Support', false, false, true],
                            ];
                        @endphp
                        @foreach($features as $feature)
                            <tr class="border-b border-white/5 hover:bg-white/[0.02]">
                                <td class="px-6 py-3 text-gray-300">{{ $feature[0] }}</td>
                                @for($i = 1; $i <= 3; $i++)
                                    <td class="text-center px-4 py-3">
                                        @if($feature[$i] === true)
                                            <svg class="w-5 h-5 text-green-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        @elseif($feature[$i] === false)
                                            <svg class="w-5 h-5 text-gray-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        @else
                                            <span class="text-gray-300 font-medium">{{ $feature[$i] }}</span>
                                        @endif
                                    </td>
                                @endfor
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- CTA Section --}}
        <section class="max-w-3xl mx-auto px-6 py-16 text-center">
            <h2 class="text-3xl font-bold mb-4">Ready to get started?</h2>
            <p class="text-gray-400 mb-8">Join hundreds of cricket organizations managing their tournaments with OrganizerPro.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('register') }}" class="px-8 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition-all duration-200 shadow-lg shadow-indigo-600/30">
                    Start for Free
                </a>
                <a href="mailto:contact@organizerpro.com" class="px-8 py-3 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold transition-all duration-200">
                    Contact Sales
                </a>
            </div>
        </section>

        {{-- Footer --}}
        <footer class="border-t border-white/10 py-8 text-center text-sm text-gray-500">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
