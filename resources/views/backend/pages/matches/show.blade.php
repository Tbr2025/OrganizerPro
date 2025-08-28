@extends('backend.layouts.app')

@section('title', 'Match Details | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto md:p-6">
        <x-breadcrumbs :breadcrumbs="[['label' => 'Matches', 'url' => route('admin.matches.index')], ['label' => 'Match Details']]" />

        <div class="mt-6 rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="p-6 space-y-6">

                {{-- Match Info --}}
                <div class="text-xl font-semibold text-gray-800 dark:text-white">
                    {{ $match->name }}
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Tournament</p>
                        <p class="font-medium">{{ $match->tournament->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Match Date</p>
                        <p class="font-medium">
                            {{ \Carbon\Carbon::parse($match->match_date)->format('F d, Y') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Start Time</p>
                        <p class="font-medium">{{ $match->start_time }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">End Time</p>
                        <p class="font-medium">{{ $match->end_time }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Team A</p>
                        <p class="font-medium">{{ $match->teamA->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Team B</p>
                        <p class="font-medium">{{ $match->teamB->name }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Location</p>
                        <p class="font-medium">{{ $match->location ?? 'Not specified' }}</p>
                    </div>
                </div>

                {{-- Team Players --}}
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-lg font-semibold">Team A Players ({{ $match->teamA->name }})</h2>
                        <ul class="mt-2 list-disc list-inside">
                            @forelse ($teamAPlayers as $p)
                                <li>{{ $p->player->name }} ({{ $p->player->jersey_name ?? 'No Jersey' }})</li>
                            @empty
                                <li>No players found for Team A.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold">Team B Players ({{ $match->teamB->name }})</h2>
                        <ul class="mt-2 list-disc list-inside">
                            @forelse ($teamBPlayers as $p)
                                <li>{{ $p->player->name }} ({{ $p->player->jersey_name ?? 'No Jersey' }})</li>
                            @empty
                                <li>No players found for Team B.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                <h3>Match Summary</h3>
                <p>Total Score: {{ $totalRuns }}/{{ $totalWickets }}</p>
                <p>Overs: {{ $totalOvers }}</p>

             

                {{-- Over-wise Summary --}}
                <div class="mt-8">
                    <h2 class="text-lg font-semibold">Over-wise Summary</h2>

                    @forelse ($summary as $over)
                        <div class="mt-3 border border-gray-200 dark:border-gray-600 rounded p-3">
                            <p class="font-semibold">Over {{ $over['over'] }}</p>

                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach ($over['balls'] as $ball)
                                    <span class="px-2 py-1 text-sm rounded bg-gray-100 dark:bg-gray-800">
                                        {{ $ball }}
                                    </span>
                                @endforeach
                            </div>

                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Runs: <span class="font-semibold">{{ $over['runs'] }}</span>,
                                Wickets: <span class="font-semibold">{{ $over['wickets'] }}</span>
                            </p>
                        </div>
                    @empty
                        <p class="text-gray-600 dark:text-gray-400">No overs recorded yet.</p>
                    @endforelse
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-6">
                    <a href="{{ route('admin.balls.create', $match) }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                        Add Score
                    </a>

                    <a href="{{ route('admin.matches.scorecard', $match) }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                        View Scorecard
                    </a>

                    <a href="{{ route('admin.matches.edit', $match) }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                        Edit
                    </a>

                    <form method="POST" action="{{ route('admin.matches.destroy', $match) }}"
                        onsubmit="return confirm('Are you sure you want to delete this match?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
