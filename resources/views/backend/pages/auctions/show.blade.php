@extends('backend.layouts.app')

@section('title', 'View Auction | ' . $auction->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Auction Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Overview for: <span class="font-semibold">{{ $auction->name }}</span></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.auctions.edit', $auction) }}" class="btn btn-secondary">Edit Configuration</a>
            <a href="{{ route('admin.auction.organizer.panel', $auction) }}" class="btn btn-success inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" /></svg>
                Go to Live Panel
            </a>
        </div>
    </div>

    {{-- Info Bar --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 text-center">
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow"><div class="text-sm font-medium text-gray-500">Status</div><div class="mt-1 text-xl font-semibold"><span class="badge-{{ $auction->status === 'live' ? 'success' : 'secondary' }}">{{ ucfirst($auction->status) }}</span></div></div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow"><div class="text-sm font-medium text-gray-500">Tournament</div><div class="mt-1 text-xl font-semibold">{{ $auction->tournament->name ?? 'N/A' }}</div></div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow"><div class="text-sm font-medium text-gray-500">Player Pool</div><div class="mt-1 text-xl font-semibold">{{ $auction->auctionPlayers->count() }} Players</div></div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow"><div class="text-sm font-medium text-gray-500">Team Budget</div><div class="mt-1 text-xl font-semibold">{{ number_format($auction->max_budget_per_team) }}</div></div>
    </div>

    {{-- Player Pool List --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Auction Player Pool</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="p-3 text-left">Player</th>
                        <th class="p-3 text-left">Role</th>
                        <th class="p-3 text-left">Base Price</th>
                        <th class="p-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($auction->auctionPlayers as $auctionPlayer)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <td class="p-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $auctionPlayer->player->image_path ? Storage::url($auctionPlayer->player->image_path) : 'https://ui-avatars.com/api/?name='.urlencode($auctionPlayer->player->name) }}" class="w-10 h-10 rounded-full object-cover">
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $auctionPlayer->player->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $auctionPlayer->player->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">{{ $auctionPlayer->player->playerType?->type ?? 'N/A' }}</td>
                        <td class="p-3 font-semibold">{{ number_format($auctionPlayer->base_price) }}</td>
                        <td class="p-3"><span class="badge-{{ $auctionPlayer->status === 'sold' ? 'success' : ($auctionPlayer->status === 'live' ? 'info' : 'secondary') }}">{{ ucfirst($auctionPlayer->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center py-10 text-gray-500">No players have been added to this auction pool yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection