@extends('backend.layouts.app')

@section('title', 'Auctioned Players | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Auctioned Players</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Every player across every auction — what they went for, and to whom.
        </p>
    </div>

    {{-- Filters.
         The auction module could already answer "who is in THIS pool" and "what has THIS auction
         sold". What it could not answer without opening an auction, then a pool, then reading,
         was "who went unsold this evening", "what did that team buy" and "what were the biggest
         buys" — which is what this row is for. --}}
    <form method="GET" class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 p-4 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Auction</label>
                <select name="auction" class="form-control text-sm">
                    <option value="">All auctions</option>
                    @foreach($auctions as $a)
                        <option value="{{ $a->id }}" @selected($filters['auction'] == $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" class="form-control text-sm">
                    <option value="">Any status</option>
                    <option value="sold" @selected($filters['status'] === 'sold')>Sold</option>
                    <option value="unsold" @selected($filters['status'] === 'unsold')>Unsold</option>
                    <option value="upcoming" @selected($filters['status'] === 'upcoming')>Upcoming</option>
                    <option value="on_auction" @selected($filters['status'] === 'on_auction')>On the block</option>
                    {{-- Not a status but a flag, and it belongs beside them: "who never went to
                         the block" is one of the questions this screen exists to answer. --}}
                    <option value="icon" @selected($filters['status'] === 'icon')>Icon players</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Team</label>
                <select name="team" class="form-control text-sm">
                    <option value="">Any team</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}" @selected($filters['team'] == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Sort by</label>
                <select name="sort" class="form-control text-sm">
                    <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Highest price</option>
                    <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Lowest price</option>
                    <option value="name" @selected($filters['sort'] === 'name')>Name</option>
                    <option value="recent" @selected($filters['sort'] === 'recent')>Most recent</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <div class="flex gap-2">
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           placeholder="Player name" class="form-control text-sm">
                    <button class="px-3 py-2 rounded-lg bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600">Go</button>
                </div>
            </div>
        </div>
    </form>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 text-sm text-gray-500">
            {{ $players->total() }} {{ Str::plural('player', $players->total()) }}
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Player</th>
                        <th class="px-4 py-3 text-left">Auction</th>
                        <th class="px-4 py-3 text-left">Pool</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Team</th>
                        <th class="px-4 py-3 text-right">Price</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($players as $ap)
                        @php
                            // The team is whoever holds them: the buyer for a sale, the keeping
                            // team for an icon player. One column, because "which squad is this
                            // player in" is one question however they got there.
                            $team = $ap->is_retained ? $ap->team : $ap->soldToTeam;
                            $price = $ap->is_retained ? $ap->retained_price : $ap->final_price;

                            [$badge, $label] = match (true) {
                                (bool) $ap->is_retained => ['bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300', 'Icon'],
                                $ap->status === 'sold' => ['bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300', 'Sold'],
                                $ap->status === 'on_auction' => ['bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300', 'On the block'],
                                in_array($ap->status, ['unsold', 'passed', 'skipped'], true) => ['bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300', 'Unsold'],
                                default => ['bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300', 'Upcoming'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    @if($ap->player?->image_path)
                                        <img src="{{ asset('storage/' . $ap->player->image_path) }}" alt=""
                                             loading="lazy" class="w-8 h-8 rounded-full object-cover shrink-0 bg-gray-100 dark:bg-gray-700">
                                    @else
                                        <span class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 shrink-0 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                            {{ strtoupper(substr($ap->player->name ?? '?', 0, 2)) }}
                                        </span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-900 dark:text-white truncate">{{ $ap->player->name ?? 'Player #' . $ap->player_id }}</span>
                                        @if($ap->player?->playerType?->type)
                                            <span class="block text-[11px] text-gray-400">{{ $ap->player->playerType->type }}</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $ap->auction?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $ap->pool?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $badge }}">{{ $label }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($team)
                                    <span class="flex items-center gap-2 min-w-0">
                                        @if($team->team_logo)
                                            <img src="{{ asset('storage/' . $team->team_logo) }}" alt=""
                                                 loading="lazy" class="w-6 h-6 rounded-full object-cover shrink-0">
                                        @endif
                                        <span class="truncate text-gray-700 dark:text-gray-200">{{ $team->name }}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900 dark:text-white">
                                {{ (float) $price > 0 ? ($ap->auction?->formatAmount($price) ?? format_points($price)) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                                No players match these filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $players->links() }}</div>
</div>
@endsection
