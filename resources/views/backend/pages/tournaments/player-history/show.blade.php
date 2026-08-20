@extends('backend.layouts.app')

@section('title', ($player->name ?? 'Player') . ' | Player History | ' . $tournament->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-6xl md:p-6">

    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
        <div class="flex items-center gap-4 min-w-0">
            @if($player->image_path)
                <img src="{{ asset('storage/' . $player->image_path) }}" alt=""
                     class="w-16 h-16 rounded-full object-cover bg-gray-100 dark:bg-gray-700 shrink-0">
            @else
                <span class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 shrink-0 flex items-center justify-center text-lg font-bold text-gray-500">
                    {{ strtoupper(substr($player->name ?? '?', 0, 2)) }}
                </span>
            @endif
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white truncate">{{ $player->name ?? 'Player #' . $player->id }}</h1>
                @if($player->email)
                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $player->email }}</p>
                @endif
                <p class="text-xs text-gray-400 mt-0.5">{{ $tournament->name }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('admin.tournaments.player-history.index', array_merge([$tournament], $backQuery)) }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-600 dark:text-gray-300">
                <iconify-icon icon="lucide:arrow-left"></iconify-icon>
                Back to list
            </a>
            @if(count($sections))
                <a href="{{ route('admin.tournaments.player-history.show-pdf', [$tournament, $player]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">
                    <iconify-icon icon="lucide:file-down"></iconify-icon>
                    Export PDF
                </a>
            @endif
        </div>
    </div>

    @forelse($sections as $section)
        @php $auction = $section['auction']; $row = $section['row']; @endphp

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 mb-6 overflow-hidden">

            {{-- What this auction did with them, at a glance. --}}
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-x-6 gap-y-2">
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Auction</div>
                    <a href="{{ route('admin.auctions.report', $auction) }}"
                       class="font-semibold text-gray-900 dark:text-white hover:underline">{{ $auction->name }}</a>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Pool</div>
                    <div class="font-medium text-gray-700 dark:text-gray-200">{{ $row->origin_pool?->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Lot</div>
                    <div class="font-medium text-gray-700 dark:text-gray-200">{{ $row->lot_number ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Outcome</div>
                    <div class="font-medium text-gray-700 dark:text-gray-200">
                        {{ $row->acquisition_label ?? ucfirst(str_replace('_', ' ', $row->status)) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Team</div>
                    <div class="font-medium text-gray-700 dark:text-gray-200">{{ $row->holding_team?->name ?? '—' }}</div>
                </div>
                <div class="ml-auto text-right">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Price</div>
                    <div class="text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ $row->price_label }}</div>
                </div>
            </div>

            {{-- The timeline. --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left">What happened</th>
                            <th class="px-5 py-3 text-left">Team</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3 text-left hidden md:table-cell">By</th>
                            @foreach($zones as $zoneLabel)
                                <th class="px-5 py-3 text-left whitespace-nowrap">{{ $zoneLabel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($section['events'] as $event)
                            <tr class="{{ $event['void'] || $event['undone'] ? 'opacity-60' : '' }}">
                                <td class="px-5 py-2.5">
                                    <span class="font-medium {{ $event['void'] || $event['undone'] ? 'line-through text-gray-500' : 'text-gray-900 dark:text-white' }}">
                                        {{ $event['label'] }}
                                    </span>
                                    @if($event['note'])
                                        <span class="text-xs text-gray-400"> · {{ $event['note'] }}</span>
                                    @endif
                                    @if($event['gap'])
                                        {{-- Seconds since the previous bid: a run of one-second gaps
                                             is what a bidding war looks like in a table. --}}
                                        <span class="text-xs text-gray-400"> · +{{ $event['gap'] }}s</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-gray-300">{{ $event['team']->name ?? '—' }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums font-medium text-gray-900 dark:text-white">
                                    {{ $event['amount_label'] ?? '—' }}
                                </td>
                                <td class="px-5 py-2.5 text-gray-400 hidden md:table-cell">{{ $event['actor'] ?? '—' }}</td>
                                @foreach(array_keys($zones) as $zone)
                                    <td class="px-5 py-2.5 text-gray-500 whitespace-nowrap tabular-nums">
                                        {{ $event['times'][$zone] ?? '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Sealed rounds. These amounts are sealed while a round is live and are never
                 broadcast; after the fact they are the record of how a contested player was
                 decided, and this is the first screen in the app to show them. --}}
            @foreach($section['rounds'] as $sealed)
                @php $round = $sealed['round']; @endphp
                <div class="border-t border-gray-100 dark:border-gray-700 px-5 py-4 bg-indigo-50/40 dark:bg-indigo-900/10">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mb-3">
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            Sealed round {{ $round->round_number }}
                        </h3>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 uppercase font-bold tracking-wide">
                            {{ str_replace('_', ' ', $round->state) }}
                        </span>
                        @if($sealed['resolution_label'])
                            <span class="text-xs text-gray-500">{{ $sealed['resolution_label'] }}</span>
                        @endif
                        <span class="ml-auto text-xs text-gray-400">
                            @foreach($zones as $zone => $zoneLabel)
                                {{ $zoneLabel }} {{ $sealed['times'][$zone] ?? '—' }}@if(!$loop->last) · @endif
                            @endforeach
                        </span>
                    </div>

                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-1.5 text-left">Team</th>
                                <th class="py-1.5 text-right">Sealed amount</th>
                                <th class="py-1.5 text-left pl-6">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sealed['entries'] as $entry)
                                <tr class="border-t border-indigo-100/60 dark:border-indigo-900/30">
                                    <td class="py-1.5 text-gray-700 dark:text-gray-200">
                                        {{ $entry['team']->name ?? '—' }}
                                        @if($round->winner_team_id && $entry['team'] && $round->winner_team_id === $entry['team']->id)
                                            <span class="ml-1 text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-400">won</span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 text-right tabular-nums font-medium text-gray-900 dark:text-white">
                                        {{ $entry['amount_label'] }}
                                    </td>
                                    <td class="py-1.5 pl-6 text-gray-500">
                                        {{ $entry['state_label'] }}
                                        @if($entry['withdrawn_by_admin'])
                                            <span class="text-xs text-amber-600 dark:text-amber-400">(by the organizer)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($sealed['winning_label'])
                        <p class="mt-3 text-sm text-gray-700 dark:text-gray-200">
                            Awarded to <strong>{{ $round->winnerTeam->name ?? '—' }}</strong>
                            at <strong>{{ $sealed['winning_label'] }}</strong>.
                        </p>
                    @endif

                    @if($sealed['lot'])
                        {{-- The seed is recorded so a draw can be re-run and checked, which is the
                             only thing that makes a random tie-break arguable-with. --}}
                        <p class="mt-2 text-xs text-gray-500">
                            Tie broken by lot ({{ $sealed['lot']['algorithm'] }}), seed
                            <code class="px-1 rounded bg-gray-100 dark:bg-gray-700">{{ $sealed['lot']['seed'] }}</code>,
                            drawn {{ $sealed['lot']['times'][array_key_first($zones)] ?? '—' }} {{ $zones[array_key_first($zones)] }}.
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-10 text-center">
            <p class="text-sm font-medium text-gray-900 dark:text-white">No auction history for this player</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $player->name }} has never been entered into an auction for {{ $tournament->name }}.
            </p>
        </div>
    @endforelse
</div>
@endsection
