@extends('backend.layouts.app')

@section('title', 'Create Player')

@section('admin-content')
<div class="p-4 mx-auto max-w-3xl md:p-6">
    <div class="mb-6">
        <a href="{{ route('team-manager.dashboard') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1 mb-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Dashboard
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Player</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Add a new player to {{ $team->name }}</p>
    </div>

    <form action="{{ route('team-manager.players.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="space-y-6">

            {{-- Basic Information --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Basic Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Player Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="name" class="form-control mt-1"
                            value="{{ old('name') }}" placeholder="Enter player's full name" required>
                        @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" name="email" id="email" class="form-control mt-1"
                            value="{{ old('email') }}" placeholder="player@example.com">
                        @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                        <input type="text" name="phone" id="phone" class="form-control mt-1"
                            value="{{ old('phone') }}" placeholder="971501234567 (without +)">
                        @error('phone') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cricheroes_number_full" class="block text-sm font-medium text-gray-700 dark:text-gray-300">CricHeroes Number</label>
                        <input type="text" name="cricheroes_number_full" id="cricheroes_number_full" class="form-control mt-1"
                            value="{{ old('cricheroes_number_full') }}" placeholder="CricHeroes registered number">
                        @error('cricheroes_number_full') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if($locations->count() > 0)
                    <div>
                        <label for="location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                        <select name="location_id" id="location_id" class="form-control mt-1">
                            <option value="">Select location</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('location_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    @endif
                </div>
            </div>

            {{-- Jersey Information --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Jersey Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="jersey_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Jersey Name</label>
                        <input type="text" name="jersey_name" id="jersey_name" class="form-control mt-1"
                            value="{{ old('jersey_name') }}" placeholder="Name on jersey">
                        @error('jersey_name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="jersey_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Jersey Number</label>
                        <input type="number" name="jersey_number" id="jersey_number" class="form-control mt-1"
                            value="{{ old('jersey_number') }}" min="0" max="999" placeholder="e.g., 7">
                        @error('jersey_number') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if($kitSizes->count() > 0)
                    <div>
                        <label for="kit_size_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Jersey Size</label>
                        <select name="kit_size_id" id="kit_size_id" class="form-control mt-1">
                            <option value="">Select size</option>
                            @foreach($kitSizes as $size)
                                <option value="{{ $size->id }}" {{ old('kit_size_id') == $size->id ? 'selected' : '' }}>{{ $size->size ?? $size->name }}</option>
                            @endforeach
                        </select>
                        @error('kit_size_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    @endif
                </div>
            </div>

            {{-- Player Profile --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Player Profile</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @if($playerTypes->count() > 0)
                    <div>
                        <label for="player_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player Type</label>
                        <select name="player_type_id" id="player_type_id" class="form-control mt-1">
                            <option value="">Select type</option>
                            @foreach($playerTypes as $type)
                                <option value="{{ $type->id }}" {{ old('player_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name ?? $type->type }}</option>
                            @endforeach
                        </select>
                        @error('player_type_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    @if($battingProfiles->count() > 0)
                    <div>
                        <label for="batting_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batting Style</label>
                        <select name="batting_profile_id" id="batting_profile_id" class="form-control mt-1">
                            <option value="">Select batting style</option>
                            @foreach($battingProfiles as $profile)
                                <option value="{{ $profile->id }}" {{ old('batting_profile_id') == $profile->id ? 'selected' : '' }}>{{ $profile->name ?? $profile->style }}</option>
                            @endforeach
                        </select>
                        @error('batting_profile_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    @if($bowlingProfiles->count() > 0)
                    <div>
                        <label for="bowling_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bowling Style</label>
                        <select name="bowling_profile_id" id="bowling_profile_id" class="form-control mt-1">
                            <option value="">Select bowling style</option>
                            @foreach($bowlingProfiles as $profile)
                                <option value="{{ $profile->id }}" {{ old('bowling_profile_id') == $profile->id ? 'selected' : '' }}>{{ $profile->name ?? $profile->style }}</option>
                            @endforeach
                        </select>
                        @error('bowling_profile_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    @endif
                </div>

                <div class="mt-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_wicket_keeper" value="1"
                               class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                               {{ old('is_wicket_keeper') ? 'checked' : '' }}>
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Wicket Keeper</span>
                    </label>
                </div>
            </div>

            {{-- Leather Ball Experience --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Leather Ball Experience</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="total_matches" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total Matches</label>
                        <input type="number" name="total_matches" id="total_matches" class="form-control mt-1"
                            value="{{ old('total_matches', 0) }}" min="0">
                        @error('total_matches') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="total_runs" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total Runs</label>
                        <input type="number" name="total_runs" id="total_runs" class="form-control mt-1"
                            value="{{ old('total_runs', 0) }}" min="0">
                        @error('total_runs') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="total_wickets" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total Wickets</label>
                        <input type="number" name="total_wickets" id="total_wickets" class="form-control mt-1"
                            value="{{ old('total_wickets', 0) }}" min="0">
                        @error('total_wickets') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Travel & Transportation --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6" x-data="{ noTravel: {{ old('no_travel_plan') ? 'true' : 'false' }} }">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Travel & Transportation</h3>

                <div class="space-y-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="transportation_required" value="1"
                               class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                               {{ old('transportation_required') ? 'checked' : '' }}>
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Transportation required to venue</span>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="no_travel_plan" value="1" x-model="noTravel"
                               class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                               {{ old('no_travel_plan') ? 'checked' : '' }}>
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No travel plans (available throughout)</span>
                    </label>

                    <div x-show="!noTravel" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                        <div>
                            <label for="travel_date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Travel From</label>
                            <input type="date" name="travel_date_from" id="travel_date_from" class="form-control mt-1"
                                value="{{ old('travel_date_from') }}">
                            @error('travel_date_from') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="travel_date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Travel To</label>
                            <input type="date" name="travel_date_to" id="travel_date_to" class="form-control mt-1"
                                value="{{ old('travel_date_to') }}">
                            @error('travel_date_to') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Photo --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Player Photo</h3>
                <div>
                    <input type="file" name="photo" id="photo" class="form-control" accept="image/*">
                    <p class="text-xs text-gray-500 mt-1">Recommended: Square image, max 6MB (JPEG, PNG)</p>
                    @error('photo') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end space-x-4">
                <a href="{{ route('team-manager.dashboard') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Player</button>
            </div>
        </div>
    </form>
</div>
@endsection
