@extends('backend.layouts.app')

@section('title', 'Matches | ' . config('app.name'))

@push('styles')
<style>
    .match-row { transition: background 0.15s ease; }
    .match-row.selected { background: rgba(99, 102, 241, 0.06) !important; }
    .match-row.dragging { opacity: 0.4; }
    .match-row.drag-over { border-top: 2px solid #6366f1 !important; }
    .filter-pill { transition: all 0.15s ease; }
    .filter-pill.active { background: #4f46e5; color: white; border-color: #4f46e5; }
    .team-logo-sm { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
    .team-logo-sm img { width: 22px; height: 22px; max-width: 22px; max-height: 22px; object-fit: contain; }
    .bulk-bar { transform: translateY(100%); transition: transform 0.2s ease; }
    .bulk-bar.show { transform: translateY(0); }
</style>
@endpush

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[['name' => 'Matches']]" />

<div x-data="matchesPage()" class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">Matches</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $matches->total() }} total matches</p>
        </div>
        <div class="flex items-center gap-2">
            @unless(auth()->user()->hasRole('Team Manager') && !auth()->user()->hasRole('Superadmin') && !auth()->user()->hasRole('Admin'))
                <a href="{{ route('admin.matches.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-150">
                    <iconify-icon icon="lucide:plus" width="16"></iconify-icon>
                    New Match
                </a>
            @endunless
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 p-4">
        <form method="GET" id="filterForm" class="space-y-3">
            {{-- Status Pills + Search --}}
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.matches.index', request()->except(['status', 'page'])) }}"
                   class="filter-pill px-3.5 py-1.5 rounded-full text-xs font-semibold border {{ !request('status') ? 'active' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                    All
                </a>
                @foreach(['upcoming' => 'Upcoming', 'live' => 'Live', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                    <a href="{{ route('admin.matches.index', array_merge(request()->except('page'), ['status' => $key])) }}"
                       class="filter-pill px-3.5 py-1.5 rounded-full text-xs font-semibold border {{ request('status') === $key ? 'active' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                        {{ $label }}
                    </a>
                @endforeach

                <div class="ml-auto">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search teams, tournament..."
                               class="w-52 pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors duration-150"
                               onchange="this.form.submit()">
                        <iconify-icon icon="lucide:search" width="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></iconify-icon>
                    </div>
                </div>
            </div>

            {{-- Advanced Filters --}}
            <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                <select name="tournament_id" onchange="this.form.submit()"
                        class="text-sm rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 dark:text-white py-2 pr-8 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">All Tournaments</option>
                    @foreach($tournaments as $t)
                        <option value="{{ $t->id }}" {{ request('tournament_id') == $t->id ? 'selected' : '' }}>{{ Str::limit($t->name, 30) }}</option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       onchange="this.form.submit()"
                       class="text-sm rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 dark:text-white py-2 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                <span class="text-gray-400 dark:text-gray-500 text-xs font-medium">to</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       onchange="this.form.submit()"
                       class="text-sm rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 dark:text-white py-2 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">

                <select name="sort" onchange="this.form.submit()"
                        class="text-sm rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 dark:text-white py-2 pr-8 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="date_desc" {{ request('sort', 'date_desc') === 'date_desc' ? 'selected' : '' }}>Date (Newest)</option>
                    <option value="date_asc" {{ request('sort') === 'date_asc' ? 'selected' : '' }}>Date (Oldest)</option>
                    <option value="number_asc" {{ request('sort') === 'number_asc' ? 'selected' : '' }}>Match # (Asc)</option>
                    <option value="number_desc" {{ request('sort') === 'number_desc' ? 'selected' : '' }}>Match # (Desc)</option>
                    <option value="created_asc" {{ request('sort') === 'created_asc' ? 'selected' : '' }}>Created (Oldest)</option>
                    <option value="created_desc" {{ request('sort') === 'created_desc' ? 'selected' : '' }}>Created (Newest)</option>
                </select>

                @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif

                @if(request()->hasAny(['status', 'tournament_id', 'date_from', 'date_to', 'search', 'sort']))
                    <a href="{{ route('admin.matches.index') }}" class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium transition-colors duration-150">
                        <iconify-icon icon="lucide:x" width="12"></iconify-icon>
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="w-10 px-5 py-3 bg-gray-50/80 dark:bg-white/[0.03]">
                            <input type="checkbox" @change="toggleAll($event)" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600">
                        </th>
                        <th class="px-5 py-3 bg-gray-50/80 dark:bg-white/[0.03] text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 w-14">#</th>
                        <th class="px-5 py-3 bg-gray-50/80 dark:bg-white/[0.03] text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Match</th>
                        <th class="px-5 py-3 bg-gray-50/80 dark:bg-white/[0.03] text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden xl:table-cell">Tournament</th>
                        <th class="px-5 py-3 bg-gray-50/80 dark:bg-white/[0.03] text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Date & Time</th>
                        <th class="px-5 py-3 bg-gray-50/80 dark:bg-white/[0.03] text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-5 py-3 bg-gray-50/80 dark:bg-white/[0.03] text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden lg:table-cell">Venue</th>
                        <th class="px-5 py-3 bg-gray-50/80 dark:bg-white/[0.03] text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 w-16"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800" id="matchesBody">
                    @forelse ($matches as $match)
                        <tr class="match-row group hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors duration-150" data-id="{{ $match->id }}"
                            :class="{ 'selected': selectedIds.includes({{ $match->id }}) }"
                            draggable="true"
                            @dragstart="dragStart($event, {{ $match->id }})"
                            @dragover.prevent="dragOver($event)"
                            @dragleave="dragLeave($event)"
                            @drop="drop($event, {{ $match->id }})">

                            <td class="px-5 py-3.5">
                                <input type="checkbox" value="{{ $match->id }}" @change="toggleSelect({{ $match->id }})"
                                       :checked="selectedIds.includes({{ $match->id }})"
                                       class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600">
                            </td>

                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-600 dark:text-gray-300 cursor-grab active:cursor-grabbing"
                                      title="Drag to reorder">
                                    {{ $match->match_number ?? '-' }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <div class="team-logo-sm ring-2 ring-gray-100 dark:ring-gray-700 bg-gray-50 dark:bg-gray-800">
                                            @if($match->teamA?->team_logo)
                                                <img src="{{ Storage::url($match->teamA->team_logo) }}" alt="" width="22" height="22" style="max-width:22px;max-height:22px;">
                                            @else
                                                <span class="text-[9px] font-bold text-gray-400 dark:text-gray-500">{{ strtoupper(substr($match->teamA?->short_name ?? $match->teamA?->name ?? '?', 0, 2)) }}</span>
                                            @endif
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white truncate max-w-[80px] {{ $match->winner_team_id === $match->team_a_id ? 'text-emerald-600 dark:text-emerald-400' : '' }}" title="{{ $match->teamA?->name }}">
                                            {{ $match->teamA?->short_name ?? $match->teamA?->name ?? 'TBA' }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold uppercase">vs</span>
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <div class="team-logo-sm ring-2 ring-gray-100 dark:ring-gray-700 bg-gray-50 dark:bg-gray-800">
                                            @if($match->teamB?->team_logo)
                                                <img src="{{ Storage::url($match->teamB->team_logo) }}" alt="" width="22" height="22" style="max-width:22px;max-height:22px;">
                                            @else
                                                <span class="text-[9px] font-bold text-gray-400 dark:text-gray-500">{{ strtoupper(substr($match->teamB?->short_name ?? $match->teamB?->name ?? '?', 0, 2)) }}</span>
                                            @endif
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white truncate max-w-[80px] {{ $match->winner_team_id === $match->team_b_id ? 'text-emerald-600 dark:text-emerald-400' : '' }}" title="{{ $match->teamB?->name }}">
                                            {{ $match->teamB?->short_name ?? $match->teamB?->name ?? 'TBA' }}
                                        </span>
                                    </div>
                                    @if($match->winner)
                                        <iconify-icon icon="lucide:trophy" width="14" class="text-yellow-500 flex-shrink-0" style="min-width:14px;"></iconify-icon>
                                    @endif
                                </div>
                            </td>

                            <td class="px-5 py-3.5 hidden xl:table-cell">
                                <span class="text-xs text-gray-600 dark:text-gray-400 truncate block max-w-[140px]" title="{{ $match->tournament->name ?? '-' }}">
                                    {{ $match->tournament->name ?? '-' }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5">
                                <p class="text-gray-900 dark:text-white font-medium text-xs">{{ $match->match_date?->format('d M Y') ?? '-' }}</p>
                                @if($match->start_time)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ \Carbon\Carbon::parse($match->start_time)->format('h:i A') }}
                                    </p>
                                @endif
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                @if($match->is_cancelled)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-md ring-1 ring-inset bg-red-50 text-red-700 ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">Cancelled</span>
                                @elseif($match->status === 'live')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold rounded-md ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 dark:bg-emerald-400 rounded-full animate-pulse"></span> LIVE
                                    </span>
                                @elseif($match->status === 'completed')
                                    <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold rounded-md ring-1 ring-inset bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-500/20">Completed</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold rounded-md ring-1 ring-inset bg-blue-50 text-blue-700 ring-blue-600/10 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20">Upcoming</span>
                                @endif
                            </td>

                            <td class="px-5 py-3.5 hidden lg:table-cell">
                                <span class="text-xs text-gray-500 dark:text-gray-400 truncate block max-w-[120px]">
                                    {{ $match->ground?->name ?? $match->venue ?? '-' }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5 text-right">
                                <x-buttons.action-buttons :label="__('Actions')" :show-label="false" align="right">
                                    <x-buttons.action-item :href="route('admin.matches.show', $match)" icon="lucide:eye" :label="__('View')" />
                                    <x-buttons.action-item :href="route('admin.matches.edit', $match)" icon="lucide:pencil" :label="__('Edit')" />
                                    <x-buttons.action-item :href="route('admin.matches.summary.edit', $match)" icon="lucide:file-text" :label="__('Summary')" />
                                    <x-buttons.action-item :href="route('admin.tournaments.templates.generate', $match->tournament) . '?type=match_poster&match_id=' . $match->id" icon="lucide:image" :label="__('Match Poster')" />
                                    <x-buttons.action-item :href="route('admin.tournaments.templates.generate', $match->tournament) . '?type=award_poster&match_id=' . $match->id" icon="lucide:star" :label="__('Award Poster')" />
                                    @if(!$match->is_cancelled && $match->status !== 'live')
                                        <form action="{{ route('admin.matches.goLive', $match) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-left text-emerald-600 dark:text-emerald-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                                <iconify-icon icon="lucide:play-circle" width="16"></iconify-icon>
                                                Go Live
                                            </button>
                                        </form>
                                    @endif
                                    @if(!$match->is_cancelled && $match->status !== 'completed')
                                        <div x-data="{ cancelOpen: false }">
                                            <button @click="cancelOpen = true" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-left text-orange-600 dark:text-orange-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                                <iconify-icon icon="lucide:ban" width="16"></iconify-icon>
                                                Cancel Match
                                            </button>
                                            <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                                                <div @click.away="cancelOpen = false" class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-md mx-4 shadow-xl border border-gray-200 dark:border-gray-700">
                                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Cancel Match</h3>
                                                    <form action="{{ route('admin.matches.cancel', $match) }}" method="POST">
                                                        @csrf
                                                        <textarea name="cancellation_reason" rows="3" class="form-control w-full mb-4 rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="Reason..."></textarea>
                                                        <div class="flex gap-3">
                                                            <button type="button" @click="cancelOpen = false" class="flex-1 px-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">Keep</button>
                                                            <button type="submit" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors duration-150">Cancel It</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    @can('match.delete')
                                        <div x-data="{ delOpen: false }">
                                            <button @click="delOpen = true" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-left text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                                <iconify-icon icon="lucide:trash-2" width="16"></iconify-icon>
                                                Delete
                                            </button>
                                            <div x-show="delOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                                                <div @click.away="delOpen = false" class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-sm mx-4 shadow-xl border border-gray-200 dark:border-gray-700">
                                                    <div class="flex items-center gap-3 mb-4">
                                                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center">
                                                            <iconify-icon icon="lucide:alert-triangle" width="20" class="text-red-600 dark:text-red-400"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Delete Match?</h3>
                                                            <p class="text-sm text-gray-500 dark:text-gray-400">This action cannot be undone.</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex gap-3">
                                                        <button @click="delOpen = false" class="flex-1 px-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">Cancel</button>
                                                        <form action="{{ route('admin.matches.destroy', $match) }}" method="POST" class="flex-1">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="w-full px-4 py-2.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors duration-150">Delete</button>
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
                            <td colspan="8" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                                        <iconify-icon icon="lucide:swords" width="32" class="text-gray-300 dark:text-gray-600"></iconify-icon>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 font-medium">No matches found</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($matches->hasPages())
            <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-4">
                {{ $matches->links() }}
            </div>
        @endif
    </div>

    {{-- Bulk Actions Bar --}}
    <div class="fixed bottom-0 left-0 right-0 z-50" :class="selectedIds.length > 0 ? '' : 'pointer-events-none'">
        <div class="bulk-bar bg-indigo-950 text-white px-6 py-3.5 flex items-center justify-between shadow-2xl rounded-t-xl pointer-events-auto max-w-5xl mx-auto"
             :class="{ 'show': selectedIds.length > 0 }">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-300 text-sm font-bold" x-text="selectedIds.length"></div>
                <span class="text-sm font-medium text-indigo-100">selected</span>
                <button @click="selectedIds = []; uncheckAll()" class="text-xs text-indigo-400 hover:text-white font-medium underline underline-offset-2 transition-colors duration-150">Clear</button>
            </div>
            <button @click="bulkDelete()" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors duration-150 shadow-sm">
                <iconify-icon icon="lucide:trash-2" width="16"></iconify-icon>
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
