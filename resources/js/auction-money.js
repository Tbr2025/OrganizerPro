/**
 * The one money formatter for the auction screens.
 *
 * Amounts read on the K / M / B ladder, never long runs of zeros, and never the Indian
 * Lakh/Crore ladder that six separate copies of this used to hardcode — an auction run
 * in points, coins or dollars has no business showing "10 Cr".
 *
 * The unit comes from the auction (`amount_unit`), so `usd` renders as a `$` prefix and
 * named units as a suffix: `$10M`, `10M Points`, `10M Coins`.
 *
 * Exposed on `window` rather than exported, because the panels are inline Alpine
 * components inside Blade views. The public displays are standalone documents that
 * cannot load this bundle and carry their own copy of `formatAmount` — same ladder, fed
 * the same unit config from the server.
 */

/**
 * Format a raw amount on the K/M/B ladder, without a unit.
 *
 * @param {number|string|null} value
 * @param {string} placeholder Shown for null/blank, so "not set" never reads as 0.
 */
window.auctionFigure = function (value, placeholder = '—') {
    if (value === null || value === undefined || value === '') return placeholder;

    const n = Number(value);
    if (!isFinite(n)) return placeholder;

    const sign = n < 0 ? '-' : '';
    const abs = Math.abs(n);

    // PHP_FLOAT_MAX reaches the client as a huge number when a tournament has no cap.
    if (abs >= 1e15) return '∞';

    let divisor = 1;
    let suffix = '';
    if (abs >= 1e9) { divisor = 1e9; suffix = 'B'; }
    else if (abs >= 1e6) { divisor = 1e6; suffix = 'M'; }
    else if (abs >= 1e3) { divisor = 1e3; suffix = 'K'; }

    // Two decimals at most, no trailing zeros: 1.50M => "1.5M", 20.00M => "20M".
    const scaled = (abs / divisor).toFixed(2).replace(/\.?0+$/, '');

    return sign + scaled + suffix;
};

/**
 * Format a raw amount with the auction's unit.
 *
 * @param {number|string|null} value
 * @param {{label?: string, prefix?: boolean}} unit From auction.amountUnitConfig().
 */
window.auctionAmount = function (value, unit = {}, placeholder = '—') {
    const figure = window.auctionFigure(value, placeholder);
    if (figure === placeholder || figure === '∞') return figure;

    const label = unit.label || 'Points';

    return unit.prefix ? label + figure : figure + ' ' + label;
};

/*
 * ── Money entry, in millions ──────────────────────────────────────────────────
 * Amounts are stored in whole units but always entered in millions, so the number
 * an operator types matches the M figures they read everywhere else.
 *
 * These previously lived inline per screen on *different* scales — the sealed-bid
 * spinner and the sell modal used Lakhs (x100,000) while everything on screen was
 * shown in millions, so typing "5" wrote 500,000 instead of 5,000,000. One shared
 * pair keeps entry and display on the same scale.
 */

/** Raw stored units → the millions figure shown in an input. */
window.auctionToM = function (raw) {
    if (raw === '' || raw === null || raw === undefined) return '';
    const n = Number(raw);
    if (!isFinite(n)) return '';
    // Trim floating-point residue without losing real precision.
    return Number((n / 1e6).toFixed(6));
};

/** Millions typed into an input → raw stored units. */
window.auctionFromM = function (value) {
    if (value === '' || value === null || value === undefined) return '';
    const n = Number(value);
    if (!isFinite(n)) return '';
    // toFixed(2) first: 0.1 * 1e6 is 100000.00000000001 in floating point.
    return Number((n * 1e6).toFixed(2));
};
