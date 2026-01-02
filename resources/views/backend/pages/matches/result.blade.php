@extends('backend.layouts.app')

@section('title', 'Match Result | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-4xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Enter Match Result</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $match->teamA?->name ?? 'TBA' }} vs {{ $match->teamB?->name ?? 'TBA' }}
            @if($match->match_date)
                - {{ $match->match_date->format('M d, Y') }}
            @endif
        </p>
    </div>

    {{-- Match Info Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                @if($match->teamA?->logo)
                    <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="h-16 w-16 object-contain">
                @else
                    <div class="h-16 w-16 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center">
                        <span class="text-xl font-bold">{{ substr($match->teamA?->short_name ?? 'A', 0, 2) }}</span>
                    </div>
                @endif
                <div>
                    <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ $match->teamA?->name ?? 'Team A' }}</h3>
                    <p class="text-sm text-gray-500">{{ $match->teamA?->short_name ?? '' }}</p>
                </div>
            </div>
            <div class="text-2xl font-bold text-gray-400">VS</div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ $match->teamB?->name ?? 'Team B' }}</h3>
                    <p class="text-sm text-gray-500">{{ $match->teamB?->short_name ?? '' }}</p>
                </div>
                @if($match->teamB?->logo)
                    <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="h-16 w-16 object-contain">
                @else
                    <div class="h-16 w-16 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center">
                        <span class="text-xl font-bold">{{ substr($match->teamB?->short_name ?? 'B', 0, 2) }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Result Form --}}
    <form action="{{ route('admin.matches.result.update', $match) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Toss Details --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Toss Details</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Toss Won By</label>
                        <select name="toss_winner_id" class="form-control">
                            <option value="">Select team</option>
                            <option value="{{ $match->team_a_id }}" {{ $result?->toss_winner_id == $match->team_a_id ? 'selected' : '' }}>
                                {{ $match->teamA?->name ?? 'Team A' }}
                            </option>
                            <option value="{{ $match->team_b_id }}" {{ $result?->toss_winner_id == $match->team_b_id ? 'selected' : '' }}>
                                {{ $match->teamB?->name ?? 'Team B' }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Elected To</label>
                        <select name="toss_decision" class="form-control">
                            <option value="">Select decision</option>
                            <option value="bat" {{ $result?->toss_decision === 'bat' ? 'selected' : '' }}>Bat</option>
                            <option value="field" {{ $result?->toss_decision === 'field' ? 'selected' : '' }}>Field</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Score Entry --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Score</h2>
            </div>
            <div class="p-6 space-y-6">
                {{-- Team A Score --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-4">{{ $match->teamA?->name ?? 'Team A' }}</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Runs *</label>
                            <input type="number" name="team_a_score" value="{{ $result?->team_a_score }}" min="0" required class="form-control">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Wickets *</label>
                            <input type="number" name="team_a_wickets" value="{{ $result?->team_a_wickets }}" min="0" max="10" required class="form-control">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Overs *</label>
                            <input type="text" name="team_a_overs" value="{{ $result?->team_a_overs }}" required class="form-control" placeholder="e.g., 20.0">
                        </div>
                    </div>
                </div>

                {{-- Team B Score --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-4">{{ $match->teamB?->name ?? 'Team B' }}</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Runs *</label>
                            <input type="number" name="team_b_score" value="{{ $result?->team_b_score }}" min="0" required class="form-control">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Wickets *</label>
                            <input type="number" name="team_b_wickets" value="{{ $result?->team_b_wickets }}" min="0" max="10" required class="form-control">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Overs *</label>
                            <input type="text" name="team_b_overs" value="{{ $result?->team_b_overs }}" required class="form-control" placeholder="e.g., 18.4">
                        </div>
                    </div>
                </div>

                {{-- Batting Order --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Who Batted First?</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="team_a_batting_first" value="1" class="form-radio"
                                   {{ $result?->team_a_batting_first !== false ? 'checked' : '' }}>
                            <span class="ml-2">{{ $match->teamA?->name ?? 'Team A' }}</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="team_a_batting_first" value="0" class="form-radio"
                                   {{ $result?->team_a_batting_first === false ? 'checked' : '' }}>
                            <span class="ml-2">{{ $match->teamB?->name ?? 'Team B' }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Match Outcome --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Match Outcome</h2>
            </div>
            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Winner *</label>
                    <select name="winner_id" required class="form-control">
                        <option value="">Select winner</option>
                        <option value="{{ $match->team_a_id }}" {{ $result?->winner_id == $match->team_a_id ? 'selected' : '' }}>
                            {{ $match->teamA?->name ?? 'Team A' }}
                        </option>
                        <option value="{{ $match->team_b_id }}" {{ $result?->winner_id == $match->team_b_id ? 'selected' : '' }}>
                            {{ $match->teamB?->name ?? 'Team B' }}
                        </option>
                        <option value="tie" {{ $result?->is_tie ? 'selected' : '' }}>Tie</option>
                        <option value="no_result" {{ $result?->is_no_result ? 'selected' : '' }}>No Result</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Result Summary</label>
                    <input type="text" name="result_summary" value="{{ $result?->result_summary }}" class="form-control"
                           placeholder="e.g., Team A won by 5 wickets">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate based on scores</p>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end gap-4">
            <a href="{{ route('admin.matches.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Result</button>
        </div>
    </form>
</div>
@endsection
