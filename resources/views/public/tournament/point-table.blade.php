@extends('public.tournament.layouts.app')

@section('title', 'Point Table - ' . $tournament->name)

@push('styles')
<style>
    .page-header {
        background: linear-gradient(160deg, #0a0e1a 0%, #111827 40%, #0f172a 100%);
    }
    .group-card {
        background: #111827;
        border: 1px solid rgba(255, 255, 255, 0.06);
        overflow: hidden;
    }
    .group-tab {
        transition: all 0.2s ease;
    }
    .group-tab:hover {
        background: rgba(251, 191, 36, 0.15);
        color: #fbbf24;
    }
    .group-tab.active {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #111827;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
    }

    /* Table styles */
    .pt-table {
        width: 100%;
        border-collapse: collapse;
    }
    .pt-table thead th {
        padding: 12px 10px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9ca3af;
        text-align: center;
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        white-space: nowrap;
    }
    .pt-table thead th:nth-child(2) {
        text-align: left;
    }
    .pt-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        transition: background 0.15s ease;
    }
    .pt-table tbody tr:last-child {
        border-bottom: none;
    }
    .pt-table tbody tr:hover {
        background: rgba(251, 191, 36, 0.05);
    }
    .pt-table tbody tr.qualified-row {
        background: rgba(34, 197, 94, 0.05);
        border-left: 3px solid #22c55e;
    }
    .pt-table tbody tr.qualified-row:hover {
        background: rgba(34, 197, 94, 0.08);
    }
    .pt-table tbody td {
        padding: 14px 10px;
        text-align: center;
        font-size: 14px;
        font-variant-numeric: tabular-nums;
    }
    .pt-table tbody td:nth-child(2) {
        text-align: left;
    }

    /* Position badges */
    .pos-badge {
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: 700;
        font-size: 12px;
    }
    .pos-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #1f2937; }
    .pos-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; }
    .pos-3 { background: linear-gradient(135deg, #cd7f32, #b8860b); color: #fff; }
    .pos-default { background: rgba(255, 255, 255, 0.08); color: #6b7280; }

    /* Team logo */
    .team-logo-circle {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.06);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
    }
    .team-logo-circle img {
        width: 28px;
        height: 28px;
        object-fit: contain;
    }

    /* Points highlight */
    .pts-value {
        font-weight: 800;
        font-size: 18px;
        color: #fbbf24;
    }

    /* NRR colors */
    .nrr-pos { color: #4ade80; }
    .nrr-neg { color: #f87171; }

    /* Mobile scroll hint */
    .scroll-hint {
        background: linear-gradient(90deg, transparent 0%, #111827 100%);
        pointer-events: none;
    }

    @media (max-width: 640px) {
        .pt-table thead th,
        .pt-table tbody td {
            padding: 10px 6px;
            font-size: 12px;
        }
        .pt-table .hide-mobile {
            display: none;
        }
        .team-logo-circle {
            width: 30px;
            height: 30px;
        }
        .team-logo-circle img {
            width: 22px;
            height: 22px;
        }
        .pts-value {
            font-size: 15px;
        }
    }
</style>
@endpush

@section('content')
    {{-- Header --}}
    <section class="page-header py-10 md:py-14 relative">
        <div class="absolute inset-0 opacity-15">
            <div class="absolute top-0 left-1/4 w-60 h-60 bg-green-500/30 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-5xl mx-auto px-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    @if($tournament->settings?->logo)
                        <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}"
                             class="w-12 h-12 md:w-14 md:h-14 rounded-xl object-contain bg-white/10 p-1.5">
                    @endif
                    <div>
                        <p class="text-yellow-400 text-xs font-semibold uppercase tracking-wider">{{ $tournament->name }}</p>
                        <h1 class="text-2xl md:text-3xl font-extrabold text-white">Points Table</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @php
                        $shareMessage = "Points Table - {$tournament->name}\n" . request()->url();
                    @endphp
                    <button onclick="sharePage()" class="w-10 h-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition">
                        <i class="fas fa-share-alt text-sm"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- Group Tabs --}}
    @if($pointTableByGroups->count() > 1)
        <section class="bg-gray-900 sticky top-16 z-40 border-b border-gray-800 backdrop-blur-sm">
            <div class="max-w-5xl mx-auto px-4">
                <div class="flex items-center gap-2 py-3 overflow-x-auto">
                    @foreach($pointTableByGroups->keys() as $groupName)
                        @if($groupName !== 'default')
                            <a href="#group-{{ Str::slug($groupName) }}"
                               class="group-tab px-5 py-2 rounded-full text-sm text-gray-400 bg-gray-800 whitespace-nowrap">
                                {{ $groupName }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Tables --}}
    <section class="py-8 md:py-12 bg-gray-900 min-h-screen">
        <div class="max-w-5xl mx-auto px-4 space-y-8">
            @forelse($pointTableByGroups as $groupName => $entries)
                <div id="group-{{ Str::slug($groupName) }}" class="group-card rounded-xl">
                    {{-- Group Name Header --}}
                    @if($groupName !== 'default')
                        <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                {{ $groupName }}
                                <span class="text-xs font-normal text-gray-500 bg-gray-800 px-2 py-0.5 rounded-full">
                                    {{ $entries->count() }} teams
                                </span>
                            </h2>
                            @php
                                $groupMatchesPlayed = $entries->sum('matches_played');
                            @endphp
                            @if($groupMatchesPlayed > 0)
                                <span class="text-xs text-gray-500">{{ $groupMatchesPlayed }} matches played</span>
                            @endif
                        </div>
                    @endif

                    {{-- Table --}}
                    <div class="relative overflow-x-auto">
                        {{-- Mobile scroll fade --}}
                        <div class="scroll-hint absolute top-0 right-0 bottom-0 w-8 z-10 md:hidden"></div>

                        <table class="pt-table">
                            <thead>
                                <tr>
                                    <th class="w-12">#</th>
                                    <th>Team</th>
                                    <th class="w-12">M</th>
                                    <th class="w-12">W</th>
                                    <th class="w-12">L</th>
                                    <th class="w-12 hide-mobile">D</th>
                                    <th class="w-12 hide-mobile">NR</th>
                                    <th class="w-20">NRR</th>
                                    <th class="w-14">PTS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entries as $index => $entry)
                                    @php
                                        $pos = $entry->position ?? ($index + 1);
                                        $isQualified = $entry->qualified;
                                    @endphp
                                    <tr class="{{ $isQualified ? 'qualified-row' : '' }}">
                                        {{-- Position --}}
                                        <td>
                                            <span class="pos-badge {{ $pos <= 3 ? 'pos-' . $pos : 'pos-default' }}">
                                                {{ $pos }}
                                            </span>
                                        </td>

                                        {{-- Team --}}
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="team-logo-circle">
                                                    @if($entry->team?->team_logo)
                                                        <img src="{{ Storage::url($entry->team->team_logo) }}" alt="{{ $entry->team->name }}">
                                                    @else
                                                        <span class="text-[10px] font-bold text-gray-500">{{ strtoupper(substr($entry->team?->short_name ?? $entry->team?->name ?? '?', 0, 3)) }}</span>
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-white text-sm truncate max-w-[160px] md:max-w-none">
                                                        {{ $entry->team?->name ?? 'Unknown' }}
                                                    </p>
                                                    @if($isQualified)
                                                        <span class="text-[10px] text-green-400 font-medium">
                                                            <i class="fas fa-check-circle mr-0.5"></i>Qualified
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Matches --}}
                                        <td class="text-gray-300 font-medium">{{ $entry->matches_played }}</td>

                                        {{-- Won --}}
                                        <td class="text-green-400 font-semibold">{{ $entry->won }}</td>

                                        {{-- Lost --}}
                                        <td class="text-red-400 font-semibold">{{ $entry->lost }}</td>

                                        {{-- Tied --}}
                                        <td class="text-gray-400 hide-mobile">{{ $entry->tied }}</td>

                                        {{-- No Result --}}
                                        <td class="text-gray-400 hide-mobile">{{ $entry->no_result }}</td>

                                        {{-- NRR --}}
                                        <td>
                                            <span class="font-mono font-semibold text-xs {{ $entry->net_run_rate >= 0 ? 'nrr-pos' : 'nrr-neg' }}">
                                                {{ $entry->net_run_rate >= 0 ? '+' : '' }}{{ number_format($entry->net_run_rate, 3) }}
                                            </span>
                                        </td>

                                        {{-- Points --}}
                                        <td>
                                            <span class="pts-value">{{ $entry->points }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-12 text-center text-gray-500">
                                            <i class="fas fa-info-circle text-xl mb-2 block"></i>
                                            No entries yet
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Legend --}}
                    <div class="px-5 py-3 border-t border-white/5 flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-gray-500">
                        @if($entries->where('qualified', true)->count() > 0)
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 bg-green-500 rounded-sm"></span> Qualified
                            </span>
                        @endif
                        <span><b class="text-gray-400">M</b> Played</span>
                        <span><b class="text-gray-400">W</b> Won</span>
                        <span><b class="text-gray-400">L</b> Lost</span>
                        <span class="hidden md:inline"><b class="text-gray-400">D</b> Draw/Tied</span>
                        <span class="hidden md:inline"><b class="text-gray-400">NR</b> No Result</span>
                        <span><b class="text-gray-400">NRR</b> Net Run Rate</span>
                        <span><b class="text-gray-400">PTS</b> Points</span>
                    </div>
                </div>
            @empty
                <div class="text-center py-24">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-800 flex items-center justify-center">
                        <i class="fas fa-table text-2xl text-gray-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-1">No Point Table Yet</h3>
                    <p class="text-sm text-gray-500">Standings will appear once matches are played.</p>
                </div>
            @endforelse

            {{-- Last Updated --}}
            @if($pointTableByGroups->isNotEmpty())
                <p class="text-center text-xs text-gray-600 mt-4">
                    Last updated: {{ now()->format('d M Y, h:i A') }}
                </p>
            @endif
        </div>
    </section>

    @push('scripts')
    <script>
        function sharePage() {
            const url = window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: @json($tournament->name . ' - Points Table'),
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    const t = document.createElement('div');
                    t.className = 'fixed bottom-6 right-6 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-lg z-50';
                    t.textContent = 'Link copied!';
                    document.body.appendChild(t);
                    setTimeout(() => t.remove(), 2000);
                });
            }
        }

        // Active group tab on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.group-tab');
            if (tabs.length === 0) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.id;
                        tabs.forEach(tab => {
                            tab.classList.toggle('active', tab.getAttribute('href') === '#' + id);
                        });
                    }
                });
            }, { threshold: 0.3 });

            document.querySelectorAll('[id^="group-"]').forEach(el => observer.observe(el));
            // Activate first tab by default
            if (tabs[0]) tabs[0].classList.add('active');
        });
    </script>
    @endpush
@endsection
