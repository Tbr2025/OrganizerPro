@extends('public.tournament.layouts.app')

@section('title', 'Statistics - ' . $tournament->name)

@push('styles')
<style>
    .page-header {
        background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #0d1b2a 100%);
    }
    .stat-card {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .tab-btn {
        transition: all 0.3s ease;
        position: relative;
    }
    .tab-btn.active {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #1f2937;
        box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
    }
    .tab-btn:not(.active):hover {
        background: rgba(255, 255, 255, 0.1);
    }
    .table-header {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
    }
    .table-row {
        transition: all 0.3s ease;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .table-row:hover {
        background: rgba(251, 191, 36, 0.1);
    }
    .top-player-row {
        background: linear-gradient(90deg, rgba(251, 191, 36, 0.15) 0%, transparent 100%);
        border-left: 4px solid #fbbf24;
    }
    .rank-badge {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
        font-size: 14px;
    }
    .rank-1 { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #1f2937; }
    .rank-2 { background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%); color: white; }
    .rank-3 { background: linear-gradient(135deg, #cd7f32 0%, #b8860b 100%); color: white; }
    .rank-other { background: rgba(255, 255, 255, 0.1); color: #9ca3af; }
    .player-avatar {
        background: linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
    }
    .stat-highlight {
        background: linear-gradient(145deg, rgba(251, 191, 36, 0.2) 0%, rgba(251, 191, 36, 0.05) 100%);
        border-radius: 8px;
        padding: 4px 12px;
    }
    .section-header {
        background: linear-gradient(90deg, rgba(251, 191, 36, 0.2) 0%, transparent 100%);
        border-left: 4px solid #fbbf24;
    }
</style>
@endpush

@section('content')
    {{-- Page Header --}}
    <section class="page-header py-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-30">
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-yellow-500/20 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <span class="inline-block px-4 py-2 bg-purple-500/20 text-purple-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-chart-bar mr-2"></i>Player Stats
                    </span>
                    <h1 class="text-4xl md:text-5xl font-bold text-white">Tournament Statistics</h1>
                </div>
                <div>
                    @php
                        $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                        $shareMessage = "Tournament Statistics - {$tournament->name}\n\n" . request()->url();
                    @endphp
                    <x-share-buttons
                        :title="'Statistics - ' . $tournament->name"
                        :description="$tournament->name . ' player statistics'"
                        :whatsappMessage="$shareMessage"
                        variant="compact"
                        :showLabel="false"
                    />
                </div>
            </div>
        </div>
    </section>

    {{-- Category Tabs --}}
    <section class="py-6 bg-gray-900 sticky top-16 z-40 border-b border-gray-800">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-wrap gap-3 justify-center">
                <a href="{{ route('public.tournament.statistics', [$tournament->slug, 'tab' => 'batting']) }}"
                   class="tab-btn px-6 py-3 rounded-xl font-semibold transition {{ $tab === 'batting' ? 'active' : 'bg-gray-800 text-white' }}">
                    <i class="fas fa-baseball-ball mr-2"></i>Batting
                </a>
                <a href="{{ route('public.tournament.statistics', [$tournament->slug, 'tab' => 'bowling']) }}"
                   class="tab-btn px-6 py-3 rounded-xl font-semibold transition {{ $tab === 'bowling' ? 'active' : 'bg-gray-800 text-white' }}">
                    <i class="fas fa-bowling-ball mr-2"></i>Bowling
                </a>
                <a href="{{ route('public.tournament.statistics', [$tournament->slug, 'tab' => 'sixes']) }}"
                   class="tab-btn px-6 py-3 rounded-xl font-semibold transition {{ $tab === 'sixes' ? 'active' : 'bg-gray-800 text-white' }}">
                    <i class="fas fa-bolt mr-2"></i>Most Sixes
                </a>
                <a href="{{ route('public.tournament.statistics', [$tournament->slug, 'tab' => 'fielding']) }}"
                   class="tab-btn px-6 py-3 rounded-xl font-semibold transition {{ $tab === 'fielding' ? 'active' : 'bg-gray-800 text-white' }}">
                    <i class="fas fa-hands mr-2"></i>Fielding
                </a>
            </div>
        </div>
    </section>

    {{-- Stats Content --}}
    <section class="py-12 bg-gray-900 min-h-screen">
        <div class="max-w-6xl mx-auto px-4">
            {{-- Batting Stats --}}
            @if($tab === 'batting')
                <div class="stat-card rounded-2xl overflow-hidden">
                    <div class="section-header px-6 py-5">
                        <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                            <i class="fas fa-baseball-ball text-yellow-400"></i>
                            Top Run Scorers
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                    <th class="px-4 py-4 text-left w-12">#</th>
                                    <th class="px-4 py-4 text-left">Player</th>
                                    <th class="px-4 py-4 text-left hidden md:table-cell">Team</th>
                                    <th class="px-4 py-4 text-center">M</th>
                                    <th class="px-4 py-4 text-center"><span class="stat-highlight">Runs</span></th>
                                    <th class="px-4 py-4 text-center">HS</th>
                                    <th class="px-4 py-4 text-center hidden md:table-cell">Avg</th>
                                    <th class="px-4 py-4 text-center hidden md:table-cell">SR</th>
                                    <th class="px-4 py-4 text-center">50s</th>
                                    <th class="px-4 py-4 text-center">100s</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topBatsmen as $index => $stat)
                                    <tr class="table-row {{ $index === 0 ? 'top-player-row' : '' }}">
                                        <td class="px-4 py-4">
                                            <div class="rank-badge {{ $index < 3 ? 'rank-' . ($index + 1) : 'rank-other' }}">
                                                {{ $index + 1 }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="player-avatar w-10 h-10 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                                                    @if($stat->player?->image_path)
                                                        <img src="{{ Storage::url($stat->player->image_path) }}" alt="{{ $stat->player->name }}" class="w-full h-full object-cover">
                                                    @else
                                                        <span class="text-sm font-bold text-gray-400">{{ substr($stat->player?->name ?? '?', 0, 1) }}</span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-white">{{ $stat->player?->name ?? 'Unknown' }}</p>
                                                    <p class="text-xs text-gray-400 md:hidden">{{ $stat->team?->short_name ?? '-' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-gray-400 hidden md:table-cell">{{ $stat->team?->short_name ?? '-' }}</td>
                                        <td class="px-4 py-4 text-center text-gray-300">{{ $stat->matches }}</td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="stat-highlight text-xl font-black text-yellow-400">{{ $stat->runs }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-center text-white font-semibold">{{ $stat->highest_score }}{{ $stat->highest_not_out ? '*' : '' }}</td>
                                        <td class="px-4 py-4 text-center text-gray-300 hidden md:table-cell">{{ number_format($stat->batting_average, 2) }}</td>
                                        <td class="px-4 py-4 text-center text-gray-300 hidden md:table-cell">{{ number_format($stat->strike_rate, 2) }}</td>
                                        <td class="px-4 py-4 text-center">
                                            @if($stat->fifties > 0)
                                                <span class="text-blue-400 font-semibold">{{ $stat->fifties }}</span>
                                            @else
                                                <span class="text-gray-500">0</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            @if($stat->hundreds > 0)
                                                <span class="text-green-400 font-semibold">{{ $stat->hundreds }}</span>
                                            @else
                                                <span class="text-gray-500">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-16 text-center">
                                            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-800 flex items-center justify-center">
                                                <i class="fas fa-baseball-ball text-3xl text-gray-600"></i>
                                            </div>
                                            <p class="text-gray-400 text-lg">No batting statistics available yet.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Bowling Stats --}}
            @if($tab === 'bowling')
                <div class="stat-card rounded-2xl overflow-hidden">
                    <div class="section-header px-6 py-5">
                        <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                            <i class="fas fa-bowling-ball text-yellow-400"></i>
                            Top Wicket Takers
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                    <th class="px-4 py-4 text-left w-12">#</th>
                                    <th class="px-4 py-4 text-left">Player</th>
                                    <th class="px-4 py-4 text-left hidden md:table-cell">Team</th>
                                    <th class="px-4 py-4 text-center">M</th>
                                    <th class="px-4 py-4 text-center"><span class="stat-highlight">Wkts</span></th>
                                    <th class="px-4 py-4 text-center">BB</th>
                                    <th class="px-4 py-4 text-center hidden md:table-cell">Avg</th>
                                    <th class="px-4 py-4 text-center hidden md:table-cell">Econ</th>
                                    <th class="px-4 py-4 text-center">4W</th>
                                    <th class="px-4 py-4 text-center">5W</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topBowlers as $index => $stat)
                                    <tr class="table-row {{ $index === 0 ? 'top-player-row' : '' }}">
                                        <td class="px-4 py-4">
                                            <div class="rank-badge {{ $index < 3 ? 'rank-' . ($index + 1) : 'rank-other' }}">
                                                {{ $index + 1 }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="player-avatar w-10 h-10 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                                                    @if($stat->player?->image_path)
                                                        <img src="{{ Storage::url($stat->player->image_path) }}" alt="{{ $stat->player->name }}" class="w-full h-full object-cover">
                                                    @else
                                                        <span class="text-sm font-bold text-gray-400">{{ substr($stat->player?->name ?? '?', 0, 1) }}</span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-white">{{ $stat->player?->name ?? 'Unknown' }}</p>
                                                    <p class="text-xs text-gray-400 md:hidden">{{ $stat->team?->short_name ?? '-' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-gray-400 hidden md:table-cell">{{ $stat->team?->short_name ?? '-' }}</td>
                                        <td class="px-4 py-4 text-center text-gray-300">{{ $stat->matches }}</td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="stat-highlight text-xl font-black text-yellow-400">{{ $stat->wickets }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-center text-white font-semibold">{{ $stat->best_bowling ?? '-' }}</td>
                                        <td class="px-4 py-4 text-center text-gray-300 hidden md:table-cell">{{ $stat->wickets > 0 ? number_format($stat->bowling_average, 2) : '-' }}</td>
                                        <td class="px-4 py-4 text-center text-gray-300 hidden md:table-cell">{{ number_format($stat->economy, 2) }}</td>
                                        <td class="px-4 py-4 text-center">
                                            @if($stat->four_wickets > 0)
                                                <span class="text-purple-400 font-semibold">{{ $stat->four_wickets }}</span>
                                            @else
                                                <span class="text-gray-500">0</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            @if($stat->five_wickets > 0)
                                                <span class="text-red-400 font-semibold">{{ $stat->five_wickets }}</span>
                                            @else
                                                <span class="text-gray-500">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-16 text-center">
                                            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-800 flex items-center justify-center">
                                                <i class="fas fa-bowling-ball text-3xl text-gray-600"></i>
                                            </div>
                                            <p class="text-gray-400 text-lg">No bowling statistics available yet.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Most Sixes --}}
            @if($tab === 'sixes')
                <div class="stat-card rounded-2xl overflow-hidden">
                    <div class="section-header px-6 py-5">
                        <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                            <i class="fas fa-bolt text-yellow-400"></i>
                            Most Sixes
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                    <th class="px-4 py-4 text-left w-12">#</th>
                                    <th class="px-4 py-4 text-left">Player</th>
                                    <th class="px-4 py-4 text-left hidden md:table-cell">Team</th>
                                    <th class="px-4 py-4 text-center">Matches</th>
                                    <th class="px-4 py-4 text-center"><span class="stat-highlight">Sixes</span></th>
                                    <th class="px-4 py-4 text-center">Fours</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topSixHitters as $index => $stat)
                                    <tr class="table-row {{ $index === 0 ? 'top-player-row' : '' }}">
                                        <td class="px-4 py-4">
                                            <div class="rank-badge {{ $index < 3 ? 'rank-' . ($index + 1) : 'rank-other' }}">
                                                {{ $index + 1 }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="player-avatar w-10 h-10 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                                                    @if($stat->player?->image_path)
                                                        <img src="{{ Storage::url($stat->player->image_path) }}" alt="{{ $stat->player->name }}" class="w-full h-full object-cover">
                                                    @else
                                                        <span class="text-sm font-bold text-gray-400">{{ substr($stat->player?->name ?? '?', 0, 1) }}</span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-white">{{ $stat->player?->name ?? 'Unknown' }}</p>
                                                    <p class="text-xs text-gray-400 md:hidden">{{ $stat->team?->short_name ?? '-' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-gray-400 hidden md:table-cell">{{ $stat->team?->short_name ?? '-' }}</td>
                                        <td class="px-4 py-4 text-center text-gray-300">{{ $stat->matches }}</td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="stat-highlight text-xl font-black text-yellow-400">{{ $stat->sixes }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-center text-blue-400 font-semibold">{{ $stat->fours }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-16 text-center">
                                            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-800 flex items-center justify-center">
                                                <i class="fas fa-bolt text-3xl text-gray-600"></i>
                                            </div>
                                            <p class="text-gray-400 text-lg">No data available yet.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Fielding Stats --}}
            @if($tab === 'fielding')
                <div class="stat-card rounded-2xl overflow-hidden">
                    <div class="section-header px-6 py-5">
                        <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                            <i class="fas fa-hands text-yellow-400"></i>
                            Top Fielders
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                    <th class="px-4 py-4 text-left w-12">#</th>
                                    <th class="px-4 py-4 text-left">Player</th>
                                    <th class="px-4 py-4 text-left hidden md:table-cell">Team</th>
                                    <th class="px-4 py-4 text-center">Catches</th>
                                    <th class="px-4 py-4 text-center">Stumpings</th>
                                    <th class="px-4 py-4 text-center">Run Outs</th>
                                    <th class="px-4 py-4 text-center"><span class="stat-highlight">Total</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topFielders as $index => $stat)
                                    <tr class="table-row {{ $index === 0 ? 'top-player-row' : '' }}">
                                        <td class="px-4 py-4">
                                            <div class="rank-badge {{ $index < 3 ? 'rank-' . ($index + 1) : 'rank-other' }}">
                                                {{ $index + 1 }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="player-avatar w-10 h-10 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                                                    @if($stat->player?->image_path)
                                                        <img src="{{ Storage::url($stat->player->image_path) }}" alt="{{ $stat->player->name }}" class="w-full h-full object-cover">
                                                    @else
                                                        <span class="text-sm font-bold text-gray-400">{{ substr($stat->player?->name ?? '?', 0, 1) }}</span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-white">{{ $stat->player?->name ?? 'Unknown' }}</p>
                                                    <p class="text-xs text-gray-400 md:hidden">{{ $stat->team?->short_name ?? '-' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-gray-400 hidden md:table-cell">{{ $stat->team?->short_name ?? '-' }}</td>
                                        <td class="px-4 py-4 text-center text-green-400 font-semibold">{{ $stat->catches }}</td>
                                        <td class="px-4 py-4 text-center text-blue-400 font-semibold">{{ $stat->stumpings }}</td>
                                        <td class="px-4 py-4 text-center text-purple-400 font-semibold">{{ $stat->run_outs }}</td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="stat-highlight text-xl font-black text-yellow-400">
                                                {{ $stat->catches + $stat->stumpings + $stat->run_outs }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-16 text-center">
                                            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-800 flex items-center justify-center">
                                                <i class="fas fa-hands text-3xl text-gray-600"></i>
                                            </div>
                                            <p class="text-gray-400 text-lg">No fielding statistics available yet.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
