<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="5">
    <title>Live Ticker - {{ $match->teamA->name ?? 'Team A' }} vs {{ $match->teamB->name ?? 'Team B' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: transparent;
            width: 1920px;
            height: 1080px;
            overflow: hidden;
        }
        .ticker-container {
            position: absolute;
            bottom: 40px;
            left: 40px;
            right: 40px;
        }
        .glass-effect {
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .team-gradient-a {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }
        .team-gradient-b {
            background: linear-gradient(135deg, #047857 0%, #10b981 100%);
        }
        .live-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .score-text {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body>
    @php
        // Get balls data
        $teamAPlayerIds = $match->teamA?->players?->pluck('player_id')->toArray() ?? [];
        $teamBPlayerIds = $match->teamB?->players?->pluck('player_id')->toArray() ?? [];

        $allBalls = \App\Models\Ball::where('match_id', $match->id)
            ->orderBy('over')
            ->orderBy('ball_in_over')
            ->get();

        // Innings 1 = Team A batting
        $innings1Balls = $allBalls->filter(fn($b) => in_array($b->batsman_id, $teamAPlayerIds));
        // Innings 2 = Team B batting
        $innings2Balls = $allBalls->filter(fn($b) => in_array($b->batsman_id, $teamBPlayerIds));

        // Calculate scores
        $teamARuns = $innings1Balls->sum('runs') + $innings1Balls->sum('extra_runs');
        $teamAWickets = $innings1Balls->where('is_wicket', 1)->count();
        $teamAOvers = $innings1Balls->groupBy('over')->count();
        $teamABalls = $innings1Balls->count() % 6;

        $teamBRuns = $innings2Balls->sum('runs') + $innings2Balls->sum('extra_runs');
        $teamBWickets = $innings2Balls->where('is_wicket', 1)->count();
        $teamBOvers = $innings2Balls->groupBy('over')->count();
        $teamBBalls = $innings2Balls->count() % 6;

        // Format overs
        $teamAOversDisplay = $teamAOvers . ($teamABalls > 0 ? '.' . $teamABalls : '');
        $teamBOversDisplay = $teamBOvers . ($teamBBalls > 0 ? '.' . $teamBBalls : '');

        // Determine current innings
        $isSecondInnings = $innings2Balls->isNotEmpty();

        // Current over balls (last 6 balls)
        $currentBalls = $isSecondInnings ? $innings2Balls : $innings1Balls;
        $lastOver = $currentBalls->groupBy('over')->sortKeysDesc()->first();
        $currentOverBalls = $lastOver ? $lastOver->map(function($ball) {
            if ($ball->is_wicket) return 'W';
            if ($ball->extra_type === 'wide') return ($ball->runs + $ball->extra_runs) . 'wd';
            if ($ball->extra_type === 'no_ball') return ($ball->runs + $ball->extra_runs) . 'nb';
            return (string) $ball->runs;
        })->values()->toArray() : [];

        // Get current batsmen and bowler
        $battingTeamPlayers = $isSecondInnings
            ? $match->teamB?->players?->pluck('player')->filter()
            : $match->teamA?->players?->pluck('player')->filter();

        $outBatsmenIds = $currentBalls->where('is_wicket', 1)->pluck('batsman_id')->toArray();
        $activeBatsmenIds = $currentBalls->pluck('batsman_id')->unique()->diff($outBatsmenIds)->values();

        $striker = $activeBatsmenIds->count() > 0
            ? \App\Models\Player::find($activeBatsmenIds->last())
            : null;
        $nonStriker = $activeBatsmenIds->count() > 1
            ? \App\Models\Player::find($activeBatsmenIds->first())
            : null;

        // Batsman stats
        $strikerRuns = $striker ? $currentBalls->where('batsman_id', $striker->id)->sum('runs') : 0;
        $strikerBalls = $striker ? $currentBalls->where('batsman_id', $striker->id)->whereNull('extra_type')->count() : 0;

        // Run rate
        $currentRuns = $isSecondInnings ? $teamBRuns : $teamARuns;
        $currentOvers = $isSecondInnings ? ($teamBOvers + $teamBBalls/6) : ($teamAOvers + $teamABalls/6);
        $runRate = $currentOvers > 0 ? round($currentRuns / $currentOvers, 2) : 0;

        // Target (if 2nd innings)
        $target = $isSecondInnings ? $teamARuns + 1 : null;
        $required = $target ? $target - $teamBRuns : null;
        $remainingOvers = $isSecondInnings ? (($match->overs ?? 20) - $teamBOvers - $teamBBalls/6) : null;
        $requiredRate = ($required && $remainingOvers > 0) ? round($required / $remainingOvers, 2) : null;
    @endphp

    <div class="ticker-container">
        <!-- Main Scoreboard -->
        <div class="glass-effect rounded-2xl overflow-hidden shadow-2xl">
            <div class="flex">
                <!-- Tournament & Match Info -->
                <div class="bg-gradient-to-r from-gray-900 to-gray-800 px-6 py-3 flex items-center gap-4 border-r border-gray-700">
                    @if($match->tournament?->logo)
                        <img src="{{ asset('storage/' . $match->tournament->logo) }}" alt="" class="h-12 w-12 object-contain">
                    @endif
                    <div>
                        <p class="text-gray-400 text-sm">{{ $match->tournament->name ?? 'Tournament' }}</p>
                        <p class="text-white font-semibold">{{ $match->name ?? 'Match' }}</p>
                    </div>
                    @if($match->status === 'live' || $match->status === 'in_progress')
                        <div class="flex items-center gap-2 ml-4 px-3 py-1 bg-red-600 rounded-full">
                            <span class="w-2 h-2 bg-white rounded-full live-pulse"></span>
                            <span class="text-white text-sm font-bold uppercase">LIVE</span>
                        </div>
                    @endif
                </div>

                <!-- Team A Score -->
                <div class="team-gradient-a px-8 py-4 flex items-center gap-6 min-w-[320px]">
                    @if($match->teamA?->logo)
                        <img src="{{ asset('storage/' . $match->teamA->logo) }}" alt="" class="h-14 w-14 object-contain rounded-lg bg-white/20 p-1">
                    @else
                        <div class="h-14 w-14 rounded-lg bg-white/20 flex items-center justify-center">
                            <span class="text-2xl font-bold text-white">{{ substr($match->teamA->short_name ?? $match->teamA->name ?? 'A', 0, 3) }}</span>
                        </div>
                    @endif
                    <div>
                        <p class="text-white/80 text-sm font-medium uppercase tracking-wider">{{ $match->teamA->short_name ?? $match->teamA->name ?? 'Team A' }}</p>
                        <p class="text-white text-4xl font-black score-text">
                            {{ $teamARuns }}/{{ $teamAWickets }}
                            <span class="text-xl font-semibold text-white/70">({{ $teamAOversDisplay }})</span>
                        </p>
                    </div>
                </div>

                <!-- VS Divider -->
                <div class="bg-gray-900 px-4 flex items-center justify-center">
                    <span class="text-gray-500 text-xl font-bold">VS</span>
                </div>

                <!-- Team B Score -->
                <div class="team-gradient-b px-8 py-4 flex items-center gap-6 min-w-[320px]">
                    @if($match->teamB?->logo)
                        <img src="{{ asset('storage/' . $match->teamB->logo) }}" alt="" class="h-14 w-14 object-contain rounded-lg bg-white/20 p-1">
                    @else
                        <div class="h-14 w-14 rounded-lg bg-white/20 flex items-center justify-center">
                            <span class="text-2xl font-bold text-white">{{ substr($match->teamB->short_name ?? $match->teamB->name ?? 'B', 0, 3) }}</span>
                        </div>
                    @endif
                    <div>
                        <p class="text-white/80 text-sm font-medium uppercase tracking-wider">{{ $match->teamB->short_name ?? $match->teamB->name ?? 'Team B' }}</p>
                        <p class="text-white text-4xl font-black score-text">
                            {{ $teamBRuns }}/{{ $teamBWickets }}
                            <span class="text-xl font-semibold text-white/70">({{ $teamBOversDisplay }})</span>
                        </p>
                    </div>
                </div>

                <!-- Stats Panel -->
                <div class="bg-gray-900 px-6 py-4 flex-1 flex items-center justify-around border-l border-gray-700">
                    <!-- Run Rate -->
                    <div class="text-center">
                        <p class="text-gray-500 text-xs uppercase tracking-wider">CRR</p>
                        <p class="text-white text-2xl font-bold">{{ $runRate }}</p>
                    </div>

                    @if($isSecondInnings && $target)
                    <!-- Target -->
                    <div class="text-center">
                        <p class="text-gray-500 text-xs uppercase tracking-wider">Target</p>
                        <p class="text-yellow-400 text-2xl font-bold">{{ $target }}</p>
                    </div>
                    <!-- Required -->
                    <div class="text-center">
                        <p class="text-gray-500 text-xs uppercase tracking-wider">Need</p>
                        <p class="text-orange-400 text-2xl font-bold">{{ $required }}</p>
                    </div>
                    <!-- Required Rate -->
                    <div class="text-center">
                        <p class="text-gray-500 text-xs uppercase tracking-wider">RRR</p>
                        <p class="text-red-400 text-2xl font-bold">{{ $requiredRate ?? '-' }}</p>
                    </div>
                    @endif

                    <!-- Current Over -->
                    <div class="text-center">
                        <p class="text-gray-500 text-xs uppercase tracking-wider mb-1">This Over</p>
                        <div class="flex gap-1">
                            @foreach($currentOverBalls as $ball)
                                @php
                                    $ballClass = 'bg-gray-700 text-white';
                                    if ($ball === 'W') $ballClass = 'bg-red-600 text-white';
                                    elseif ($ball === '4') $ballClass = 'bg-blue-500 text-white';
                                    elseif ($ball === '6') $ballClass = 'bg-purple-500 text-white';
                                    elseif (str_contains($ball, 'wd') || str_contains($ball, 'nb')) $ballClass = 'bg-yellow-500 text-black';
                                @endphp
                                <span class="w-8 h-8 rounded-full {{ $ballClass }} flex items-center justify-center text-sm font-bold">
                                    {{ $ball }}
                                </span>
                            @endforeach
                            @for($i = count($currentOverBalls); $i < 6; $i++)
                                <span class="w-8 h-8 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-600 text-sm">
                                    -
                                </span>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Bar - Current Batsman -->
            @if($striker)
            <div class="bg-gray-900/90 border-t border-gray-700 px-6 py-2 flex items-center justify-between">
                <div class="flex items-center gap-8">
                    <div class="flex items-center gap-3">
                        <span class="text-yellow-400 text-lg">*</span>
                        <span class="text-white font-semibold">{{ $striker->name ?? 'Striker' }}</span>
                        <span class="text-gray-400">{{ $strikerRuns }} ({{ $strikerBalls }})</span>
                    </div>
                    @if($nonStriker)
                    <div class="flex items-center gap-3 opacity-70">
                        <span class="text-white font-semibold">{{ $nonStriker->name ?? 'Non-Striker' }}</span>
                    </div>
                    @endif
                </div>
                <div class="text-gray-500 text-sm">
                    {{ $match->venue ?? '' }} | {{ $match->overs ?? 20 }} Overs
                </div>
            </div>
            @endif
        </div>
    </div>

    <script>
        // Auto-refresh handled by meta tag
        // Add keyboard shortcut for manual refresh
        document.addEventListener('keydown', function(e) {
            if (e.key === 'r' || e.key === 'R') {
                location.reload();
            }
        });
    </script>
</body>
</html>
