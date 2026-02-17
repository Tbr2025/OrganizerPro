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
        body {
            font-family: 'Roboto', sans-serif;
            background: transparent;
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
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes score-pop {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .live-pulse { animation: pulse-live 1.5s ease-in-out infinite; }
        .slide-in { animation: slide-in 0.5s ease-out; }
        .score-updated { animation: score-pop 0.3s ease-out; }

        /* Glass morphism */
        .glass {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        /* Score text shadow */
        .score-shadow {
            text-shadow: 0 4px 8px rgba(0,0,0,0.5), 0 2px 4px rgba(0,0,0,0.3);
        }

        /* Border glow effect */
        .glow-border {
            box-shadow: 0 0 20px rgba(255, 210, 0, 0.3), inset 0 1px 0 rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div id="ticker-app">
        <!-- Main Ticker Container - Bottom of screen -->
        <div class="fixed bottom-0 left-0 right-0 p-8">
            <!-- Scoreboard Card -->
            <div class="glass rounded-2xl overflow-hidden glow-border slide-in">
                <!-- Top Gold Bar -->
                <div class="gold-gradient h-1"></div>

                <div class="flex items-stretch">
                    <!-- Tournament Logo & Info -->
                    <div class="ipl-gradient px-6 py-4 flex items-center gap-4 min-w-[280px] border-r border-white/10">
                        @if($match->tournament?->logo)
                            <img src="{{ asset('storage/' . $match->tournament->logo) }}"
                                 alt="{{ $match->tournament->name }}"
                                 class="h-16 w-16 object-contain drop-shadow-lg">
                        @else
                            <div class="h-16 w-16 rounded-lg bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center">
                                <span class="text-2xl font-bold text-white oswald">{{ substr($match->tournament->name ?? 'T', 0, 1) }}</span>
                            </div>
                        @endif
                        <div>
                            <p class="text-yellow-400 text-sm font-semibold uppercase tracking-wider oswald">{{ $match->tournament->name ?? 'Tournament' }}</p>
                            <p class="text-white/60 text-xs mt-1">{{ $match->name ?? 'Match' }}</p>
                            <p class="text-white/40 text-xs">{{ $match->venue ?? '' }}</p>
                        </div>
                    </div>

                    <!-- LIVE Badge -->
                    <div class="ipl-gradient px-4 flex items-center justify-center border-r border-white/10">
                        <div id="live-badge" class="flex items-center gap-2 px-4 py-2 bg-red-600 rounded-lg live-pulse">
                            <span class="w-3 h-3 bg-white rounded-full"></span>
                            <span class="text-white text-sm font-bold uppercase tracking-wider oswald">LIVE</span>
                        </div>
                    </div>

                    <!-- Team A -->
                    <div class="team-a-gradient px-8 py-4 flex items-center gap-5 min-w-[380px]">
                        <div class="relative">
                            @if($match->teamA?->logo)
                                <img src="{{ asset('storage/' . $match->teamA->logo) }}"
                                     alt="{{ $match->teamA->name }}"
                                     class="h-16 w-16 object-contain rounded-xl bg-white/20 p-1 shadow-lg">
                            @else
                                <div class="h-16 w-16 rounded-xl bg-white/20 flex items-center justify-center">
                                    <span class="text-2xl font-bold text-white oswald">{{ substr($match->teamA->short_name ?? $match->teamA->name ?? 'A', 0, 3) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <p class="text-white/80 text-sm font-semibold uppercase tracking-wider oswald">
                                {{ $match->teamA->short_name ?? $match->teamA->name ?? 'Team A' }}
                            </p>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span id="team-a-score" class="text-white text-5xl font-black oswald score-shadow">0/0</span>
                                <span id="team-a-overs" class="text-white/70 text-xl font-semibold oswald">(0.0)</span>
                            </div>
                        </div>
                    </div>

                    <!-- VS Separator -->
                    <div class="ipl-gradient px-3 flex items-center justify-center">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center shadow-lg">
                            <span class="text-black font-bold text-sm oswald">VS</span>
                        </div>
                    </div>

                    <!-- Team B -->
                    <div class="team-b-gradient px-8 py-4 flex items-center gap-5 min-w-[380px]">
                        <div class="relative">
                            @if($match->teamB?->logo)
                                <img src="{{ asset('storage/' . $match->teamB->logo) }}"
                                     alt="{{ $match->teamB->name }}"
                                     class="h-16 w-16 object-contain rounded-xl bg-white/20 p-1 shadow-lg">
                            @else
                                <div class="h-16 w-16 rounded-xl bg-white/20 flex items-center justify-center">
                                    <span class="text-2xl font-bold text-white oswald">{{ substr($match->teamB->short_name ?? $match->teamB->name ?? 'B', 0, 3) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <p class="text-white/80 text-sm font-semibold uppercase tracking-wider oswald">
                                {{ $match->teamB->short_name ?? $match->teamB->name ?? 'Team B' }}
                            </p>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span id="team-b-score" class="text-white text-5xl font-black oswald score-shadow">0/0</span>
                                <span id="team-b-overs" class="text-white/70 text-xl font-semibold oswald">(0.0)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Panel -->
                    <div class="ipl-gradient flex-1 px-6 py-4 flex items-center justify-around border-l border-white/10">
                        <!-- Current Run Rate -->
                        <div class="text-center">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald">CRR</p>
                            <p id="crr" class="text-yellow-400 text-3xl font-bold oswald mt-1">0.00</p>
                        </div>

                        <!-- Target (shown in 2nd innings) -->
                        <div id="target-section" class="text-center hidden">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald">TARGET</p>
                            <p id="target" class="text-white text-3xl font-bold oswald mt-1">-</p>
                        </div>

                        <!-- Required -->
                        <div id="required-section" class="text-center hidden">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald">NEED</p>
                            <p id="required" class="text-orange-400 text-3xl font-bold oswald mt-1">-</p>
                        </div>

                        <!-- Required Run Rate -->
                        <div id="rrr-section" class="text-center hidden">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald">RRR</p>
                            <p id="rrr" class="text-red-400 text-3xl font-bold oswald mt-1">-</p>
                        </div>

                        <!-- This Over -->
                        <div class="text-center">
                            <p class="text-white/50 text-xs uppercase tracking-wider oswald mb-2">THIS OVER</p>
                            <div id="this-over" class="flex gap-1.5 justify-center">
                                <!-- Balls will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Bar - Batsmen & Bowler Info -->
                <div class="ipl-gradient border-t border-white/10 px-6 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-8">
                        <!-- Striker -->
                        <div class="flex items-center gap-3">
                            <span class="text-yellow-400 text-lg">*</span>
                            <span id="striker-name" class="text-white font-semibold">-</span>
                            <span id="striker-stats" class="text-white/60 text-sm">0 (0)</span>
                        </div>
                        <!-- Non-Striker -->
                        <div class="flex items-center gap-3 opacity-70">
                            <span id="non-striker-name" class="text-white font-semibold">-</span>
                            <span id="non-striker-stats" class="text-white/60 text-sm">0 (0)</span>
                        </div>
                    </div>

                    <!-- Match Status -->
                    <div id="match-status" class="text-yellow-400 font-medium oswald text-sm">
                        {{ $match->overs ?? 20 }} OVERS MATCH
                    </div>

                    <!-- Bowler -->
                    <div class="flex items-center gap-3">
                        <span class="text-white/50 text-sm">Bowling:</span>
                        <span id="bowler-name" class="text-white font-semibold">-</span>
                        <span id="bowler-stats" class="text-white/60 text-sm">0-0-0-0</span>
                    </div>
                </div>

                <!-- Bottom Gold Bar -->
                <div class="gold-gradient h-1"></div>
            </div>
        </div>
    </div>

    <script>
        const matchId = {{ $match->id }};
        const teamAPlayerIds = @json($match->teamA?->players?->pluck('player_id')->toArray() ?? []);
        const teamBPlayerIds = @json($match->teamB?->players?->pluck('player_id')->toArray() ?? []);
        const maxOvers = {{ $match->overs ?? 20 }};

        let previousScoreA = null;
        let previousScoreB = null;

        // Update score display with animation
        function updateScore(elementId, newValue, animate = true) {
            const element = document.getElementById(elementId);
            if (element && element.textContent !== newValue) {
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
            div.className = 'w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold oswald shadow-md';

            if (ball === 'W') {
                div.className += ' bg-red-600 text-white';
            } else if (ball === '4') {
                div.className += ' bg-blue-500 text-white';
            } else if (ball === '6') {
                div.className += ' bg-purple-600 text-white';
            } else if (ball.includes('wd') || ball.includes('nb')) {
                div.className += ' bg-yellow-500 text-black';
            } else if (ball === '0') {
                div.className += ' bg-gray-700 text-white/70';
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
                updateScore('team-a-score', teamAScore);
                updateScore('team-a-overs', teamAOvers, false);

                // Update Team B score
                const teamBScore = `${data.innings2Stats?.runs ?? 0}/${data.innings2Stats?.wickets ?? 0}`;
                const teamBOvers = `(${data.innings2Stats?.overs ?? '0.0'})`;
                updateScore('team-b-score', teamBScore);
                updateScore('team-b-overs', teamBOvers, false);

                // Determine current innings and update CRR
                const isSecondInnings = data.currentInnings === 2;
                const currentRuns = isSecondInnings ? (data.innings2Stats?.runs ?? 0) : (data.innings1Stats?.runs ?? 0);
                const currentOvers = isSecondInnings ? (data.innings2Stats?.totalOvers ?? 0) : (data.innings1Stats?.totalOvers ?? 0);
                const crr = currentOvers > 0 ? (currentRuns / currentOvers).toFixed(2) : '0.00';
                updateScore('crr', crr, false);

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

                    updateScore('target', target.toString(), false);
                    updateScore('required', required > 0 ? required.toString() : '0', false);
                    updateScore('rrr', rrr, false);
                }

                // Update this over
                const thisOverContainer = document.getElementById('this-over');
                thisOverContainer.innerHTML = '';

                const currentOverBalls = data.currentOverBalls || [];
                currentOverBalls.forEach(ball => {
                    thisOverContainer.appendChild(createBallElement(ball));
                });

                // Fill remaining with empty balls
                for (let i = currentOverBalls.length; i < 6; i++) {
                    const emptyBall = document.createElement('div');
                    emptyBall.className = 'w-9 h-9 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-600 text-sm';
                    emptyBall.textContent = '-';
                    thisOverContainer.appendChild(emptyBall);
                }

                // Update match status if innings complete or match ended
                if (data.isAllOut) {
                    document.getElementById('match-status').textContent = isSecondInnings ? 'MATCH COMPLETED' : 'INNINGS BREAK';
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
