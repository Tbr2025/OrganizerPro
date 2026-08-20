@extends('backend.layouts.app')

@section('title', 'Player History | ' . $tournament->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6">

    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Player History</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Every player in {{ $tournament->name }} — how they were acquired, out of which pool,
                for how much, and when.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tournaments.player-history.pdf', array_merge([$tournament], request()->query())) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">
                <iconify-icon icon="lucide:file-down"></iconify-icon>
                Export PDF
            </a>
        </div>
    </div>

    {{-- Filters. A printed report has to say what it is a report of, so every one of these is
         carried into the PDF link above and spelled out in the document's header. --}}
    <form method="GET" action="{{ route('admin.tournaments.player-history.index', $tournament) }}"
          class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 p-4 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Auction</label>
                <select name="auction_id" class="form-control text-sm">
                    <option value="">All auctions</option>
                    @foreach($auctions as $a)
                        <option value="{{ $a->id }}" @selected($filters['auction_id'] == $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Pool</label>
                <select name="pool_id" class="form-control text-sm">
                    <option value="">All pools</option>
                    @foreach($pools as $p)
                        <option value="{{ $p->id }}" @selected($filters['pool_id'] == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Team</label>
                <select name="team" class="form-control text-sm">
                    <option value="">Any team</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}" @selected($filters['team'] == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] }}"
                       placeholder="Player name or email" class="form-control text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">How acquired</label>
                <select name="acquisition" class="form-control text-sm">
                    <option value="">Any route</option>
                    <option value="auction" @selected($filters['acquisition'] === 'auction')>Auction</option>
                    <option value="icon" @selected($filters['acquisition'] === 'icon')>Icon Player</option>
                    <option value="none" @selected($filters['acquisition'] === 'none')>Not acquired</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                <select name="status" class="form-control text-sm">
                    <option value="">Any status</option>
                    <option value="sold" @selected($filters['status'] === 'sold')>Sold</option>
                    <option value="unsold" @selected($filters['status'] === 'unsold')>Unsold</option>
                    <option value="waiting" @selected($filters['status'] === 'waiting')>Upcoming</option>
                    <option value="on_auction" @selected($filters['status'] === 'on_auction')>On the block</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Price from</label>
                <input type="number" step="any" min="0" name="price_min" value="{{ $filters['price_min'] }}"
                       placeholder="Min" class="form-control text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Price to</label>
                <input type="number" step="any" min="0" name="price_max" value="{{ $filters['price_max'] }}"
                       placeholder="Max" class="form-control text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date from</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date to</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control text-sm">
            </div>

            {{-- A bare date means nothing until you say whose midnight it is: an evening auction in
                 Dubai runs past midnight in India, so the same sale falls on different days in the
                 two columns below. This select decides, and the PDF prints which was used. --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Dates read in</label>
                <select name="tz" class="form-control text-sm">
                    @foreach($zones as $zone => $label)
                        <option value="{{ $zone }}" @selected($filters['tz'] === $zone)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sort by</label>
                <select name="sort" class="form-control text-sm">
                    <option value="recent" @selected($filters['sort'] === 'recent')>Most recent</option>
                    <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Highest price</option>
                    <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Lowest price</option>
                    <option value="name" @selected($filters['sort'] === 'name')>Name</option>
                    <option value="lot" @selected($filters['sort'] === 'lot')>Lot number</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-2 mt-4">
            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-brand-500 hover:bg-brand-600 text-white">Apply</button>
            @if($isFiltered)
                <a href="{{ filter_url([], $filterKeys) }}"
                   class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">Reset</a>
            @endif
        </div>
    </form>

    {{-- The figures describe the whole filtered set, aggregated in SQL — not the 25 rows below. --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
        @php
            $unit = $auctions->firstWhere('id', (int) ($filters['auction_id'] ?? 0)) ?: $auctions->first();
            $money = fn ($v) => $unit ? $unit->formatAmount($v) : format_points($v);
        @endphp
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Players</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['players']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Sold</div>
            <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($summary['sold']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Icon players</div>
            <div class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($summary['icons']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Unsold</div>
            <div class="text-xl font-bold text-red-600 dark:text-red-400">{{ number_format($summary['unsold']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Total spend</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $money($summary['spend']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">Highest buy</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $money($summary['highest']) }}</div>
        </div>
    </div>

    @if($isFiltered)
        <div class="mb-3 flex items-center gap-2 text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg px-4 py-2">
            <iconify-icon icon="lucide:filter" class="text-base"></iconify-icon>
            <span>Showing <strong>{{ number_format($rows->total()) }}</strong> filtered {{ Str::plural('player', $rows->total()) }}</span>
            <a href="{{ filter_url([], $filterKeys) }}" class="ml-auto text-xs underline">Clear all filters</a>
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Player</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Auction</th>
                        <th class="px-4 py-3 text-left">Pool</th>
                        <th class="px-4 py-3 text-left">How acquired</th>
                        <th class="px-4 py-3 text-left">Team</th>
                        <th class="px-4 py-3 text-right">Price</th>
                        @foreach($zones as $zoneLabel)
                            <th class="px-4 py-3 text-left whitespace-nowrap">Acquired ({{ $zoneLabel }})</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($rows as $row)
                        @php
                            [$badge, $label] = match (true) {
                                (bool) $row->is_retained => ['bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300', $row->acquisition_label],
                                $row->status === 'sold' => ['bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300', $row->acquisition_label],
                                $row->status === 'on_auction' => ['bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300', 'On the block'],
                                in_array($row->status, ['unsold', 'passed', 'skipped'], true) => ['bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300', 'Unsold'],
                                default => ['bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300', 'Upcoming'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    @if($row->player?->image_path)
                                        <img src="{{ asset('storage/' . $row->player->image_path) }}" alt=""
                                             loading="lazy" class="w-8 h-8 rounded-full object-cover shrink-0 bg-gray-100 dark:bg-gray-700">
                                    @else
                                        <span class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 shrink-0 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                            {{ strtoupper(substr($row->player->name ?? '?', 0, 2)) }}
                                        </span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-900 dark:text-white truncate">
                                            {{ $row->player->name ?? 'Player #' . $row->player_id }}
                                        </span>
                                        @if($row->player?->email)
                                            <span class="block text-[11px] text-gray-400 truncate">{{ $row->player->email }}</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300 hidden md:table-cell">
                                @if($row->auction)
                                    <a href="{{ route('admin.auctions.report', $row->auction) }}"
                                       class="hover:underline">{{ $row->auction->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ $row->origin_pool?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $badge }}">{{ $label }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($row->holding_team)
                                    <span class="flex items-center gap-2 min-w-0">
                                        @if($row->holding_team->team_logo)
                                            <img src="{{ asset('storage/' . $row->holding_team->team_logo) }}" alt=""
                                                 loading="lazy" class="w-6 h-6 rounded-full object-cover shrink-0">
                                        @endif
                                        <span class="truncate text-gray-700 dark:text-gray-200">{{ $row->holding_team->name }}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900 dark:text-white">
                                {{ $row->price_label }}
                            </td>
                            @foreach(array_keys($zones) as $zone)
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap tabular-nums">
                                    {{ $row->event_times[$zone] ?? '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 6 + count($zones) }}" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">No player history yet</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    @if($isFiltered)
                                        No players match these filters.
                                    @elseif($auctions->isEmpty())
                                        This tournament has no auction, so there is nothing to trace.
                                        Players in an open tournament are added to squads directly.
                                    @else
                                        No players have been assigned to a pool in this tournament's auctions yet.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rows->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
