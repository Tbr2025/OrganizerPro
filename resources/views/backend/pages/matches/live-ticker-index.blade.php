@extends('backend.layouts.app')

@section('title')
    {{ __('Live Match Ticker') }} | {{ config('app.name') }}
@endsection

@section('admin-content')
<div class="p-4 mx-auto sm:p-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                {{ __('Live Match Ticker (1920x1080)') }}
            </h2>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Select a match to display as a broadcast ticker overlay') }}
        </p>
    </div>

    <!-- Info Card -->
    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <div class="flex items-start gap-3">
            <iconify-icon icon="lucide:monitor" class="text-2xl text-blue-500 mt-0.5"></iconify-icon>
            <div>
                <h3 class="font-medium text-blue-800 dark:text-blue-200">{{ __('Broadcast Display') }}</h3>
                <p class="text-sm text-blue-600 dark:text-blue-300 mt-1">
                    {{ __('The ticker opens in a new window optimized for 1920x1080 resolution. Use it as an OBS browser source or display on a secondary monitor for live streaming.') }}
                </p>
                <ul class="mt-2 text-sm text-blue-600 dark:text-blue-300 list-disc list-inside">
                    <li>{{ __('Auto-refreshes every 5 seconds') }}</li>
                    <li>{{ __('Transparent background for overlay use') }}</li>
                    <li>{{ __('Press F11 for fullscreen mode') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Matches List -->
    <div class="overflow-hidden bg-white border border-gray-200 rounded-xl dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-5 py-4 text-left sm:px-6">
                            <span class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">{{ __('Match') }}</span>
                        </th>
                        <th class="px-5 py-4 text-left sm:px-6">
                            <span class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">{{ __('Tournament') }}</span>
                        </th>
                        <th class="px-5 py-4 text-left sm:px-6">
                            <span class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">{{ __('Date & Time') }}</span>
                        </th>
                        <th class="px-5 py-4 text-left sm:px-6">
                            <span class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">{{ __('Status') }}</span>
                        </th>
                        <th class="px-5 py-4 text-center sm:px-6">
                            <span class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">{{ __('Actions') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($matches as $match)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-5 py-4 sm:px-6">
                            <div class="flex items-center gap-3">
                                <div class="flex -space-x-2">
                                    @if($match->teamA?->logo)
                                        <img src="{{ asset('storage/' . $match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 object-cover">
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-brand-100 dark:bg-brand-900 flex items-center justify-center border-2 border-white dark:border-gray-800">
                                            <span class="text-xs font-bold text-brand-600 dark:text-brand-400">{{ substr($match->teamA->name ?? 'A', 0, 1) }}</span>
                                        </div>
                                    @endif
                                    @if($match->teamB?->logo)
                                        <img src="{{ asset('storage/' . $match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 object-cover">
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center border-2 border-white dark:border-gray-800">
                                            <span class="text-xs font-bold text-green-600 dark:text-green-400">{{ substr($match->teamB->name ?? 'B', 0, 1) }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-white">
                                        {{ $match->teamA->name ?? 'TBA' }} vs {{ $match->teamB->name ?? 'TBA' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $match->name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <span class="text-gray-600 dark:text-gray-300">{{ $match->tournament->name ?? '-' }}</span>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <div>
                                <p class="text-gray-800 dark:text-white">{{ \Carbon\Carbon::parse($match->match_date)->format('d M Y') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $match->start_time }} - {{ $match->end_time }}</p>
                            </div>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            @php
                                $statusColors = [
                                    'live' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'in_progress' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    'completed' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                ];
                                $statusColor = $statusColors[$match->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $statusColor }}">
                                @if($match->status === 'live' || $match->status === 'in_progress')
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-1.5 animate-pulse"></span>
                                @endif
                                {{ ucfirst($match->status ?? 'Unknown') }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center sm:px-6">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('public.live-ticker', $match) }}"
                                   target="_blank"
                                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 transition-colors">
                                    <iconify-icon icon="lucide:tv-2" class="text-lg"></iconify-icon>
                                    {{ __('Open Ticker') }}
                                </a>
                                <button onclick="copyPublicUrl('{{ url('/live/' . $match->id) }}')"
                                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                        title="Copy public URL">
                                    <iconify-icon icon="lucide:copy" class="text-lg"></iconify-icon>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center">
                            <iconify-icon icon="lucide:calendar-x" class="text-4xl text-gray-300 dark:text-gray-600 mb-2"></iconify-icon>
                            <p class="text-gray-500 dark:text-gray-400">{{ __('No matches available') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($matches->hasPages())
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-800">
            {{ $matches->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function copyPublicUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        // Show toast notification
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 transition-opacity duration-300';
        toast.textContent = 'Public URL copied!';
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    });
}
</script>
@endpush
@endsection
