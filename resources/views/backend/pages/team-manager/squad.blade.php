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
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Header: Photo + Name + Status --}}
                <div class="flex items-start gap-4 p-4 border-b border-gray-100 dark:border-gray-700">
                    @if($player->image_path)
                        <img src="{{ asset('storage/' . $player->image_path) }}" alt="{{ $player->name }}"
                             class="w-16 h-20 rounded-lg object-cover flex-shrink-0 bg-gray-100 dark:bg-gray-700">
                    @else
                        <div class="w-16 h-20 rounded-lg bg-gray-200 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                            <span class="text-2xl font-bold text-gray-400 dark:text-gray-500">{{ strtoupper(substr($player->name, 0, 1)) }}</span>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">{{ $player->name }}</h3>
                        @if($player->jersey_name || $player->jersey_number)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                @if($player->jersey_name){{ $player->jersey_name }}@endif
                                @if($player->jersey_number) #{{ $player->jersey_number }}@endif
                            </p>
                        @endif
                        <div class="flex flex-wrap gap-1 mt-2">
                            @if($player->status === 'approved')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Player Verified</span>
                            @elseif($player->status === 'rejected')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Player Rejected</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Player Pending</span>
                            @endif
                            @if($player->user?->hasRole('Team Manager'))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">Team Manager</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Details Grid --}}
                <div class="p-4">
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
