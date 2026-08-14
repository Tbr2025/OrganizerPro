@extends('backend.layouts.app')

@section('title', 'Sealed Bid Screen · ' . $auction->name . ' | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-5xl md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Sealed bid screen</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                What the LED wall shows while bidding is private. It appears on its own, for as long
                as the round runs.
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

    {{-- Said once: this screen puts itself up. There is no "show it now" button anywhere and
         somebody will look for one. --}}
    <div class="mb-5 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 text-xs text-blue-800 dark:text-blue-300">
        This screen appears <strong>automatically</strong> the moment a sealed round opens and comes
        down when it ends &mdash; there is nothing to switch on during the auction. It stands aside
        for the draw, which has its own screen.
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- ── The settings ── --}}
        <form action="{{ route('admin.auctions.sealed-screen.update', $auction) }}" method="POST"
              enctype="multipart/form-data"
              class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 space-y-5"
              x-data="{
                  heading: @js($auction->sealed_heading ?? ''),
                  message: @js($auction->sealed_message ?? ''),
                  preview: @js($auction->sealed_logo_url),
                  pick(event) {
                      const file = event.target.files?.[0];
                      if (file) this.preview = URL.createObjectURL(file);
                  }
              }">
            @csrf

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Logo</label>

                <div class="flex items-center gap-4">
                    <div class="w-24 h-24 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 flex items-center justify-center overflow-hidden shrink-0">
                        <template x-if="preview">
                            <img :src="preview" alt="" class="max-w-full max-h-full object-contain">
                        </template>
                        <template x-if="! preview">
                            <span class="text-3xl">&#128274;</span>
                        </template>
                    </div>

                    <div class="min-w-0">
                        <input type="file" name="sealed_logo" accept="image/*" @change="pick($event)"
                               class="block w-full text-xs text-gray-600 dark:text-gray-300
                                      file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                                      file:text-xs file:font-semibold file:bg-brand-500 file:text-white">
                        {{-- The fallback stated, so nobody uploads the same file twice. --}}
                        <p class="text-[11px] text-gray-400 mt-1.5 leading-relaxed">
                            Leave empty to use the auction's own logo, or the tournament's.
                            PNG with a transparent background reads best on a dark wall. Max 4MB.
                        </p>
                    </div>
                </div>

                @if($auction->sealed_logo)
                    <button form="remove-sealed-logo" class="mt-2 text-[11px] font-semibold text-red-600 dark:text-red-400 hover:underline">
                        Remove this logo
                    </button>
                @endif
            </div>

            <div>
                <label for="sealed_heading" class="block text-xs font-medium text-gray-500 mb-1">Headline</label>
                <input type="text" name="sealed_heading" id="sealed_heading" maxlength="80"
                       x-model="heading" class="form-control text-sm"
                       placeholder="Sealed Bid In Progress">
                <p class="text-[11px] text-gray-400 mt-1">Blank uses the default wording.</p>
            </div>

            <div>
                <label for="sealed_message" class="block text-xs font-medium text-gray-500 mb-1">Message</label>
                <input type="text" name="sealed_message" id="sealed_message" maxlength="160"
                       x-model="message" class="form-control text-sm"
                       placeholder="Amounts are revealed once every team has submitted">
                <p class="text-[11px] text-gray-400 mt-1">
                    One line. It sits under the headline and is read from the back of a hall, so keep
                    it short.
                </p>
            </div>

            <button class="px-4 py-2 rounded-lg bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600">
                Save
            </button>

            {{-- ── The preview ──
                 Inside the same Alpine scope so it moves as the fields are typed. It is a
                 likeness, not the wall: the real screen adds the round number and a countdown,
                 both of which only exist during a round. --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <div class="text-xs font-semibold text-gray-500 mb-2">Preview</div>

                <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700"
                     style="background:radial-gradient(circle at 50% 42%, rgba(88,28,135,0.72) 0%, rgba(2,6,23,0.94) 62%), #020617;">
                    <div class="py-8 px-6 text-center">
                        <div class="flex items-center justify-center gap-4 mb-4" style="min-height:64px;">
                            <template x-if="preview">
                                <img :src="preview" alt="" style="max-height:64px;max-width:120px;object-fit:contain;">
                            </template>
                            <template x-if="! preview">
                                <span style="font-size:48px;line-height:1;">&#128274;</span>
                            </template>
                        </div>

                        <div class="text-white font-black uppercase tracking-wide"
                             style="font-size:22px;line-height:1.1;"
                             x-text="heading || 'Sealed Bid In Progress'"></div>

                        <div class="mt-2" style="color:#e9d5ff;font-size:13px;"
                             x-text="message || 'Amounts are revealed once every team has submitted'"></div>

                        <div class="mt-3" style="color:rgba(233,213,255,0.55);font-size:10px;letter-spacing:0.3em;text-transform:uppercase;">
                            Round 1 of 2 &middot; 30s
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-gray-400 mt-2">
                    The round number and the countdown are added live &mdash; they are not settings.
                </p>
            </div>
        </form>

        {{-- ── Where it appears ── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Where this shows</h2>

            <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <li class="flex gap-2">
                    <span class="text-emerald-500 font-bold">&check;</span>
                    <span>
                        <strong class="text-gray-900 dark:text-white">The LED wall</strong> &mdash;
                        full screen, for the whole round.
                        <a href="{{ route('public.auction.live', $auction) }}" target="_blank"
                           class="text-brand-500 hover:underline">Open the wall</a>
                    </span>
                </li>
                <li class="flex gap-2">
                    <span class="text-emerald-500 font-bold">&check;</span>
                    <span>
                        <strong class="text-gray-900 dark:text-white">The ticker</strong> &mdash; as a
                        band across the strip, using the same wording. A ticker is one line read at a
                        glance, so it is never taken over.
                    </span>
                </li>
                <li class="flex gap-2">
                    <span class="text-gray-400 font-bold">&times;</span>
                    <span>
                        <strong class="text-gray-900 dark:text-white">Not during the draw.</strong>
                        A tie has its own screen with the tied teams on it, and covering that with a
                        logo would hide the part the hall is waiting for.
                    </span>
                </li>
            </ul>

            <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1.5">What it never shows</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    No amounts and no team names, whatever is typed above. A sealed round exists to keep
                    those private until every team has submitted, and the wall is the one place they must
                    not appear early &mdash; the server withholds them rather than trusting the screen.
                </p>
            </div>
        </div>
    </div>

    {{-- Its own form so the Remove button is not a submit inside the settings form, which would
         carry the unsaved fields with it. --}}
    <form id="remove-sealed-logo" method="POST" class="hidden"
          action="{{ route('admin.auctions.sealed-screen.logo.destroy', $auction) }}">
        @csrf @method('DELETE')
    </form>
</div>
@endsection
