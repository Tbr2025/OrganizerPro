@extends('backend.layouts.app')

@section('title', 'Manage Pools · ' . $auction->name . ' | ' . config('app.name'))

@php
    $modeLabels = ['sequential' => 'Sequential', 'random' => 'Random', 'odd_even' => 'Odd / Even', 'manual' => 'Manual'];
@endphp

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6" x-data="poolManager({{ $auction->id }})">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- The archive, findable without the progress dialog. Once that was dismissed the export was
         invisible: the zip sat on disk for an hour with no way to fetch it again, so a second run
         of three hundred posters was the only way to recover a download somebody had closed. --}}
    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.auctions.card-exports', $auction) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            <iconify-icon icon="lucide:archive" width="14"></iconify-icon>
            Poster Archives
        </a>
    </div>

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Pools · {{ $auction->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Build pools and assign players. The auction runs pool-by-pool in the order below, players in drawn lot order.</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Auto-assign sweeps every unassigned player at once, so the last run stays
                 revertible until something in it is actioned. --}}
            @isset($revertibleAutoAssign)
                @if($revertibleAutoAssign)
                    <form action="{{ route('admin.auctions.pools.auto-assign.revert', $auction) }}" method="POST"
                          @submit.prevent="confirmForm($event.target, {
                              title: 'Undo auto-assign',
                              message: @js($revertibleAutoAssign->description . ' — players return to Unassigned. Anyone already on the block or sold is left as they are.'),
                          })">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/20"
                                title="{{ $revertibleAutoAssign->description }}">
                            &#8630; Undo auto-assign
                        </button>
                    </form>
                @endif
            @endisset
            <form action="{{ route('admin.auctions.pools.auto-assign', $auction) }}" method="POST"
                  @submit.prevent="confirmForm($event.target, {
                      title: 'Auto-assign by type',
                      message: 'Group every unassigned player into pools by player type? This can be undone afterwards.',
                      confirmLabel: 'Yes, assign',
                      danger: false,
                  })">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                    Auto-assign by type
                </button>
            </form>
            {{-- Scrolls the panel into view and reflects its own state.
                 The toggle worked but the form opens ABOVE this button, so on a page of three
                 hundred players a click could scroll nothing into sight and read as a dead
                 button. `aria-expanded` and the label make the response visible either way. --}}
            <button type="button"
                    @click="showCreate = !showCreate; if (showCreate) $nextTick(() => document.getElementById('create-pool')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                    :aria-expanded="showCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white"
                    :class="showCreate ? 'bg-gray-700 hover:bg-gray-600' : 'bg-brand-500 hover:bg-brand-600'">
                <span x-text="showCreate ? 'Close' : '+ New Pool'"></span>
            </button>
            <a href="{{ route('admin.auctions.show', $auction) }}" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">Back to auction</a>
        </div>
    </div>

    {{-- Team Budgets (auction tournaments only) --}}
    @if($isAuctionType && $teamBudgets->count())
    <div class="mb-6 rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Team budgets</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Remaining = allocated − retained − sold. Icon players are deducted up front.</p>
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-gray-800">
                        <th class="py-2 pr-4">Team</th>
                        <th class="py-2 px-3 text-right">Allocated</th>
                        <th class="py-2 px-3 text-right">Icon Player</th>
                        <th class="py-2 px-3 text-center">Kept</th>
                        <th class="py-2 px-3 text-right">Sold</th>
                        <th class="py-2 pl-3 text-right">Remaining</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($teamBudgets as $row)
                    <tr>
                        <td class="py-2 pr-4 text-gray-800 dark:text-gray-100">{{ $row['team']->name }}</td>
                        <td class="py-2 px-3 text-right text-gray-500">{{ $auction->formatAmount($row['allocated'], '0') }}</td>
                        <td class="py-2 px-3 text-right text-amber-600">{{ $auction->formatAmount($row['retained'], '0') }}</td>
                        <td class="py-2 px-3 text-center">
                            @if(($row['retained_expected'] ?? 0) > 0)
                                {{-- Advisory: the organizer said teams sometimes retain more,
                                     so a mismatch is flagged and never blocked. --}}
                                <span class="text-xs font-semibold {{ $row['retained_count'] !== $row['retained_expected'] ? 'text-amber-600' : 'text-gray-500' }}"
                                      title="{{ $row['retained_count'] }} retained; {{ $row['retained_expected'] }} expected.">
                                    {{ $row['retained_count'] }}/{{ $row['retained_expected'] }}
                                </span>
                            @else
                                <span class="text-xs text-gray-500">{{ $row['retained_count'] ?? 0 }}</span>
                            @endif
                            @if(($row['retained_unpriced'] ?? 0) > 0)
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 whitespace-nowrap"
                                      title="These retained players have no retention price, so this team is being charged nothing for them.">
                                    {{ $row['retained_unpriced'] }} unpriced
                                </span>
                            @endif
                        </td>
                        <td class="py-2 px-3 text-right text-gray-500">{{ $auction->formatAmount($row['sold'], '0') }}</td>
                        <td class="py-2 pl-3 text-right font-semibold {{ $row['remaining'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $auction->formatAmount($row['remaining'], '0') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Create Pool --}}
    <div id="create-pool" x-show="showCreate" x-cloak class="mb-6 rounded-md border-2 border-brand-400 bg-white dark:border-brand-600 dark:bg-white/[0.03] p-5">
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

    {{-- ── Undo auto-assign ──
         The header already has this button, but auto-assign rewrites the whole board in one
         click and the button sits among four others — easy to miss at the moment you most
         want it. A banner states plainly that the last run is still reversible, and says
         exactly what it would put back. --}}
    @isset($revertibleAutoAssign)
        @if($revertibleAutoAssign)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 px-4 py-3 rounded-md border border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">The last auto-assign can still be undone</p>
                <p class="text-xs text-amber-700 dark:text-amber-300">{{ $revertibleAutoAssign->description }} — players return to Unassigned. Anyone already on the block or sold is left as they are.</p>
            </div>
            <form action="{{ route('admin.auctions.pools.auto-assign.revert', $auction) }}" method="POST" class="shrink-0"
                  @submit.prevent="confirmForm($event.target, {
                      title: 'Undo auto-assign',
                      message: @js($revertibleAutoAssign->description . ' — players return to Unassigned. Anyone already on the block or sold is left as they are.'),
                      confirmLabel: 'Yes, undo it',
                  })">
                @csrf
                <button type="submit"
                        class="px-3 py-2 text-sm font-semibold rounded-lg bg-amber-600 text-white hover:bg-amber-700 whitespace-nowrap">
                    &#8630; Undo auto-assign
                </button>
            </form>
        </div>
        @endif
    @endisset

    {{-- ── Bulk selection bar ──
         Shown only once something is picked, so the ordinary single-pool workflow is not
         cluttered by a toolbar that does nothing. --}}
    @if($pools->count())
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 px-4 py-3 rounded-md border transition-colors"
         :class="selected.length
            ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
            : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]'">
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer select-none">
            <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                   :checked="allSelected" @change="toggleAll($event.target.checked)">
            <span x-text="selected.length ? `${selected.length} selected` : 'Select all pools'"></span>
        </label>
        <div class="flex items-center gap-2">
            <button type="button" x-show="selected.length" @click="clearSelection()"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">
                Clear
            </button>
            <button type="button" x-show="selected.length" @click="confirmBulkDelete()"
                    :disabled="busy"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!busy" x-text="`Delete ${selected.length} selected`"></span>
                <span x-show="busy">Deleting…</span>
            </button>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Pools --}}
        <div class="lg:col-span-2 space-y-4">
            @forelse($pools as $pool)
                @php $players = $pool->players->sortBy(fn($p) => $p->lot_number ?? PHP_INT_MAX); @endphp
                <div class="rounded-md border bg-white dark:bg-white/[0.03] transition-colors"
                     x-show="!removed.includes({{ $pool->id }})" x-transition
                     :class="selected.includes({{ $pool->id }})
                        ? 'border-red-300 dark:border-red-800 ring-1 ring-red-200 dark:ring-red-900'
                        : 'border-gray-200 dark:border-gray-800'">
                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- A running pool cannot be deleted, so it is not selectable
                                 either: offering the checkbox and then refusing the delete
                                 wastes the organizer's time mid-auction. --}}
                            @if($pool->isActive())
                                <span class="inline-block w-4 h-4" title="This pool is running — close it on the control panel first"></span>
                            @else
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 cursor-pointer"
                                       data-pool-select
                                       data-pool-id="{{ $pool->id }}"
                                       data-pool-name="{{ $pool->name }}"
                                       data-pool-waiting="{{ $pool->players->where('status', 'waiting')->count() }}"
                                       :checked="selected.includes({{ $pool->id }})"
                                       @change="toggle({{ $pool->id }})"
                                       aria-label="Select {{ $pool->name }}">
                            @endif
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-brand-500/10 text-brand-600 text-xs font-bold">{{ $pool->sequence }}</span>
                            <h4 class="font-semibold text-gray-900 dark:text-white">{{ $pool->name }}</h4>
                            @if($pool->category)<span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500">{{ $pool->category }}</span>@endif

                            {{-- Lifecycle: which pool is live, which are done. --}}
                            @if($pool->isActive())
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 font-semibold">Running</span>
                            @elseif($pool->isCompleted())
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 font-semibold">Completed</span>
                            @endif
                            @if($pool->isUnsoldPool())
                                {{-- Holding pool: players nobody bid on, kept for final allotment. --}}
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-300 font-semibold"
                                      title="Players nobody bid on, held for final allotment after the auction">
                                    {{-- Interpolated rather than an inline @if: Blade does not
                                         compile a directive glued to a word character, so
                                         "Unsold@if(...)" left the @if as literal text while its
                                         @endif compiled, breaking the whole template. --}}
                                    Unsold{{ $pool->parentPool ? ' from ' . $pool->parentPool->name : '' }}
                                </span>
                            @elseif(! $pool->isEnabled())
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-300 font-semibold">Disabled</span>
                            @endif
                            @if($pool->times_used > 0)
                                <span class="text-[11px] text-gray-400" title="Number of times this pool has been run">
                                    run {{ $pool->times_used }}&times;
                                </span>
                            @endif

                            <span class="text-xs text-gray-400">{{ $players->count() }}{{ $pool->capacity ? '/'.$pool->capacity : '' }} players · {{ $modeLabels[$pool->order_mode] ?? $pool->order_mode }}@if($pool->base_price) · base {{ $auction->formatAmount($pool->base_price) }}@endif</span>
                        </div>
                        <div class="flex items-center gap-3">
                            {{-- Take a pool out of play without deleting it or its history. --}}
                            <form action="{{ route('admin.auction.organizer.api.pool.toggle-enabled', [$auction, $pool]) }}"
                                  method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="is_enabled" value="{{ $pool->isEnabled() ? 0 : 1 }}">
                                <button type="submit"
                                        @disabled($pool->isActive())
                                        class="text-xs {{ $pool->isEnabled() ? 'text-amber-600' : 'text-emerald-600' }} hover:underline disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed"
                                        title="{{ $pool->isActive() ? 'Close this pool on the control panel before disabling it' : '' }}">
                                    {{ $pool->isEnabled() ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                            <button type="button" class="text-xs text-indigo-600 hover:underline" onclick="document.getElementById('edit-pool-{{ $pool->id }}').classList.toggle('hidden')">Edit</button>
                            {{-- Deleted over fetch so the page keeps its scroll position and the
                                 unsaved inline-edit forms below. --}}
                            <button type="button" :disabled="busy"
                                    @click="confirmDeleteOne({{ $pool->id }}, @js($pool->name), {{ $pool->players->where('status', 'waiting')->count() }})"
                                    class="text-xs text-red-600 hover:underline disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed">
                                Delete
                            </button>
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
                        {{-- Per-pool selection strip. Scoped to this pool so "select all"
                             on a page of six pools cannot pick up 200 players at once. --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer select-none">
                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                       :checked="poolFullySelected({{ $pool->id }})"
                                       @change="toggleAllInPool({{ $pool->id }}, $event.target.checked)">
                                <span x-text="selectedInPool({{ $pool->id }}).length
                                    ? `${selectedInPool({{ $pool->id }}).length} selected in this pool`
                                    : 'Select all in this pool'"></span>
                            </label>
                            {{-- Cards for the players ticked in THIS pool, as one zip. Rendered
                                 server-side, a browser per card, so it is seconds per player —
                                 which is why it is a deliberate click on a chosen few rather
                                 than something offered for the whole auction by default. --}}
                            <button type="button" x-show="selectedInPool({{ $pool->id }}).length" :disabled="busy"
                                    @click="downloadCards(selectedInPool({{ $pool->id }}))"
                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50">
                                {{-- "poster" when a design exists, "card" when the wall screenshot is
                                     the only thing available — the two are different artwork and the
                                     button should not claim the one it is not producing. --}}
                                <span x-text="`Download ${selectedInPool({{ $pool->id }}).length} {{ $posterTemplates->isNotEmpty() ? 'poster' : 'card' }}(s)`"></span>
                            </button>
                            {{-- Counts only the removable ones. Now that a sold player can be
                                 ticked for a poster, a selection of eight sold players would
                                 otherwise offer to remove eight and remove none. --}}
                            <button type="button" x-show="removableInPool({{ $pool->id }}).length" :disabled="busy"
                                    @click="confirmRemovePlayers(removableInPool({{ $pool->id }}))"
                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700 disabled:opacity-50">
                                <span x-text="`Remove ${removableInPool({{ $pool->id }}).length} from pool`"></span>
                            </button>

                            @if($posterTemplates->isNotEmpty())
                                {{-- Posters for this pool, sold and unsold as separate runs.
                                     Separate because they are separate jobs: a sold poster is
                                     an announcement and an unsold one is a list for allotment,
                                     and each usually wants its own design. The server matches
                                     on where a player came FROM as well as where they are now,
                                     since an unsold player has already been moved out of this
                                     pool into the auction's shared unsold pile. --}}
                                <div x-data="{ open: false, status: 'sold' }" class="relative ml-auto">
                                    <button type="button" @click="open = !open"
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-500 text-white hover:bg-amber-600">
                                        {{-- Says its scope, so "Posters" cannot mean two different
                                             sizes of job depending on what is ticked. --}}
                                        <span x-text="selectedInPool({{ $pool->id }}).length
                                            ? `Posters (${selectedInPool({{ $pool->id }}).length})`
                                            : 'Posters (pool)'"></span>
                                    </button>
                                    <div x-show="open" x-cloak @click.outside="open = false"
                                         class="absolute right-0 z-40 mt-1 w-72 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl overflow-hidden">
                                        <div class="flex border-b border-gray-100 dark:border-gray-800">
                                            <button type="button" @click="status = 'sold'"
                                                    class="flex-1 px-3 py-2 text-[11px] font-bold uppercase tracking-wide"
                                                    :class="status === 'sold' ? 'bg-emerald-500 text-white' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'">
                                                Sold
                                            </button>
                                            <button type="button" @click="status = 'unsold'"
                                                    class="flex-1 px-3 py-2 text-[11px] font-bold uppercase tracking-wide"
                                                    :class="status === 'unsold' ? 'bg-red-500 text-white' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'">
                                                Unsold
                                            </button>
                                            <button type="button" @click="status = 'all'"
                                                    class="flex-1 px-3 py-2 text-[11px] font-bold uppercase tracking-wide"
                                                    :class="status === 'all' ? 'bg-gray-700 text-white' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'">
                                                All
                                            </button>
                                        </div>
                                        @foreach($posterTemplates as $posterTemplate)
                                            <button type="button"
                                                    {{-- The ticked players when any are ticked, the whole pool
                                             otherwise. Passing null unconditionally meant a
                                             selection of one quietly became a run of three hundred
                                             and seven — the checkboxes were right there and
                                             ignored. --}}
                                        @click="open = false; Alpine.store('cardExport').start(
                                                        {{ $auction->id }},
                                                        selectedInPool({{ $pool->id }}).length ? selectedInPool({{ $pool->id }}) : null,
                                                        true, {{ $posterTemplate->id }}, status, {{ $pool->id }})"
                                                    class="w-full text-left px-3 py-2 text-xs hover:bg-gray-100 dark:hover:bg-gray-800">
                                                <span class="font-semibold block truncate text-gray-900 dark:text-white">{{ $posterTemplate->name }}</span>
                                                <span class="text-gray-400">
                                                    {{ $posterTemplate->type === \App\Models\TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT ? 'Vertical' : 'Horizontal' }}
                                                    &middot; {{ $posterTemplate->canvas_width }}&times;{{ $posterTemplate->canvas_height }}
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($players as $ap)
                            <div class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm transition-colors"
                                 x-show="!playerRemoved.includes({{ $ap->player_id }})" x-transition
                                 :class="selectedPlayers.includes({{ $ap->player_id }})
                                    ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
                                    : 'border-gray-100 dark:border-gray-700'">
                                <span class="flex items-center gap-2 min-w-0">
                                    {{-- Every player gets a tick, sold or not.
                                         This was rendered only for `waiting`, on the reasoning that
                                         a sold player is not removable — true, but the tick had
                                         since grown a second job: it is also what picks players for
                                         a poster run. So a completed pool had nothing selectable in
                                         it, and the posters an organizer actually wants — the sold
                                         ones, with the buying team and the price on them — were the
                                         only posters the screen refused to produce.
                                         Removability travels as an attribute instead, and the
                                         Remove action reads it. --}}
                                    <input type="checkbox"
                                           data-player-select
                                           data-player-id="{{ $ap->player_id }}"
                                           data-player-name="{{ $ap->player->name ?? 'Player #'.$ap->player_id }}"
                                           data-pool-of="{{ $pool->id }}"
                                           @if($ap->status === 'waiting') data-removable="1" @endif
                                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 cursor-pointer shrink-0"
                                           :checked="selectedPlayers.includes({{ $ap->player_id }})"
                                           @change="togglePlayer({{ $ap->player_id }})"
                                           aria-label="Select {{ $ap->player->name ?? 'player' }}">
                                    <span class="text-xs text-gray-400 w-5">{{ $ap->is_retained ? '★' : ($ap->lot_number ?? '–') }}</span>

                                    {{-- The same summary the players list gives: face, name, and how
                                         they bat and bowl. A pool is reviewed to judge whether it is a
                                         sensible group to auction together, and a name alone cannot
                                         answer that. Deliberately no contact details — this screen is
                                         shared and projected, and a phone number has no bearing on
                                         which pool somebody belongs in. --}}
                                    @if($ap->player?->image_path)
                                        <img src="{{ asset('storage/' . $ap->player->image_path) }}" alt=""
                                             loading="lazy"
                                             class="w-7 h-7 rounded-full object-cover shrink-0 bg-gray-100 dark:bg-gray-700">
                                    @else
                                        <span class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-700 shrink-0 flex items-center justify-center text-[9px] font-bold text-gray-500">
                                            {{ strtoupper(substr($ap->player->name ?? '?', 0, 2)) }}
                                        </span>
                                    @endif

                                    <span class="min-w-0">
                                        <span class="block truncate text-gray-800 dark:text-gray-100">{{ $ap->player->name ?? 'Player #'.$ap->player_id }}</span>
                                        @php
                                            $styles = array_filter([
                                                $ap->player?->battingProfile?->style,
                                                $ap->player?->bowlingProfile?->style,
                                            ]);
                                        @endphp
                                        @if($styles)
                                            <span class="block text-[10px] text-gray-400 truncate">{{ implode(' · ', $styles) }}</span>
                                        @endif
                                        {{-- Wicket keeper, and when they are in the country.
                                             An organizer building pools is deciding both who can
                                             actually turn up and whether a pool has a keeper in
                                             it — and neither answer was on this screen. --}}
                                        <span class="flex flex-wrap items-center gap-1.5">
                                            @if($ap->player?->is_wicket_keeper)
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300 whitespace-nowrap"
                                                      title="Wicket keeper">
                                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                        <path d="M10 2a4 4 0 00-4 4v1.2A3 3 0 004 10v4a4 4 0 004 4h4a4 4 0 004-4v-4a3 3 0 00-2-2.8V6a4 4 0 00-4-4zm-2 4a2 2 0 114 0v1H8V6z"/>
                                                    </svg>
                                                    WK
                                                </span>
                                            @endif
                                            <x-travel-plan :player="$ap->player" />
                                        </span>
                                    </span>
                                    @if($ap->is_retained)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 whitespace-nowrap">Icon Player{{ $ap->team ? ' · '.$ap->team->name : '' }}{{ (float) $ap->retained_price > 0 ? ' · ' . $auction->formatAmount($ap->retained_price) : '' }}</span>@if((float) $ap->retained_price <= 0)<span class="text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 whitespace-nowrap" title="No retention price — this player currently costs their team nothing.">no price</span>@endif
                                    @elseif($ap->player?->playerType)<span class="text-[10px] text-gray-400">{{ $ap->player->playerType->name }}</span>@endif
                                    {{-- "sold" on its own left the obvious question unanswered: sold
                                         to WHOM. The buying team is the whole result of the lot and
                                         the reason anybody reads this list afterwards. --}}
                                    @if($ap->status === 'sold')
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 whitespace-nowrap">
                                            sold{{ $ap->soldToTeam ? ' · ' . $ap->soldToTeam->name : '' }}{{ (float) $ap->final_price > 0 ? ' · ' . $auction->formatAmount($ap->final_price) : '' }}
                                        </span>
                                    @elseif($ap->status !== 'waiting')
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">{{ $ap->status }}</span>
                                    @endif
                                </span>

                                <span class="flex items-center gap-1.5 shrink-0">
                                    {{-- One player's poster, rendered on the spot. Going through
                                         the export queue for a single PNG means a job, a zip and
                                         a progress dialog to fetch one file.

                                         Not gated on a designed poster any more: with no template
                                         this whole control vanished, so on a tournament that had
                                         never opened the poster editor the screen simply had no
                                         download on it and no way to say why. The server falls back
                                         to the LED wall card, which every auction has. --}}
                                    <a href="{{ route('admin.auctions.player-poster', [$auction, $ap]) }}"
                                       class="text-xs text-gray-400 hover:text-amber-600"
                                       title="Download this player's {{ $posterTemplates->isNotEmpty() ? 'poster' : 'card' }}">
                                        <iconify-icon icon="lucide:image-down" width="15"></iconify-icon>
                                    </a>
                                    @if($ap->status === 'waiting')
                                    <button type="button" :disabled="busy"
                                            @click="confirmRemovePlayers([{{ $ap->player_id }}])"
                                            class="text-xs text-gray-400 hover:text-red-600 disabled:opacity-40 disabled:cursor-not-allowed"
                                            title="Remove from pool">&times;</button>
                                    @endif
                                </span>
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
                    <div class="max-h-[28rem] overflow-y-auto space-y-1 pr-1">
                        {{-- Icon players are not listed: this panel feeds a bidding
                             queue, and a retained player is never bid on. Their retention
                             price is set on their team (Teams -> edit -> squad). --}}
                        @foreach($available as $p)
                        <div class="flex items-center gap-2 rounded-lg border border-gray-100 dark:border-gray-700 px-3 py-1.5 text-sm"
                             x-show="q === '' || '{{ strtolower($p->name) }}'.includes(q.toLowerCase())">
                            <label class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer">
                                <input type="checkbox" name="player_ids[]" value="{{ $p->id }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <span class="truncate text-gray-800 dark:text-gray-100">{{ $p->name }}</span>
                                @if($p->playerType)<span class="text-[10px] text-gray-400">{{ $p->playerType->name }}</span>@endif
                            </label>
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

    {{-- ── Confirm + toasts ──
         Native confirm() was used here before. It cannot show a list, cannot say how many
         players are about to be unassigned, and on a bulk delete that is exactly the detail
         the organizer needs before committing. --}}
    <div x-show="confirmBox.open" x-cloak
         class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 p-4"
         @click.self="cancelConfirm()" x-transition.opacity>
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-2xl p-6">
            <h3 class="text-base font-bold text-gray-900 dark:text-white" x-text="confirmBox.title"></h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300" x-text="confirmBox.message"></p>

            <ul x-show="confirmBox.names.length"
                class="mt-3 max-h-40 overflow-y-auto text-sm text-gray-700 dark:text-gray-200 space-y-1 pl-4">
                <template x-for="n in confirmBox.names" :key="n">
                    <li class="list-disc" x-text="n"></li>
                </template>
            </ul>

            <p x-show="confirmBox.waiting > 0" class="mt-3 text-xs text-amber-600 dark:text-amber-400"
               x-text="`${confirmBox.waiting} waiting player(s) will return to Unassigned. Anyone already sold or on the block keeps their result.`"></p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" @click="cancelConfirm()"
                        class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                    No, cancel
                </button>
                <button type="button" @click="runConfirm()" :disabled="busy"
                        class="px-4 py-2 text-sm font-semibold rounded-lg text-white disabled:opacity-50"
                        :class="confirmBox.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-500 hover:bg-brand-600'">
                    <span x-show="!busy" x-text="confirmBox.confirmLabel"></span>
                    <span x-show="busy">Working…</span>
                </button>
            </div>
        </div>
    </div>

    <div class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-start gap-3 max-w-md px-4 py-3 rounded-xl shadow-2xl bg-gray-900 border border-white/10 border-l-4"
                 :class="t.type === 'error' ? 'border-l-red-500' : 'border-l-emerald-500'"
                 x-transition.opacity>
                <p class="text-sm text-white" x-text="t.message"></p>
                <button type="button" @click="toasts = toasts.filter(x => x.id !== t.id)"
                        class="text-gray-400 hover:text-white text-xs font-bold">&times;</button>
            </div>
        </template>
    </div>
</div>

@include('backend.pages.auctions.partials.card-export-progress')

@push('scripts')
<script>
/**
 * Pools screen: selection, confirmation and AJAX delete.
 *
 * Deleting over fetch rather than a form post keeps the scroll position and any inline
 * edit forms the organizer has open — on a page with a dozen pools, a full reload after
 * every delete meant scrolling back each time.
 */
function poolManager(auctionId) {
    return {
        showCreate: false,
        selected: [],
        // Players picked for removal from their pool, and those already gone.
        selectedPlayers: [],
        playerRemoved: [],
        // Ids already gone from the server. Their cards are hidden rather than the page
        // reloaded, so the rest of the screen survives.
        removed: [],
        busy: false,
        toasts: [],
        _seq: 0,
        /* One dialog for every destructive action on this page: an id-list delete (fetch)
           or an ordinary form post held back until the organizer agrees. */
        confirmBox: {
            open: false, title: '', message: '', names: [], waiting: 0,
            ids: [], playerIds: [], form: null, confirmLabel: 'Yes, delete', danger: true,
        },

        // Every non-running pool on the page, so "select all" cannot pick one the server
        // will refuse.
        get selectableIds() {
            return Array.from(this.$root.querySelectorAll('input[data-pool-select]'))
                .map(el => Number(el.getAttribute('data-pool-id')))
                .filter(id => id && !this.removed.includes(id));
        },

        get allSelected() {
            const ids = this.selectableIds;
            return ids.length > 0 && ids.every(id => this.selected.includes(id));
        },

        toggle(id) {
            this.selected = this.selected.includes(id)
                ? this.selected.filter(x => x !== id)
                : [...this.selected, id];
        },

        toggleAll(checked) {
            this.selected = checked ? this.selectableIds : [];
        },

        clearSelection() { this.selected = []; },

        toast(message, type = 'success') {
            const id = ++this._seq;
            this.toasts.push({ id, message, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); },
                type === 'error' ? 6000 : 3500);
        },

        _poolEl(id) {
            return this.$root.querySelector(`input[data-pool-select][data-pool-id="${id}"]`);
        },

        poolName(id) {
            return this._poolEl(id)?.getAttribute('data-pool-name') || `Pool #${id}`;
        },

        poolWaiting(id) {
            return Number(this._poolEl(id)?.getAttribute('data-pool-waiting') || 0);
        },

        /* ── Player selection, scoped per pool ── */
        _playerBoxes(poolId = null) {
            const sel = poolId === null
                ? 'input[data-player-select]'
                : `input[data-player-select][data-pool-of="${poolId}"]`;

            return Array.from(this.$root.querySelectorAll(sel))
                .filter(el => !this.playerRemoved.includes(Number(el.getAttribute('data-player-id'))));
        },

        playerIdsInPool(poolId) {
            return this._playerBoxes(poolId).map(el => Number(el.getAttribute('data-player-id')));
        },

        selectedInPool(poolId) {
            const ids = this.playerIdsInPool(poolId);
            return this.selectedPlayers.filter(id => ids.includes(id));
        },

        /**
         * The ticked players in this pool that can actually leave it.
         *
         * A tick means two things now — "include in the poster run" and "remove from the pool" —
         * and only the first applies to a player who has already been sold or is on the block.
         * Read off the DOM rather than tracked in state, so it cannot drift from what is rendered.
         */
        removableInPool(poolId) {
            const removable = this._playerBoxes(poolId)
                .filter(el => el.hasAttribute('data-removable'))
                .map(el => Number(el.getAttribute('data-player-id')));

            return this.selectedInPool(poolId).filter(id => removable.includes(id));
        },

        poolFullySelected(poolId) {
            const ids = this.playerIdsInPool(poolId);
            return ids.length > 0 && ids.every(id => this.selectedPlayers.includes(id));
        },

        /**
         * The selected players' cards, as a zip.
         *
         * Handed to the shared progress dialog rather than navigated to. The render is seconds
         * per card on the server, so a plain download link left the operator on a blank tab
         * with no way to tell a slow export from a dead one — and for a large pool the gateway
         * cut the connection before the zip ever arrived.
         */
        /**
         * The ticked players' posters, drawn from the tournament's POSTER TEMPLATE.
         *
         * This passed no template, so the export fell through to the LED wall path — a headless
         * screenshot of the wall card. That is the wrong artwork for a download and it is also the
         * slow one: a browser per player, seconds each, against GD's milliseconds. The design the
         * organizer drew in the editor is the one they expect out of this button.
         *
         * Falls back to the wall card only when no poster has been designed, because then there
         * is nothing else to render.
         */
        downloadCards(ids) {
            if (!ids || ids.length === 0) return;

            Alpine.store('cardExport').start(
                {{ $auction->id }},
                ids,
                true,
                @js($posterTemplates->first()?->id)
            );
        },

        togglePlayer(id) {
            this.selectedPlayers = this.selectedPlayers.includes(id)
                ? this.selectedPlayers.filter(x => x !== id)
                : [...this.selectedPlayers, id];
        },

        toggleAllInPool(poolId, checked) {
            const ids = this.playerIdsInPool(poolId);
            this.selectedPlayers = checked
                ? [...new Set([...this.selectedPlayers, ...ids])]
                : this.selectedPlayers.filter(id => !ids.includes(id));
        },

        playerName(id) {
            const el = this.$root.querySelector(`input[data-player-select][data-player-id="${id}"]`);
            return el?.getAttribute('data-player-name') || `Player #${id}`;
        },

        confirmRemovePlayers(ids) {
            /*
             * Removable only, whoever asked. The per-row × is rendered for waiting players alone,
             * but the bulk path takes a list, and a sold player in it would be sent to a server
             * that refuses them — reported back as a failure the operator did not cause.
             */
            const removable = Array.from(this.$root.querySelectorAll('input[data-player-select][data-removable]'))
                .map(el => Number(el.getAttribute('data-player-id')));

            const asked = (ids || []).filter(id => !this.playerRemoved.includes(id));
            const list = asked.filter(id => removable.includes(id));

            if (!list.length) {
                if (asked.length) {
                    this.toast('Those players are already sold or on the block — a result cannot be undone by removing them from a pool.', 'error');
                }

                return;
            }

            this.confirmBox = {
                open: true,
                title: list.length > 1 ? `Remove ${list.length} players` : 'Remove player',
                message: list.length > 1
                    ? 'These players go back to Unassigned and the pool\'s lot numbers are redrawn.'
                    : `Remove “${this.playerName(list[0])}” from this pool? They go back to Unassigned.`,
                // Only worth listing when there is more than one.
                names: list.length > 1 ? list.map(id => this.playerName(id)) : [],
                waiting: 0,
                ids: [],
                playerIds: list,
                form: null,
                confirmLabel: list.length > 1 ? `Yes, remove ${list.length}` : 'Yes, remove',
                danger: true,
            };
        },

        confirmDeleteOne(id, name, waiting) {
            this.confirmBox = {
                open: true,
                title: 'Delete pool',
                message: `Delete “${name}”? This cannot be undone.`,
                names: [],
                waiting: Number(waiting || 0),
                ids: [id],
                playerIds: [],
                form: null,
                confirmLabel: 'Yes, delete',
                danger: true,
            };
        },

        confirmBulkDelete() {
            const ids = [...this.selected];
            if (!ids.length) return;

            this.confirmBox = {
                open: true,
                title: `Delete ${ids.length} pool${ids.length > 1 ? 's' : ''}`,
                message: 'This cannot be undone. The following will be deleted:',
                names: ids.map(id => this.poolName(id)),
                waiting: ids.reduce((sum, id) => sum + this.poolWaiting(id), 0),
                ids,
                playerIds: [],
                form: null,
                confirmLabel: `Yes, delete ${ids.length}`,
                danger: true,
            };
        },

        _resetConfirm() {
            this.confirmBox = {
                open: false, title: '', message: '', names: [], waiting: 0,
                ids: [], playerIds: [], form: null, confirmLabel: 'Yes, delete', danger: true,
            };
        },

        cancelConfirm() {
            if (this.busy) return;
            this._resetConfirm();
        },

        /** Hold a normal form post behind the same dialog. */
        confirmForm(form, { title, message, confirmLabel = 'Yes, continue', danger = true } = {}) {
            this.confirmBox = {
                open: true, title, message, names: [], waiting: 0,
                ids: [], playerIds: [], form, confirmLabel, danger,
            };
        },

        async _removePlayers(playerIds) {
            this.busy = true;
            try {
                const single = playerIds.length === 1;
                const url = single
                    ? `/admin/auctions/${auctionId}/pools/unassign`
                    : `/admin/auctions/${auctionId}/pools/bulk-unassign`;

                const res = await fetch(url, {
                    method: 'POST',
                    headers: this._headers(),
                    body: JSON.stringify(single ? { player_id: playerIds[0] } : { player_ids: playerIds }),
                });

                const data = await res.json().catch(() => null);

                if (!res.ok || !data?.success) {
                    this.toast(data?.message || 'Could not remove. Nothing was changed.', 'error');
                    return;
                }

                // Hide what the server confirms it removed, never what we asked for.
                const gone = data.affected || playerIds;
                this.playerRemoved = [...this.playerRemoved, ...gone];
                this.selectedPlayers = this.selectedPlayers.filter(id => !gone.includes(id));
                this.toast(data.message || 'Removed.');
            } catch (e) {
                this.toast('Network error — nothing was removed.', 'error');
            } finally {
                this.busy = false;
                this._resetConfirm();
            }
        },

        _headers() {
            return {
                'Content-Type': 'application/json',
                // Both required: X-Requested-With is what makes expectsJson() true, so
                // without it the controller answers with a redirect.
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
        },

        async runConfirm() {
            if (this.busy) return;

            // A held-back form: let it through now. No fetch, so the page reloads as it
            // always did — these actions rewrite the whole board.
            if (this.confirmBox.form) {
                const form = this.confirmBox.form;
                this._resetConfirm();
                form.submit();
                return;
            }

            // Removing players from a pool, rather than deleting pools.
            const playerIds = [...(this.confirmBox.playerIds || [])];
            if (playerIds.length) {
                await this._removePlayers(playerIds);
                return;
            }

            const ids = [...this.confirmBox.ids];
            if (!ids.length) return;

            this.busy = true;
            try {
                const single = ids.length === 1;
                const url = single
                    ? `/admin/auctions/${auctionId}/pools/${ids[0]}`
                    : `/admin/auctions/${auctionId}/pools/bulk`;

                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: this._headers(),
                    body: single ? null : JSON.stringify({ pool_ids: ids }),
                });

                const data = await res.json().catch(() => null);

                if (!res.ok || !data?.success) {
                    this.toast(data?.message || 'Could not delete. Nothing was changed.', 'error');
                    return;
                }

                // Hide exactly what the server says it removed, never what we asked it to.
                const gone = data.deleted || ids;
                this.removed = [...this.removed, ...gone];
                this.selected = this.selected.filter(id => !gone.includes(id));
                this.toast(data.message || 'Deleted.');
            } catch (e) {
                this.toast('Network error — nothing was deleted.', 'error');
            } finally {
                this.busy = false;
                this._resetConfirm();
            }
        },
    };
}
</script>
@endpush
@endsection
