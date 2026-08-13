@extends('backend.layouts.app')

@section('title', 'Network Test | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-4xl md:p-6">
    <x-breadcrumbs :breadcrumbs="['title' => 'Network Test', 'items' => [['label' => 'Auctions', 'url' => route('admin.auctions.index')]]]" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Network Test</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Rehearse the auction on the connection the hall actually has, before a hundred people are watching.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    {{-- The state, said plainly. An operator has to be able to answer "is this on?" at a
         glance, because a throttle left running looks exactly like the auction breaking. --}}
    <div class="rounded-xl border p-4 mb-6 {{ $active ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800' }}">
        @if($active)
            <div class="flex items-start gap-3">
                <span class="mt-0.5 w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse flex-shrink-0"></span>
                <div>
                    <p class="font-semibold text-amber-800 dark:text-amber-300">
                        Throttling this browser to {{ number_format($active) }} kbps
                    </p>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                        Only this browser is affected — nobody else's screens, and not the public wall.
                        @if($expiresAt)
                            It lifts by itself at
                            <strong>{{ \Carbon\Carbon::createFromTimestamp($expiresAt)->timezone(config('app.timezone'))->format('H:i') }}</strong>.
                        @endif
                    </p>
                </div>
            </div>
        @else
            <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 flex-shrink-0"></span>
                <p class="font-semibold text-gray-800 dark:text-gray-200">No limit — full speed</p>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.network-test.update') }}"
          class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Simulated speed</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {{-- No limit is the first option and the default, because it is the state the
                     system should spend nearly all of its life in. --}}
                <label class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 cursor-pointer transition
                              {{ $active ? 'border-gray-200 dark:border-gray-700' : 'border-emerald-400 bg-emerald-50 dark:bg-emerald-900/20' }}">
                    <input type="radio" name="kbps" value="0" {{ $active ? '' : 'checked' }}
                           class="text-emerald-600 focus:ring-emerald-500">
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">No limit</span>
                </label>

                @foreach($presets as $kbps => $label)
                    <label class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 cursor-pointer transition
                                  {{ $active === $kbps ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'border-gray-200 dark:border-gray-700' }}">
                        <input type="radio" name="kbps" value="{{ $kbps }}" {{ $active === $kbps ? 'checked' : '' }}
                               class="text-amber-600 focus:ring-amber-500">
                        <span class="text-sm text-gray-800 dark:text-gray-200">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            @error('kbps')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Lift automatically after
            </label>
            <div class="flex items-center gap-2">
                <input type="number" name="minutes" value="15" min="1" max="{{ $maxMinutes }}"
                       class="form-control w-28">
                <span class="text-sm text-gray-500">minutes</span>
            </div>
            {{-- Not optional, and not a convenience. --}}
            <p class="text-xs text-gray-400 mt-1">
                A throttle left on looks exactly like the auction breaking, and would be found at
                the worst possible moment. It always expires; {{ $maxMinutes }} minutes is the longest allowed.
            </p>
        </div>

        <div class="flex gap-2 pt-1">
            <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600 transition">
                Apply
            </button>
            <a href="{{ route('admin.auctions.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Back to auctions
            </a>
        </div>
    </form>

    {{-- What the number does and does not mean. A test that is trusted further than it
         deserves is worse than no test. --}}
    <div class="mt-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4 text-sm text-gray-600 dark:text-gray-400 space-y-2">
        <p class="font-semibold text-gray-800 dark:text-gray-200">What this does, and what it does not</p>
        <ul class="list-disc pl-5 space-y-1.5 text-xs leading-relaxed">
            <li>Holds back every page and API response in <strong>this browser</strong> by the time that
                many bytes would take on the chosen link. Nobody else is affected.</li>
            <li><strong>Writes are never held.</strong> Placing a bid, selling, closing a pool — none of
                them are slowed, because a test that broke the auction would be no test at all.</li>
            <li><strong>Static files are not throttled.</strong> JS, CSS and images are served by nginx
                and never reach PHP, so a real slow link is worse on first page load than this shows.
                Load the page once, then judge the auction screens.</li>
            <li>Open the organizer panel, a team screen and the LED wall with this on, and watch
                whether bids still land promptly. That is the question worth answering.</li>
        </ul>
    </div>
</div>
@endsection
