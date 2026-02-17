@extends('backend.layouts.app')

@section('title', 'Edit Match | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-4xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[['label' => 'Matches', 'url' => route('admin.matches.index')], ['label' => 'Edit']]" />

        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Edit Match</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Update the match details below</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.matches.update', $match) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        {{-- Tournament Selection --}}
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                            <label for="tournament_id" class="block text-sm font-semibold text-blue-800 dark:text-blue-300 mb-2">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                Tournament
                            </label>
                            <select name="tournament_id" id="tournament_id" required
                                class="block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Choose a tournament...</option>
                                @foreach ($tournaments as $tournament)
                                    <option value="{{ $tournament->id }}" @selected(old('tournament_id', $match->tournament_id) == $tournament->id)>
                                        {{ $tournament->name }}
                                        ({{ \Carbon\Carbon::parse($tournament->start_date)->format('M d') }} -
                                        {{ \Carbon\Carbon::parse($tournament->end_date)->format('M d, Y') }})
                                    </option>
                                @endforeach
                            </select>
                            @error('tournament_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Match Title --}}
                            <div class="sm:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Match Title <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" id="name" value="{{ old('name', $match->name) }}" required
                                    placeholder="e.g., Semi Final 1, League Match Day 3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Team A --}}
                            <div>
                                <label for="team_a_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Team A <span class="text-red-500">*</span>
                                </label>
                                <select name="team_a_id" id="team_a_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Team A</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" @selected(old('team_a_id', $match->team_a_id) == $team->id)>
                                            {{ $team->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('team_a_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Team B --}}
                            <div>
                                <label for="team_b_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Team B <span class="text-red-500">*</span>
                                </label>
                                <select name="team_b_id" id="team_b_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Team B</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" @selected(old('team_b_id', $match->team_b_id) == $team->id)>
                                            {{ $team->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('team_b_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Date & Time Section --}}
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Schedule
                            </h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- Match Date --}}
                                <div>
                                    <label for="match_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Match Date <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="match_date" id="match_date"
                                        value="{{ old('match_date', $match->match_date ? \Carbon\Carbon::parse($match->match_date)->format('Y-m-d') : '') }}"
                                        required readonly
                                        class="flatpickr-date block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm cursor-pointer"
                                        placeholder="Select date">
                                    @error('match_date')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Start Time --}}
                                <div>
                                    <label for="start_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Start Time <span class="text-red-500">*</span>
                                    </label>
                                    <input type="time" name="start_time" id="start_time"
                                        value="{{ old('start_time', $match->start_time ? \Carbon\Carbon::parse($match->start_time)->format('H:i') : '') }}"
                                        required
                                        class="block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @error('start_time')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- End Time --}}
                                <div>
                                    <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        End Time <span class="text-red-500">*</span>
                                    </label>
                                    <input type="time" name="end_time" id="end_time"
                                        value="{{ old('end_time', $match->end_time ? \Carbon\Carbon::parse($match->end_time)->format('H:i') : '') }}"
                                        required
                                        class="block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @error('end_time')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Overs --}}
                            <div>
                                <label for="overs" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Overs
                                </label>
                                <select name="overs" id="overs"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Overs</option>
                                    @foreach ([5, 6, 8, 10, 12, 15, 20, 50] as $over)
                                        <option value="{{ $over }}" @selected(old('overs', $match->overs) == $over)>{{ $over }} Overs</option>
                                    @endforeach
                                </select>
                                @error('overs')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Venue --}}
                            <div>
                                <label for="venue" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Venue
                                </label>
                                <input type="text" name="venue" id="venue" value="{{ old('venue', $match->venue) }}"
                                    placeholder="Stadium or Ground name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('venue')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Status --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Status
                                </label>
                                <select name="status" id="status"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="upcoming" @selected(old('status', $match->status) == 'upcoming')>Upcoming</option>
                                    <option value="live" @selected(old('status', $match->status) == 'live')>Live</option>
                                    <option value="completed" @selected(old('status', $match->status) == 'completed')>Completed</option>
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Submit Buttons --}}
                        <div class="pt-4 flex items-center justify-between border-t border-gray-200 dark:border-gray-700">
                            <a href="{{ route('admin.matches.index') }}"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-6 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Update Match
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tournament data with dates
            const tournaments = @json($tournaments->mapWithKeys(fn($t) => [$t->id => ['start_date' => $t->start_date, 'end_date' => $t->end_date]]));

            // Initialize date picker
            const datePicker = flatpickr('#match_date', {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'F j, Y',
                disableMobile: true
            });

            // Update date picker when tournament changes
            const tournamentSelect = document.getElementById('tournament_id');

            function updateDatePickerRange() {
                const tournamentId = tournamentSelect.value;

                if (tournamentId && tournaments[tournamentId]) {
                    const tournament = tournaments[tournamentId];
                    datePicker.set('minDate', tournament.start_date);
                    datePicker.set('maxDate', tournament.end_date);

                    // Clear date if it's outside the new range
                    const currentDate = datePicker.selectedDates[0];
                    if (currentDate) {
                        const minDate = new Date(tournament.start_date);
                        const maxDate = new Date(tournament.end_date);
                        if (currentDate < minDate || currentDate > maxDate) {
                            datePicker.clear();
                        }
                    }
                } else {
                    datePicker.set('minDate', null);
                    datePicker.set('maxDate', null);
                }
            }

            tournamentSelect.addEventListener('change', updateDatePickerRange);

            // Initialize on page load if tournament is already selected
            if (tournamentSelect.value) {
                updateDatePickerRange();
            }

            // Prevent selecting same team for both sides
            const teamA = document.getElementById('team_a_id');
            const teamB = document.getElementById('team_b_id');

            function preventSameSelection() {
                const selectedA = teamA.value;
                const selectedB = teamB.value;

                Array.from(teamA.options).forEach(option => {
                    option.disabled = (option.value === selectedB && option.value !== "");
                });
                Array.from(teamB.options).forEach(option => {
                    option.disabled = (option.value === selectedA && option.value !== "");
                });
            }

            teamA.addEventListener('change', preventSameSelection);
            teamB.addEventListener('change', preventSameSelection);
            preventSameSelection();
        });
    </script>
@endpush
