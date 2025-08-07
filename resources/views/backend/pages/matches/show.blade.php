@extends('backend.layouts.app')

@section('title', 'Match Details | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-screen-xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[['label' => 'Matches', 'url' => route('admin.matches.index')], ['label' => 'Match Details']]" />

        <div class="mt-6 rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="p-6 space-y-4">
                {{-- Match Info --}}
                <div class="text-xl font-semibold text-gray-800 dark:text-white">
                    {{ $match->name }}
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Tournament</p>
                        <p class="font-medium text-gray-800 dark:text-white">{{ $match->tournament->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Match Date</p>
                        <p class="font-medium text-gray-800 dark:text-white">
                            {{ \Carbon\Carbon::parse($match->match_date)->format('F d, Y') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Start Time</p>
                        <p class="font-medium text-gray-800 dark:text-white">{{ $match->start_time }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">End Time</p>
                        <p class="font-medium text-gray-800 dark:text-white">{{ $match->end_time }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Team A</p>
                        <p class="font-medium text-gray-800 dark:text-white">{{ $match->teamA->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Team B</p>
                        <p class="font-medium text-gray-800 dark:text-white">{{ $match->teamB->name }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Location</p>
                        <p class="font-medium text-gray-800 dark:text-white">
                            {{ $match->location ?? 'Not specified' }}
                        </p>
                    </div>
                </div>
                {{-- Team Players --}}
                <div class="mt-8 space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Team A Players
                            ({{ $match->teamA->name }})</h2>
                        <ul class="mt-2 list-disc list-inside text-gray-700 dark:text-gray-300">
                            @forelse ($teamAPlayers as $player)
                                <li>{{ $player->name }} ({{ $player->jersey_name ?? 'No Jersey' }})</li>
                            @empty
                                <li>No players found for Team A.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Team B Players
                            ({{ $match->teamB->name }})</h2>
                        <ul class="mt-2 list-disc list-inside text-gray-700 dark:text-gray-300">
                            @forelse ($teamBPlayers as $player)
                                <li>{{ $player->name }} ({{ $player->jersey_name ?? 'No Jersey' }})</li>
                            @empty
                                <li>No players found for Team B.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                {{-- Over Summary --}}
                <div class="mt-8">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Over-wise Summary</h2>
                    @forelse ($overs as $overNum => $balls)
                        <div class="mt-2 border border-gray-200 dark:border-gray-600 rounded p-3">
                            <p class="font-semibold">Over {{ $overNum }}</p>
                            <div class="flex flex-wrap gap-2 mt-1">
                                @foreach ($balls as $ball)
                                    <span class="px-2 py-1 text-sm rounded bg-gray-100 dark:bg-gray-800">
                                        @if ($ball->is_wicket)
                                            W
                                        @elseif ($ball->extra_type === 'wide')
                                            {{ $ball->runs + $ball->extra_runs }}wd
                                        @elseif ($ball->extra_type === 'no_ball')
                                            {{ $ball->runs + $ball->extra_runs }}nb
                                        @else
                                            {{ $ball->runs }}
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-600 dark:text-gray-400">No balls recorded yet.</p>
                    @endforelse
                </div>
                @foreach ($overs as $over => $balls)
                    <div class="mt-2">
                        <strong>Over {{ $over }}:</strong>
                        @foreach ($balls as $ball)
                            <span class="inline-block px-2 py-1 bg-gray-100 rounded mr-1">
                                {{ $ball->runs }}{{ $ball->extra_type ? $ball->extra_type[0] : '' }}{{ $ball->is_wicket ? 'W' : '' }}
                            </span>
                        @endforeach
                    </div>
                @endforeach

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-6">
                    <a href="{{ route('admin.balls.create', $match) }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md shadow-sm hover:bg-indigo-700">
                        Add Score
                    </a>
                    <a href="{{ route('admin.matches.scorecard', $match) }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md shadow-sm hover:bg-indigo-700">
                        View Scorecard
                    </a>

                    <a href="{{ route('admin.matches.edit', $match) }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md shadow-sm hover:bg-indigo-700">
                        Edit
                    </a>

                    <form method="POST" action="{{ route('admin.matches.destroy', $match) }}"
                        onsubmit="return confirm('Are you sure you want to delete this match?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md shadow-sm hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
