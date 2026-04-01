@extends('backend.layouts.app')

@section('title', 'Matches | ' . config('app.name'))

@push('styles')
<style>
    .match-row { transition: background 0.15s ease; }
    .match-row:hover { background: rgba(251, 191, 36, 0.03); }
    .match-row.selected { background: rgba(59, 130, 246, 0.08) !important; }
    .match-row.dragging { opacity: 0.4; }
    .match-row.drag-over { border-top: 2px solid #3b82f6 !important; }
    .filter-pill { transition: all 0.15s ease; }
    .filter-pill.active { background: #3b82f6; color: white; border-color: #3b82f6; }
    .team-logo-sm { width: 28px; height: 28px; border-radius: 50%; background: rgba(0,0,0,0.05); display: inline-flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
    .dark .team-logo-sm { background: rgba(255,255,255,0.08); }
    .team-logo-sm img { width: 22px; height: 22px; max-width: 22px; max-height: 22px; object-fit: contain; }
    .bulk-bar { transform: translateY(100%); transition: transform 0.2s ease; }
    .bulk-bar.show { transform: translateY(0); }
</style>
@endpush

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[['name' => 'Matches']]" />

<div x-data="matchesPage()" class="space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Matches</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $matches->total() }} total matches</p>
        </div>
        <div class="flex items-center gap-2">
            @unless(auth()->user()->hasRole('Team Manager') && !auth()->user()->hasRole('Superadmin') && !auth()->user()->hasRole('Admin'))
                <a href="{{ route('admin.matches.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Match
                </a>
            @endunless
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <form method="GET" id="filterForm" class="space-y-3">
            {{-- Status Pills + Search --}}
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.matches.index', request()->except(['status', 'page'])) }}"
                   class="filter-pill px-3 py-1.5 rounded-full text-xs font-medium border {{ !request('status') ? 'active' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    All
                </a>
                @foreach(['upcoming' => 'Upcoming', 'live' => 'Live', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                    <a href="{{ route('admin.matches.index', array_merge(request()->except('page'), ['status' => $key])) }}"
                       class="filter-pill px-3 py-1.5 rounded-full text-xs font-medium border {{ request('status') === $key ? 'active' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        {{ $label }}
                    </a>
                @endforeach

                <div class="ml-auto">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search teams, tournament..."
                               class="w-48 pl-8 pr-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-1 focus:ring-blue-500"
                               onchange="this.form.submit()">
                        <svg class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>
            </div>

            {{-- Advanced Filters --}}
            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <select name="tournament_id" onchange="this.form.submit()"
                        class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 pr-8">
                    <option value="">All Tournaments</option>
                    @foreach($tournaments as $t)
                        <option value="{{ $t->id }}" {{ request('tournament_id') == $t->id ? 'selected' : '' }}>{{ Str::limit($t->name, 30) }}</option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       onchange="this.form.submit()"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5">
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       onchange="this.form.submit()"
                       class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5">

                <select name="sort" onchange="this.form.submit()"
                        class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5 pr-8">
                    <option value="date_desc" {{ request('sort', 'date_desc') === 'date_desc' ? 'selected' : '' }}>Date (Newest)</option>
                    <option value="date_asc" {{ request('sort') === 'date_asc' ? 'selected' : '' }}>Date (Oldest)</option>
                    <option value="number_asc" {{ request('sort') === 'number_asc' ? 'selected' : '' }}>Match # (Asc)</option>
                    <option value="number_desc" {{ request('sort') === 'number_desc' ? 'selected' : '' }}>Match # (Desc)</option>
                    <option value="created_asc" {{ request('sort') === 'created_asc' ? 'selected' : '' }}>Created (Oldest)</option>
                    <option value="created_desc" {{ request('sort') === 'created_desc' ? 'selected' : '' }}>Created (Newest)</option>
                </select>

                @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif

                @if(request()->hasAny(['status', 'tournament_id', 'date_from', 'date_to', 'search', 'sort']))
                    <a href="{{ route('admin.matches.index') }}" class="text-xs text-red-500 hover:text-red-700 font-medium">
                        <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                        <th class="w-10 px-3 py-3">
                            <input type="checkbox" @change="toggleAll($event)" class="rounded border-gray-300 dark:border-gray-600 text-blue-600">
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-14">#</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Match</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden xl:table-cell">Tournament</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Venue</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50" id="matchesBody">
                    @forelse ($matches as $match)
                        <tr class="match-row" data-id="{{ $match->id }}"
                            :class="{ 'selected': selectedIds.includes({{ $match->id }}) }"
                            draggable="true"
                            @dragstart="dragStart($event, {{ $match->id }})"
                            @dragover.prevent="dragOver($event)"
                            @dragleave="dragLeave($event)"
                            @drop="drop($event, {{ $match->id }})">

                            <td class="px-3 py-3">
                                <input type="checkbox" value="{{ $match->id }}" @change="toggleSelect({{ $match->id }})"
                                       :checked="selectedIds.includes({{ $match->id }})"
                                       class="rounded border-gray-300 dark:border-gray-600 text-blue-600">
                            </td>

                            <td class="px-3 py-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 text-xs font-bold text-gray-600 dark:text-gray-300 cursor-grab"
                                      title="Drag to reorder">
                                    {{ $match->match_number ?? '-' }}
                                </span>
                            </td>

                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <div class="team-logo-sm">
                                            @if($match->teamA?->team_logo)
                                                <img src="{{ Storage::url($match->teamA->team_logo) }}" alt="" width="22" height="22" style="max-width:22px;max-height:22px;">
                                            @else
                                                <span class="text-[9px] font-bold text-gray-400">{{ strtoupper(substr($match->teamA?->short_name ?? $match->teamA?->name ?? '?', 0, 2)) }}</span>
                                            @endif
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white truncate max-w-[80px] {{ $match->winner_team_id === $match->team_a_id ? 'text-green-600 dark:text-green-400' : '' }}" title="{{ $match->teamA?->name }}">
                                            {{ $match->teamA?->short_name ?? $match->teamA?->name ?? 'TBA' }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 font-medium">vs</span>
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <div class="team-logo-sm">
                                            @if($match->teamB?->team_logo)
                                                <img src="{{ Storage::url($match->teamB->team_logo) }}" alt="" width="22" height="22" style="max-width:22px;max-height:22px;">
                                            @else
                                                <span class="text-[9px] font-bold text-gray-400">{{ strtoupper(substr($match->teamB?->short_name ?? $match->teamB?->name ?? '?', 0, 2)) }}</span>
                                            @endif
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white truncate max-w-[80px] {{ $match->winner_team_id === $match->team_b_id ? 'text-green-600 dark:text-green-400' : '' }}" title="{{ $match->teamB?->name }}">
                                            {{ $match->teamB?->short_name ?? $match->teamB?->name ?? 'TBA' }}
                                        </span>
                                    </div>
                                    @if($match->winner)
                                        <svg class="w-3.5 h-3.5 text-yellow-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/></svg>
                                    @endif
                                </div>
                            </td>

                            <td class="px-3 py-3 hidden xl:table-cell">
                                <span class="text-xs text-gray-600 dark:text-gray-400 truncate block max-w-[140px]" title="{{ $match->tournament->name ?? '-' }}">
                                    {{ $match->tournament->name ?? '-' }}
                                </span>
                            </td>

                            <td class="px-3 py-3">
                                <p class="text-gray-900 dark:text-white font-medium text-xs">{{ $match->match_date?->format('d M Y') ?? '-' }}</p>
                                @if($match->start_time)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ \Carbon\Carbon::parse($match->start_time)->format('h:i A') }}
                                    </p>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-center">
                                @if($match->is_cancelled)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300">Cancelled</span>
                                @elseif($match->status === 'live')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-red-500 text-white">
                                        <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span> LIVE
                                    </span>
                                @elseif($match->status === 'completed')
                                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300">Completed</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">Upcoming</span>
                                @endif
                            </td>

                            <td class="px-3 py-3 hidden lg:table-cell">
                                <span class="text-xs text-gray-500 dark:text-gray-400 truncate block max-w-[120px]">
                                    {{ $match->ground?->name ?? $match->venue ?? '-' }}
                                </span>
                            </td>

                            <td class="px-3 py-3 text-right">
                                <x-buttons.action-buttons :label="__('Actions')" :show-label="false" align="right">
                                    <x-buttons.action-item :href="route('admin.matches.show', $match)" icon="eye" :label="__('View')" />
                                    <x-buttons.action-item :href="route('admin.matches.edit', $match)" icon="pencil" :label="__('Edit')" />
                                    <x-buttons.action-item :href="route('admin.matches.summary.edit', $match)" icon="file-text" :label="__('Summary')" />
                                    <x-buttons.action-item :href="route('admin.tournaments.templates.generate', $match->tournament) . '?type=award_poster'" icon="star" :label="__('Award Poster')" />
                                    @if(!$match->is_cancelled && $match->status !== 'live')
                                        <form action="{{ route('admin.matches.goLive', $match) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-left text-green-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="10,8 16,12 10,16"/></svg>
                                                Go Live
                                            </button>
                                        </form>
                                    @endif
                                    @if(!$match->is_cancelled && $match->status !== 'completed')
                                        <div x-data="{ cancelOpen: false }">
                                            <button @click="cancelOpen = true" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-left text-orange-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                Cancel Match
                                            </button>
                                            <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                                                <div @click.away="cancelOpen = false" class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4 shadow-xl">
                                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Cancel Match</h3>
                                                    <form action="{{ route('admin.matches.cancel', $match) }}" method="POST">
                                                        @csrf
                                                        <textarea name="cancellation_reason" rows="3" class="form-control w-full mb-4" placeholder="Reason..."></textarea>
                                                        <div class="flex gap-3">
                                                            <button type="button" @click="cancelOpen = false" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg">Keep</button>
                                                            <button type="submit" class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Cancel It</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    @can('match.delete')
                                        <div x-data="{ delOpen: false }">
                                            <button @click="delOpen = true" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-left text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Delete
                                            </button>
                                            <div x-show="delOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                                                <div @click.away="delOpen = false" class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-sm mx-4 shadow-xl">
                                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Delete Match?</h3>
                                                    <p class="text-sm text-gray-500 mb-4">This action cannot be undone.</p>
                                                    <div class="flex gap-3">
                                                        <button @click="delOpen = false" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg">Cancel</button>
                                                        <form action="{{ route('admin.matches.destroy', $match) }}" method="POST" class="flex-1">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="w-full px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endcan
                                </x-buttons.action-buttons>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">No matches found</p>
                                <p class="text-xs text-gray-400 mt-1">Try adjusting your filters</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($matches->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $matches->links() }}
            </div>
        @endif
    </div>

    {{-- Bulk Actions Bar --}}
    <div class="fixed bottom-0 left-0 right-0 z-50">
        <div class="bulk-bar bg-gray-900 text-white px-6 py-3 flex items-center justify-between shadow-2xl"
             :class="{ 'show': selectedIds.length > 0 }">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium" x-text="selectedIds.length + ' selected'"></span>
                <button @click="selectedIds = []; uncheckAll()" class="text-xs text-gray-400 hover:text-white underline">Clear</button>
            </div>
            <button @click="bulkDelete()" class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete Selected
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function matchesPage() {
    return {
        selectedIds: [],
        dragId: null,

        toggleAll(event) {
            if (event.target.checked) {
                this.selectedIds = Array.from(document.querySelectorAll('#matchesBody tr[data-id]')).map(r => parseInt(r.dataset.id));
            } else {
                this.selectedIds = [];
            }
            document.querySelectorAll('#matchesBody input[type="checkbox"]').forEach(cb => cb.checked = event.target.checked);
        },

        toggleSelect(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx > -1) this.selectedIds.splice(idx, 1);
            else this.selectedIds.push(id);
        },

        uncheckAll() {
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        },

        bulkDelete() {
            if (!confirm(`Delete ${this.selectedIds.length} match(es)? This cannot be undone.`)) return;
            fetch('{{ route("admin.matches.bulkDelete") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ ids: this.selectedIds })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.selectedIds.forEach(id => document.querySelector(`tr[data-id="${id}"]`)?.remove());
                    this.selectedIds = [];
                }
            })
            .catch(() => alert('Failed to delete'));
        },

        dragStart(event, id) {
            this.dragId = id;
            event.target.closest('tr').classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
        },
        dragOver(event) {
            const row = event.target.closest('tr');
            if (row) {
                document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                row.classList.add('drag-over');
            }
        },
        dragLeave(event) { event.target.closest('tr')?.classList.remove('drag-over'); },
        drop(event, targetId) {
            document.querySelectorAll('.dragging, .drag-over').forEach(el => el.classList.remove('dragging', 'drag-over'));
            if (this.dragId === targetId) return;

            const tbody = document.getElementById('matchesBody');
            const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
            const dragRow = rows.find(r => parseInt(r.dataset.id) === this.dragId);
            const targetRow = rows.find(r => parseInt(r.dataset.id) === targetId);

            if (dragRow && targetRow) {
                tbody.insertBefore(dragRow, targetRow);
                const order = Array.from(tbody.querySelectorAll('tr[data-id]')).map(r => parseInt(r.dataset.id));

                // Update numbers visually
                tbody.querySelectorAll('tr[data-id]').forEach((row, idx) => {
                    const numEl = row.querySelector('td:nth-child(2) span');
                    if (numEl) numEl.textContent = idx + 1;
                });

                fetch('{{ route("admin.matches.reorder") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ order })
                });
            }
            this.dragId = null;
        }
    };
}
</script>
@endpush
@endsection
