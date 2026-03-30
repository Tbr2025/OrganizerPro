@extends('backend.layouts.app')

@section('title', 'Fixtures | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    ['name' => 'Fixtures']
]" />

<div class="space-y-6" x-data="fixtureManager()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Fixtures</h1>
            <p class="text-gray-500">Manage tournament matches and schedules</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button @click="openAddModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition text-sm">
                + Add Match
            </button>
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
                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-lg py-2 z-10">
                    @foreach(['quarter_final' => 'Quarter Finals', 'semi_final' => 'Semi Finals', 'third_place' => 'Third Place', 'final' => 'Final'] as $stage => $label)
                        <form action="{{ route('admin.tournaments.fixtures.generate-knockouts', $tournament) }}" method="POST">
                            @csrf
                            <input type="hidden" name="stage" value="{{ $stage }}">
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                {{ $label }}
                            </button>
                        </form>
                    @endforeach
                    <div class="border-t dark:border-gray-700 my-1"></div>
                    <form action="{{ route('admin.tournaments.fixtures.generate-ipl-playoffs', $tournament) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 font-medium text-purple-600 dark:text-purple-400">
                            IPL Playoffs (Q1, E, Q2, F)
                        </button>
                    </form>
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
                                    <button @click="openEditModal({
                                        id: {{ $match->id }},
                                        team_a_id: '{{ $match->team_a_id }}',
                                        team_b_id: '{{ $match->team_b_id }}',
                                        stage: '{{ $match->stage }}',
                                        date: '{{ $match->match_date ? \Carbon\Carbon::parse($match->match_date)->format('Y-m-d') : '' }}',
                                        start_time: '{{ $match->start_time }}',
                                        ground_id: '{{ $match->ground_id }}',
                                        group_id: '{{ $match->tournament_group_id }}',
                                        overs: '{{ $match->overs }}'
                                    })" class="p-1 text-gray-400 hover:text-blue-500" title="Edit Match">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <form action="{{ route('admin.tournaments.fixtures.generate-poster', [$tournament, $match]) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="p-1 text-gray-400 hover:text-green-500" title="{{ $match->poster_image ? 'Regenerate Poster' : 'Generate Poster' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    </form>
                                    @if($match->poster_image)
                                        <button type="button" @click="posterUrl = '{{ asset('storage/' . $match->poster_image) }}'; posterMatch = '#{{ $match->match_number }} {{ $match->teamA?->name ?? 'TBD' }} vs {{ $match->teamB?->name ?? 'TBD' }}'; showPosterModal = true" class="p-1 text-green-500 hover:text-green-700" title="View Poster">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                    @endif
                                    @if($match->status !== 'completed')
                                        <form action="{{ route('admin.tournaments.fixtures.destroy', [$tournament, $match]) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Delete this match? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 text-gray-400 hover:text-red-500" title="Delete Match">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
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

    <!-- Add Match Modal -->
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50" @click="showAddModal = false"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Add Match</h3>
                <form action="{{ route('admin.tournaments.fixtures.store', $tournament) }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team A</label>
                            <select name="team_a_id" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">Select team...</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team B</label>
                            <select name="team_b_id" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">Select team...</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stage</label>
                            <select name="stage" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="group">Group Stage</option>
                                <option value="league">League</option>
                                <option value="quarter_final">Quarter Final</option>
                                <option value="semi_final">Semi Final</option>
                                <option value="qualifier_1">Qualifier 1</option>
                                <option value="eliminator">Eliminator</option>
                                <option value="qualifier_2">Qualifier 2</option>
                                <option value="third_place">3rd Place</option>
                                <option value="final">Final</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Group</label>
                            <select name="group_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">None</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-data="{
                            init() {
                                flatpickr(this.$refs.addDatePicker, {
                                    enableTime: false,
                                    dateFormat: 'Y-m-d',
                                    altInput: true,
                                    altFormat: 'F j, Y',
                                    disableMobile: true,
                                    static: true,
                                    locale: { firstDayOfWeek: 1 }
                                });
                            }
                        }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <iconify-icon icon="lucide:calendar" class="text-gray-400 dark:text-gray-500 z-1"></iconify-icon>
                                </div>
                                <input x-ref="addDatePicker" type="text" name="date"
                                       class="form-control !ps-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" placeholder="Select date">
                            </div>
                        </div>
                        <div x-data="{
                            init() {
                                flatpickr(this.$refs.addTimePicker, {
                                    enableTime: true,
                                    noCalendar: true,
                                    dateFormat: 'H:i',
                                    altInput: true,
                                    altFormat: 'h:i K',
                                    time_24hr: false,
                                    disableMobile: true,
                                    static: true
                                });
                            }
                        }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <iconify-icon icon="lucide:clock" class="text-gray-400 dark:text-gray-500 z-1"></iconify-icon>
                                </div>
                                <input x-ref="addTimePicker" type="text" name="start_time"
                                       class="form-control !ps-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" placeholder="Select time">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ground</label>
                            <select name="ground_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">Select ground...</option>
                                @if($tournament->location)
                                    <option value="location:{{ $tournament->location }}" selected>{{ $tournament->location }} (Tournament Location)</option>
                                @endif
                                @foreach($grounds as $ground)
                                    <option value="{{ $ground->id }}">{{ $ground->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Overs</label>
                            <input type="number" name="overs" min="1" max="50" placeholder="{{ $tournament->settings->overs_per_match ?? 20 }}"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="showAddModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 btn-primary">
                            Create Match
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Match Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50" @click="showEditModal = false"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Edit Match</h3>
                <form :action="editFormAction" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team A</label>
                            <select name="team_a_id" x-model="editMatch.team_a_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">Select team...</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team B</label>
                            <select name="team_b_id" x-model="editMatch.team_b_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">Select team...</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stage</label>
                            <select name="stage" x-model="editMatch.stage" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="group">Group Stage</option>
                                <option value="league">League</option>
                                <option value="quarter_final">Quarter Final</option>
                                <option value="semi_final">Semi Final</option>
                                <option value="qualifier_1">Qualifier 1</option>
                                <option value="eliminator">Eliminator</option>
                                <option value="qualifier_2">Qualifier 2</option>
                                <option value="third_place">3rd Place</option>
                                <option value="final">Final</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Group</label>
                            <select name="group_id" x-model="editMatch.group_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">None</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <iconify-icon icon="lucide:calendar" class="text-gray-400 dark:text-gray-500 z-1"></iconify-icon>
                                </div>
                                <input x-ref="editDatePicker" type="text" name="date"
                                       class="form-control !ps-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" placeholder="Select date">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <iconify-icon icon="lucide:clock" class="text-gray-400 dark:text-gray-500 z-1"></iconify-icon>
                                </div>
                                <input x-ref="editTimePicker" type="text" name="start_time"
                                       class="form-control !ps-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" placeholder="Select time">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ground</label>
                            <select name="ground_id" x-model="editMatch.ground_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                <option value="">Select ground...</option>
                                @if($tournament->location)
                                    <option value="location:{{ $tournament->location }}">{{ $tournament->location }} (Tournament Location)</option>
                                @endif
                                @foreach($grounds as $ground)
                                    <option value="{{ $ground->id }}">{{ $ground->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Overs</label>
                            <input type="number" name="overs" x-model="editMatch.overs" min="1" max="50"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button type="button" @click="showEditModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 btn-primary">
                            Update Match
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function fixtureManager() {
    return {
        showAddModal: false,
        showEditModal: false,
        editMatch: {
            id: null,
            team_a_id: '',
            team_b_id: '',
            stage: 'group',
            date: '',
            start_time: '',
            ground_id: '',
            group_id: '',
            overs: ''
        },
        get editFormAction() {
            return '{{ url("admin/tournaments/" . $tournament->id . "/fixtures") }}/' + this.editMatch.id;
        },
        editDateFp: null,
        editTimeFp: null,
        initEditPickers() {
            this.$nextTick(() => {
                if (!this.editDateFp && this.$refs.editDatePicker) {
                    this.editDateFp = flatpickr(this.$refs.editDatePicker, {
                        enableTime: false,
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'F j, Y',
                        disableMobile: true,
                        static: true,
                        locale: { firstDayOfWeek: 1 },
                        onChange: (selectedDates, dateStr) => {
                            this.editMatch.date = dateStr;
                        }
                    });
                }
                if (!this.editTimeFp && this.$refs.editTimePicker) {
                    this.editTimeFp = flatpickr(this.$refs.editTimePicker, {
                        enableTime: true,
                        noCalendar: true,
                        dateFormat: 'H:i',
                        altInput: true,
                        altFormat: 'h:i K',
                        time_24hr: false,
                        disableMobile: true,
                        static: true,
                        onChange: (selectedDates, dateStr) => {
                            this.editMatch.start_time = dateStr;
                        }
                    });
                }
            });
        },
        openAddModal() {
            this.showAddModal = true;
        },
        openEditModal(match) {
            this.editMatch = { ...match };
            this.showEditModal = true;
            this.$nextTick(() => {
                this.initEditPickers();
                if (this.editDateFp) {
                    this.editDateFp.setDate(this.editMatch.date || '', false);
                }
                if (this.editTimeFp) {
                    this.editTimeFp.setDate(this.editMatch.start_time || '', false);
                }
            });
        }
    };
}
</script>
@endpush
@endsection
