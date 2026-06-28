@extends('backend.layouts.app')

@section('title', 'Auction Pools | ' . $auction->name)

@section('admin-content')
<div class="px-4 py-6 max-w-7xl mx-auto" id="pools-app"
     data-store-url="{{ route('admin.auctions.pools.store', $auction) }}"
     data-budget-url="{{ route('admin.auctions.budgets.allocate', $auction) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Auction Pools &amp; Lots</h1>
            <p class="text-sm text-gray-500">{{ $auction->name }}</p>
        </div>
        <a href="{{ route('admin.auctions.show', $auction) }}" class="text-sm text-indigo-600 hover:underline">← Back to auction</a>
    </div>

    {{-- Create pool --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-6">
        <h2 class="font-semibold mb-3 text-gray-800 dark:text-gray-200">Create a pool</h2>
        <form id="create-pool-form" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Pool name</label>
                <input name="name" required placeholder="e.g. Pool A" class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Capacity</label>
                <input name="capacity" type="number" min="1" placeholder="50" class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Order mode</label>
                <select name="order_mode" class="mt-1 w-full border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
                    @foreach($orderModes as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="inline-flex items-center text-xs text-gray-600 dark:text-gray-400 mb-2">
                    <input type="checkbox" name="is_unsold_pool" value="1" class="mr-1"> Unsold bucket
                </label>
                <button class="w-full bg-indigo-600 text-white rounded px-3 py-2 text-sm hover:bg-indigo-700">Create</button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Pools --}}
        <div class="lg:col-span-2 space-y-4">
            @forelse($auction->pools as $pool)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4" x-data="{ editing: false }">
                    <div class="flex items-center justify-between mb-2">
                        <div x-show="!editing">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $pool->name }}</span>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                {{ $orderModes[$pool->order_mode] ?? $pool->order_mode }}
                            </span>
                            @if($pool->is_unsold_pool)
                                <span class="ml-1 text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">unsold</span>
                            @endif
                            <span class="ml-2 text-xs text-gray-500">{{ $pool->players->count() }}{{ $pool->capacity ? '/'.$pool->capacity : '' }} players</span>
                        </div>
                        <div class="flex gap-2" x-show="!editing">
                            <button @click="editing = true" class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded px-2 py-1 hover:bg-gray-200">Edit</button>
                            <button class="draw-lots-btn text-xs bg-emerald-600 text-white rounded px-2 py-1 hover:bg-emerald-700"
                                    data-url="{{ route('admin.auctions.pools.draw-lots', [$auction, $pool]) }}">Draw lots</button>
                            <button class="delete-pool-btn text-xs bg-red-100 text-red-700 rounded px-2 py-1 hover:bg-red-200"
                                    data-url="{{ route('admin.auctions.pools.destroy', [$auction, $pool]) }}">Delete</button>
                        </div>
                    </div>

                    {{-- Inline edit (name / capacity / order mode) --}}
                    <form class="pool-edit-form flex flex-wrap items-end gap-2 mb-3" x-show="editing" x-cloak
                          data-url="{{ route('admin.auctions.pools.update', [$auction, $pool]) }}">
                        <div>
                            <label class="block text-[10px] text-gray-400">Name</label>
                            <input name="name" value="{{ $pool->name }}" class="border rounded px-2 py-1 text-sm dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400">Capacity</label>
                            <input name="capacity" type="number" min="1" value="{{ $pool->capacity }}" class="w-20 border rounded px-2 py-1 text-sm dark:bg-gray-700 dark:border-gray-600">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400">Order</label>
                            <select name="order_mode" class="border rounded px-2 py-1 text-sm dark:bg-gray-700 dark:border-gray-600">
                                @foreach($orderModes as $val => $label)
                                    <option value="{{ $val }}" {{ $pool->order_mode === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="text-xs bg-indigo-600 text-white rounded px-3 py-1.5 hover:bg-indigo-700">Save</button>
                        <button type="button" @click="editing = false" class="text-xs text-gray-500 px-2 py-1.5">Cancel</button>
                    </form>
                    <ol class="text-sm divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($pool->players->sortBy('lot_number') as $ap)
                            <li class="py-1 flex justify-between">
                                <span>{{ $ap->lot_number ? '#'.$ap->lot_number : '—' }} {{ $ap->player->name ?? 'Player #'.$ap->player_id }}</span>
                                <span class="text-xs text-gray-400">{{ $ap->status }}</span>
                            </li>
                        @endforeach
                        @if($pool->players->isEmpty())
                            <li class="py-2 text-xs text-gray-400">No players assigned.</li>
                        @endif
                    </ol>
                </div>
            @empty
                <p class="text-sm text-gray-500">No pools yet — create one above.</p>
            @endforelse
        </div>

        {{-- Unassigned players + budgets --}}
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <h2 class="font-semibold mb-3 text-gray-800 dark:text-gray-200">Unassigned players ({{ $unassigned->count() }})</h2>
                @if($auction->pools->isNotEmpty() && $unassigned->isNotEmpty())
                <form id="assign-form">
                    <select id="assign-pool" class="w-full border rounded px-3 py-2 mb-2 text-sm dark:bg-gray-700 dark:border-gray-600">
                        @foreach($auction->pools as $pool)
                            <option value="{{ route('admin.auctions.pools.assign', [$auction, $pool]) }}">{{ $pool->name }}</option>
                        @endforeach
                    </select>
                    <div class="max-h-72 overflow-y-auto border rounded p-2 dark:border-gray-600 mb-2">
                        @foreach($unassigned as $ap)
                            <label class="flex items-center text-sm py-0.5">
                                <input type="checkbox" name="ids" value="{{ $ap->id }}" class="mr-2">
                                {{ $ap->player->name ?? 'Player #'.$ap->player_id }}
                            </label>
                        @endforeach
                    </div>
                    <button class="w-full bg-indigo-600 text-white rounded px-3 py-2 text-sm hover:bg-indigo-700">Assign to pool</button>
                </form>
                @else
                    <p class="text-xs text-gray-400">{{ $auction->pools->isEmpty() ? 'Create a pool first.' : 'All players assigned.' }}</p>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <h2 class="font-semibold mb-3 text-gray-800 dark:text-gray-200">Per-team budgets</h2>
                <p class="text-xs text-gray-400 mb-2">Blank = uniform cap ({{ number_format((float) ($auction->max_budget_per_team ?? 0)) }}).</p>
                <form id="budget-form" class="space-y-2">
                    @foreach($teams as $team)
                        @php $alloc = $auction->teamBudgets->firstWhere('actual_team_id', $team->id); @endphp
                        <div class="flex items-center gap-2">
                            <span class="flex-1 text-sm truncate">{{ $team->name }}</span>
                            <input type="number" min="0" step="0.01" data-team="{{ $team->id }}"
                                   value="{{ $alloc->budget ?? '' }}"
                                   class="budget-input w-28 border rounded px-2 py-1 text-sm dark:bg-gray-700 dark:border-gray-600">
                        </div>
                    @endforeach
                    @if($teams->isNotEmpty())
                        <button class="w-full bg-indigo-600 text-white rounded px-3 py-2 text-sm hover:bg-indigo-700 mt-2">Save budgets</button>
                    @else
                        <p class="text-xs text-gray-400">No teams in this auction's tournament yet.</p>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const app = document.getElementById('pools-app');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const post = (url, body, method = 'POST') => fetch(url, {
        method,
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body ? JSON.stringify(body) : null,
    }).then(r => r.json());

    document.getElementById('create-pool-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const f = new FormData(this);
        post(app.dataset.storeUrl, {
            name: f.get('name'), capacity: f.get('capacity') || null,
            order_mode: f.get('order_mode'), is_unsold_pool: f.get('is_unsold_pool') ? 1 : 0,
        }).then(r => r.success && location.reload());
    });

    document.querySelectorAll('.pool-edit-form').forEach(f => f.addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        post(this.dataset.url, {
            name: fd.get('name'),
            capacity: fd.get('capacity') || null,
            order_mode: fd.get('order_mode'),
        }, 'PUT').then(r => r.success && location.reload());
    }));

    document.querySelectorAll('.draw-lots-btn').forEach(b => b.addEventListener('click', function () {
        post(this.dataset.url).then(r => r.success && location.reload());
    }));

    document.querySelectorAll('.delete-pool-btn').forEach(b => b.addEventListener('click', function () {
        if (!confirm('Delete this pool? Players return to unassigned.')) return;
        post(this.dataset.url, null, 'DELETE').then(r => r.success && location.reload());
    }));

    document.getElementById('assign-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const url = document.getElementById('assign-pool').value;
        const ids = [...this.querySelectorAll('input[name="ids"]:checked')].map(c => parseInt(c.value));
        if (!ids.length) return;
        post(url, { auction_player_ids: ids }).then(r => r.success && location.reload());
    });

    document.getElementById('budget-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const budgets = [...this.querySelectorAll('.budget-input')]
            .filter(i => i.value !== '')
            .map(i => ({ actual_team_id: parseInt(i.dataset.team), budget: parseFloat(i.value) }));
        post(app.dataset.budgetUrl, { budgets }).then(r => r.success && alert('Budgets saved.'));
    });
})();
</script>
@endpush
