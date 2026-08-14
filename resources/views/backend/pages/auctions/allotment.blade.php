@extends('backend.layouts.app')

@section('title', 'Final Allotment · ' . $auction->name . ' | ' . config('app.name'))

@section('admin-content')
{{--
    Final allotment: place players nobody bid on with teams that are still short of a
    legal squad. Grouped by the pool each player went unsold from, so allotment can be
    worked through pool by pool.

    Budget rule here is deliberately the *total* purse, not the squad reserve — the
    reserve exists to keep these very slots affordable, so applying it here would refuse
    the purchases it was held back for.
--}}
<div class="p-4 mx-auto max-w-7xl md:p-6"
     x-data="allotmentScreen({{ $auction->id }}, {{ json_encode($teams->map(fn ($t) => [
         'id' => $t['team']->id,
         'name' => $t['team']->name,
         'logo_url' => $t['team']->team_logo_url,
         'slots_filled' => $t['slots_filled'],
         'slots_required' => $t['slots_required'],
         'slots_short' => $t['slots_short'],
         'remaining' => $t['remaining'],
         'needs_players' => $t['needs_players'],
     ])->values()) }})">

    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Final Allotment · {{ $auction->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Place unsold players with teams that still need them. Players go at base price — no bidding.
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($totalUnsold > 0)
                <button type="button" @click="openAutoPreview()"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                    Auto-distribute
                </button>
            @endif
            <a href="{{ route('admin.auctions.pools.index', $auction) }}"
               class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Pools</a>
            <a href="{{ route('admin.auctions.show', $auction) }}"
               class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Back to auction</a>
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] px-5 py-4">
            <p class="text-[11px] uppercase tracking-wider text-gray-400">Unsold players</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalUnsold }}</p>
        </div>
        <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] px-5 py-4">
            <p class="text-[11px] uppercase tracking-wider text-gray-400">Squad places to fill</p>
            <p class="text-2xl font-bold {{ $totalShortfall > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $totalShortfall }}</p>
        </div>
        <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] px-5 py-4">
            <p class="text-[11px] uppercase tracking-wider text-gray-400">Minimum squad</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $auction->minSquadSize() }}</p>
        </div>
    </div>

    {{-- Teams: the worklist, shortest squad first --}}
    <div class="mb-6 rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Teams</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Sorted by how many places each team still has to fill.</p>
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm min-w-[620px]">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-gray-800">
                        <th class="py-2 pr-4">Team</th>
                        <th class="py-2 px-3 text-right">Squad</th>
                        <th class="py-2 px-3 text-right">Still needs</th>
                        <th class="py-2 px-3 text-right">Spent</th>
                        <th class="py-2 pl-3 text-right">Purse left</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($teams as $row)
                    <tr class="{{ $row['needs_players'] ? '' : 'opacity-60' }}">
                        <td class="py-2 pr-4">
                            <div class="flex items-center gap-2">
                                @if($row['team']->team_logo_url)
                                    <img src="{{ $row['team']->team_logo_url }}" alt="{{ $row['team']->name }}"
                                         class="w-7 h-7 rounded-full object-cover border border-gray-200 dark:border-gray-700">
                                @else
                                    <span class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                        {{ strtoupper(substr($row['team']->name, 0, 2)) }}
                                    </span>
                                @endif
                                <span class="text-gray-800 dark:text-gray-100">{{ $row['team']->name }}</span>
                            </div>
                        </td>
                        <td class="py-2 px-3 text-right font-mono text-gray-500">
                            {{ $row['slots_filled'] }}/{{ $row['slots_required'] }}
                        </td>
                        <td class="py-2 px-3 text-right font-mono font-semibold {{ $row['slots_short'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                            {{ $row['slots_short'] > 0 ? $row['slots_short'] : '—' }}
                        </td>
                        <td class="py-2 px-3 text-right font-mono text-gray-500">{{ format_points($row['spent'], '0') }}</td>
                        <td class="py-2 pl-3 text-right font-mono font-semibold {{ $row['remaining'] <= 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            {{ format_points($row['remaining'], '0') }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="py-4 text-center text-gray-500">No teams in this tournament.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Unsold players, grouped by the pool they came from --}}
    @forelse($groups as $group)
        <div class="mb-5 rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $group['source_pool_name'] }}</h3>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-300 font-semibold">
                        {{ $group['players']->count() }} unsold
                    </span>
                    @if($group['pool']->category)
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500">{{ $group['pool']->category }}</span>
                    @endif
                </div>
            </div>

            <div class="p-4 space-y-2">
                @foreach($group['players'] as $ap)
                    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-100 dark:border-gray-700 px-3 py-2.5">
                        {{-- Player --}}
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            @if($ap->player?->image_path)
                                <img src="{{ asset('storage/' . $ap->player->image_path) }}" alt=""
                                     class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                            @else
                                <span class="w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-[10px] font-bold text-gray-500 flex-shrink-0">
                                    {{ strtoupper(substr($ap->player->name ?? '?', 0, 2)) }}
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $ap->player->name ?? 'Player #' . $ap->player_id }}
                                </p>
                                <p class="text-[11px] text-gray-500">
                                    {{ $ap->player?->playerType?->name ?? 'Player' }}
                                    · base {{ format_points($ap->base_price) }}
                                    {{-- Which pool they went unsold from. Unsold players now share
                                         one pile for the whole auction, because allotment is a
                                         question about the whole auction — but the origin is still
                                         worth knowing when deciding who goes where, so it rides on
                                         the player rather than organising the screen. --}}
                                    @if($ap->sourcePool)
                                        · from {{ $ap->sourcePool->name }}
                                    @endif
                                    @if($ap->status === 'skipped')
                                        · <span class="text-amber-600">skipped</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Allot form --}}
                        <form action="{{ route('admin.auctions.allotment.allot', $auction) }}" method="POST"
                              class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="auction_player_id" value="{{ $ap->id }}">

                            <select name="team_id" required
                                    class="form-control !py-1.5 !text-sm min-w-[190px]">
                                <option value="">Allot to…</option>
                                @foreach($teams as $row)
                                    @php
                                        $affordable = $row['remaining'] >= (float) $ap->base_price;
                                    @endphp
                                    <option value="{{ $row['team']->id }}" @disabled(! $affordable)>
                                        {{ $row['team']->name }}
                                        ({{ $row['slots_short'] > 0 ? 'needs ' . $row['slots_short'] : 'squad full' }},
                                        {{ format_points($row['remaining'], '0') }} left){{ $affordable ? '' : ' — cannot afford' }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- Entered in MILLIONS, like every other money field.
                                 This asked for 1000000 while the row beside it reads "base 1M"
                                 and every screen in the auction talks in M — so an allotment
                                 price had to be counted out in zeroes and then checked against a
                                 figure written the other way. The raw value still posts, through
                                 the hidden input, so the endpoint is unchanged. --}}
                            <div class="flex items-center gap-1"
                                 x-data="{
                                     raw: @js((float) $ap->base_price),
                                     toM(v) { return window.auctionToM ? window.auctionToM(v) : v },
                                     fromM(v) { return window.auctionFromM ? window.auctionFromM(v) : v },
                                 }">
                                <div class="relative">
                                    <input type="number" step="any" min="0"
                                           :value="toM(raw)" @input="raw = fromM($event.target.value)"
                                           class="form-control !py-1.5 !text-sm w-24 pr-6"
                                           title="Allotment price in millions — 1 means 1,000,000">
                                    <span class="absolute inset-y-0 right-2 flex items-center text-[10px] font-bold text-gray-400">M</span>
                                </div>
                                <input type="hidden" name="price" :value="raw">
                            </div>

                            <button type="submit"
                                    class="px-3 py-1.5 text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 whitespace-nowrap">
                                Allot
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] px-5 py-10 text-center">
            <p class="text-gray-900 dark:text-white font-medium">Nothing to allot</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                No players have gone unsold in this auction yet. Players nobody bids on are collected here
                automatically, grouped by the pool they were auctioned from.
            </p>
        </div>
    @endforelse

    {{-- ══ Auto-distribute preview ══
         Shows exactly who would go where before anything is written. The server
         recomputes the plan when applying it, so a stale preview can never overspend. --}}
    <div x-show="showAuto" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
         @click.self="showAuto = false">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Auto-distribute unsold players</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Each player goes to the team with the biggest shortfall that can still afford them, at base price.
                </p>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-4">
                <p x-show="loadingAuto" class="text-sm text-gray-500">Working out the distribution…</p>

                <template x-if="!loadingAuto && proposals.length">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-400 mb-2">
                            <span x-text="proposals.length"></span> to be allotted
                        </p>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                            <template x-for="p in proposals" :key="p.auction_player_id">
                                <li class="py-2 flex items-center justify-between gap-3">
                                    <span class="text-gray-800 dark:text-gray-100 truncate" x-text="p.player_name"></span>
                                    <span class="flex items-center gap-2 flex-shrink-0">
                                        <span class="text-emerald-600 font-medium" x-text="p.team_name"></span>
                                        <span class="text-gray-400 font-mono text-xs" x-text="formatMoney(p.price)"></span>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="!loadingAuto && unassigned.length">
                    <div class="mt-5">
                        <p class="text-xs uppercase tracking-wider text-amber-600 mb-2">
                            <span x-text="unassigned.length"></span> cannot be placed
                        </p>
                        <ul class="space-y-1.5 text-sm">
                            <template x-for="u in unassigned" :key="u.auction_player_id">
                                <li class="text-gray-500">
                                    <span class="text-gray-800 dark:text-gray-200 font-medium" x-text="u.player_name"></span>
                                    — <span x-text="u.reason"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <p x-show="!loadingAuto && !proposals.length && !unassigned.length" class="text-sm text-gray-500">
                    Nothing to distribute.
                </p>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3">
                <button type="button" @click="showAuto = false"
                        class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Cancel</button>
                <form action="{{ route('admin.auctions.allotment.auto', $auction) }}" method="POST" x-show="proposals.length">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                        Allot <span x-text="proposals.length"></span> player(s)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function allotmentScreen(auctionId, teams) {
        return {
            auctionId,
            teams,
            showAuto: false,
            loadingAuto: false,
            proposals: [],
            unassigned: [],

            async openAutoPreview() {
                this.showAuto = true;
                this.loadingAuto = true;
                this.proposals = [];
                this.unassigned = [];

                try {
                    const res = await fetch(`/admin/auctions/${this.auctionId}/allotment/preview`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    this.proposals = data.proposals || [];
                    this.unassigned = data.unassigned || [];
                } catch (e) {
                    console.error('Allotment preview failed:', e);
                } finally {
                    this.loadingAuto = false;
                }
            },

            /** K/M/B, matching how amounts are shown everywhere else. */
            formatMoney(value) {
                const n = Number(value) || 0;
                if (Math.abs(n) >= 1e9) return (n / 1e9).toFixed(2).replace(/\.?0+$/, '') + 'B';
                if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(2).replace(/\.?0+$/, '') + 'M';
                if (Math.abs(n) >= 1e3) return (n / 1e3).toFixed(2).replace(/\.?0+$/, '') + 'K';
                return String(n);
            },
        };
    }
</script>
@endpush
@endsection
