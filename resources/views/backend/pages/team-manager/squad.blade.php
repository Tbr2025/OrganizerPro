@extends('backend.layouts.app')

@section('title', 'My Squad')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('team-manager.dashboard')],
    ['name' => 'My Squad']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Squad</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $team->name }} &mdash; {{ $teamPlayers->count() }} player{{ $teamPlayers->count() !== 1 ? 's' : '' }}</p>
    </div>

    @if($teamPlayers->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($teamPlayers as $player)
            <div x-data="{ expanded: false }" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Compact Summary --}}
                <div class="flex items-center gap-3 p-4">
                    @if($player->image_path)
                        <img src="{{ asset('storage/' . $player->image_path) }}" alt="{{ $player->name }}"
                             class="w-12 h-12 rounded-full object-cover flex-shrink-0 bg-gray-100 dark:bg-gray-700">
                    @else
                        <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                            <span class="text-lg font-bold text-gray-400 dark:text-gray-500">{{ strtoupper(substr($player->name, 0, 1)) }}</span>
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $player->name }}</h3>
                            @if($player->jersey_number)
                                <span class="flex-shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-bold text-gray-700 dark:text-gray-300">#{{ $player->jersey_number }}</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @if($player->battingProfile?->style)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $player->battingProfile->style }}</span>
                            @endif
                            @if($player->bowlingProfile?->style)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ $player->bowlingProfile->style }}</span>
                            @endif
                            @if($player->status === 'approved')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Verified</span>
                            @elseif($player->status === 'rejected')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300">Rejected</span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">Pending</span>
                            @endif
                            @if($player->user?->hasRole('Team Manager'))
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">Manager</span>
                            @endif
                        </div>
                    </div>

                    <button @click="expanded = !expanded" class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-300 dark:hover:bg-gray-700 transition">
                        <svg class="w-5 h-5 transition-transform duration-200" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Expandable Details --}}
                <div x-show="expanded" x-collapse>
                    <div class="px-4 pb-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2.5">
                            @php
                                $countries = config('countries.list', []);
                                $visaList = config('registration.visa_statuses', []);
                                $details = array_filter([
                                    'Email' => $player->email,
                                    'Mobile' => $player->mobile_number_full,
                                    'CricHeroes' => $player->cricheroes_number_full,
                                    'CricHeroes URL' => $player->cricheroes_profile_url,
                                    'Date of Birth' => $player->date_of_birth ? \Carbon\Carbon::parse($player->date_of_birth)->format('d M Y') : null,
                                    'Nationality' => $player->country ? ($countries[$player->country] ?? $player->country) : null,
                                    'State' => $player->state,
                                    'Location' => $player->location?->name,
                                    'Visa Status' => $player->visa_status ? ($visaList[$player->visa_status] ?? $player->visa_status) : null,
                                    'Visa Expiry' => $player->visa_expiry ? \Carbon\Carbon::parse($player->visa_expiry)->format('d M Y') : null,
                                    'Employer' => $player->employer_name,
                                    'Position' => $player->employer_position,
                                    'Player Type' => $player->playerType?->type,
                                    'Batting' => $player->battingProfile?->style,
                                    'Batting Mode' => $player->batting_mode,
                                    'Bowling' => $player->bowlingProfile?->style,
                                    'Wicket Keeper' => $player->is_wicket_keeper ? 'Yes' : null,
                                    'Preferred Positions' => is_array($player->preferred_batting_positions) ? implode(', ', $player->preferred_batting_positions) : null,
                                    'Jersey Name' => $player->jersey_name,
                                    'T-Shirt Size' => $player->tshirt_size,
                                    'Pant Size' => $player->pant_size,
                                    'Kit Size' => $player->kitSize?->size,
                                    'Matches' => $player->total_matches,
                                    'Runs' => $player->total_runs,
                                    'Wickets' => $player->total_wickets,
                                    'Transport' => is_null($player->transportation_required) ? null : ($player->transportation_required ? 'Required' : 'Self'),
                                    'Travel Plan' => $player->no_travel_plan ? 'No' : (($player->travel_date_from || $player->travel_date_to) ? 'Yes' : null),
                                    'Travel From' => $player->travel_date_from ? \Carbon\Carbon::parse($player->travel_date_from)->format('d M Y') : null,
                                    'Travel To' => $player->travel_date_to ? \Carbon\Carbon::parse($player->travel_date_to)->format('d M Y') : null,
                                    'Sat Available' => is_null($player->available_saturday) ? null : ($player->available_saturday ? 'Yes' : 'No'),
                                    'Sun Available' => is_null($player->available_sunday) ? null : ($player->available_sunday ? 'Yes' : 'No'),
                                ], fn($v) => $v !== null && $v !== '');
                            @endphp

                            @foreach($details as $label => $value)
                                <div class="min-w-0">
                                    <p class="text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500 font-medium">{{ $label }}</p>
                                    @if($label === 'CricHeroes URL')
                                        <a href="{{ $value }}" target="_blank" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline truncate block">{{ Str::limit($value, 30) }}</a>
                                    @else
                                        <p class="text-xs font-medium text-gray-900 dark:text-white truncate">{{ $value }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No players in your squad</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Players who register for your team will appear here.</p>
        </div>
    @endif
</div>
@endsection
