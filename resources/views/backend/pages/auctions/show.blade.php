@extends('backend.layouts.app')

@section('title', 'Auction Details | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 max-w-3xl mx-auto md:p-6">
        <x-breadcrumbs :breadcrumbs="[['label' => 'Auctions', 'url' => route('admin.auctions.index')], ['label' => 'Details']]" />

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow p-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">{{ $auction->name }}</h2>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Organization</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        {{ $auction->organization->name ?? 'N/A' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tournament</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        {{ $auction->tournament->name ?? 'N/A' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        {{ $auction->start_at ? $auction->start_at->format('Y-m-d') : '-' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        {{ $auction->end_at ? $auction->end_at->format('Y-m-d') : '-' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Base Price</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        {{ $auction->base_price ?? '-' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Max Bid Per Player</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        {{ $auction->max_bid_per_player ?? '-' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Max Budget Per Team</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                        {{ $auction->max_budget_per_team ?? '-' }}
                    </dd>
                </div>
            </dl>

            <div class="mt-6">
                <a href="{{ route('admin.auctions.index') }}"
                    class="inline-block px-4 py-2 bg-gray-300 dark:bg-gray-700 rounded-md text-gray-700 dark:text-white hover:bg-gray-400 dark:hover:bg-gray-600">
                    Back to Auctions
                </a>
            </div>
        </div>
    </div>
@endsection
