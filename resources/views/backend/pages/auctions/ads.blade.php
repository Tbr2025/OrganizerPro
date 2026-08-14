@extends('backend.layouts.app')

@section('title', 'Ads & Sponsors · ' . $auction->name . ' | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-5xl md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Ads &amp; Sponsors</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Artwork for the LED wall and the ticker, shown between lots.
            </p>
        </div>
        <a href="{{ route('admin.auctions.show', $auction) }}"
           class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            Back to auction
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @error('images.*')
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
            {{ $message }}
        </div>
    @enderror

    {{-- Two kinds, said plainly, because the difference is where the image is DRAWN and that is
         not something an organizer can infer from an upload box. --}}
    @foreach([
        ['kind' => 'slide', 'title' => 'Full slides', 'items' => $slides,
         'blurb' => 'Take a whole turn of the reel, between the player cards. Landscape artwork reads best — the wall is 16:9.'],
        ['kind' => 'sponsor', 'title' => 'Sponsor strip', 'items' => $sponsors,
         'blurb' => 'Ride the strip along the bottom of every slide. Logos on a transparent or dark background work best at this size.'],
    ] as $group)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 mb-6">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $group['title'] }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-4">{{ $group['blurb'] }}</p>

            <form action="{{ route('admin.auctions.ads.store', $auction) }}" method="POST"
                  enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 mb-5">
                @csrf
                <input type="hidden" name="kind" value="{{ $group['kind'] }}">

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Images</label>
                    {{-- Several at once: sponsors arrive as a folder, not one at a time. --}}
                    <input type="file" name="images[]" accept="image/*" multiple required
                           class="text-sm text-gray-600 dark:text-gray-300">
                </div>

                @if($group['kind'] === 'slide')
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Caption (optional)</label>
                        <input type="text" name="caption" maxlength="120"
                               class="form-control text-sm w-56" placeholder="e.g. Official Partner">
                    </div>
                @endif

                <button class="px-3 py-2 rounded-lg bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600">
                    Upload
                </button>
            </form>

            @if($group['items']->isEmpty())
                <p class="text-sm text-gray-400">Nothing uploaded yet.</p>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($group['items'] as $ad)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden {{ $ad->is_active ? '' : 'opacity-50' }}">
                            <div class="bg-gray-100 dark:bg-gray-900 aspect-video flex items-center justify-center">
                                <img src="{{ $ad->url }}" alt="" class="max-w-full max-h-full object-contain">
                            </div>

                            <form action="{{ route('admin.auctions.ads.update', [$auction, $ad]) }}" method="POST"
                                  class="p-2.5 space-y-2">
                                @csrf @method('PUT')

                                @if($ad->kind === 'slide')
                                    <input type="text" name="caption" value="{{ $ad->caption }}" maxlength="120"
                                           class="form-control !py-1 !text-xs w-full" placeholder="Caption">
                                @endif

                                <div class="flex items-center gap-2">
                                    <input type="number" name="sort_order" value="{{ $ad->sort_order }}" min="0" max="999"
                                           class="form-control !py-1 !text-xs w-16" title="Order">
                                    <label class="flex items-center gap-1 text-[11px] text-gray-500 cursor-pointer">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($ad->is_active)
                                               class="h-3.5 w-3.5 rounded border-gray-300">
                                        On
                                    </label>
                                    <button class="ml-auto px-2 py-1 rounded bg-gray-800 text-white text-[11px] font-semibold hover:bg-gray-700">
                                        Save
                                    </button>
                                </div>
                            </form>

                            <form action="{{ route('admin.auctions.ads.destroy', [$auction, $ad]) }}" method="POST"
                                  onsubmit="return confirm('Remove this artwork? The file is deleted from the server.')"
                                  class="px-2.5 pb-2.5">
                                @csrf @method('DELETE')
                                <button class="w-full px-2 py-1 rounded border border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 text-[11px] font-semibold hover:bg-red-50 dark:hover:bg-red-900/20">
                                    Remove
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
