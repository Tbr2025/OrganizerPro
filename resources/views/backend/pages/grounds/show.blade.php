@extends('backend.layouts.app')

@section('title', $ground->name . ' | Grounds | ' . config('app.name'))

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
    ['name' => 'Grounds', 'route' => route('admin.grounds.index')],
    ['name' => $ground->name],
]" />

<div class="p-4 mx-auto max-w-5xl md:p-6">

    {{-- Hero --}}
    <div class="rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 mb-6">
        <div class="relative h-44 sm:h-56">
            @if($ground->image && Storage::disk('public')->exists($ground->image))
                <img src="{{ Storage::url($ground->image) }}" alt="{{ $ground->name }}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
            @else
                <div class="w-full h-full bg-gradient-to-br from-emerald-600 via-green-600 to-teal-700"></div>
            @endif

            <div class="absolute bottom-0 left-0 right-0 p-5">
                <div class="flex items-end justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl font-bold text-white truncate">{{ $ground->name }}</h1>
                        <p class="text-sm text-white/80 mt-0.5 truncate">
                            @if($ground->address && $ground->city)
                                {{ $ground->address }}, {{ $ground->city }}
                            @else
                                {{ $ground->city ?: $ground->address ?: 'No address added' }}
                            @endif
                        </p>
                    </div>
                    <span class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide
                                 {{ $ground->is_active ? 'bg-emerald-500 text-white' : 'bg-gray-800 text-gray-200' }}">
                        {{ $ground->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 px-5 py-3.5 border-t border-gray-100 dark:border-gray-700">
            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                <i class="fas fa-calendar-check text-[11px] text-gray-400"></i>
                {{ $matchCount }} {{ Str::plural('match', $matchCount) }} scheduled here
            </span>

            @if($ground->organization && auth()->user()?->hasRole('Superadmin'))
                <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                    <i class="fas fa-building text-[11px] text-gray-400"></i> {{ $ground->organization->name }}
                </span>
            @endif

            <div class="ml-auto flex items-center gap-2">
                @if($ground->google_maps_link)
                    <a href="{{ $ground->google_maps_link }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium
                              text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition">
                        <i class="fas fa-map-location-dot text-[11px]"></i> Open in Maps
                    </a>
                @endif
                <a href="{{ route('admin.grounds.index', ['action' => 'edit', 'ground' => $ground->id]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                          text-white bg-gray-900 dark:bg-gray-700 hover:bg-gray-800 transition">
                    <i class="fas fa-pen text-[11px]"></i> Edit
                </a>
            </div>
        </div>
    </div>

    {{-- Upcoming matches --}}
    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Upcoming matches</h2>
        </div>

        @forelse($upcomingMatches as $match)
            <div class="flex items-center gap-3 px-5 py-3 {{ ! $loop->last ? 'border-b border-gray-50 dark:border-gray-700/50' : '' }}">
                <div class="w-10 shrink-0 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase">
                        {{ $match->match_date?->format('M') ?: '—' }}
                    </p>
                    <p class="text-base font-bold text-gray-900 dark:text-white leading-none">
                        {{ $match->match_date?->format('d') ?: '' }}
                    </p>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                        {{ $match->teamA?->name ?? 'TBD' }} <span class="text-gray-400 font-normal">vs</span> {{ $match->teamB?->name ?? 'TBD' }}
                    </p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                        {{ $match->tournament?->name ?? 'No tournament' }}
                        @if($match->start_time)
                            &middot; {{ \Carbon\Carbon::parse($match->start_time)->format('h:i A') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('admin.matches.show', $match) }}"
                   class="shrink-0 px-2.5 py-1.5 rounded-lg text-[11px] font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    View
                </a>
            </div>
        @empty
            <div class="px-5 py-10 text-center">
                <i class="fas fa-calendar-xmark text-2xl text-gray-300 dark:text-gray-600"></i>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No upcoming matches at this ground.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
