/**
 * Instant updates between screens on the SAME machine, with no server in the middle.
 *
 * A price step on the panel reaches a wall in another window of the same browser in well under a
 * millisecond, because it never leaves the machine — no TLS handshake to Pusher, no round trip,
 * nothing to be late. That is the case an operator sees most often: the panel on one monitor and
 * the wall projected from the same laptop.
 *
 * It is an ADDITION to push, never a replacement:
 *
 *   - BroadcastChannel is same-origin and same-browser. A wall on a separate device, or in a
 *     different browser, hears nothing at all — which at a venue is the normal arrangement, so
 *     removing Pusher would break the screens that matter most.
 *   - It carries an optimistic figure. The server is still the authority; the reconcile that
 *     follows corrects anything this got ahead of.
 *
 * Server-Sent Events were the obvious alternative and are the wrong tool here: each open stream
 * holds a php-fpm worker for its whole life, and this box runs twelve of them against forty-odd
 * screens. The auction would stop answering requests long before the last screen connected.
 */

const CHANNEL = 'fast-auction';

/** BroadcastChannel is not everywhere (older Safari), and its absence must not break a screen. */
function makeChannel() {
    if (typeof window === 'undefined' || typeof window.BroadcastChannel !== 'function') {
        return null;
    }

    try {
        return new window.BroadcastChannel(CHANNEL);
    } catch (e) {
        return null;
    }
}

/**
 * Announce a local change. Cheap enough to call on every keypress.
 *
 * @param {number|string} auctionId
 * @param {object} payload
 */
export function publishLocal(auctionId, payload) {
    const channel = makeChannel();
    if (!channel) return;

    try {
        channel.postMessage({ auctionId: String(auctionId), at: Date.now(), ...payload });
    } finally {
        // One channel per message: a long-lived one has to be closed on unmount, and a screen
        // that forgets leaks a listener for the whole evening.
        channel.close();
    }
}

/**
 * Listen for changes from another window on this machine.
 *
 * @returns {() => void} unsubscribe
 */
export function subscribeLocal(auctionId, handler) {
    const channel = makeChannel();
    if (!channel) return () => {};

    const onMessage = (event) => {
        const data = event.data;
        // Two auctions open side by side must not drive each other.
        if (!data || String(data.auctionId) !== String(auctionId)) return;
        handler(data);
    };

    channel.addEventListener('message', onMessage);

    return () => {
        channel.removeEventListener('message', onMessage);
        channel.close();
    };
}
