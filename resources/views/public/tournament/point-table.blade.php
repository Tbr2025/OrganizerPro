@extends('public.tournament.layouts.app')

@section('title', 'Point Table - ' . $tournament->name)

@push('styles')
<style>
    .page-header {
        background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #0d1b2a 100%);
    }
    .group-card {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .group-header {
        background: linear-gradient(90deg, rgba(251, 191, 36, 0.2) 0%, transparent 100%);
        border-left: 4px solid #fbbf24;
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
    .position-badge {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
        font-size: 14px;
    }
    .position-1 { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #1f2937; }
    .position-2 { background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%); color: white; }
    .position-3 { background: linear-gradient(135deg, #cd7f32 0%, #b8860b 100%); color: white; }
    .position-other { background: rgba(255, 255, 255, 0.1); color: #9ca3af; }
    .qualified-badge {
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.3) 0%, rgba(34, 197, 94, 0.1) 100%);
        border: 1px solid rgba(34, 197, 94, 0.5);
        color: #4ade80;
    }
    .nrr-positive { color: #4ade80; }
    .nrr-negative { color: #f87171; }
    .team-logo-container {
        background: linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
    }
    .stat-highlight {
        background: linear-gradient(145deg, rgba(251, 191, 36, 0.2) 0%, rgba(251, 191, 36, 0.05) 100%);
        border-radius: 8px;
        padding: 4px 12px;
    }
    .tab-btn {
        transition: all 0.3s ease;
    }
    .tab-btn.active {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #1f2937;
        box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
    }
</style>
@endpush

@section('content')
    {{-- Page Header --}}
    <section class="page-header py-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-30">
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-green-500/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-yellow-500/20 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <span class="inline-block px-4 py-2 bg-green-500/20 text-green-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-table mr-2"></i>Standings
                    </span>
                    <h1 class="text-4xl md:text-5xl font-bold text-white">Point Table</h1>
                </div>
                <div>
                    @php
                        $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                        $shareMessage = "Point Table - {$tournament->name}\n\n" . request()->url();
                    @endphp
                    <x-share-buttons
                        :title="'Point Table - ' . $tournament->name"
                        :description="$tournament->name . ' standings'"
                        :whatsappMessage="$shareMessage"
                        variant="compact"
                        :showLabel="false"
                    />
                </div>
            </div>
        </div>
    </section>

    {{-- Group Tabs (if multiple groups) --}}
    @if($pointTableByGroups->count() > 1)
        <section class="py-6 bg-gray-900 sticky top-16 z-40 border-b border-gray-800">
            <div class="max-w-6xl mx-auto px-4">
                <div class="flex flex-wrap gap-3 justify-center">
                    @foreach($pointTableByGroups->keys() as $groupName)
                        @if($groupName !== 'default')
                            <a href="#group-{{ Str::slug($groupName) }}"
                               class="tab-btn px-6 py-3 bg-gray-800 hover:bg-gray-700 rounded-xl font-semibold text-white transition">
                                {{ $groupName }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Point Tables --}}
    <section class="py-12 bg-gray-900 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 space-y-12">
            @forelse($pointTableByGroups as $groupName => $entries)
                <div id="group-{{ Str::slug($groupName) }}" class="group-card rounded-2xl overflow-hidden">
                    {{-- Group Header --}}
                    @if($groupName !== 'default')
                        <div class="group-header px-6 py-5">
                            <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                                <i class="fas fa-layer-group text-yellow-400"></i>
                                {{ $groupName }}
                                <span class="text-sm font-normal text-gray-400 ml-2">
                                    ({{ $entries->count() }} {{ Str::plural('team', $entries->count()) }})
                                </span>
                            </h2>
                        </div>
                    @endif

                    {{-- Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                    <th class="px-4 py-4 text-left w-12">#</th>
                                    <th class="px-4 py-4 text-left">Team</th>
                                    <th class="px-4 py-4 text-center w-16">P</th>
                                    <th class="px-4 py-4 text-center w-16">W</th>
                                    <th class="px-4 py-4 text-center w-16">L</th>
                                    <th class="px-4 py-4 text-center w-16 hidden md:table-cell">T</th>
                                    <th class="px-4 py-4 text-center w-16 hidden md:table-cell">NR</th>
                                    <th class="px-4 py-4 text-center w-24">NRR</th>
                                    <th class="px-4 py-4 text-center w-20">
                                        <span class="stat-highlight">Pts</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entries as $index => $entry)
                                    @php
                                        $position = $entry->position ?? ($index + 1);
                                    @endphp
                                    <tr class="table-row">
                                        {{-- Position --}}
                                        <td class="px-4 py-4">
                                            <div class="position-badge {{ $position <= 3 ? 'position-' . $position : 'position-other' }}">
                                                {{ $position }}
                                            </div>
                                        </td>

                                        {{-- Team --}}
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="team-logo-container w-10 h-10 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                                                    @if($entry->team?->logo)
                                                        <img src="{{ Storage::url($entry->team->logo) }}" alt="{{ $entry->team->name }}" class="w-8 h-8 object-contain">
                                                    @else
                                                        <span class="text-sm font-bold text-gray-400">{{ substr($entry->team?->short_name ?? '?', 0, 2) }}</span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-white">{{ $entry->team?->name ?? 'Unknown' }}</p>
                                                    @if($entry->qualified ?? ($position <= 2))
                                                        <span class="qualified-badge text-xs font-semibold px-2 py-0.5 rounded inline-block mt-1">
                                                            <i class="fas fa-check-circle mr-1"></i>Qualified
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Played --}}
                                        <td class="px-4 py-4 text-center text-gray-300 font-medium">{{ $entry->played }}</td>

                                        {{-- Won --}}
                                        <td class="px-4 py-4 text-center">
                                            <span class="text-green-400 font-semibold">{{ $entry->won }}</span>
                                        </td>

                                        {{-- Lost --}}
                                        <td class="px-4 py-4 text-center">
                                            <span class="text-red-400 font-semibold">{{ $entry->lost }}</span>
                                        </td>

                                        {{-- Tied --}}
                                        <td class="px-4 py-4 text-center text-gray-400 hidden md:table-cell">{{ $entry->tied }}</td>

                                        {{-- No Result --}}
                                        <td class="px-4 py-4 text-center text-gray-400 hidden md:table-cell">{{ $entry->no_result }}</td>

                                        {{-- NRR --}}
                                        <td class="px-4 py-4 text-center">
                                            <span class="font-mono font-semibold {{ $entry->nrr >= 0 ? 'nrr-positive' : 'nrr-negative' }}">
                                                {{ $entry->nrr >= 0 ? '+' : '' }}{{ number_format($entry->nrr, 3) }}
                                            </span>
                                        </td>

                                        {{-- Points --}}
                                        <td class="px-4 py-4 text-center">
                                            <span class="stat-highlight text-xl font-black text-yellow-400">{{ $entry->points }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                                            <i class="fas fa-info-circle text-2xl mb-2"></i>
                                            <p>No entries yet</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Legend --}}
                    <div class="px-6 py-4 bg-gray-800/50 border-t border-gray-700/50">
                        <div class="flex flex-wrap gap-6 text-xs text-gray-400">
                            <span class="flex items-center gap-2">
                                <span class="w-3 h-3 bg-green-500 rounded"></span>
                                <strong class="text-green-400">Qualified</strong>
                            </span>
                            <span><strong class="text-gray-300">P</strong> = Played</span>
                            <span><strong class="text-gray-300">W</strong> = Won</span>
                            <span><strong class="text-gray-300">L</strong> = Lost</span>
                            <span><strong class="text-gray-300">T</strong> = Tied</span>
                            <span><strong class="text-gray-300">NR</strong> = No Result</span>
                            <span><strong class="text-gray-300">NRR</strong> = Net Run Rate</span>
                            <span><strong class="text-gray-300">Pts</strong> = Points</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gray-800 flex items-center justify-center">
                        <i class="fas fa-table text-4xl text-gray-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-2">No Point Table Yet</h3>
                    <p class="text-gray-400">Point table will be available once matches are played.</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- Qualification Info --}}
    <section class="py-12 bg-gray-800">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <div class="bg-gradient-to-r from-green-600/20 to-emerald-600/20 rounded-2xl p-8 border border-green-500/30">
                <i class="fas fa-info-circle text-3xl text-green-400 mb-4"></i>
                <h3 class="text-xl font-bold text-white mb-2">Qualification Criteria</h3>
                <p class="text-gray-300">
                    Top 2 teams from each group will qualify for the knockout stage.
                    Teams are ranked by points, then by Net Run Rate (NRR).
                </p>
            </div>
        </div>
    </section>
@endsection
