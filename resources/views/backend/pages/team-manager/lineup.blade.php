@extends('backend.layouts.app')

@section('title', __('Playing XI') . ' | ' . config('app.name'))

@section('admin-content')
<div class="max-w-4xl mx-auto p-4 sm:p-6">
    <div class="mb-6">
        <a href="{{ route('admin.matches.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">&larr; {{ __('Back to matches') }}</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ __('Playing XI') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ $match->teamA?->name ?? 'TBD' }} vs {{ $match->teamB?->name ?? 'TBD' }}
            @if($match->match_date) · {{ $match->match_date->format('D, M j Y') }} @endif
            · {{ $match->tournament?->name }}
        </p>
        <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400 mt-1">
            {{ __('Naming the XI for') }} {{ $team->name }}
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
    @endif

    @if($squad->isEmpty())
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
            {{ __('No players in your squad yet, so there is nobody to name. Players appear here once they are added to your roster.') }}
        </div>
    @else
        <form method="POST" action="{{ route('team-manager.matches.lineup.save', $match) }}"
              class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            @csrf

            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ __('The playing squad') }}
                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">— {{ __('pick from your squad of') }} {{ $squad->count() }}</span>
                </h2>
                <span class="text-xs text-gray-400">{{ __('Order is the order they appear on posters') }}</span>
            </div>

            <div class="space-y-2">
                {{-- Twelve rows, not eleven: a named 12th or impact player is ordinary, and empty
                     rows are dropped on save, so a side naming eleven just leaves the last blank. --}}
                @for($i = 0; $i < 12; $i++)
                    @php $row = $lineup[$i] ?? null; @endphp
                    <div class="flex items-center gap-2">
                        <span class="w-6 shrink-0 text-xs font-semibold {{ $i === 11 ? 'text-gray-300 dark:text-gray-600' : 'text-gray-400' }} tabular-nums">{{ $i + 1 }}.</span>
                        <select name="players[{{ $i }}][player_id]"
                                class="flex-1 min-w-0 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">— {{ $i === 11 ? __('12th player (optional)') : __('empty') }} —</option>
                            @foreach($squad as $p)
                                <option value="{{ $p->id }}" @selected($row && $row->player_id === $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <select name="players[{{ $i }}][role]"
                                class="w-28 shrink-0 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">—</option>
                            @foreach($roles as $key => $label)
                                <option value="{{ $key }}" @selected($row && $row->role === $key)>{{ $key }}</option>
                            @endforeach
                        </select>
                    </div>
                @endfor
            </div>

            <p class="text-xs text-gray-400 mt-4">
                {{ __('Saving replaces the whole list. Leave a row empty to skip it — name eleven or twelve as you need.') }}
            </p>

            <div class="mt-4 flex gap-2">
                <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                    {{ __('Save Playing XI') }}
                </button>
                <a href="{{ route('admin.matches.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    @endif
</div>
@endsection
