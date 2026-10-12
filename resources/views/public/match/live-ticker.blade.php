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

        /* Stat box styling */
        .stat-box {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div id="ticker-app">
        <!-- TV Logo - Top Right Corner -->
        <div class="fixed top-8 right-8 z-50">
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

        <!-- Main Ticker Container - Bottom of screen -->
        <div class="fixed bottom-0 left-0 right-0 p-6">
            <!-- What the LED console puts up (wicket, boundary, cards, stats), above the strip. -->
            <div id="overlay"></div>

            <!-- Scoreboard Card -->
            <div class="glass rounded-2xl overflow-hidden glow-border slide-in">
                <!-- Top Gold Bar -->
                <div class="gold-gradient h-1.5"></div>

                <!-- Main Score Section -->
                <div class="flex items-stretch">
                    <!-- Team A -->
                    <div class="team-a-gradient px-6 py-3 flex items-center gap-4 min-w-[340px]">
                        <div class="relative">
                            @if($match->teamA?->team_logo)
                                <img src="{{ Storage::url($match->teamA->team_logo) }}"
                                     alt="{{ $match->teamA->name }}"
                                     class="h-14 w-14 object-cover rounded-full bg-white/30 backdrop-blur-sm p-0.5 shadow-lg ring-2 ring-white/40">
                            @else
                                <div class="h-14 w-14 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center ring-2 ring-white/30">
                                    <span class="text-xl font-bold text-white oswald">{{ substr($match->teamA->short_name ?? $match->teamA->name ?? 'A', 0, 3) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <p class="text-white/90 text-sm font-bold uppercase tracking-wider oswald">
                                {{ $match->teamA->short_name ?? $match->teamA->name ?? 'Team A' }}
                            </p>
                            <div class="flex items-baseline gap-2 mt-0.5">
                                <span id="team-a-score" class="text-white text-4xl font-black oswald score-shadow">0/0</span>
                                <span id="team-a-overs" class="text-white/70 text-lg font-semibold oswald">(0.0)</span>
                            </div>
                        </div>
                    </div>

                    <!-- VS Separator -->
                    <div class="ipl-gradient px-2 flex items-center justify-center">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center shadow-lg glow-animate">
                            <span class="text-black font-bold text-xs oswald">VS</span>
                        </div>
                    </div>

                    <!-- Team B -->
                    <div class="team-b-gradient px-6 py-3 flex items-center gap-4 min-w-[340px]">
                        <div class="relative">
                            @if($match->teamB?->team_logo)
                                <img src="{{ Storage::url($match->teamB->team_logo) }}"
                                     alt="{{ $match->teamB->name }}"
                                     class="h-14 w-14 object-cover rounded-full bg-white/30 backdrop-blur-sm p-0.5 shadow-lg ring-2 ring-white/40">
                            @else
                                <div class="h-14 w-14 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center ring-2 ring-white/30">
                                    <span class="text-xl font-bold text-white oswald">{{ substr($match->teamB->short_name ?? $match->teamB->name ?? 'B', 0, 3) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <p class="text-white/90 text-sm font-bold uppercase tracking-wider oswald">
                                {{ $match->teamB->short_name ?? $match->teamB->name ?? 'Team B' }}
                            </p>
                            <div class="flex items-baseline gap-2 mt-0.5">
                                <span id="team-b-score" class="text-white text-4xl font-black oswald score-shadow">0/0</span>
                                <span id="team-b-overs" class="text-white/70 text-lg font-semibold oswald">(0.0)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Panel -->
                    <div class="ipl-gradient flex-1 px-5 py-3 flex items-center justify-around border-l border-white/10">
                        <!-- Current Run Rate -->
                        <div class="text-center stat-box rounded-lg px-4 py-2">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald">CRR</p>
                            <p id="crr" class="text-yellow-400 text-2xl font-bold oswald mt-0.5">0.00</p>
                        </div>

                        <!-- Target (shown in 2nd innings) -->
                        <div id="target-section" class="text-center stat-box rounded-lg px-4 py-2 hidden">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald">TARGET</p>
                            <p id="target" class="text-white text-2xl font-bold oswald mt-0.5">-</p>
                        </div>

                        <!-- Required -->
                        <div id="required-section" class="text-center stat-box rounded-lg px-4 py-2 hidden">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald">NEED</p>
                            <p id="required" class="text-orange-400 text-2xl font-bold oswald mt-0.5">-</p>
                        </div>

                        <!-- Required Run Rate -->
                        <div id="rrr-section" class="text-center stat-box rounded-lg px-4 py-2 hidden">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald">RRR</p>
                            <p id="rrr" class="text-red-400 text-2xl font-bold oswald mt-0.5">-</p>
                        </div>

                        <!-- This Over -->
                        <div class="text-center">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald mb-1.5">THIS OVER</p>
                            <div id="this-over" class="flex gap-1 justify-center">
                                <!-- Balls will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Player Stats Bar - IPL Style -->
                <div class="ipl-gradient border-t border-white/10">
                    <div class="flex items-stretch">
                        <!-- Batsmen Section -->
                        <div class="flex-1 px-5 py-3 border-r border-white/10">
                            <div class="flex items-center gap-6">
                                <!-- Striker -->
                                <div class="flex items-center gap-3 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></span>
                                        <span class="text-yellow-400 text-xs font-bold oswald uppercase">Striker</span>
                                    </div>
                                    <div class="flex items-center gap-3 bg-white/5 rounded-lg px-3 py-1.5 flex-1">
                                        <span id="striker-name" class="text-white font-bold text-sm">-</span>
                                        <div class="flex items-center gap-2 ml-auto">
                                            <span id="striker-runs" class="text-yellow-400 font-black text-lg oswald">0</span>
                                            <span class="text-white/40 text-xs">(</span>
                                            <span id="striker-balls" class="text-white/70 text-sm">0</span>
                                            <span class="text-white/40 text-xs">)</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs border-l border-white/20 pl-2 ml-1">
                                            <span class="text-blue-400"><span id="striker-fours" class="font-bold">0</span>×4</span>
                                            <span class="text-purple-400"><span id="striker-sixes" class="font-bold">0</span>×6</span>
                                            <span class="text-green-400">SR: <span id="striker-sr" class="font-bold">0.00</span></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Non-Striker -->
                                <div class="flex items-center gap-3 flex-1 opacity-70">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 bg-gray-500 rounded-full"></span>
                                        <span class="text-white/50 text-xs font-bold oswald uppercase">Non-Striker</span>
                                    </div>
                                    <div class="flex items-center gap-3 bg-white/5 rounded-lg px-3 py-1.5 flex-1">
                                        <span id="non-striker-name" class="text-white font-semibold text-sm">-</span>
                                        <div class="flex items-center gap-2 ml-auto">
                                            <span id="non-striker-runs" class="text-white font-bold text-lg oswald">0</span>
                                            <span class="text-white/40 text-xs">(</span>
                                            <span id="non-striker-balls" class="text-white/70 text-sm">0</span>
                                            <span class="text-white/40 text-xs">)</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs border-l border-white/20 pl-2 ml-1">
                                            <span class="text-blue-400"><span id="non-striker-fours" class="font-bold">0</span>×4</span>
                                            <span class="text-purple-400"><span id="non-striker-sixes" class="font-bold">0</span>×6</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Partnership -->
                        <div class="px-4 py-3 border-r border-white/10 min-w-[140px]">
                            <p class="text-white/40 text-xs uppercase tracking-wider oswald text-center">Partnership</p>
                            <div class="flex items-center justify-center gap-1 mt-1">
                                <span id="partnership-runs" class="text-cyan-400 font-black text-xl oswald">0</span>
                                <span class="text-white/40 text-xs">(</span>
                                <span id="partnership-balls" class="text-white/60 text-sm">0</span>
                                <span class="text-white/40 text-xs">)</span>
                            </div>
                        </div>

                        <!-- Bowler Section -->
                        <div class="flex-1 px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                    <span class="text-red-400 text-xs font-bold oswald uppercase">Bowler</span>
                                </div>
                                <div class="flex items-center gap-3 bg-white/5 rounded-lg px-3 py-1.5 flex-1">
                                    <span id="bowler-name" class="text-white font-bold text-sm">-</span>
                                    <div class="flex items-center gap-4 ml-auto text-sm">
                                        <div class="text-center">
                                            <span class="text-white/40 text-xs block">O</span>
                                            <span id="bowler-overs" class="text-white font-bold">0.0</span>
                                        </div>
                                        <div class="text-center">
                                            <span class="text-white/40 text-xs block">M</span>
                                            <span id="bowler-maidens" class="text-white font-bold">0</span>
                                        </div>
                                        <div class="text-center">
                                            <span class="text-white/40 text-xs block">R</span>
                                            <span id="bowler-runs" class="text-white font-bold">0</span>
                                        </div>
                                        <div class="text-center">
                                            <span class="text-white/40 text-xs block">W</span>
                                            <span id="bowler-wickets" class="text-green-400 font-black">0</span>
                                        </div>
                                        <div class="text-center border-l border-white/20 pl-3">
                                            <span class="text-white/40 text-xs block">ECON</span>
                                            <span id="bowler-economy" class="text-yellow-400 font-bold">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Last Wicket / Match Status Bar -->
                <div class="ipl-gradient border-t border-white/10 px-5 py-2 flex items-center justify-between">
                    <div id="last-wicket-section" class="flex items-center gap-2 text-sm hidden">
                        <span class="text-red-400 font-bold oswald">LAST WKT:</span>
                        <span id="last-wicket-info" class="text-white/70">-</span>
                    </div>
                    <div id="match-status" class="text-yellow-400 font-medium oswald text-sm mx-auto">
                        {{ $match->overs ?? 20 }} OVERS MATCH
                    </div>
                    <div class="text-white/30 text-xs setup-hint">
                        Press R to refresh • F for fullscreen
                    </div>
                </div>

                <!-- Bottom Gold Bar -->
                <div class="gold-gradient h-1.5"></div>
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
                return card(`<div class="flex items-center gap-8"><span class="ov-word oswald text-red-500">WICKET</span><div>
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
                return card(`<div class="flex items-center gap-8"><div>${head('Batting')}<p class="text-white text-4xl font-bold oswald">${esc(b.name)}</p></div>
                    <p class="ml-auto text-white text-6xl font-bold oswald">${esc(b.runs)} <span class="text-white/60 text-3xl">(${esc(b.balls)})</span></p>
                    <p class="text-white/70 text-2xl">4s ${esc(b.fours)} · 6s ${esc(b.sixes)} · SR ${esc(b.strike_rate)}</p></div>`);
            },
            bowler_card(p, st) {
                const b = p.bowler || st?.bowler || {};
                return card(`<div class="flex items-center gap-8"><div>${head('Bowling')}<p class="text-white text-4xl font-bold oswald">${esc(b.name)}</p></div>
                    <p class="ml-auto text-white text-6xl font-bold oswald">${esc(b.wickets)}-${esc(b.runs)}</p>
                    <p class="text-white/70 text-2xl">${esc(b.overs)} ov · ${esc(b.maidens)} m · Econ ${esc(b.economy)}</p></div>`);
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
            sponsor(p) {
                return card(`${head('Our sponsors')}<div class="flex flex-wrap items-center justify-center gap-10">${(p.sponsors || []).map(x => x.image ? `<img src="${esc(x.image)}" alt="" class="max-h-24 max-w-[320px] object-contain">` : '').join('')}</div>`);
            },
            prematch(p, st) {
                const [a, b] = st?.teams || [];
                return card(`<div class="flex items-center justify-center gap-10">${logoImg(a?.logo)}<p class="text-white text-4xl font-bold oswald">${esc(a?.name)}</p>
                    <span class="text-white/50 text-3xl oswald">vs</span><p class="text-white text-4xl font-bold oswald">${esc(b?.name)}</p>${logoImg(b?.logo)}</div>
                    ${st?.match?.toss ? `<p class="text-center text-yellow-400 text-2xl mt-3">${esc(st.match.toss)}</p>` : ''}`);
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
            const inn = Object.values(data.innings || {}).find(i => i.team && i.team.id === teamId);
            return inn && inn.batted ? inn : null;
        }

        async function fetchScores() {
            clearTimeout(timer);
            try {
                const response = await fetch(STATE_URL, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const data = await response.json();
                lastVersion = data.version;

                // Scores by TEAM (team A's box is team A's, whoever batted first).
                const [teamA, teamB] = data.teams || [];
                const a = teamA ? teamLine(data, teamA.id) : null;
                const b = teamB ? teamLine(data, teamB.id) : null;
                updateText('team-a-score', a ? `${a.runs}/${a.wickets}` : '0/0', true);
                updateText('team-a-overs', `(${a ? a.overs : '0.0'})`);
                updateText('team-b-score', b ? `${b.runs}/${b.wickets}` : '0/0', true);
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

                const statusText = { innings_break: 'INNINGS BREAK', completed: data.result ? data.result.text.toUpperCase() : 'MATCH COMPLETED', prematch: 'UPCOMING', cancelled: 'CANCELLED' }[data.status];
                if (statusText) updateText('match-status', statusText);

                drawOverlay(data);
            } catch (error) {
                console.error('Error fetching scores:', error);
            } finally {
                // Chained, never stacked: a slow response cannot queue a second
                // request behind itself. 15 s with push healthy, else 3 s.
                timer = setTimeout(fetchScores, pushOk ? 15000 : 3000);
            }
        }

        fetchScores();

        try {
            window.pushChannel?.('match.' + matchId)?.listen('.score.updated', e => { if (e.version > lastVersion) fetchScores(); });
            const conn = window.Echo?.connector?.pusher?.connection;
            conn?.bind('connected', () => { pushOk = true; fetchScores(); });
            conn?.bind('unavailable', () => { pushOk = false; });
            conn?.bind('disconnected', () => { pushOk = false; });
        } catch (e) {
            console.warn('[ticker] live updates unavailable, polling only:', e);
        }
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') fetchScores(); });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'r' || e.key === 'R') {
                fetchScores();
            }
            if (e.key === 'f' || e.key === 'F') {
                document.documentElement.requestFullscreen?.() || document.body.webkitRequestFullscreen?.();
            }
        });
    </script>
</body>
</html>
