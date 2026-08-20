/**
 * Push first, reconcile slowly.
 *
 * The old screens poll — every 2 s when the socket is down or a sealed round is live, every 15 s
 * otherwise. This keeps the same safety net but drives the display from the events, because on
 * `ap2` a broadcast lands in ~4 ms warm and a poll cannot beat that.
 *
 * `window.auctionChannel` comes from the existing echo-init partial, which is reused rather than
 * reimplemented: it serves pusher-js and laravel-echo SAME ORIGIN from public/js/push, with a CDN
 * fallback, because at a venue an ad blocker or a restricted network that cannot reach
 * js.pusher.com takes the whole push layer down and every screen silently falls back to polling.
 */

/** How often to reconcile when push is healthy. The old screen's figure, kept deliberately. */
const RECONCILE_MS = 15000;

/** And when it is not — or when a sealed round is live, which push does not fully cover. */
const RECONCILE_FAST_MS = 2000;

export function connect({ auctionId, onFrame, reconcile, isSealedActive, silentWhenHealthy = false }) {
    let timer = null;
    let connected = false;

    const needsFast = () => !connected || Boolean(isSealedActive?.());

    /*
     * `silentWhenHealthy` stops the timer entirely while push is up — what the classic wall and
     * ticker do, and for a good reason: recovery does not need a heartbeat. pusher-js reconnects
     * on its own, the `connected` binding refetches, and returning to visibility or regaining the
     * network refetches too. A periodic request adds load to fix a delay the events have already
     * removed.
     *
     * Off by default. The bidding screen and the panel keep their 15-second reconcile because
     * their classic counterparts do: they carry per-team money and control state, and a screen
     * that has silently missed a nudge is worse there than one extra request a minute.
     */
    function schedule() {
        clearTimeout(timer);

        if (silentWhenHealthy && !needsFast()) {
            return;
        }

        timer = setTimeout(tick, needsFast() ? RECONCILE_FAST_MS : RECONCILE_MS);
    }

    async function tick() {
        try {
            await reconcile('timer');
        } finally {
            // Chained, not setInterval: a slow response must not queue a second request behind
            // itself, which is how a struggling box gets pushed over.
            schedule();
        }
    }

    const channel = window.auctionChannel?.(auctionId) ?? null;

    if (channel) {
        /*
         * `bid.raised` is the only frame applied directly. It carries the price, the leader and
         * the whole timer state, and it is ordered by a monotonic bid_id so a late-arriving older
         * frame can be dropped rather than rewinding the price in front of the room.
         */
        channel.listen('.bid.raised', (e) => onFrame('bid.raised', e));

        /*
         * Everything else is a NUDGE — refetch, never read the payload.
         *
         * `player.onbid` in particular is broadcast by two different event classes with
         * incompatible shapes depending on which organizer button was pressed, so reading it
         * would work until the day somebody used the other button.
         */
        ['.player-on-sold', '.player.onbid', '.sealed.changed', '.board.changed'].forEach((name) => {
            channel.listen(name, () => reconcile(name));
        });

        // The status event lives on a different channel, which is easy to miss and silent when
        // missed: the screen simply never learns the auction was paused or restarted.
        window.Echo?.channel(`auction.public.${auctionId}`)
            ?.listen('.auction.status', () => reconcile('auction.status'));

        window.Echo?.connector?.pusher?.connection?.bind('connected', () => {
            connected = true;
            // Reconnecting is exactly when a missed event has to be healed.
            reconcile('reconnected');
            schedule();
        });

        window.Echo?.connector?.pusher?.connection?.bind('unavailable', () => {
            connected = false;
            schedule();
        });

        window.Echo?.connector?.pusher?.connection?.bind('disconnected', () => {
            connected = false;
            schedule();
        });
    }

    /*
     * A woken phone must not wait fifteen seconds. The existing bidding page has no
     * visibilitychange handler, which is why a manager who pockets their phone comes back to a
     * stale price; the wall and the ticker both do.
     */
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            reconcile('refocus');
            schedule();
        }
    });

    window.addEventListener('online', () => {
        reconcile('online');
        schedule();
    });

    schedule();

    return {
        isConnected: () => connected,
        stop: () => clearTimeout(timer),
    };
}
