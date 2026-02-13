@extends('backend.layouts.app')

@section('title', 'Fixture Calendar | ' . $tournament->name)

@push('styles')
<style>
    .calendar-day {
        min-height: 120px;
    }
    .time-slot {
        font-size: 0.75rem;
        padding: 4px 8px;
        margin-bottom: 4px;
        border-radius: 4px;
        cursor: pointer;
    }
    .time-slot.available {
        background-color: #d1fae5;
        border: 1px solid #10b981;
    }
    .time-slot.occupied {
        background-color: #dbeafe;
        border: 1px solid #3b82f6;
    }
    .time-slot:hover {
        opacity: 0.8;
    }
    .unscheduled-match {
        padding: 8px;
        margin-bottom: 8px;
        background-color: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 4px;
        cursor: grab;
    }
    .unscheduled-match:active {
        cursor: grabbing;
    }
</style>
@endpush

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.show', $tournament)],
    ['name' => 'Fixture Calendar']
]" />

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Main Calendar Area -->
    <div class="lg:col-span-3">
        <div class="card p-4">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-xl font-bold">Fixture Calendar</h2>
                    <p class="text-gray-500 text-sm">Manage match scheduling for {{ $tournament->name }}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="{{ route('admin.tournaments.calendar.index', ['tournament' => $tournament, 'month' => $startDate->copy()->subMonth()->format('Y-m')]) }}"
                       class="p-2 border rounded hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <span class="font-medium">{{ $startDate->format('F Y') }}</span>
                    <a href="{{ route('admin.tournaments.calendar.index', ['tournament' => $tournament, 'month' => $startDate->copy()->addMonth()->format('Y-m')]) }}"
                       class="p-2 border rounded hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Stats Bar -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 dark:bg-blue-900 p-3 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-300">{{ $stats['total_matches'] }}</div>
                    <div class="text-sm text-blue-600 dark:text-blue-300">Total Matches</div>
                </div>
                <div class="bg-green-50 dark:bg-green-900 p-3 rounded-lg">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-300">{{ $stats['scheduled_matches'] }}</div>
                    <div class="text-sm text-green-600 dark:text-green-300">Scheduled</div>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900 p-3 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-300">{{ $stats['unscheduled_matches'] }}</div>
                    <div class="text-sm text-yellow-600 dark:text-yellow-300">Unscheduled</div>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900 p-3 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-300">{{ $stats['upcoming_available_slots'] }}</div>
                    <div class="text-sm text-purple-600 dark:text-purple-300">Available Slots</div>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="border rounded-lg overflow-hidden">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 bg-gray-100 dark:bg-gray-800">
                    @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                        <div class="p-2 text-center text-sm font-medium text-gray-600 dark:text-gray-300 border-r last:border-r-0">
                            {{ $day }}
                        </div>
                    @endforeach
                </div>

                <!-- Calendar Days -->
                @php
                    $currentDate = $startDate->copy()->startOfMonth()->startOfWeek();
                    $endOfMonth = $startDate->copy()->endOfMonth()->endOfWeek();
                @endphp

                <div class="grid grid-cols-7">
                    @while($currentDate <= $endOfMonth)
                        @php
                            $dateKey = $currentDate->format('Y-m-d');
                            $isCurrentMonth = $currentDate->month === $startDate->month;
                            $isToday = $currentDate->isToday();
                            $daySlots = $calendarData[$dateKey] ?? [];
                        @endphp

                        <div class="calendar-day border-r border-b p-2 {{ !$isCurrentMonth ? 'bg-gray-50 dark:bg-gray-900' : '' }} {{ $isToday ? 'bg-blue-50 dark:bg-blue-900' : '' }}"
                             data-date="{{ $dateKey }}"
                             ondragover="event.preventDefault()"
                             ondrop="handleDrop(event, '{{ $dateKey }}')">
                            <div class="text-sm font-medium {{ !$isCurrentMonth ? 'text-gray-400' : '' }} {{ $isToday ? 'text-blue-600' : '' }}">
                                {{ $currentDate->day }}
                            </div>

                            <div class="mt-1 space-y-1">
                                @foreach($daySlots as $slot)
                                    <div class="time-slot {{ $slot['is_available'] ? 'available' : 'occupied' }}"
                                         title="{{ $slot['time_range'] }}{{ $slot['ground'] ? ' - ' . $slot['ground']['name'] : '' }}">
                                        <div class="font-medium">{{ \Carbon\Carbon::parse($slot['start_time'])->format('h:i A') }}</div>
                                        @if($slot['match'])
                                            <div class="text-xs truncate">
                                                {{ $slot['match']['team_a'] }} vs {{ $slot['match']['team_b'] }}
                                            </div>
                                        @elseif($slot['ground'])
                                            <div class="text-xs text-gray-500">{{ $slot['ground']['name'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @php $currentDate->addDay(); @endphp
                    @endwhile
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button"
                        onclick="document.getElementById('generate-slots-modal').classList.remove('hidden')"
                        class="btn-primary">
                    Generate Time Slots
                </button>
                <form action="{{ route('admin.tournaments.calendar.auto-fill', $tournament) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Auto-Fill Matches
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar: Unscheduled Matches -->
    <div class="lg:col-span-1">
        <div class="card p-4 sticky top-4">
            <h3 class="font-bold mb-4">Unscheduled Matches</h3>

            @if($unscheduledMatches->count() > 0)
                <div class="space-y-2 max-h-[600px] overflow-y-auto">
                    @foreach($unscheduledMatches as $match)
                        <div class="unscheduled-match"
                             draggable="true"
                             ondragstart="handleDragStart(event, {{ json_encode($match) }})">
                            <div class="font-medium text-sm">
                                #{{ $match['match_number'] }} - {{ $match['stage_display'] }}
                            </div>
                            <div class="text-xs text-gray-600">
                                {{ $match['team_a'] }} vs {{ $match['team_b'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-sm">All matches are scheduled!</p>
            @endif

            <!-- Legend -->
            <div class="mt-6 pt-4 border-t">
                <h4 class="font-medium text-sm mb-2">Legend</h4>
                <div class="space-y-2 text-xs">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-green-100 border border-green-500 rounded mr-2"></div>
                        <span>Available Slot</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-blue-100 border border-blue-500 rounded mr-2"></div>
                        <span>Scheduled Match</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-yellow-100 border border-yellow-500 rounded mr-2"></div>
                        <span>Unscheduled Match</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generate Slots Modal -->
<div id="generate-slots-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-bold mb-4">Generate Time Slots</h3>

        <form action="{{ route('admin.tournaments.calendar.generate-slots', $tournament) }}" method="POST">
            @csrf

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Start Date</label>
                        <input type="date" name="start_date" required
                               value="{{ $tournament->start_date?->format('Y-m-d') ?? now()->format('Y-m-d') }}"
                               class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">End Date</label>
                        <input type="date" name="end_date" required
                               value="{{ $tournament->end_date?->format('Y-m-d') ?? now()->addMonth()->format('Y-m-d') }}"
                               class="w-full rounded-lg border-gray-300">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Time Slots</label>
                    <div id="time-slots-container">
                        <div class="flex gap-2 mb-2">
                            <input type="time" name="time_slots[0][start]" value="09:00" class="rounded-lg border-gray-300">
                            <span class="self-center">to</span>
                            <input type="time" name="time_slots[0][end]" value="13:00" class="rounded-lg border-gray-300">
                        </div>
                        <div class="flex gap-2 mb-2">
                            <input type="time" name="time_slots[1][start]" value="14:00" class="rounded-lg border-gray-300">
                            <span class="self-center">to</span>
                            <input type="time" name="time_slots[1][end]" value="18:00" class="rounded-lg border-gray-300">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Grounds</label>
                    <select name="ground_ids[]" multiple class="w-full rounded-lg border-gray-300" size="3">
                        @foreach($grounds as $ground)
                            <option value="{{ $ground->id }}">{{ $ground->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple. Leave empty for all grounds.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Available Days</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $index => $day)
                            <label class="flex items-center">
                                <input type="checkbox" name="available_days[]" value="{{ $index }}"
                                       {{ in_array($index, [0, 6]) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary-600">
                                <span class="ml-1 text-sm">{{ $day }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button"
                        onclick="document.getElementById('generate-slots-modal').classList.add('hidden')"
                        class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="btn-primary">
                    Generate Slots
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
let draggedMatch = null;

function handleDragStart(event, match) {
    draggedMatch = match;
    event.dataTransfer.setData('text/plain', JSON.stringify(match));
}

function handleDrop(event, date) {
    event.preventDefault();
    if (!draggedMatch) return;

    // TODO: Implement AJAX call to assign match to slot
    console.log('Dropped match', draggedMatch.id, 'on date', date);

    // For now, show alert
    alert('To assign match #' + draggedMatch.match_number + ' to ' + date + ', please use the Auto-Fill feature or edit the match directly.');

    draggedMatch = null;
}
</script>
@endpush
@endsection
