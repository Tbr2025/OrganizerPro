@extends('backend.layouts.app')

@section('title')
    {{ $player->name }} | Player Profile
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-screen-xl md:p-6 lg:p-8">

        {{-- HEADER: Breadcrumbs & Edit Button --}}
        <div class="flex justify-between items-center mb-6">
            <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
            @can('player.edit')
                <a href="{{ route('admin.players.edit', $player->id) }}" class="btn btn-primary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" />
                    </svg>
                    Edit Player
                </a>
            @endcan
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
                        @php $personalFields = ['email', 'mobile_number_full', 'cricheroes_number_full', 'location.name']; @endphp
                        @foreach ($personalFields as $field)
                            <div>
                                <label
                                    class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $fields[$field] }}</label>
                                <p class="mt-1 text-md font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    {{ data_get($player, $field, 'N/A') }}
                                    @if ($player->{'verified_' . str_replace('.', '_', $field)})
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

                <!-- Additional Information -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-white">Additional Information</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="flex items-center gap-3">
                            @if ($player->is_wicket_keeper)
                                <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            @else
                                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                            <span class="text-md text-gray-800 dark:text-gray-200">Wicket Keeper</span>
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($player->transportation_required)
                                <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            @else
                                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                            <span class="text-md text-gray-800 dark:text-gray-200">Requires Transportation</span>
                        </div>
                        <div class="flex items-start gap-3">
                            @if (!$player->no_travel_plan)
                                <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <div>
                                    <span class="text-md text-gray-800 dark:text-gray-200">Unavailable</span>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        From:
                                        <strong>{{ $player->travel_date_from ? \Carbon\Carbon::parse($player->travel_date_from)->format('M d, Y') : 'N/A' }}</strong>
                                        To:
                                        <strong>{{ $player->travel_date_to ? \Carbon\Carbon::parse($player->travel_date_to)->format('M d, Y') : 'N/A' }}</strong>
                                    </p>
                                </div>
                            @else
                                <svg class="w-6 h-6 text-green-500 flex-shrink-0" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <div>
                                    <span class="text-md text-gray-800 dark:text-gray-200">Available</span>

                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    </div>
@endsection
