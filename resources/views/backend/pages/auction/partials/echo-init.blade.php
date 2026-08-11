{{--
    Echo bootstrap for the live auction panels.

    Bidding used to reach the other screens only by a 2-second poll sitting behind a
    1-second feed cache, so a team could be bidding against a price roughly three seconds
    old. This subscribes the panels to the raise as it happens. The polls are untouched and
    remain the reconciliation path — if this never connects, every screen behaves exactly as
    it did before.

    Two deliberate choices:

    - CDN, not the Vite bundle. `laravel-echo` and `pusher-js` are in package.json but
      imported nowhere in resources/js, and public/auction/live.blade.php already proves
      these exact CDN versions work in production. Introducing a bundle step days before a
      live auction buys nothing.

    - config(), not env(). The wall reads env('PUSHER_APP_KEY') directly, which returns null
      the moment anyone runs `php artisan config:cache` — a silent failure that would take
      the whole push layer down with no error anywhere.
--}}
@once
    @php
        $pusherKey = config('broadcasting.connections.pusher.key');
        $pusherCluster = config('broadcasting.connections.pusher.options.cluster');
        $pushEnabled = config('broadcasting.default') === 'pusher' && ! empty($pusherKey);
    @endphp

    @if ($pushEnabled)
        <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.11.3/echo.iife.js"></script>
        <script>
            /**
             * Subscribe to an auction's public channel, or return null.
             *
             * Never throws and never retries in a loop: a panel that cannot reach Pusher has
             * a working 2-second poll and must carry on quietly. (backend/pages/auctions/
             * show.blade.php retries window.Echo every 100ms forever, which is the shape
             * being avoided here.)
             */
            window.auctionChannel = function (auctionId) {
                try {
                    /*
                     * Say so, loudly, when Echo is missing.
                     *
                     * This threw and was caught silently, so a blocked CDN script and a
                     * working connection looked identical from the outside — the page just
                     * carried on polling with nothing to explain why. An ad blocker or
                     * privacy extension blocking js.pusher.com is the usual cause.
                     */
                    if (typeof Echo === 'undefined') {
                        console.warn('[auction] Echo failed to load (js.pusher.com or cdnjs '
                            + 'blocked by an extension?) — polling only.');
                        return null;
                    }

                    if (!window.Echo) {
                        window.Echo = new Echo({
                            broadcaster: 'pusher',
                            key: @json($pusherKey),
                            cluster: @json($pusherCluster),
                            forceTLS: true,
                        });

                        // The connection reports itself, so "push or polling?" is answerable
                        // from the console rather than by reading WebSocket frames.
                        const conn = window.Echo.connector.pusher.connection;
                        conn.bind('connected', () => console.info('[auction] LIVE — pusher connected (@json($pusherCluster))'));
                        conn.bind('unavailable', () => console.warn('[auction] pusher unavailable — polling only.'));
                        conn.bind('failed', () => console.warn('[auction] pusher failed — polling only.'));
                        conn.bind('disconnected', () => console.warn('[auction] pusher disconnected — polling only.'));
                    }

                    return window.Echo.channel('auction.' + auctionId);
                } catch (e) {
                    console.warn('[auction] live updates unavailable, falling back to polling:', e);
                    return null;
                }
            };
        </script>
    @else
        <script>
            // Broadcasting is not configured for this environment (locally BROADCAST_DRIVER
            // is `log` with blank keys). Polling carries everything; say so once rather than
            // leaving a silent no-op.
            window.auctionChannel = function () { return null; };
            console.info('[auction] push disabled — polling only.');
        </script>
    @endif
@endonce
