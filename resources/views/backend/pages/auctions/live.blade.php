@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="p-6 bg-white shadow rounded-lg">
    <h1 class="text-2xl font-bold mb-4">{{ $auction->name }} – Live Bidding</h1>

    <div id="current-player" class="mb-6">
        <h2 class="text-xl font-semibold">Current Player</h2>
        <div id="player-info" class="p-4 bg-gray-100 rounded">
            Waiting for next player...
        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold">Players Queue</h3>
        <table class="min-w-full bg-white border mt-2">
            <thead>
                <tr>
                    <th class="px-4 py-2 border">Name</th>
                    <th class="px-4 py-2 border">Base Price</th>
                    <th class="px-4 py-2 border">Current Price</th>
                    <th class="px-4 py-2 border">Status</th>
                </tr>
            </thead>
            <tbody id="players-table">
                @foreach($players as $p)
                    <tr>
                        <td class="px-4 py-2 border">{{ $p->player->name }}</td>
                        <td class="px-4 py-2 border">₹{{ number_format($p->base_price) }}</td>
                        <td class="px-4 py-2 border">₹{{ number_format($p->current_price) }}</td>
                        <td class="px-4 py-2 border">{{ ucfirst($p->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script src="{{ mix('js/app.js') }}"></script>
<script>
    Echo.channel('auction.{{ $auction->id }}')
        .listen('.bid.placed', (e) => {
            console.log('Bid update:', e);
            // TODO: Update DOM with latest bid data
        });
</script>
@endsection
