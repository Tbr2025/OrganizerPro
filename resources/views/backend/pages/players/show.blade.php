@extends('backend.layouts.app')

@section('title')
    {{ $player->name }} | Player Profile
@endsection

@section('admin-content')
    <div class="p-4 mx-auto  md:p-6 lg:p-8">

        {{-- HEADER: Breadcrumbs & Edit Button --}}
        <div class="flex justify-between items-center mb-6">
            <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
            <div class="flex items-center gap-2">
                @can('player.edit')
                    <a href="{{ route('admin.players.edit', $player->id) }}" class="btn btn-primary inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" />
                        </svg>
                        Edit Player
                    </a>
                @endcan
                @can('player.delete')
                    <form action="{{ route('admin.players.destroy', $player->id) }}" method="POST"
                        onsubmit="return confirm('Are you sure you want to delete this player? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- LEFT COLUMN: Player Identity & Key Stats --}}
            <div class="lg:col-span-1 space-y-8">

                <!-- Player Hero Card -->
                <div
                    class="relative rounded-lg shadow-xl overflow-hidden bg-gradient-to-br from-gray-800 to-gray-900 text-white p-6 text-center">
                    <img src="{{ $player->image_path ? Storage::url($player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($player->name) . '&size=128&background=4F46E5&color=FFFFFF' }}"
                        alt="{{ $player->name }}"
                        class="w-32 h-32 object-cover rounded-full mx-auto mb-4 border-4 border-gray-700 ring-4 ring-blue-500">
                    <h1 class="text-3xl font-extrabold tracking-tight">{{ $player->name }}</h1>
                    @if ($player->jersey_name)
                        <p class="text-lg text-blue-300 font-medium">{{ $player->jersey_name }}</p>
                    @endif

                    @if ($verifiedProfile)
                        <div class="absolute top-4 right-4" title="Fully Verified Profile">
                            <div
                                class="flex items-center gap-1 bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                Verified
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Key Stats Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-5">
                    <h3 class="font-semibold text-lg mb-4 text-gray-800 dark:text-white">Career Stats</h3>
                    <div class="flex justify-around text-center">
                        <div>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $player->total_matches ?? 0 }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Matches</p>
                        </div>
                        <div>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $player->total_runs ?? 0 }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Runs</p>
                        </div>
                        <div>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $player->total_wickets ?? 0 }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Wickets</p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- RIGHT COLUMN: Detailed Information --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- ======================================================= --}}
                {{-- THE FIX IS HERE: Defining the $fields variable          --}}
                {{-- ======================================================= --}}
                @php
                    $fields = [
                        'email' => 'Email Address',
                        'mobile_number_full' => 'Mobile Number',
                        'cricheroes_number_full' => 'Cricheroes Number',
                        'country_display' => 'Country',
                        'location.name' => 'Player Location',
                        'team.name' => 'Current Team',
                        'team_name_ref' => 'If Others',
                        'kitSize.size' => 'Jersey Size',
                        'jersey_number' => 'Jersey Number',
                        'battingProfile.style' => 'Batting Profile',
                        'bowlingProfile.style' => 'Bowling Profile',
                        'playerType.type' => 'Player Type',
                    ];
                @endphp

                @if (!$verifiedProfile && !$player->isApproved())
                    <div class="bg-yellow-100 dark:bg-yellow-900/50 border-l-4 border-yellow-500 text-yellow-800 dark:text-yellow-200 p-4 rounded-r-lg"
                        role="alert">
                        <p class="font-bold">Pending Approval & Verification</p>
                        <p>This player's profile is awaiting review by an administrator.</p>
                    </div>
                @endif

                <!-- Personal & Contact Details -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Personal Details</h3>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                        @php $personalFields = ['email', 'mobile_number_full', 'cricheroes_number_full', 'country_display', 'location.name']; @endphp
                        @foreach ($personalFields as $field)
                            <div>
                                <label
                                    class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $fields[$field] }}</label>
                                <p class="mt-1 text-md font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    @if ($field === 'country_display')
                                        {{ $player->country ? config('countries.list.' . $player->country, $player->country) : 'N/A' }}
                                    @else
                                        {{ data_get($player, $field, 'N/A') }}
                                    @endif
                                    @php
                                        $verifiedKey = $field === 'country_display' ? 'verified_country' : 'verified_' . str_replace('.', '_', $field);
                                    @endphp
                                    @if ($player->$verifiedKey ?? false)
                                        <span class="text-green-500" title="Verified"><svg class="w-4 h-4"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg></span>
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Cricketing Profile -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Cricketing Profile</h3>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- CORRECTED: Removed 'team_name_ref' from the array to prevent it from displaying as a separate row --}}
                        @php $cricketFields = ['team.name', 'kitSize.size','jersey_number', 'battingProfile.style', 'bowlingProfile.style', 'playerType.type']; @endphp

                        @foreach ($cricketFields as $field)
                            <div>
                                {{-- The label for "Team" is now more generic to cover both cases --}}
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    @if ($field === 'team.name')
                                        Current Team
                                    @else
                                        {{ $fields[$field] }}
                                    @endif
                                </label>

                                <p class="mt-1 text-md font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    {{-- **THIS IS THE FIX** --}}
                                    {{-- Conditionally display the team name or the reference name --}}
                                    @if ($field === 'team.name' && data_get($player, 'team.name') === 'Others')
                                        {{ data_get($player, 'team_name_ref', 'N/A') }}
                                    @else
                                        {{ data_get($player, $field, 'N/A') }}
                                    @endif

                                    {{-- The verification check remains the same. It will check the original 'team.name' field's verification status --}}
                                    @if ($player->{'verified_' . str_replace('.', '_', $field)})
                                        <span class="text-green-500" title="Verified">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>

        {{-- TOURNAMENT STATISTICS (Full Width) --}}
        @if($tournamentAssignments->count() > 0)
            <div class="mt-8 space-y-6" x-data="{ openTab: {{ $tournamentAssignments->first()->tournament_id }} }">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Tournament Statistics</h2>

                {{-- Tournament Tabs --}}
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex flex-wrap gap-2 -mb-px">
                        @foreach($tournamentAssignments as $assignment)
                            <button type="button"
                                @click="openTab = {{ $assignment->tournament_id }}"
                                :class="openTab === {{ $assignment->tournament_id }}
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 px-4 py-3 border-b-2 font-medium text-sm transition-colors">
                                {{ $assignment->tournament_name }}
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                    {{ $assignment->team_name }}
                                </span>
                                @if($assignment->role)
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300">
                                        {{ ucfirst($assignment->role) }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- Tab Content --}}
                @foreach($tournamentAssignments as $assignment)
                    @php $stats = $tournamentStats->get($assignment->tournament_id); @endphp
                    <div x-show="openTab === {{ $assignment->tournament_id }}"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-cloak>
                        @if($stats)
                            {{-- Quick Stats Row --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats->matches }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Matches</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats->runs }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Runs</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats->wickets }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Wickets</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats->catches + $stats->stumpings + $stats->run_outs }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Dismissals (Field)</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {{-- Batting Stats --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        <h3 class="font-semibold text-gray-800 dark:text-white">Batting</h3>
                                    </div>
                                    <div class="p-4">
                                        <table class="w-full text-sm">
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Innings</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->innings_batted }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Runs</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->runs }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Balls Faced</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->balls_faced }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Highest Score</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->highest_score_display }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Average</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->batting_average }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Strike Rate</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->strike_rate }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">4s / 6s</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->fours }} / {{ $stats->sixes }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">50s / 100s</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->fifties }} / {{ $stats->hundreds }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Not Outs</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->not_outs }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Ducks</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->ducks }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- Bowling Stats --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <h3 class="font-semibold text-gray-800 dark:text-white">Bowling</h3>
                                    </div>
                                    <div class="p-4">
                                        <table class="w-full text-sm">
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Innings</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->innings_bowled }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Overs</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->overs_bowled }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Wickets</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->wickets }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Runs Conceded</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->runs_conceded }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Best Bowling</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->best_bowling ?? '-' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Average</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->bowling_average }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Economy</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->economy_rate }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">Maidens</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->maidens }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 text-gray-500 dark:text-gray-400">4W / 5W</td>
                                                    <td class="py-2 text-right font-semibold text-gray-800 dark:text-white">{{ $stats->four_wickets }} / {{ $stats->five_wickets }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- Fielding Stats --}}
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg lg:col-span-2">
                                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"/>
                                        </svg>
                                        <h3 class="font-semibold text-gray-800 dark:text-white">Fielding</h3>
                                    </div>
                                    <div class="p-4">
                                        <div class="grid grid-cols-3 gap-6 text-center">
                                            <div>
                                                <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $stats->catches }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Catches</p>
                                            </div>
                                            <div>
                                                <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $stats->stumpings }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Stumpings</p>
                                            </div>
                                            <div>
                                                <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $stats->run_outs }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Run Outs</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center">
                                <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400">No statistics recorded for this tournament yet.</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    </div>
@endsection
