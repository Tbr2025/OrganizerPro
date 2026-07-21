@extends('backend.layouts.app')

@section('title', 'Player Dashboard')

@section('admin-content')

<div class="p-4 mx-auto max-w-7xl md:p-6">

    {{-- Top Banner --}}
    @if($tournament)
        <x-tournament-banner :tournament="$tournament" page="player_dashboard" position="top" />
    @endif

    {{-- Player Profile Header --}}
    <div class="relative overflow-hidden rounded-2xl p-4 sm:p-6 md:p-8 mb-8" style="background: linear-gradient(135deg, #059669 0%, #0d9488 50%, #065f46 100%)">
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 400 200" preserveAspectRatio="none"><path d="M0 100 Q100 20 200 100 T400 100 L400 200 L0 200 Z" fill="white"/></svg>
        </div>
        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4 md:gap-6">
            <div class="flex items-center gap-3 sm:gap-5">
                @if($player->profile_image)
                    <img src="{{ asset('storage/' . $player->profile_image) }}" alt="{{ $player->name }}" class="w-14 h-14 sm:w-20 sm:h-20 rounded-xl object-cover border-2 border-white/30 shadow-lg">
                @else
                    <div class="w-14 h-14 sm:w-20 sm:h-20 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-xl sm:text-2xl font-bold border border-white/20">
                        {{ strtoupper(substr($player->first_name ?? $player->name, 0, 1)) }}{{ strtoupper(substr($player->last_name ?? '', 0, 1)) }}
                    </div>
                @endif
                <div>
                    <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-white">{{ $player->name }}</h1>
                    <p class="text-white/70 text-xs sm:text-sm mt-1">{{ $player->email }}</p>
                    @if($player->jersey_name)
                        <p class="text-white/60 text-xs mt-0.5">Jersey: {{ $player->jersey_name }} @if($player->jersey_number)#{{ $player->jersey_number }}@endif</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                <a href="{{ route('profileplayers.edit') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-white text-emerald-700 hover:bg-emerald-50 rounded-xl text-sm font-semibold transition-all shadow-md hover:shadow-lg">
                    <iconify-icon icon="lucide:edit-3" width="16" height="16"></iconify-icon>
                    Edit Registration
                </a>
                <a href="{{ route('profile.edit') }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-white/15 hover:bg-white/25 backdrop-blur-sm text-white rounded-xl text-sm font-semibold transition-all border border-white/30 hover:border-white/50">
                    <iconify-icon icon="lucide:settings" width="16" height="16"></iconify-icon>
                    Account Settings
                </a>
            </div>
        </div>
    </div>

    {{-- CricHeroes Info (controlled by tournament settings) --}}
    @if(($fieldConfig['cricheroes_number']['visible'] ?? false) && $player->cricheroes_number_full)
        <div class="flex flex-wrap items-center gap-4 mb-6 px-4 py-3 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 text-sm">
                <iconify-icon icon="lucide:phone" width="16" height="16" class="text-gray-400"></iconify-icon>
                <span class="text-gray-500 dark:text-gray-400">CricHeroes Number:</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ $player->cricheroes_number_full }}</span>
            </div>
            @if(($fieldConfig['cricheroes_profile_url']['visible'] ?? false) && $player->cricheroes_profile_url)
                <div class="flex items-center gap-2 text-sm">
                    <iconify-icon icon="lucide:link" width="16" height="16" class="text-gray-400"></iconify-icon>
                    <span class="text-gray-500 dark:text-gray-400">CricHeroes Profile:</span>
                    <a href="{{ $player->cricheroes_profile_url }}" target="_blank" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline truncate max-w-xs">{{ $player->cricheroes_profile_url }}</a>
                </div>
            @endif
        </div>
    @endif

    {{-- Registration Status Overview --}}
    @if($registrations->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">My Registrations</h2>

            {{-- Status Summary --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $statusCounts['approved'] }}</div>
                    <div class="text-xs text-green-700 dark:text-green-300 mt-0.5">Approved</div>
                </div>
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $statusCounts['pending'] }}</div>
                    <div class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">Pending</div>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $statusCounts['queued'] }}</div>
                    <div class="text-xs text-blue-700 dark:text-blue-300 mt-0.5">Queued</div>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $statusCounts['rejected'] }}</div>
                    <div class="text-xs text-red-700 dark:text-red-300 mt-0.5">Rejected</div>
                </div>
            </div>

            {{-- Registration Cards --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($registrations as $reg)
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-gray-900 dark:text-white truncate">{{ $reg->tournament?->name ?? 'Unknown Tournament' }}</h3>
                                @if($reg->tournament?->start_date)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $reg->tournament->start_date->format('d M Y') }}</p>
                                @endif
                            </div>
                            @php
                                $statusConfig = match($reg->status) {
                                    'approved' => ['bg' => 'bg-green-100 dark:bg-green-900/30', 'text' => 'text-green-700 dark:text-green-300', 'label' => 'Approved', 'icon' => "\u{2705}"],
                                    'pending' => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-300', 'label' => 'Pending', 'icon' => "\u{23F3}"],
                                    'rejected' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-300', 'label' => 'Rejected', 'icon' => "\u{274C}"],
                                    'queued' => ['bg' => 'bg-blue-100 dark:bg-blue-900/30', 'text' => 'text-blue-700 dark:text-blue-300', 'label' => 'Queued', 'icon' => "\u{1F504}"],
                                    'cancelled' => ['bg' => 'bg-gray-100 dark:bg-gray-900/30', 'text' => 'text-gray-700 dark:text-gray-300', 'label' => 'Cancelled', 'icon' => "\u{26D4}"],
                                    default => ['bg' => 'bg-gray-100 dark:bg-gray-900/30', 'text' => 'text-gray-700 dark:text-gray-300', 'label' => ucfirst($reg->status ?? 'Unknown'), 'icon' => ''],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} whitespace-nowrap">
                                {{ $statusConfig['icon'] }} {{ $statusConfig['label'] }}
                            </span>
                        </div>

                        @if(!empty($reg->pending_changes))
                            <div class="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 mb-3">
                                <iconify-icon icon="lucide:clock" width="14" height="14"></iconify-icon>
                                {{ count($reg->pending_changes) }} pending {{ Str::plural('change', count($reg->pending_changes)) }}
                            </div>
                        @endif

                        <a href="{{ route('profileplayers.edit', ['registration_id' => $reg->id]) }}" class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">
                            <iconify-icon icon="lucide:external-link" width="14" height="14"></iconify-icon>
                            View Details
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center mb-8">
            <iconify-icon icon="lucide:clipboard-list" width="48" height="48" class="text-gray-300 dark:text-gray-600 mx-auto mb-3"></iconify-icon>
            <p class="text-gray-500 dark:text-gray-400">No tournament registrations yet.</p>
        </div>
    @endif

    {{-- Career Statistics --}}
    @if($careerStats['matches'] > 0)
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Career Statistics</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3">
                @php
                    $statItems = [
                        ['label' => 'Matches', 'value' => $careerStats['matches'], 'icon' => 'lucide:swords'],
                        ['label' => 'Runs', 'value' => $careerStats['runs'], 'icon' => 'lucide:zap'],
                        ['label' => 'Wickets', 'value' => $careerStats['wickets'], 'icon' => 'lucide:target'],
                        ['label' => 'Catches', 'value' => $careerStats['catches'], 'icon' => 'lucide:hand'],
                        ['label' => 'Highest Score', 'value' => $careerStats['highest_score'] ?? '-', 'icon' => 'lucide:trophy'],
                        ['label' => 'Fifties', 'value' => $careerStats['fifties'], 'icon' => 'lucide:star'],
                        ['label' => 'Hundreds', 'value' => $careerStats['hundreds'], 'icon' => 'lucide:crown'],
                        ['label' => 'Best Bowling', 'value' => $careerStats['best_bowling'] ?? '-', 'icon' => 'lucide:flame'],
                        ['label' => 'Awards', 'value' => $awardsCount, 'icon' => 'lucide:award'],
                    ];
                @endphp
                @foreach($statItems as $stat)
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
                        <iconify-icon icon="{{ $stat['icon'] }}" width="20" height="20" class="text-gray-400 dark:text-gray-500 mb-2"></iconify-icon>
                        <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $stat['value'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Bottom Banner --}}
    @if($tournament)
        <x-tournament-banner :tournament="$tournament" page="player_dashboard" position="bottom" />
    @endif

</div>

@endsection
