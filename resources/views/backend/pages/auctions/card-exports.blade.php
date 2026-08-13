@extends('backend.layouts.app')

@section('title', 'Poster Archives · ' . $auction->name . ' | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-6xl md:p-6" x-data>
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Poster Archives</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Every export this auction has produced. Download one again, stop one that is still
                running, or delete the ones you are finished with.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.auctions.pools.index', $auction) }}"
               class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Pools
            </a>
            <a href="{{ route('admin.auctions.show', $auction) }}"
               class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold hover:bg-gray-700 transition">
                Back to auction
            </a>
        </div>
    </div>

    {{-- A zip of 300 posters is tens of megabytes on the same box that serves the auction, so
         they do not live forever. Saying so here is the difference between "deleted" and
         "disappeared". --}}
    <div class="mb-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-2.5 text-xs text-blue-800 dark:text-blue-300">
        Archives are swept an hour after they are created, because a zip of three hundred posters
        is tens of megabytes on the machine running the auction. Download anything you want to keep.
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        @forelse($exports as $export)
            @php
                $badge = match ($export->status) {
                    'done' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                    'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                    'cancelled' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                    default => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                };
            @endphp
            <div class="flex items-center gap-4 px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0 flex-wrap">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ $export->filename ?: 'export' }}
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $badge }}">
                            {{ $export->status }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-2 flex-wrap">
                        {{-- What it drew from, because "which design was this?" is the first thing
                             asked of an archive a day later. --}}
                        <span>{{ $export->tournamentTemplate?->name ?? 'LED wall card' }}</span>
                        <span>&middot;</span>
                        <span class="tabular-nums">{{ $export->completed }} of {{ $export->total }}</span>
                        @if($export->failed)
                            <span class="text-amber-600 dark:text-amber-400">{{ $export->failed }} failed</span>
                        @endif
                        <span>&middot;</span>
                        <span>{{ $export->created_at?->diffForHumans() }}</span>
                    </div>
                    @if($export->message)
                        <p class="text-[11px] text-gray-400 mt-1">{{ $export->message }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($export->status === 'done' && $export->path)
                        <a href="{{ route('admin.auctions.cards.export.download', [$auction, $export->token]) }}"
                           class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition">
                            Download
                        </a>
                    @elseif(! $export->isFinished())
                        {{-- Stop, not delete: the job is mid-render and has to see the change.
                             Deleting the row would leave it writing into a zip nothing points at. --}}
                        <button type="button"
                                @click="fetch('{{ route('admin.auctions.cards.export.cancel', [$auction, $export->token]) }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' }
                                }).then(() => window.location.reload())"
                                class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-xs font-semibold hover:bg-amber-600 transition">
                            Stop
                        </button>
                    @endif

                    <form method="POST" action="{{ route('admin.auctions.cards.export.destroy', [$auction, $export->token]) }}"
                          onsubmit="return confirm('Delete this archive? The zip is removed from the server.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg border border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-4 py-12 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No posters have been exported for this auction yet.
                </p>
                <a href="{{ route('admin.auctions.pools.index', $auction) }}"
                   class="inline-block mt-3 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                    Generate some from the pools screen →
                </a>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $exports->links() }}</div>
</div>
@endsection
