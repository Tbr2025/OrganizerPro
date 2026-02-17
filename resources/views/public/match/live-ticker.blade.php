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
                    <div class="text-white/30 text-xs">
                        Press R to refresh • F for fullscreen
                    </div>
                </div>

                <!-- Bottom Gold Bar -->
                <div class="gold-gradient h-1.5"></div>
            </div>
        </div>
    </div>

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

        // Fetch and update scores
        async function fetchScores() {
            try {
                const response = await fetch(`/admin/matches/${matchId}/state`);
                const data = await response.json();

                // Update Team A score
                const teamAScore = `${data.innings1Stats?.runs ?? 0}/${data.innings1Stats?.wickets ?? 0}`;
                const teamAOvers = `(${data.innings1Stats?.overs ?? '0.0'})`;
                updateText('team-a-score', teamAScore, true);
                updateText('team-a-overs', teamAOvers);

                // Update Team B score
                const teamBScore = `${data.innings2Stats?.runs ?? 0}/${data.innings2Stats?.wickets ?? 0}`;
                const teamBOvers = `(${data.innings2Stats?.overs ?? '0.0'})`;
                updateText('team-b-score', teamBScore, true);
                updateText('team-b-overs', teamBOvers);

                // Determine current innings and update CRR
                const isSecondInnings = data.currentInnings === 2;
                const currentRuns = isSecondInnings ? (data.innings2Stats?.runs ?? 0) : (data.innings1Stats?.runs ?? 0);
                const currentOversNum = isSecondInnings ? (data.innings2Stats?.totalOvers ?? 0) : (data.innings1Stats?.totalOvers ?? 0);
                const crr = currentOversNum > 0 ? (currentRuns / currentOversNum).toFixed(2) : '0.00';
                updateText('crr', crr);

                // Show 2nd innings stats
                if (isSecondInnings && data.innings1Stats?.runs > 0) {
                    const target = (data.innings1Stats?.runs ?? 0) + 1;
                    const required = target - (data.innings2Stats?.runs ?? 0);
                    const oversUsed = data.innings2Stats?.totalOvers ?? 0;
                    const remainingOvers = maxOvers - oversUsed;
                    const rrr = remainingOvers > 0 ? (required / remainingOvers).toFixed(2) : '-';

                    document.getElementById('target-section').classList.remove('hidden');
                    document.getElementById('required-section').classList.remove('hidden');
                    document.getElementById('rrr-section').classList.remove('hidden');

                    updateText('target', target.toString());
                    updateText('required', required > 0 ? required.toString() : '0');
                    updateText('rrr', rrr);
                }

                // Update this over
                const thisOverContainer = document.getElementById('this-over');
                thisOverContainer.innerHTML = '';

                const currentOverBalls = data.currentOverBalls || [];
                currentOverBalls.forEach(ball => {
                    thisOverContainer.appendChild(createBallElement(ball));
                });

                // Fill remaining with empty balls
                const legalBalls = currentOverBalls.filter(b => !b.includes('wd') && !b.includes('nb')).length;
                for (let i = legalBalls; i < 6; i++) {
                    const emptyBall = document.createElement('div');
                    emptyBall.className = 'w-8 h-8 rounded-full bg-gray-800/50 border border-gray-700 flex items-center justify-center text-gray-600 text-xs';
                    emptyBall.textContent = '-';
                    thisOverContainer.appendChild(emptyBall);
                }

                // Update Striker Details
                if (data.strikerDetails) {
                    updateText('striker-name', data.strikerDetails.name);
                    updateText('striker-runs', data.strikerDetails.runs, true);
                    updateText('striker-balls', data.strikerDetails.balls);
                    updateText('striker-fours', data.strikerDetails.fours);
                    updateText('striker-sixes', data.strikerDetails.sixes);
                    updateText('striker-sr', data.strikerDetails.strikeRate);
                } else {
                    updateText('striker-name', '-');
                    updateText('striker-runs', '0');
                    updateText('striker-balls', '0');
                    updateText('striker-fours', '0');
                    updateText('striker-sixes', '0');
                    updateText('striker-sr', '0.00');
                }

                // Update Non-Striker Details
                if (data.nonStrikerDetails) {
                    updateText('non-striker-name', data.nonStrikerDetails.name);
                    updateText('non-striker-runs', data.nonStrikerDetails.runs);
                    updateText('non-striker-balls', data.nonStrikerDetails.balls);
                    updateText('non-striker-fours', data.nonStrikerDetails.fours);
                    updateText('non-striker-sixes', data.nonStrikerDetails.sixes);
                } else {
                    updateText('non-striker-name', '-');
                    updateText('non-striker-runs', '0');
                    updateText('non-striker-balls', '0');
                    updateText('non-striker-fours', '0');
                    updateText('non-striker-sixes', '0');
                }

                // Update Partnership
                if (data.partnership) {
                    updateText('partnership-runs', data.partnership.runs);
                    updateText('partnership-balls', data.partnership.balls);
                }

                // Update Bowler Details
                if (data.bowlerDetails) {
                    updateText('bowler-name', data.bowlerDetails.name);
                    updateText('bowler-overs', data.bowlerDetails.overs);
                    updateText('bowler-maidens', data.bowlerDetails.maidens);
                    updateText('bowler-runs', data.bowlerDetails.runs);
                    updateText('bowler-wickets', data.bowlerDetails.wickets);
                    updateText('bowler-economy', data.bowlerDetails.economy);
                } else {
                    updateText('bowler-name', '-');
                    updateText('bowler-overs', '0.0');
                    updateText('bowler-maidens', '0');
                    updateText('bowler-runs', '0');
                    updateText('bowler-wickets', '0');
                    updateText('bowler-economy', '0.00');
                }

                // Update Last Wicket
                if (data.lastWicket) {
                    document.getElementById('last-wicket-section').classList.remove('hidden');
                    updateText('last-wicket-info', `${data.lastWicket.name} ${data.lastWicket.runs} (${data.lastWicket.balls}) - ${data.lastWicket.score}/${data.totalWickets}`);
                }

                // Update match status
                if (data.isAllOut) {
                    updateText('match-status', isSecondInnings ? 'MATCH COMPLETED' : 'INNINGS BREAK');
                }

            } catch (error) {
                console.error('Error fetching scores:', error);
            }
        }

        // Initial fetch
        fetchScores();

        // Refresh every 3 seconds
        setInterval(fetchScores, 3000);

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
