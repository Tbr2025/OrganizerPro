@extends('backend.layouts.app')

@section('title', 'Create Match | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-screen-xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[['label' => 'Matches', 'url' => route('admin.matches.index')], ['label' => 'Create']]" />

        <div class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.matches.store') }}" class="space-y-6">
                        @csrf

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

                            {{-- Match Title --}}
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Match Title
                                </label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    placeholder="Enter Match Title"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            {{-- Match Date --}}
                            <div>
                                <label for="match_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Match Date
                                </label>
                                <input type="text" name="match_date" id="match_date" value="{{ old('match_date') }}" required
                                    class="flatpickr-date mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="Select match date">
                            </div>

                            {{-- Start Time --}}
                            <div>
                                <label for="start_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Start Time
                                </label>
                                <input type="text" name="start_time" id="start_time" value="{{ old('start_time') }}" required
                                    class="flatpickr-time mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="Start Time">
                            </div>

                            {{-- End Time --}}
                            <div>
                                <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    End Time
                                </label>
                                <input type="text" name="end_time" id="end_time" value="{{ old('end_time') }}" required
                                    class="flatpickr-time mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="End Time">
                            </div>

                            {{-- Team A --}}
                            <div>
                                <label for="team_a_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Team A
                                </label>
                                <select name="team_a_id" id="team_a_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Team A</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" @selected(old('team_a_id') == $team->id)>
                                            {{ $team->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Team B --}}
                            <div>
                                <label for="team_b_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Team B
                                </label>
                                <select name="team_b_id" id="team_b_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Team B</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" @selected(old('team_b_id') == $team->id)>
                                            {{ $team->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Tournament --}}
                            <div class="sm:col-span-2">
                                <label for="tournament_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Tournament
                                </label>
                                <select name="tournament_id" id="tournament_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Tournament</option>
                                    @foreach ($tournaments as $tournament)
                                        <option value="{{ $tournament->id }}" @selected(old('tournament_id') == $tournament->id)>
                                            {{ $tournament->name }}
                                            ({{ \Carbon\Carbon::parse($tournament->start_date)->format('M d') }} -
                                            {{ \Carbon\Carbon::parse($tournament->end_date)->format('M d, Y') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Location --}}
                            <div class="sm:col-span-2">
                                <label for="location"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Location
                                </label>
                                <input type="text" name="location" id="location" value="{{ old('location') }}"
                                    placeholder="Stadium or Ground"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        {{-- Submit Buttons --}}
                        <div class="pt-4 flex items-center justify-between">
                            <a href="{{ route('admin.matches.index') }}"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Save Match
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
        document.addEventListener('DOMContentLoaded', function () {
            flatpickr('.flatpickr-date', {
                dateFormat: 'Y-m-d',
                minDate: 'today'
            });

            flatpickr('.flatpickr-time', {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i",
                time_24hr: true
            });

            const teamA = document.getElementById('team_a_id');
            const teamB = document.getElementById('team_b_id');

            function preventSameSelection() {
                const selectedA = teamA.value;
                Array.from(teamB.options).forEach(option => {
                    option.disabled = (option.value === selectedA && option.value !== "");
                });
                if (teamB.value === selectedA) {
                    teamB.value = '';
                }
            }

            teamA.addEventListener('change', preventSameSelection);
            preventSameSelection();
        });
    </script>
@endpush
