@extends('backend.layouts.app')

@section('title', 'Create Player')

@section('admin-content')
<div class="p-4 mx-auto max-w-2xl md:p-6">
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
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="space-y-6">
                {{-- Player Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Player Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" class="form-control mt-1"
                        value="{{ old('name') }}" placeholder="Enter player's full name" required>
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email & Phone --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email
                        </label>
                        <input type="email" name="email" id="email" class="form-control mt-1"
                            value="{{ old('email') }}" placeholder="player@example.com">
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Phone
                        </label>
                        <input type="text" name="phone" id="phone" class="form-control mt-1"
                            value="{{ old('phone') }}" placeholder="+1234567890">
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Date of Birth --}}
                <div>
                    <label for="dob" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Date of Birth
                    </label>
                    <input type="date" name="dob" id="dob" class="form-control mt-1"
                        value="{{ old('dob') }}">
                    @error('dob')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Playing Role --}}
                <div>
                    <label for="playing_role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Playing Role
                    </label>
                    <select name="playing_role" id="playing_role" class="form-control mt-1">
                        <option value="">Select Role</option>
                        <option value="Batsman" {{ old('playing_role') == 'Batsman' ? 'selected' : '' }}>Batsman</option>
                        <option value="Bowler" {{ old('playing_role') == 'Bowler' ? 'selected' : '' }}>Bowler</option>
                        <option value="All-rounder" {{ old('playing_role') == 'All-rounder' ? 'selected' : '' }}>All-rounder</option>
                        <option value="Wicket-keeper" {{ old('playing_role') == 'Wicket-keeper' ? 'selected' : '' }}>Wicket-keeper</option>
                        <option value="Wicket-keeper Batsman" {{ old('playing_role') == 'Wicket-keeper Batsman' ? 'selected' : '' }}>Wicket-keeper Batsman</option>
                    </select>
                    @error('playing_role')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Batting & Bowling Style --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="batting_style" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Batting Style
                        </label>
                        <select name="batting_style" id="batting_style" class="form-control mt-1">
                            <option value="">Select Style</option>
                            <option value="Right-handed" {{ old('batting_style') == 'Right-handed' ? 'selected' : '' }}>Right-handed</option>
                            <option value="Left-handed" {{ old('batting_style') == 'Left-handed' ? 'selected' : '' }}>Left-handed</option>
                        </select>
                        @error('batting_style')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="bowling_style" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Bowling Style
                        </label>
                        <select name="bowling_style" id="bowling_style" class="form-control mt-1">
                            <option value="">Select Style</option>
                            <option value="Right-arm Fast" {{ old('bowling_style') == 'Right-arm Fast' ? 'selected' : '' }}>Right-arm Fast</option>
                            <option value="Right-arm Medium" {{ old('bowling_style') == 'Right-arm Medium' ? 'selected' : '' }}>Right-arm Medium</option>
                            <option value="Right-arm Off-break" {{ old('bowling_style') == 'Right-arm Off-break' ? 'selected' : '' }}>Right-arm Off-break</option>
                            <option value="Right-arm Leg-break" {{ old('bowling_style') == 'Right-arm Leg-break' ? 'selected' : '' }}>Right-arm Leg-break</option>
                            <option value="Left-arm Fast" {{ old('bowling_style') == 'Left-arm Fast' ? 'selected' : '' }}>Left-arm Fast</option>
                            <option value="Left-arm Medium" {{ old('bowling_style') == 'Left-arm Medium' ? 'selected' : '' }}>Left-arm Medium</option>
                            <option value="Left-arm Orthodox" {{ old('bowling_style') == 'Left-arm Orthodox' ? 'selected' : '' }}>Left-arm Orthodox</option>
                            <option value="Left-arm Chinaman" {{ old('bowling_style') == 'Left-arm Chinaman' ? 'selected' : '' }}>Left-arm Chinaman</option>
                            <option value="N/A" {{ old('bowling_style') == 'N/A' ? 'selected' : '' }}>N/A (Does not bowl)</option>
                        </select>
                        @error('bowling_style')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Jersey Number --}}
                <div>
                    <label for="jersey_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Jersey Number
                    </label>
                    <input type="number" name="jersey_number" id="jersey_number" class="form-control mt-1 max-w-xs"
                        value="{{ old('jersey_number') }}" min="0" max="99" placeholder="e.g., 7">
                    @error('jersey_number')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Photo --}}
                <div>
                    <label for="photo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Player Photo
                    </label>
                    <input type="file" name="photo" id="photo" class="form-control mt-1" accept="image/*">
                    <p class="text-xs text-gray-500 mt-1">Recommended: Square image, max 2MB (JPEG, PNG)</p>
                    @error('photo')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-4">
                <a href="{{ route('team-manager.dashboard') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Player</button>
            </div>
        </div>
    </form>
</div>
@endsection
