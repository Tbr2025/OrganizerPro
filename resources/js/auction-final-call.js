/**
 * Closing-call helpers for the auction screens.
 *
 * The server ships the call thresholds with every poll (`final_call_stages`), so no
 * screen re-implements the rule — they all just look up which threshold the clock has
 * reached. That matters because the countdown ticks locally between 2-second polls,
 * and the organizer panel, offline panel, bidding page and audience display must all
 * escalate on the same second.
 *
 * Exposed on `window` rather than exported, because the panels are inline Alpine
 * components inside Blade views. The public displays are standalone documents that do
 * not load this bundle and carry their own copy of `finalCallFor` — same two-line
 * lookup over the same server-provided thresholds.
 */

/**
 * The most advanced call the clock has reached, or null when still outside the window.
 *
 * @param {number|null} remaining Seconds left on the clock.
 * @param {Array<{at:number,stage:number,label:string,is_final:boolean}>} stages
 *        Thresholds from the server, ordered final-first.
 */
window.auctionFinalCallFor = function (remaining, stages) {
    if (remaining === null || remaining === undefined || !Array.isArray(stages) || !stages.length) {
        return null;
    }

    return stages.find((s) => remaining <= s.at) || null;
};

/** Tailwind classes for a call badge, escalating in urgency. */
window.auctionFinalCallClass = function (call) {
    if (!call) return '';

    return call.is_final
        ? 'bg-red-600 text-white ring-4 ring-red-400/50'
        : 'bg-amber-500 text-black ring-2 ring-amber-300/50';
};
