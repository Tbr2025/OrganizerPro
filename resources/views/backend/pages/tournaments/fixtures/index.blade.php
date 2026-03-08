@extends('backend.layouts.app')

@section('title', 'Fixtures | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    ['name' => 'Fixtures']
]" />

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Fixtures</h1>
            <p class="text-gray-500">Manage tournament matches and schedules</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form action="{{ route('admin.tournaments.fixtures.generate-group', $tournament) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition text-sm">
                    Generate Group Stage
                </button>
            </form>
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition text-sm">
                    Generate Knockouts
                </button>
                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg py-2 z-10">
                    @foreach(['quarter_final' => 'Quarter Finals', 'semi_final' => 'Semi Finals', 'third_place' => 'Third Place', 'final' => 'Final'] as $stage => $label)
                        <form action="{{ route('admin.tournaments.fixtures.generate-knockouts', $tournament) }}" method="POST">
                            @csrf
                            <input type="hidden" name="stage" value="{{ $stage }}">
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                {{ $label }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
            <form action="{{ route('admin.tournaments.fixtures.bulk-posters', $tournament) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm">
                    Generate All Posters
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-green-100 text-green-700 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-red-100 text-red-700 rounded-lg">{{ session('error') }}</div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $matches->count() }}</div>
            <div class="text-sm text-gray-500">Total Matches</div>
        </div>
        <div class="card p-4">
            <div class="text-2xl font-bold text-green-600">{{ $matches->where('status', 'completed')->count() }}</div>
            <div class="text-sm text-gray-500">Completed</div>
        </div>
        <div class="card p-4">
            <div class="text-2xl font-bold text-yellow-600">{{ $matches->where('status', 'upcoming')->count() }}</div>
            <div class="text-sm text-gray-500">Upcoming</div>
        </div>
        <div class="card p-4">
            <div class="text-2xl font-bold text-red-600">{{ $matches->where('is_cancelled', true)->count() }}</div>
            <div class="text-sm text-gray-500">Cancelled</div>
        </div>
    </div>

    <!-- Matches by Stage -->
    @if($matches->count() > 0)
        @foreach($groupedMatches as $stage => $stageMatches)
            <div class="card overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 px-4 py-3 flex items-center justify-between">
                    <h3 class="text-white font-bold">{{ ucwords(str_replace('_', ' ', $stage)) }} Stage</h3>
                    <span class="text-white/80 text-sm">{{ $stageMatches->count() }} Matches</span>
                </div>
                <div class="divide-y dark:divide-gray-700">
                    @foreach($stageMatches as $match)
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 {{ $match->is_cancelled ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                            <div class="flex items-center gap-4">
                                <span class="text-gray-400 text-sm font-medium">#{{ $match->match_number }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $match->teamA?->name ?? 'TBD' }}</span>
                                    <span class="text-gray-400">vs</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $match->teamB?->name ?? 'TBD' }}</span>
                                </div>
                                @if($match->group)
                                    <span class="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 rounded">
                                        {{ $match->group->name }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-sm text-gray-500">
                                    @if($match->match_date)
                                        {{ \Carbon\Carbon::parse($match->match_date)->format('M d, Y') }}
                                        @if($match->start_time)
                                            @ {{ \Carbon\Carbon::parse($match->start_time)->format('h:i A') }}
                                        @endif
                                    @else
                                        <span class="text-yellow-600">Not scheduled</span>
                                    @endif
                                </div>
                                @if($match->is_cancelled)
                                    <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded">Cancelled</span>
                                @elseif($match->status === 'completed')
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded">Completed</span>
                                @elseif($match->status === 'live')
                                    <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded animate-pulse">Live</span>
                                @else
                                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded">Upcoming</span>
                                @endif
                                <div class="flex gap-1">
                                    <button onclick="openRescheduleModal({{ $match->id }}, '{{ $match->match_date }}', '{{ $match->start_time }}', '{{ $match->ground_id }}')"
                                            class="p-1 text-gray-400 hover:text-blue-500">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <form action="{{ route('admin.tournaments.fixtures.generate-poster', [$tournament, $match]) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="p-1 text-gray-400 hover:text-green-500" title="Generate Poster">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <!-- Delete Group Stage -->
        @if($groupedMatches->has('group'))
            <div class="flex justify-end">
                <form action="{{ route('admin.tournaments.fixtures.delete-group', $tournament) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete all group stage fixtures? This cannot be undone.')"
                            class="px-4 py-2 text-red-500 hover:text-red-700 text-sm">
                        Delete Group Stage Fixtures
                    </button>
                </form>
            </div>
        @endif
    @else
        <div class="card p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No fixtures created</h3>
            <p class="text-gray-500 mb-4">Generate group stage or knockout fixtures to get started.</p>
        </div>
    @endif
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('rescheduleModal').classList.add('hidden')"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Reschedule Match</h3>
            <form id="rescheduleForm" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date</label>
                        <input type="date" name="match_date" id="reschedule_date" required
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Start Time</label>
                        <input type="time" name="start_time" id="reschedule_time"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ground</label>
                        <select name="ground_id" id="reschedule_ground"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">Select ground...</option>
                            @foreach($grounds as $ground)
                                <option value="{{ $ground->id }}">{{ $ground->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button type="button" onclick="document.getElementById('rescheduleModal').classList.add('hidden')"
                            class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 btn-primary">
                        Reschedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openRescheduleModal(matchId, date, time, groundId) {
    document.getElementById('rescheduleForm').action = '{{ url("admin/tournaments/" . $tournament->id . "/fixtures") }}/' + matchId + '/reschedule';
    document.getElementById('reschedule_date').value = date || '';
    document.getElementById('reschedule_time').value = time || '';
    document.getElementById('reschedule_ground').value = groundId || '';
    document.getElementById('rescheduleModal').classList.remove('hidden');
}
</script>
@endpush
@endsection
