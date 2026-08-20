/**
 * The auction's money format, in one place.
 *
 * This is the Javascript twin of `formatMillions()` on the classic wall and of `format_points()` /
 * `Auction::formatAmount()` on the server. It has to agree with them: the same figure appears on
 * the wall, on a manager's phone and in an exported report, and three different renderings of one
 * price is how a room ends up arguing about what was bid.
 *
 * **K / M / B — not lakh and crore.** That was the first mistake here: an Indian-notation
 * formatter looked more at home for a cricket auction and disagreed with every other surface in
 * the application, so `1.2M Points` on the classic wall read as `12L Points` on the new one. The
 * ladder the app actually uses wins, whatever it would have been nicer to pick.
 *
 * The unit comes from the auction — Points, Coins, a currency symbol — and may sit before or
 * after the figure, which is why it is passed in rather than assumed.
 */

const PLACEHOLDER = '—';

/**
 * @param {number|string|null|undefined} amount
 * @param {{label: string, prefix: boolean}} unit  from Auction::amountUnitConfig()
 */
export function formatAmount(amount, unit = { label: 'Points', prefix: false }) {
    if (amount === null || amount === undefined || amount === '') return PLACEHOLDER;

    const n = Number(amount);
    if (!Number.isFinite(n)) return PLACEHOLDER;

    const abs = Math.abs(n);
    if (abs >= 1e15) return '∞';

    const sign = n < 0 ? '-' : '';

    let divisor = 1;
    let suffix = '';

    if (abs >= 1e9) { divisor = 1e9; suffix = 'B'; }
    else if (abs >= 1e6) { divisor = 1e6; suffix = 'M'; }
    else if (abs >= 1e3) { divisor = 1e3; suffix = 'K'; }

    // Trailing zeros trimmed, so 1.20M reads as 1.2M and 1.00M as 1M.
    const figure = sign + (abs / divisor).toFixed(2).replace(/\.?0+$/, '') + suffix;

    return unit?.prefix ? `${unit.label}${figure}` : `${figure} ${unit?.label ?? ''}`.trim();
}

/** A bound formatter, so a component can just call `money(v)`. */
export function moneyFor(unit) {
    return (v) => formatAmount(v, unit);
}

/**
 * What the price is called right now — the classic wall's `sold-text`.
 *
 * BASE VALUE until somebody bids, CURRENT BID once a team is leading, SOLD PRICE after the
 * hammer. Reproduced rather than invented because the template author positioned an element
 * expecting these words, and a wall that says CURRENT BID over an opening price is wrong.
 */
export function priceLabel(row) {
    if (!row) return 'BASE VALUE';
    if (row.status === 'sold') return 'SOLD PRICE';

    return row.current_bid_team ? 'CURRENT BID' : 'BASE VALUE';
}
