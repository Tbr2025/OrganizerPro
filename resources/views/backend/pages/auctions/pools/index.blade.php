@extends('backend.layouts.app')

@section('title', 'Manage Pools · ' . $auction->name . ' | ' . config('app.name'))

@php
    $modeLabels = ['sequential' => 'Sequential', 'random' => 'Random', 'odd_even' => 'Odd / Even', 'manual' => 'Manual'];
@endphp

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6" x-data="{ showCreate: false }">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Pools · {{ $auction->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Build pools and assign players. The auction runs pool-by-pool in the order below, players in drawn lot order.</p>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('admin.auctions.pools.auto-assign', $auction) }}" method="POST"
                  onsubmit="return confirm('Auto-group all unassigned players into pools by player type?')">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                    Auto-assign by type
                </button>
            </form>
            <button type="button" @click="showCreate = !showCreate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-brand-500 text-white hover:bg-brand-600">
                + New Pool
            </button>
            <a href="{{ route('admin.auctions.show', $auction) }}" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Back to auction</a>
        </div>
    </div>

    {{-- Team Budgets (auction tournaments only) --}}
    @if($isAuctionType && $teamBudgets->count())
    <div class="mb-6 rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Team budgets</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Remaining = allocated − retained − sold. Retained players are deducted up front.</p>
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-gray-800">
                        <th class="py-2 pr-4">Team</th>
                        <th class="py-2 px-3 text-right">Allocated</th>
                        <th class="py-2 px-3 text-right">Retained</th>
                        <th class="py-2 px-3 text-right">Sold</th>
                        <th class="py-2 pl-3 text-right">Remaining</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($teamBudgets as $row)
                    <tr>
                        <td class="py-2 pr-4 text-gray-800 dark:text-gray-100">{{ $row['team']->name }}</td>
                        <td class="py-2 px-3 text-right text-gray-500">{{ number_format($row['allocated']) }}</td>
                        <td class="py-2 px-3 text-right text-amber-600">{{ number_format($row['retained']) }}</td>
                        <td class="py-2 px-3 text-right text-gray-500">{{ number_format($row['sold']) }}</td>
                        <td class="py-2 pl-3 text-right font-semibold {{ $row['remaining'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($row['remaining']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Create Pool --}}
    <div x-show="showCreate" x-cloak class="mb-6 rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-5">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Create a pool</h3>
        <form action="{{ route('admin.auctions.pools.store', $auction) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            @csrf
            <div class="lg:col-span-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Name *</label>
                <input type="text" name="name" required value="{{ old('name') }}" class="form-control" placeholder="Pool A / Marquee">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Category</label>
                <input type="text" name="category" value="{{ old('category') }}" class="form-control" placeholder="Batsman / Grade A">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Base price</label>
                <input type="number" name="base_price" min="0" step="any" value="{{ old('base_price') }}" class="form-control" placeholder="{{ $auction->base_price }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Capacity</label>
                <input type="number" name="capacity" min="1" value="{{ old('capacity') }}" class="form-control" placeholder="∞">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Order mode</label>
                <select name="order_mode" class="form-control">
                    @foreach($orderModes as $m)<option value="{{ $m }}">{{ $modeLabels[$m] ?? $m }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-5">
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-brand-500 text-white hover:bg-brand-600">Create pool</button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Pools --}}
        <div class="lg:col-span-2 space-y-4">
            @forelse($pools as $pool)
                @php $players = $pool->players->sortBy(fn($p) => $p->lot_number ?? PHP_INT_MAX); @endphp
                <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-brand-500/10 text-brand-600 text-xs font-bold">{{ $pool->sequence }}</span>
                            <h4 class="font-semibold text-gray-900 dark:text-white">{{ $pool->name }}</h4>
                            @if($pool->category)<span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500">{{ $pool->category }}</span>@endif
                            <span class="text-xs text-gray-400">{{ $players->count() }}{{ $pool->capacity ? '/'.$pool->capacity : '' }} players · {{ $modeLabels[$pool->order_mode] ?? $pool->order_mode }}@if($pool->base_price) · base {{ number_format((float)$pool->base_price) }}@endif</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" class="text-xs text-indigo-600 hover:underline" onclick="document.getElementById('edit-pool-{{ $pool->id }}').classList.toggle('hidden')">Edit</button>
                            <form action="{{ route('admin.auctions.pools.destroy', [$auction, $pool]) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Delete pool “{{ $pool->name }}”? Waiting players return to Unassigned.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>

                    {{-- Inline edit --}}
                    <div id="edit-pool-{{ $pool->id }}" class="hidden px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40">
                        <form action="{{ route('admin.auctions.pools.update', [$auction, $pool]) }}" method="POST" class="grid grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                            @csrf @method('PUT')
                            <div><label class="block text-xs text-gray-500 mb-1">Name</label><input type="text" name="name" value="{{ $pool->name }}" required class="form-control"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Category</label><input type="text" name="category" value="{{ $pool->category }}" class="form-control"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Base price</label><input type="number" name="base_price" min="0" step="any" value="{{ $pool->base_price }}" class="form-control"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Capacity</label><input type="number" name="capacity" min="1" value="{{ $pool->capacity }}" class="form-control"></div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Order</label>
                                <select name="order_mode" class="form-control">
                                    @foreach($orderModes as $m)<option value="{{ $m }}" @selected($pool->order_mode === $m)>{{ $modeLabels[$m] ?? $m }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-span-2 lg:col-span-5"><button class="px-3 py-1.5 text-sm rounded-lg bg-brand-500 text-white hover:bg-brand-600">Save changes</button></div>
                        </form>
                    </div>

                    {{-- Players --}}
                    <div class="p-4">
                        @if($players->count())
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($players as $ap)
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 dark:border-gray-700 px-3 py-2 text-sm">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="text-xs text-gray-400 w-5">{{ $ap->is_retained ? '★' : ($ap->lot_number ?? '–') }}</span>
                                    <span class="truncate text-gray-800 dark:text-gray-100">{{ $ap->player->name ?? 'Player #'.$ap->player_id }}</span>
                                    @if($ap->is_retained)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 whitespace-nowrap">Retained{{ $ap->team ? ' · '.$ap->team->name : '' }}@if($ap->retained_price) · {{ number_format((float)$ap->retained_price) }}@endif</span>
                                    @elseif($ap->player?->playerType)<span class="text-[10px] text-gray-400">{{ $ap->player->playerType->name }}</span>@endif
                                    @if($ap->status !== 'waiting')<span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">{{ $ap->status }}</span>@endif
                                </span>
                                @if($ap->status === 'waiting')
                                <form action="{{ route('admin.auctions.pools.unassign', $auction) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="player_id" value="{{ $ap->player_id }}">
                                    <button type="submit" class="text-xs text-gray-400 hover:text-red-600" title="Remove from pool">✕</button>
                                </form>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="text-sm text-gray-400">No players in this pool yet — assign some from the right.</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-md border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center text-gray-500">
                    No pools yet. Click <strong>New Pool</strong> to create one, or <strong>Auto-assign by type</strong>.
                </div>
            @endforelse
        </div>

        {{-- Unassigned players + assign form --}}
        <div class="lg:col-span-1">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="{ q: '' }">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                    <h4 class="font-semibold text-gray-900 dark:text-white">Unassigned players <span class="text-xs text-gray-400">({{ $available->count() }})</span></h4>
                </div>
                @if($pools->count() && $available->count())
                <form action="{{ route('admin.auctions.pools.assign', $auction) }}" method="POST" class="p-4">
                    @csrf
                    <div class="flex items-center gap-2 mb-3">
                        <select name="pool_id" required class="form-control flex-1">
                            @foreach($pools as $pool)<option value="{{ $pool->id }}">{{ $pool->name }}</option>@endforeach
                        </select>
                        <button type="submit" class="px-3 py-2 text-sm font-medium rounded-lg bg-brand-500 text-white hover:bg-brand-600 whitespace-nowrap">Assign →</button>
                    </div>
                    <input type="text" x-model="q" placeholder="Search players…" class="form-control mb-2">
                    <label class="flex items-center gap-2 text-xs text-gray-500 mb-2 cursor-pointer">
                        <input type="checkbox" @change="$root.querySelectorAll('input[name=\'player_ids[]\']').forEach(c => c.checked = $event.target.checked)"> Select all (visible)
                    </label>
                    @if($isAuctionType && $available->contains(fn($p) => $p->player_mode === 'retained'))
                    <p class="text-[11px] text-amber-600 mb-2">Retained players are added to their team up front; enter a retention price (deducted from that team's budget).</p>
                    @endif
                    <div class="max-h-[28rem] overflow-y-auto space-y-1 pr-1">
                        @foreach($available as $p)
                        @php $isRet = $p->player_mode === 'retained'; @endphp
                        <div class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm {{ $isRet ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-900/10' : 'border-gray-100 dark:border-gray-700' }}"
                             x-show="q === '' || '{{ strtolower($p->name) }}'.includes(q.toLowerCase())">
                            <label class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer">
                                <input type="checkbox" name="player_ids[]" value="{{ $p->id }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <span class="truncate text-gray-800 dark:text-gray-100">{{ $p->name }}</span>
                                @if($isRet)<span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 whitespace-nowrap">Retained{{ $p->actualTeam ? ' · '.$p->actualTeam->name : '' }}</span>
                                @elseif($p->playerType)<span class="text-[10px] text-gray-400">{{ $p->playerType->name }}</span>@endif
                            </label>
                            @if($isRet && $isAuctionType)
                            <input type="number" name="retained_prices[{{ $p->id }}]" min="0" step="any" placeholder="price"
                                   class="form-control w-24 !text-xs !py-1"
                                   title="Retention price (deducted from {{ $p->actualTeam->name ?? 'team' }} budget)">
                            @endif
                        </div>
                        @endforeach
                    </div>
                </form>
                @elseif(! $pools->count())
                <p class="p-4 text-sm text-gray-400">Create a pool first, then assign players to it.</p>
                @else
                <p class="p-4 text-sm text-gray-400">All approved players are assigned to a pool. 🎉</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
