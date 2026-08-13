{{--
    Echo bootstrap for the live auction panels.

    Bidding used to reach the other screens only by a 2-second poll sitting behind a
    1-second feed cache, so a team could be bidding against a price roughly three seconds
    old. This subscribes the panels to the raise as it happens. The polls are untouched and
    remain the reconciliation path — if this never connects, every screen behaves exactly as
    it did before.

    Two deliberate choices:

    - SAME ORIGIN, not a CDN. This loaded pusher-js from js.pusher.com and laravel-echo from
      cdnjs, and at a venue that is two more things that have to work: an ad blocker or
      privacy extension recognising js.pusher.com takes the whole push layer down, and so
      does a hall connection that cannot reach a third-party CDN. The symptom is a permanent
      "Slow link" badge on a perfectly good network, with every screen quietly back on its
      poll. The files are 105KB together, they are already in node_modules, and served from
      our own domain they cannot be blocked or unreachable while the auction itself is
      loading. The CDN stays as a fallback for a deploy that forgot to copy them.

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
        <script src="{{ asset('js/push/pusher.min.js') }}"></script>
        <script src="{{ asset('js/push/echo.iife.js') }}"></script>
        {{-- Only if the same-origin copies are missing — a deploy that did not carry them.
             document.write because these have to be in place before the init below runs, and
             a dynamically appended script is not. --}}
        <script>
            if (typeof Pusher === 'undefined') {
                document.write('<script src="https://js.pusher.com/7.2/pusher.min.js"><\/script>');
            }
            if (typeof Echo === 'undefined') {
                document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.11.3/echo.iife.js"><\/script>');
            }
        </script>
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
                        console.warn('[auction] Echo failed to load from ' + '{{ asset('js/push/echo.iife.js') }}'
                            + ' and from the CDN fallback — live updates are off, polling only.');
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
