<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIVE - {{ $match->teamA->name ?? 'Team A' }} vs {{ $match->teamB->name ?? 'Team B' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: 'Roboto', sans-serif;
            background: transparent !important;
            background-color: transparent !important;
            width: 1920px;
            height: 1080px;
            overflow: hidden;
        }
        .oswald { font-family: 'Oswald', sans-serif; }
        /* ?bg=green|black for tools that cannot key a transparent page
           (chroma key on a hardware mixer, a plain TV). Transparent by default. */
        @php $bg = ['green' => '#00b140', 'black' => '#000000'][is_string(request()->query('bg')) ? request()->query('bg') : ''] ?? null; @endphp
        @if($bg)
        html, body { background: {{ $bg }} !important; background-color: {{ $bg }} !important; }
        @endif
        /* The keyboard hint is for whoever sets the screen up, not for viewers. */
        .setup-hint { animation: hint-out 1s ease 6s forwards; }
        @keyframes hint-out { to { opacity: 0; } }

        /* IPL Style Gradients */
        .ipl-gradient {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        }
        .gold-gradient {
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
        }
        .team-a-gradient {
            background: linear-gradient(135deg, {{ $match->teamA->primary_color ?? '#1e40af' }} 0%, {{ $match->teamA->secondary_color ?? '#3b82f6' }} 100%);
        }
        .team-b-gradient {
            background: linear-gradient(135deg, {{ $match->teamB->primary_color ?? '#047857' }} 0%, {{ $match->teamB->secondary_color ?? '#10b981' }} 100%);
        }

        /* Animations */
        @keyframes pulse-live {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
        }
        @keyframes slide-in {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes score-pop {
            0% { transform: scale(1); }
            50% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 20px rgba(255, 210, 0, 0.3); }
            50% { box-shadow: 0 0 40px rgba(255, 210, 0, 0.6); }
        }
        .live-pulse { animation: pulse-live 1.5s ease-in-out infinite; }
        .slide-in { animation: slide-in 0.5s ease-out; }
        .score-updated { animation: score-pop 0.3s ease-out; }
        .glow-animate { animation: glow 2s ease-in-out infinite; }

        /* Glass morphism */
        .glass {
            background: rgba(10, 10, 30, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        /* Score text shadow */
        .score-shadow {
            text-shadow: 0 4px 8px rgba(0,0,0,0.5), 0 2px 4px rgba(0,0,0,0.3);
        }

        /* Border glow effect */
        .glow-border {
            box-shadow: 0 0 30px rgba(255, 210, 0, 0.2), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        /* LED console panels, laid over the video above the strip (see #overlay). */
        #overlay:empty { display: none; }
        .ov { animation: slide-in 0.45s ease-out; max-width: 1240px; margin: 0 auto 14px; }
        .ov-word { font-size: 96px; line-height: 1; font-weight: 700; letter-spacing: 6px; }
        .ov table { width: 100%; border-collapse: collapse; font-size: 26px; }
        .ov th { font-size: 16px; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: 2px; text-align: left; padding: 6px 14px; }
        .ov td { padding: 8px 14px; border-top: 1px solid rgba(255,255,255,.08); color: #fff; }
        .ov .n { text-align: right; font-weight: 700; }

        /* Ticker design (TickerDesign): colours and parts, set from the LED console. */
        #ticker-card, .ov.glass { background: var(--t-bg, rgba(10,10,30,.95)) !important; }
        #ticker-card { border-radius: var(--t-radius, 0) !important; overflow: hidden; }
        .ov { border-radius: var(--t-radius, 16px) !important; }
        #ticker-card .ipl-gradient { background: var(--t-panel, linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)) !important; }
        #ticker-card .gold-gradient { background: var(--t-accent, linear-gradient(135deg, #f7971e 0%, #ffd200 100%)) !important; }
        #ticker-card .text-yellow-400, .ov .text-yellow-400 { color: var(--t-highlight, #facc15) !important; }
        #ticker-card .text-white, .ov .text-white { color: var(--t-text, #fff) !important; }
        body.t-flat-teams .team-a-gradient, body.t-flat-teams .team-b-gradient { background: var(--t-panel) !important; }
        body.t-no-stats .t-stat, body.t-no-this-over #ticker-this-over, body.t-no-players #ticker-players,
        body.t-no-partnership #ticker-partnership, body.t-no-bowler #ticker-bowler, body.t-no-status #ticker-status,
        body.t-no-moments #overlay, body.t-badge-hidden #ticker-badge { display: none !important; }
        body.t-top #ticker-wrap { display: flex; flex-direction: column-reverse; }
        body.t-top .ov { margin: 14px auto 0; }

        /* Green score highlight box */
        .score-box { background: #16a34a; border-radius: 6px; padding: 4px 16px; display: inline-block; }
        /* Dark overlay for logo/score sections */
        .dark-overlay { position: relative; }
        .dark-overlay::before { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,0.5); z-index: 0; }
        .dark-overlay > * { position: relative; z-index: 1; }
        .score-overlay { position: relative; }
        .score-overlay::before { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,0.4); z-index: 0; }
        .score-overlay > * { position: relative; z-index: 1; }
        /* Smaller balls in This Over bar */
        #ticker-this-over #this-over > div { width: 22px !important; height: 22px !important; font-size: 9px !important; }
        /* Pre-match screen */
        #prematch-screen .team-split { position: absolute; inset: 0; display: flex; }
        #prematch-screen .team-split > div { flex: 1; opacity: 0.25; }
        @keyframes prematch-in { from { opacity: 0; transform: scale(1.05); } to { opacity: 1; transform: scale(1); } }
        #prematch-screen .pm-content { animation: prematch-in 0.6s ease-out; }
        /* Control panel */
        #control-panel .ctrl-btn { padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; transition: all 0.15s; border: 1px solid rgba(255,255,255,0.15); background: transparent; color: #fff; }
        #control-panel .ctrl-btn:hover { background: rgba(255,255,255,0.1); }
        #control-panel .ctrl-btn.active { background: #f59e0b; color: #000; border-color: #f59e0b; }
        #control-panel .ctrl-toggle { display: flex; align-items: center; gap: 8px; padding: 4px 0; cursor: pointer; font-size: 13px; }
        #control-panel .ctrl-toggle input[type="checkbox"] { accent-color: #f59e0b; width: 16px; height: 16px; cursor: pointer; }
        /* Extra section toggles */
        body.t-no-score #ticker-score { display: none !important; }
        body.t-no-innings #ticker-innings-badge { display: none !important; }
        body.t-hidden #ticker-card, body.t-hidden #ticker-badge { display: none !important; }
    </style>
</head>
<body>
    <div id="ticker-app">
        <!-- TV Logo - Top Right Corner -->
        <div id="ticker-badge" class="fixed top-8 right-8 z-50">
            <div class="flex items-stretch gap-0 rounded-2xl overflow-hidden shadow-2xl" style="box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                <!-- LIVE Badge -->
                <div class="bg-gradient-to-b from-red-500 to-red-700 px-6 py-4 flex items-center justify-center">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 bg-white rounded-full animate-pulse shadow-lg"></span>
                        <span class="text-white text-xl font-black uppercase tracking-wider oswald">LIVE</span>
                    </div>
                </div>

                <!-- Tournament Info -->
                <div class="bg-gradient-to-b from-gray-800 to-gray-900 px-5 py-3 flex items-center gap-4 border-l-2 border-yellow-500">
                    @if($match->tournament?->logo)
                        <img src="{{ Storage::url($match->tournament->logo) }}"
                             alt="{{ $match->tournament->name }}"
                             class="h-14 w-14 object-contain rounded-lg bg-white/10 p-1">
                    @elseif($match->tournament?->organization?->logo)
                        <img src="{{ Storage::url($match->tournament->organization->logo) }}"
                             alt="{{ $match->tournament->name }}"
                             class="h-14 w-14 object-contain rounded-lg bg-white/10 p-1">
                    @else
                        <div class="h-14 w-14 rounded-lg bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center">
                            <span class="text-xl font-black text-white oswald">{{ substr($match->tournament->name ?? 'T', 0, 2) }}</span>
                        </div>
                    @endif
                    <div>
                        <p class="text-yellow-400 font-black text-xl oswald uppercase tracking-wide">{{ $match->tournament->name ?? 'Tournament' }}</p>
                        <p class="text-white/60 text-sm font-medium">{{ $match->match_date?->format('d M Y') ?? '' }} • {{ $match->venue ?? '' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pre-match Overlay Screen -->
        <div id="prematch-screen" class="fixed inset-0 z-40 hidden" style="overflow:hidden">
            <div class="team-split">
                <div style="background:{{ $match->teamA->primary_color ?? '#1e40af' }}"></div>
                <div style="background:{{ $match->teamB->primary_color ?? '#047857' }}"></div>
            </div>
            <div class="absolute inset-0 bg-black/60"></div>
            <div class="relative z-10 flex flex-col items-center justify-center h-full pm-content">
                @if($match->tournament?->logo)
                    <img src="{{ Storage::url($match->tournament->logo) }}" class="h-24 w-24 object-contain rounded-2xl bg-white/10 p-2 mb-4">
                @endif
                <p class="text-yellow-400 text-2xl font-bold oswald uppercase tracking-widest mb-16">{{ $match->tournament->name ?? 'Tournament' }}</p>
                <div class="flex items-center gap-24">
                    <div class="flex flex-col items-center">
                        @if($match->teamA?->team_logo)
                            <img src="{{ Storage::url($match->teamA->team_logo) }}" class="h-44 w-44 object-contain rounded-full ring-4 ring-white/20 shadow-2xl mb-6" style="background:{{ $match->teamA->primary_color ?? '#1e40af' }}">
                        @else
                            <div class="h-44 w-44 rounded-full flex items-center justify-center ring-4 ring-white/20 shadow-2xl mb-6" style="background:{{ $match->teamA->primary_color ?? '#1e40af' }}">
                                <span class="text-5xl font-black text-white oswald">{{ substr($match->teamA->short_name ?? $match->teamA->name ?? 'A', 0, 3) }}</span>
                            </div>
                        @endif
                        <p class="text-white text-5xl font-black oswald uppercase tracking-wider">{{ $match->teamA->short_name ?? Str::limit($match->teamA->name ?? 'A', 6, '') }}</p>
                        <p class="text-white/40 text-xl oswald mt-1">{{ $match->teamA->name ?? 'Team A' }}</p>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-white/20 text-5xl font-black oswald" style="letter-spacing:6px">VS</span>
                    </div>
                    <div class="flex flex-col items-center">
                        @if($match->teamB?->team_logo)
                            <img src="{{ Storage::url($match->teamB->team_logo) }}" class="h-44 w-44 object-contain rounded-full ring-4 ring-white/20 shadow-2xl mb-6" style="background:{{ $match->teamB->primary_color ?? '#047857' }}">
                        @else
                            <div class="h-44 w-44 rounded-full flex items-center justify-center ring-4 ring-white/20 shadow-2xl mb-6" style="background:{{ $match->teamB->primary_color ?? '#047857' }}">
                                <span class="text-5xl font-black text-white oswald">{{ substr($match->teamB->short_name ?? $match->teamB->name ?? 'B', 0, 3) }}</span>
                            </div>
                        @endif
                        <p class="text-white text-5xl font-black oswald uppercase tracking-wider">{{ $match->teamB->short_name ?? Str::limit($match->teamB->name ?? 'B', 6, '') }}</p>
                        <p class="text-white/40 text-xl oswald mt-1">{{ $match->teamB->name ?? 'Team B' }}</p>
                    </div>
                </div>
                <div class="mt-16 text-center">
                    @if($match->match_number)
                        <p class="text-white/30 text-xl oswald uppercase tracking-widest mb-3">MATCH {{ $match->match_number }}</p>
                    @endif
                    <p class="text-white text-2xl font-bold oswald uppercase">{{ $match->overs ?? 20 }} OVERS</p>
                    @if($match->venue)
                        <p class="text-yellow-400/70 text-xl oswald uppercase tracking-wider mt-4">LIVE FROM {{ strtoupper($match->venue) }}</p>
                    @endif
                    <p class="text-white/30 text-lg oswald mt-2">{{ $match->match_date?->format('d M Y') ?? '' }}</p>
                </div>
            </div>
        </div>

        <!-- Control Panel (press C to toggle) -->
        <div id="control-panel" class="fixed top-4 left-4 z-50 rounded-xl p-4 text-white hidden" style="min-width:260px; background:rgba(17,24,39,0.95); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.1);">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/40 mb-3">Ticker Controls <span class="text-white/20 ml-1">C to close</span></p>
            <div class="flex gap-2 mb-4">
                <button onclick="setView('prematch')" id="btn-prematch" class="ctrl-btn flex-1">Pre-match</button>
                <button onclick="setView('live')" id="btn-live" class="ctrl-btn flex-1 active">Live</button>
            </div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-2">Show / Hide</p>
            <label class="ctrl-toggle"><input type="checkbox" checked onchange="toggleSection('score',this.checked)"> Score</label>
            <label class="ctrl-toggle"><input type="checkbox" checked onchange="toggleSection('players',this.checked)"> Batters</label>
            <label class="ctrl-toggle"><input type="checkbox" checked onchange="toggleSection('bowler',this.checked)"> Bowler</label>
            <label class="ctrl-toggle"><input type="checkbox" checked onchange="toggleSection('stats',this.checked)"> Stats (CRR / Target)</label>
            <label class="ctrl-toggle"><input type="checkbox" checked onchange="toggleSection('this-over',this.checked)"> This Over</label>
            <label class="ctrl-toggle"><input type="checkbox" checked onchange="toggleSection('badge',this.checked)"> Badge</label>
            <label class="ctrl-toggle"><input type="checkbox" checked onchange="toggleSection('moments',this.checked)"> Overlays</label>
            <label class="ctrl-toggle"><input type="checkbox" checked onchange="toggleSection('innings',this.checked)"> Current Innings</label>
            <div class="border-t border-white/10 my-3"></div>
            <div class="flex gap-2">
                <button onclick="hideAll()" class="ctrl-btn flex-1" style="color:#f87171;border-color:rgba(248,113,113,0.3)">Hide All</button>
                <button onclick="showAll()" class="ctrl-btn flex-1" style="color:#4ade80;border-color:rgba(74,222,128,0.3)">Show All</button>
            </div>
        </div>

        <!-- Main Ticker Container - Bottom of screen -->
        <div id="ticker-wrap" class="fixed bottom-0 left-0 right-0">
            <!-- What the LED console puts up (wicket, boundary, cards, stats), above the strip. -->
            <div id="overlay"></div>

            <!-- IPL-Style Flat Ticker Strip -->
            <div id="ticker-card" class="overflow-hidden slide-in">
                <!-- Gold accent bar -->
                <div class="gold-gradient" style="height:4px"></div>

                <!-- Main single-row strip -->
                <div class="flex items-stretch" style="min-height:100px">
                    <!-- 1. Team A Logo (very dark) -->
                    <div class="team-a-gradient dark-overlay flex items-center justify-center" style="width:110px;background:{{ $match->teamA->primary_color ?? '#1e40af' }}">
                        @if($match->teamA?->team_logo)
                            <img src="{{ Storage::url($match->teamA->team_logo) }}"
                                 alt="{{ $match->teamA->name }}"
                                 class="object-contain rounded-full ring-2 ring-white/40 shadow-lg" style="height:72px;width:72px">
                        @else
                            <div class="rounded-full bg-white/10 flex items-center justify-center ring-2 ring-white/25" style="height:72px;width:72px">
                                <span class="text-2xl font-black text-white oswald">{{ substr($match->teamA->short_name ?? $match->teamA->name ?? 'A', 0, 3) }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- 2. Score Block (dark overlay on team color) -->
                    <div id="ticker-score" class="team-a-gradient score-overlay flex flex-col justify-center px-6" style="min-width:280px;background:{{ $match->teamA->primary_color ?? '#1e40af' }}">
                        <div class="flex items-center gap-4">
                            <span class="text-white font-black text-2xl oswald uppercase tracking-wide" style="min-width:48px">{{ $match->teamA->short_name ?? Str::limit($match->teamA->name ?? 'A', 3, '') }}</span>
                            <span id="team-a-score" class="text-white font-black oswald leading-none score-box" style="font-size:40px">0/0</span>
                            <span id="team-a-overs" class="text-white/70 text-lg oswald font-bold">(0.0)</span>
                        </div>
                        <div class="flex items-center gap-3 mt-1.5">
                            <span class="text-white/40 text-sm oswald font-semibold uppercase">v {{ $match->teamB->short_name ?? Str::limit($match->teamB->name ?? 'B', 3, '') }}</span>
                            <span id="team-b-score" class="text-white/30 text-sm oswald font-bold">0/0</span>
                            <span id="team-b-overs" class="text-white/20 text-xs oswald">(0.0)</span>
                            <span class="text-white/15 text-xs oswald ml-auto">#{{ ($match->teamA->short_name ?? 'A') }}v{{ ($match->teamB->short_name ?? 'B') }}</span>
                        </div>
                    </div>

                    <!-- 3. Batters Block (BRIGHT team A color — no overlay) -->
                    <div id="ticker-players" class="team-a-gradient flex-1 flex items-center px-6" style="background:{{ $match->teamA->primary_color ?? '#1e40af' }};min-width:280px">
                        <div class="flex flex-col justify-center w-full" style="max-width:400px">
                            <div class="flex items-center justify-between">
                                <span id="striker-name" class="text-white font-black text-2xl oswald uppercase tracking-wide truncate" style="max-width:200px">-</span>
                                <div class="flex items-baseline gap-4 flex-shrink-0">
                                    <span id="striker-runs" class="text-white font-black oswald" style="font-size:36px">0</span>
                                    <span id="striker-balls" class="text-white/50 text-2xl oswald font-bold">0</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-white/50 text-lg font-bold">✓</span>
                                    <span id="non-striker-name" class="text-white/50 font-bold text-lg oswald uppercase tracking-wide truncate" style="max-width:180px">-</span>
                                </div>
                                <div class="flex items-baseline gap-4 flex-shrink-0">
                                    <span id="non-striker-runs" class="text-white/50 font-bold text-2xl oswald">0</span>
                                    <span id="non-striker-balls" class="text-white/30 text-xl oswald font-bold">0</span>
                                </div>
                            </div>
                            <div class="hidden">
                                <span id="striker-fours">0</span><span id="striker-sixes">0</span><span id="striker-sr">0.00</span>
                                <span id="non-striker-fours">0</span><span id="non-striker-sixes">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Chase / Stats Block (GOLD) -->
                    <div class="gold-gradient flex flex-col items-center justify-center px-6" style="min-width:150px">
                        <div id="ticker-innings-badge" class="text-center mb-1">
                            <span id="current-innings" class="text-black/40 text-[10px] font-bold oswald uppercase tracking-wider">1ST INNINGS</span>
                        </div>
                        <div class="t-stat text-center">
                            <p class="text-black/40 text-sm font-bold oswald uppercase tracking-wider">CRR</p>
                            <p id="crr" class="text-black text-3xl font-black oswald leading-tight">0.00</p>
                        </div>
                        <div id="target-section" class="t-stat text-center mt-1 hidden">
                            <p class="text-black/40 text-sm font-bold oswald uppercase tracking-wider">TARGET</p>
                            <p id="target" class="text-black font-black oswald leading-none" style="font-size:48px">-</p>
                        </div>
                        <div id="required-section" class="t-stat text-center mt-1 hidden">
                            <p class="text-black/40 text-[10px] font-bold oswald uppercase">NEED</p>
                            <p id="required" class="text-black/80 text-xl font-black oswald">-</p>
                        </div>
                    </div>

                    <!-- 5. Bowler Block (BRIGHT team B color — no overlay) -->
                    <div id="ticker-bowler" class="team-b-gradient flex-1 flex items-center px-6" style="background:{{ $match->teamB->primary_color ?? '#047857' }};min-width:280px">
                        <div class="flex flex-col justify-center w-full" style="max-width:400px;margin-left:auto">
                            <div class="flex items-center justify-between">
                                <span id="bowler-name" class="text-white font-black text-2xl oswald uppercase tracking-wide truncate" style="max-width:200px">-</span>
                                <div class="flex items-baseline gap-4 flex-shrink-0">
                                    <span class="text-white font-black oswald" style="font-size:36px"><span id="bowler-wickets">0</span><span class="text-white/40">-</span><span id="bowler-runs">0</span></span>
                                    <span id="bowler-overs" class="text-white/50 text-2xl oswald font-bold">0.0</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <div id="rrr-section" class="t-stat flex items-center gap-3 hidden">
                                    <span class="text-white/50 text-sm font-bold oswald uppercase tracking-wider">REQ RATE</span>
                                    <span id="rrr" class="text-white font-black text-2xl oswald">-</span>
                                </div>
                                <div id="ticker-status">
                                    <span id="match-status" class="text-white/40 text-sm font-bold oswald uppercase tracking-wider">{{ $match->overs ?? 20 }} OVERS MATCH</span>
                                </div>
                            </div>
                            <div class="hidden">
                                <span id="bowler-maidens">0</span>
                                <span id="bowler-economy">0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Team B Logo (very dark) -->
                    <div class="team-b-gradient dark-overlay flex items-center justify-center" style="width:110px;background:{{ $match->teamB->primary_color ?? '#047857' }}">
                        @if($match->teamB?->team_logo)
                            <img src="{{ Storage::url($match->teamB->team_logo) }}"
                                 alt="{{ $match->teamB->name }}"
                                 class="object-contain rounded-full ring-2 ring-white/40 shadow-lg" style="height:72px;width:72px">
                        @else
                            <div class="rounded-full bg-white/10 flex items-center justify-center ring-2 ring-white/25" style="height:72px;width:72px">
                                <span class="text-2xl font-black text-white oswald">{{ substr($match->teamB->short_name ?? $match->teamB->name ?? 'B', 0, 3) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- This Over bar -->
                <div id="ticker-this-over" class="ipl-gradient flex items-center justify-center gap-1.5 border-t border-white/5" style="padding:3px 0">
                    <span class="text-white/25 text-[9px] font-bold oswald uppercase tracking-widest mr-2">THIS OVER</span>
                    <div id="this-over" class="flex gap-1"></div>
                    <span class="text-white/10 text-[8px] setup-hint ml-auto mr-3">R · F</span>
                </div>

                <!-- Hidden sections for JS compatibility -->
                <div class="hidden">
                    <div id="ticker-partnership">
                        <span id="partnership-runs">0</span>
                        <span id="partnership-balls">0</span>
                    </div>
                    <div id="last-wicket-section">
                        <span id="last-wicket-info">-</span>
                    </div>
                </div>

                <!-- Bottom gold accent -->
                <div class="gold-gradient" style="height:2px"></div>
            </div>
        </div>
    </div>

    @include('backend.pages.auction.partials.echo-init')
    <script>
        const matchId = {{ $match->id }};
        const maxOvers = {{ $match->overs ?? 20 }};

        // Update element text with animation
        function updateText(elementId, newValue, animate = false) {
            const element = document.getElementById(elementId);
            if (element && element.textContent !== String(newValue)) {
                element.textContent = newValue;
                if (animate) {
                    element.classList.add('score-updated');
                    setTimeout(() => element.classList.remove('score-updated'), 300);
                }
            }
        }

        // Create ball element for this over display
        function createBallElement(ball) {
            const div = document.createElement('div');
            div.className = 'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold oswald shadow-md';

            if (ball === 'W') {
                div.className += ' bg-red-600 text-white ring-2 ring-red-400';
            } else if (ball === '4') {
                div.className += ' bg-blue-500 text-white';
            } else if (ball === '6') {
                div.className += ' bg-purple-600 text-white ring-2 ring-purple-400';
            } else if (ball.includes('wd') || ball.includes('nb')) {
                div.className += ' bg-yellow-500 text-black';
            } else if (ball === '0') {
                div.className += ' bg-gray-700 text-white/50';
            } else {
                div.className += ' bg-gray-600 text-white';
            }

            div.textContent = ball;
            return div;
        }

        // ---- design ----
        // Colours, size, position and parts from the LED console (TickerDesign).
        // In the console's preview (?preview=1) a draft arrives by postMessage
        // and wins over the saved design until the page reloads.
        const PREVIEW = new URLSearchParams(location.search).has('preview');
        let draftDesign = null;
        function rgba(hex, opacity) {
            const n = parseInt(String(hex).slice(1), 16);
            return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${opacity / 100})`;
        }
        function applyDesign(d) {
            if (!d) return;
            const root = document.documentElement.style, body = document.body.classList;
            root.setProperty('--t-bg', rgba(d.bg, d.opacity));
            root.setProperty('--t-panel', `linear-gradient(135deg, ${d.panel_from} 0%, ${d.panel_to} 100%)`);
            root.setProperty('--t-accent', `linear-gradient(135deg, ${d.accent_from} 0%, ${d.accent_to} 100%)`);
            root.setProperty('--t-highlight', d.highlight);
            root.setProperty('--t-text', d.text);
            root.setProperty('--t-radius', d.radius + 'px');
            body.toggle('t-flat-teams', !d.team_colours);
            [['stats', 'show_stats'], ['this-over', 'show_this_over'], ['players', 'show_players'], ['partnership', 'show_partnership'],
             ['bowler', 'show_bowler'], ['status', 'show_status'], ['moments', 'show_moments']].forEach(([cls, key]) => body.toggle('t-no-' + cls, !d[key]));
            body.toggle('t-badge-hidden', d.badge === 'hidden');
            body.toggle('t-top', d.position === 'top');

            const badge = document.getElementById('ticker-badge');
            badge.style.left = d.badge === 'left' ? '32px' : '';
            badge.style.right = d.badge === 'left' ? 'auto' : '';
            // The badge takes the corner the strip is not in.
            badge.style.top = d.position === 'top' ? 'auto' : '';
            badge.style.bottom = d.position === 'top' ? '32px' : '';

            // Where the strip sits, and its size (scaled from the edge it hugs).
            const w = document.getElementById('ticker-wrap'), s = d.scale / 100;
            w.className = 'fixed';
            Object.assign(w.style, { top: '', bottom: '', left: '', right: '', width: '', padding: '0' });
            w.style[d.position === 'top' ? 'top' : 'bottom'] = d.offset_y + 'px';
            const vertical = d.position === 'top' ? 'top' : 'bottom';
            let translate = '', horizontal = 'center';
            if (d.align === 'stretch') {
                // Full width at any size: widen the box by 1/scale so it still spans after scaling.
                w.style.left = d.offset_x + 'px';
                w.style.width = `calc((100% - ${2 * d.offset_x}px) / ${s})`;
                horizontal = 'left';
            } else {
                w.style.width = d.width + 'px';
                if (d.align === 'left') { w.style.left = d.offset_x + 'px'; horizontal = 'left'; }
                if (d.align === 'right') { w.style.right = d.offset_x + 'px'; horizontal = 'right'; }
                if (d.align === 'center') { w.style.left = '50%'; translate = 'translateX(-50%) '; }
            }
            w.style.transformOrigin = `${horizontal} ${vertical}`;
            w.style.transform = translate + `scale(${s})`;
        }
        applyDesign(@json($design ?? \App\Services\LedWall\TickerDesign::DEFAULTS));
        if (PREVIEW) {
            window.addEventListener('message', e => {
                if (e.origin !== location.origin || !e.data) return;
                if (e.data.type === 'ticker-design') { draftDesign = e.data.design; applyDesign(draftDesign); }
                if (e.data.type === 'ticker-sample') {
                    drawOverlay({ version: -1, overlay: { item_id: -1, panel: 'wicket', ends_in_ms: 8000,
                        payload: { wicket: { name: 'Sample Batter', runs: 34, balls: 22, how_out: 'c Fielder b Bowler' }, score: '87/3 (9.4)', team: { short: 'TEAM' } } } });
                }
            });
        }

        // ---- LED console panels ----
        // The console decides what airs; the ticker shows the same panel above its
        // strip for that panel's time, then clears. Freeze never applies here.
        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const logoImg = src => src ? `<img src="${esc(src)}" alt="" class="h-20 w-20 object-contain rounded-full bg-white/10 p-1">` : '';
        const ballRow = list => (list || []).map(b => {
            const el = createBallElement(String(b));
            el.classList.replace('w-8', 'w-12'); el.classList.replace('h-8', 'h-12'); el.classList.add('text-lg');
            return el.outerHTML;
        }).join('');
        const card = (inner, accent = '#facc15') => `<div class="ov glass rounded-2xl overflow-hidden glow-border"><div class="h-1.5" style="background:${accent}"></div><div class="px-8 py-5">${inner}</div></div>`;
        const head = t => `<p class="text-yellow-400 text-xl font-bold oswald uppercase tracking-wider mb-2">${esc(t)}</p>`;
        const rows = (title, cols, list) => card(head(title) + `<table><tr>${cols.map((c, i) => `<th class="${i ? 'n' : ''}">${esc(c[0])}</th>`).join('')}</tr>`
            + (list || []).slice(0, 5).map(r => `<tr>${cols.map((c, i) => `<td class="${i ? 'n' : ''}">${i ? esc(c[1](r)) : esc(r.name) + (r.team ? ` <span class="text-white/50 text-lg">${esc(r.team)}</span>` : '')}</td>`).join('')}</tr>`).join('') + '</table>');
        const BAT = [['Player'], ['Runs', r => r.runs], ['Balls', r => r.balls ?? r.innings ?? ''], ['SR', r => r.strike_rate ?? '']];
        const BOWL = [['Player'], ['Wkts', r => r.wickets], ['Runs', r => r.best ?? r.runs ?? ''], ['Econ', r => r.economy ?? '']];

        const OVERLAY = {
            wicket(p, st) {
                const w = p.wicket || {};
                const img = w.image ? `<img src="${esc(w.image)}" class="h-24 w-24 object-cover rounded-full ring-2 ring-red-400/50 shadow-lg flex-shrink-0">` : '';
                return card(`<div class="flex items-center gap-8">${img}<span class="ov-word oswald text-red-500">WICKET</span><div>
                    <p class="text-white text-4xl font-bold oswald">${esc(w.name)} ${esc(w.runs)} <span class="text-white/60">(${esc(w.balls)})</span></p>
                    <p class="text-white/60 text-2xl mt-1">${esc(w.how_out || '')}</p></div>
                    <p class="ml-auto text-yellow-400 text-3xl font-bold oswald">${esc(p.team?.short || '')} ${esc(p.score || '')}</p></div>`, '#ef4444');
            },
            boundary(p) {
                const six = p.runs === 6, colour = six ? '#a855f7' : '#3b82f6';
                return card(`<div class="flex items-center gap-8"><span class="ov-word oswald" style="color:${colour}">${six ? 'SIX' : 'FOUR'}</span><div>
                    <p class="text-white text-4xl font-bold oswald">${esc(p.batter?.name || '')}</p>
                    <p class="text-white/60 text-2xl mt-1">${esc(p.batter?.runs ?? '')} (${esc(p.batter?.balls ?? '')})</p></div>
                    <p class="ml-auto text-yellow-400 text-3xl font-bold oswald">${esc(p.score || '')}</p></div>`, colour);
            },
            last_over(p) {
                const o = p.over || {};
                return card(`<div class="flex items-center gap-8"><div>${head('End of over ' + (o.over ?? '') + (o.bowler ? ' · ' + o.bowler : ''))}
                    <div class="flex gap-2">${ballRow(o.balls)}</div></div>
                    <div class="ml-auto text-right"><p class="text-white text-4xl font-bold oswald">${esc(o.runs)} runs · ${esc(o.wickets)} ${o.wickets === 1 ? 'wkt' : 'wkts'}</p>
                    <p class="text-white/60 text-2xl mt-1">${esc(p.score || '')}</p></div></div>`);
            },
            batter_card(p, st) {
                const b = p.batter || st?.striker || {};
                const img = b.image ? `<img src="${esc(b.image)}" class="h-28 w-28 object-cover rounded-full ring-2 ring-yellow-400/50 shadow-lg flex-shrink-0">` : '';
                return card(`<div class="flex items-center gap-8">${img}<div>${head('Batting')}<p class="text-white text-4xl font-bold oswald">${esc(b.name)}</p>
                    <p class="text-white/70 text-2xl mt-2">4s ${esc(b.fours)} · 6s ${esc(b.sixes)} · SR ${esc(b.strike_rate)}</p></div>
                    <p class="ml-auto text-white text-6xl font-bold oswald">${esc(b.runs)} <span class="text-white/60 text-3xl">(${esc(b.balls)})</span></p></div>`);
            },
            bowler_card(p, st) {
                const b = p.bowler || st?.bowler || {};
                const img = b.image ? `<img src="${esc(b.image)}" class="h-28 w-28 object-cover rounded-full ring-2 ring-yellow-400/50 shadow-lg flex-shrink-0">` : '';
                return card(`<div class="flex items-center gap-8">${img}<div>${head('Bowling')}<p class="text-white text-4xl font-bold oswald">${esc(b.name)}</p>
                    <p class="text-white/70 text-2xl mt-2">${esc(b.overs)} ov · ${esc(b.maidens)} m · Econ ${esc(b.economy)}</p></div>
                    <p class="ml-auto text-white text-6xl font-bold oswald">${esc(b.wickets)}-${esc(b.runs)}</p></div>`);
            },
            partnership(p, st) {
                const pt = p.partnership || st?.partnership || {}, a = p.striker || st?.striker, b = p.non_striker || st?.non_striker;
                const who = x => x ? `${esc(x.name)} ${esc(x.runs)} (${esc(x.balls)})` : '';
                return card(`<div class="flex items-center gap-8"><div>${head('Partnership')}<p class="text-white text-6xl font-bold oswald">${esc(pt.runs)} <span class="text-white/60 text-3xl">(${esc(pt.balls)})</span></p></div>
                    <p class="ml-auto text-white text-3xl oswald">${who(a)}${a && b ? ' &nbsp;·&nbsp; ' : ''}${who(b)}</p></div>`);
            },
            innings_summary(p) {
                const i = p.innings || {};
                const bat = (i.batters || []).filter(b => b.balls > 0 || b.runs > 0).sort((x, y) => y.runs - x.runs).slice(0, 3);
                const bowl = (i.bowlers || []).slice().sort((x, y) => y.wickets - x.wickets || x.runs - y.runs).slice(0, 3);
                return card(`<div class="flex items-baseline justify-between">${head(i.team?.name || 'Innings')}
                    <p class="text-white text-4xl font-bold oswald">${esc(i.runs)}/${esc(i.wickets)} <span class="text-white/60 text-2xl">(${esc(i.overs)})</span></p></div>
                    <div class="flex gap-10"><table><tr><th>Batting</th><th class="n">R</th><th class="n">B</th></tr>
                    ${bat.map(b => `<tr><td>${esc(b.name)}${b.out ? '' : '*'}</td><td class="n">${esc(b.runs)}</td><td class="n">${esc(b.balls)}</td></tr>`).join('')}</table>
                    <table><tr><th>Bowling</th><th class="n">W-R</th><th class="n">O</th></tr>
                    ${bowl.map(b => `<tr><td>${esc(b.name)}</td><td class="n">${esc(b.wickets)}-${esc(b.runs)}</td><td class="n">${esc(b.overs)}</td></tr>`).join('')}</table></div>`);
            },
            top_batting: p => rows('Top batting — this match', BAT, p.rows),
            top_bowling: p => rows('Top bowling — this match', BOWL, p.rows),
            tournament_batting: p => rows('Top run scorers — tournament', BAT, p.rows),
            tournament_bowling: p => rows('Top wicket takers — tournament', [BOWL[0], BOWL[1], ['Best', r => r.best ?? r.runs ?? ''], BOWL[3]], p.rows),
            result(p, st) {
                const r = p.result || st?.result || {};
                return card(`<div class="flex items-center justify-center gap-6">${logoImg(r.winner?.logo)}<p class="text-green-400 text-5xl font-bold oswald uppercase">${esc(r.text)}</p></div>`, '#22c55e');
            },
            sponsor() { return ''; },
            prematch(p, st) {
                const [a, b] = st?.teams || [];
                return card(`<div class="flex items-center justify-center gap-10">${logoImg(a?.logo)}<p class="text-white text-4xl font-bold oswald">${esc(a?.name)}</p>
                    <span class="text-white/50 text-3xl oswald">vs</span><p class="text-white text-4xl font-bold oswald">${esc(b?.name)}</p>${logoImg(b?.logo)}</div>
                    ${st?.match?.toss ? `<p class="text-center text-yellow-400 text-2xl mt-3">${esc(st.match.toss)}</p>` : ''}`);
            },
            playing_xi(p, st) {
                const lineups = p.lineups || st?.lineups || {};
                const sides = Object.values(lineups);
                if (!sides.length) return '';
                const col = side => {
                    const team = side.team || {};
                    const players = (side.players || []).slice(0, 11);
                    return `<div class="flex-1">${head(team.name || 'Team')}<table>
                        <tr><th>#</th><th>Player</th><th class="n">Role</th></tr>
                        ${players.map((p, i) => `<tr><td>${esc(p.batting_order ?? i + 1)}</td><td>${esc(p.name)}</td><td class="n">${esc(p.role || '')}</td></tr>`).join('')}</table></div>`;
                };
                return card(`${head('Playing XI')}<div class="flex gap-10">${sides.map(col).join('')}</div>`);
            },
            full_scorecard(p) {
                const i = p.innings || {};
                const bat = (i.batters || []);
                const bowl = (i.bowlers || []);
                return card(`<div class="flex items-baseline justify-between">${head(i.team?.name || 'Innings')}
                    <p class="text-white text-4xl font-bold oswald">${esc(i.runs)}/${esc(i.wickets)} <span class="text-white/60 text-2xl">(${esc(i.overs)})</span></p></div>
                    <div class="flex gap-10"><table class="flex-1"><tr><th>Batter</th><th>How out</th><th class="n">R</th><th class="n">B</th><th class="n">4s</th><th class="n">6s</th><th class="n">SR</th></tr>
                    ${bat.map(b => `<tr><td>${esc(b.name)}${b.out ? '' : '*'}</td><td class="text-white/40">${esc(b.how_out || 'not out')}</td><td class="n">${esc(b.runs)}</td><td class="n">${esc(b.balls)}</td><td class="n">${esc(b.fours)}</td><td class="n">${esc(b.sixes)}</td><td class="n">${esc(b.strike_rate)}</td></tr>`).join('')}
                    <tr><td colspan="2" class="text-white/40">Extras</td><td class="n" colspan="5">${esc(i.extras?.total ?? 0)}</td></tr></table>
                    <table><tr><th>Bowler</th><th class="n">O</th><th class="n">M</th><th class="n">R</th><th class="n">W</th><th class="n">Econ</th></tr>
                    ${bowl.map(b => `<tr><td>${esc(b.name)}</td><td class="n">${esc(b.overs)}</td><td class="n">${esc(b.maidens)}</td><td class="n">${esc(b.runs)}</td><td class="n">${esc(b.wickets)}</td><td class="n">${esc(b.economy)}</td></tr>`).join('')}</table></div>`);
            },
            opening(p, st) {
                const s = p.striker || {}, n = p.non_striker || {}, bw = p.bowler;
                return card(`<div class="flex items-center justify-center gap-8">${head('Opening pair')}
                    <p class="text-white text-4xl font-bold oswald">${esc(s.name)}</p>
                    <span class="text-white/50 text-3xl oswald">&amp;</span>
                    <p class="text-white text-4xl font-bold oswald">${esc(n.name)}</p>
                    ${bw ? `<span class="text-white/50 text-2xl ml-6">vs ${esc(bw.name)}</span>` : ''}</div>`);
            },
        };

        let shownOverlay = null, overlayTimer = null, ledChannel = null;
        function drawOverlay(data) {
            const o = data.overlay || {};
            const box = document.getElementById('overlay');
            const key = o.item_id ? o.item_id + ':' + data.version : null;
            if (key !== shownOverlay) {
                let html = '';
                if (o.item_id && OVERLAY[o.panel]) {
                    try { html = OVERLAY[o.panel](o.payload || {}, data); } catch (e) { html = ''; }
                }
                box.innerHTML = html;
                shownOverlay = key;
            }
            // Clear it on time, and ask what comes next.
            clearTimeout(overlayTimer);
            if (o.item_id && o.ends_in_ms) {
                overlayTimer = setTimeout(() => { box.innerHTML = ''; shownOverlay = null; fetchScores(); }, o.ends_in_ms + 150);
            }
            // The console nudges this channel the moment it puts something up.
            if (o.display_id && !ledChannel) {
                ledChannel = window.pushChannel?.('led.' + o.display_id) || null;
                ledChannel?.listen('.led.changed', () => fetchScores());
            }
        }

        // Public state (names and scores only). This used to poll the admin-only
        // /admin/matches/{id}/state, so a logged-out screen stayed at 0/0.
        const STATE_URL = @json(route('public.live-state', $match));
        let lastVersion = -1, timer = null, pushOk = false;

        function teamLine(data, teamId) {
            const inn = Object.values(data.innings || {}).filter(i => i.team && i.team.id === teamId && i.batted).pop();
            return inn && inn.batted ? inn : null;
        }

        async function fetchScores(pushData) {
            clearTimeout(timer);
            try {
                const data = pushData || await fetch(STATE_URL, { headers: { Accept: 'application/json' }, cache: 'no-store' }).then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
                lastVersion = data.version;

                // Scores by TEAM (team A's box is team A's, whoever batted first).
                const [teamA, teamB] = data.teams || [];
                const a = teamA ? teamLine(data, teamA.id) : null;
                const b = teamB ? teamLine(data, teamB.id) : null;
                // Two innings each: the side's innings in turn, "245/10 & 180/6".
                const agg = id => (data.innings_per_team || 1) > 1 ? (data.team_scores || []).find(t => t.team && t.team.id === id)?.display : null;
                updateText('team-a-score', teamA && agg(teamA.id) ? agg(teamA.id) : (a ? `${a.runs}/${a.wickets}` : '0/0'), true);
                updateText('team-a-overs', `(${a ? a.overs : '0.0'})`);
                updateText('team-b-score', teamB && agg(teamB.id) ? agg(teamB.id) : (b ? `${b.runs}/${b.wickets}` : '0/0'), true);
                updateText('team-b-overs', `(${b ? b.overs : '0.0'})`);

                updateText('crr', Number(data.score?.run_rate ?? 0).toFixed(2));

                if (data.chase) {
                    document.getElementById('target-section').classList.remove('hidden');
                    document.getElementById('required-section').classList.remove('hidden');
                    document.getElementById('rrr-section').classList.remove('hidden');
                    updateText('target', String(data.chase.target));
                    updateText('required', String(data.chase.runs_needed));
                    updateText('rrr', data.chase.required_run_rate === null ? '-' : Number(data.chase.required_run_rate).toFixed(2));
                }

                // This over
                const thisOverContainer = document.getElementById('this-over');
                thisOverContainer.innerHTML = '';
                const currentOverBalls = data.score?.this_over || [];
                currentOverBalls.forEach(ball => thisOverContainer.appendChild(createBallElement(ball)));
                const legalBalls = currentOverBalls.filter(b => !b.includes('wd') && !b.includes('nb')).length;
                for (let i = legalBalls; i < 6; i++) {
                    const emptyBall = document.createElement('div');
                    emptyBall.className = 'w-8 h-8 rounded-full bg-gray-800/50 border border-gray-700 flex items-center justify-center text-gray-600 text-xs';
                    emptyBall.textContent = '-';
                    thisOverContainer.appendChild(emptyBall);
                }

                const s = data.striker, n = data.non_striker, bw = data.bowler;
                updateText('striker-name', s ? s.name : '-');
                updateText('striker-runs', s ? s.runs : 0, true);
                updateText('striker-balls', s ? s.balls : 0);
                updateText('striker-fours', s ? s.fours : 0);
                updateText('striker-sixes', s ? s.sixes : 0);
                updateText('striker-sr', s ? s.strike_rate : '0.00');
                updateText('non-striker-name', n ? n.name : '-');
                updateText('non-striker-runs', n ? n.runs : 0);
                updateText('non-striker-balls', n ? n.balls : 0);
                updateText('non-striker-fours', n ? n.fours : 0);
                updateText('non-striker-sixes', n ? n.sixes : 0);

                if (data.partnership) {
                    updateText('partnership-runs', data.partnership.runs);
                    updateText('partnership-balls', data.partnership.balls);
                }

                updateText('bowler-name', bw ? bw.name : '-');
                updateText('bowler-overs', bw ? bw.overs : '0.0');
                updateText('bowler-maidens', bw ? bw.maidens : 0);
                updateText('bowler-runs', bw ? bw.runs : 0);
                updateText('bowler-wickets', bw ? bw.wickets : 0);
                updateText('bowler-economy', bw ? bw.economy : '0.00');

                if (data.last_wicket) {
                    document.getElementById('last-wicket-section').classList.remove('hidden');
                    const w = data.last_wicket;
                    updateText('last-wicket-info', `${w.name} ${w.runs} (${w.balls}) - ${w.score}/${w.wicket}`);
                }

                const statusText = { innings_break: data.break?.text ? 'INNINGS BREAK · ' + data.break.text.toUpperCase() : 'INNINGS BREAK', completed: data.result ? data.result.text.toUpperCase() : 'MATCH COMPLETED', prematch: 'UPCOMING', cancelled: 'CANCELLED',
                    live: (data.in_powerplay ? 'POWERPLAY · ' : '') + `${data.match?.overs_limit ?? maxOvers} OVERS MATCH` + ((data.total_innings || 2) > 2 ? ` · INNINGS ${data.current_innings} OF ${data.total_innings}` : '') }[data.status];
                if (statusText) updateText('match-status', statusText);

                // Current innings indicator
                const inn = data.current_innings || 1;
                const suffix = inn === 1 ? 'ST' : inn === 2 ? 'ND' : inn === 3 ? 'RD' : 'TH';
                updateText('current-innings', inn + suffix + ' INNINGS');

                drawOverlay(data);
                applyDesign(draftDesign || data.design);
            } catch (error) {
                console.error('Error fetching scores:', error);
            } finally {
                // Chained, never stacked: a slow response cannot queue a second
                // request behind itself. 15 s with push healthy, else 3 s.
                timer = setTimeout(fetchScores, pushOk ? 30000 : 5000);
            }
        }

        fetchScores();

        try {
            window.pushChannel?.('match.' + matchId)?.listen('.score.updated', e => { if (e.version > lastVersion) { e.score ? fetchScores(e) : fetchScores(); } });
            const conn = window.Echo?.connector?.pusher?.connection;
            conn?.bind('connected', () => { pushOk = true; fetchScores(); });
            conn?.bind('unavailable', () => { pushOk = false; });
            conn?.bind('disconnected', () => { pushOk = false; });
        } catch (e) {
            console.warn('[ticker] live updates unavailable, polling only:', e);
        }
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') fetchScores(); });

        // ---- control panel ----
        let currentView = 'live';
        const sections = ['score', 'players', 'bowler', 'stats', 'this-over', 'badge', 'moments', 'innings'];

        function setView(view) {
            currentView = view;
            document.getElementById('prematch-screen').classList.toggle('hidden', view !== 'prematch');
            document.getElementById('ticker-wrap').style.display = view === 'prematch' ? 'none' : '';
            document.getElementById('btn-prematch').classList.toggle('active', view === 'prematch');
            document.getElementById('btn-live').classList.toggle('active', view === 'live');
        }

        function toggleSection(section, show) {
            if (section === 'badge') {
                document.body.classList.toggle('t-badge-hidden', !show);
            } else {
                document.body.classList.toggle('t-no-' + section, !show);
            }
        }

        function hideAll() {
            document.body.classList.add('t-hidden');
            document.querySelectorAll('#control-panel input[type=checkbox]').forEach(cb => { cb.checked = false; });
            sections.forEach(s => toggleSection(s, false));
        }

        function showAll() {
            document.body.classList.remove('t-hidden');
            document.querySelectorAll('#control-panel input[type=checkbox]').forEach(cb => { cb.checked = true; });
            sections.forEach(s => toggleSection(s, true));
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'r' || e.key === 'R') {
                fetchScores();
            }
            if (e.key === 'f' || e.key === 'F') {
                document.documentElement.requestFullscreen?.() || document.body.webkitRequestFullscreen?.();
            }
            if (e.key === 'c' || e.key === 'C') {
                document.getElementById('control-panel').classList.toggle('hidden');
            }
            if (e.key === 'p' || e.key === 'P') {
                setView(currentView === 'prematch' ? 'live' : 'prematch');
            }
            if (e.key === 'h' || e.key === 'H') {
                const hidden = document.body.classList.toggle('t-hidden');
                if (!hidden) showAll();
            }
        });
    </script>
</body>
</html>
