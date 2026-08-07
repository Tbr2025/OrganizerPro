@extends('backend.layouts.app')

@section('title')
    {{ __('Auction Broadcast Screens') }} | {{ config('app.name') }}
@endsection

{{--
    Class strings are copied verbatim from matches/live-ticker-index.blade.php on purpose:
    Tailwind v4 scans resources/views and any first-use class is missing from production
    CSS until `npm run build` runs on the server, so reusing already-built classes means
    this page renders correctly the moment it is deployed.
--}}
@section('admin-content')
<div class="p-4 mx-auto sm:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                {{ __('Auction Broadcast Screens') }}
            </h2>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Open the stream ticker or the LED wall for any auction.') }}
        </p>
    </div>

    <!-- Info Card -->
    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <div class="flex items-start gap-3">
            <iconify-icon icon="lucide:monitor" class="text-2xl text-blue-500 mt-0.5"></iconify-icon>
            <div>
                <h3 class="font-medium text-blue-800 dark:text-blue-200">{{ __('Broadcast Display') }}</h3>
                <p class="text-sm text-blue-600 dark:text-blue-300 mt-1">
                    {{ __('The stream ticker is a 1920x1080 overlay with a transparent background — add it to OBS as a browser source. The LED wall is a full-screen display for a projector or hall screen.') }}
                </p>
                <ul class="mt-2 text-sm text-blue-600 dark:text-blue-300 list-disc list-inside">
                    <li>{{ __('Both refresh themselves every 2 seconds') }}</li>
                    <li>{{ __('Neither needs a login, so they are safe to open on a shared machine') }}</li>
                    <li>{{ __('Press F for fullscreen, R to reload') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Auctions List -->
    <div class="overflow-hidden bg-white border border-gray-200 rounded-xl dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900/50">
                        <th class="px-5 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">{{ __('Auction') }}</th>
                        <th class="px-5 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">{{ __('Tournament') }}</th>
                        <th class="px-5 py-3 text-xs font-medium text-left text-gray-500 uppercase dark:text-gray-400">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-xs font-medium text-right text-gray-500 uppercase dark:text-gray-400">{{ __('Screens') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($auctions as $auction)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                            <td class="px-5 py-4 text-sm font-medium text-gray-800 dark:text-white/90">
                                {{ $auction->name }}
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $auction->tournament->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4">
                                @php
                                    $isLive = in_array($auction->status, ['running', 'paused'], true);
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full
                                    {{ $isLive
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                    @if($auction->status === 'running')
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                    @endif
                                    {{ ucfirst($auction->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('public.auction.ticker', $auction) }}" target="_blank"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                        <iconify-icon icon="lucide:tv"></iconify-icon>
                                        {{ __('Stream Ticker') }}
                                    </a>
                                    <a href="{{ route('public.auction.live', $auction) }}" target="_blank"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 border border-gray-300 rounded-lg dark:text-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <iconify-icon icon="lucide:presentation"></iconify-icon>
                                        {{ __('LED Wall') }}
                                    </a>
                                    <button type="button"
                                            onclick="copyTickerUrl('{{ route('public.auction.ticker', $auction) }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 border border-gray-300 rounded-lg dark:text-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800"
                                            title="{{ __('Copy the ticker URL for OBS') }}">
                                        <iconify-icon icon="lucide:copy"></iconify-icon>
                                        {{ __('Copy URL') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                                {{ __('No auctions yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($auctions->hasPages())
        <div class="mt-4">{{ $auctions->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function copyTickerUrl(url) {
        navigator.clipboard.writeText(url).then(() => {
            const toast = document.createElement('div');
            toast.textContent = '{{ __('Ticker URL copied') }}';
            toast.className = 'fixed bottom-6 right-6 z-50 px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg shadow-lg';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        });
    }
</script>
@endpush
