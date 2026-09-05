{{--
    Fast Auction's shell. Standalone on purpose — no @extends.

    The whole point of this module is that it does NOT extend backend.layouts.app, which pulls in
    resources/js/app.js (1.3 MB) and resources/css/app.css (384 KB) plus an Alpine sidebar, so a
    team manager on a venue phone downloaded all of it to render a bidding screen. The public wall
    (public/auction/live.blade.php) set the precedent for escaping that; this adds a manifest, so
    the assets are still hashed and cache-busted by Vite.

    Served by Laravel rather than as static files under public/, for three reasons: nginx here has
    `index index.php` only, so a static index.html would never be served without a config change
    that lives outside this repo; the session cookie and CSRF token come for free; and the first
    snapshot is inlined below, so the first paint costs no requests.

    There is a test asserting this file stays lean. If you are about to add @extends for a nav bar,
    that test is why.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Fast Auction')</title>
    @vite(['resources/css/fast-auction.css', 'resources/js/fast-auction/main.js'])
</head>
<body class="bg-slate-950">
    <div id="fast-auction" data-screen="@yield('screen')"></div>

    {{-- The snapshot, the URLs and the role context, written once by the server. A cold bundle
         does not have to fetch a CSRF token and then a snapshot before it can draw anything. --}}
    <script type="application/json" id="fast-auction-boot">@json($boot)</script>

    {{-- The way back to the classic screen. Each Fast view fills this in, because only it
         knows which classic page it is the counterpart of. --}}
    @yield('switch')

    {{-- Reused verbatim: same-origin pusher-js and laravel-echo with a CDN fallback, config()
         rather than env() so `config:cache` cannot silently kill push, and the
         window.auctionChannel() helper the realtime module expects. --}}
    @include('backend.pages.auction.partials.echo-init')
</body>
</html>
