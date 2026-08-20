/**
 * The one place that talks to Laravel.
 *
 * Two things live here rather than at the call sites, because both are easy to forget once and
 * hard to notice afterwards:
 *
 *  - `?team_id=`. An admin previewing a team's screen is identified by that query parameter, and
 *    only Superadmin/Admin may use it. Drop it from a single request and a previewing admin reads
 *    as "no team at all" — the exact confusing 403 the controller's own comments describe.
 *  - The CSRF token. Writes go to the existing session-authenticated endpoints, unchanged, so
 *    every POST needs the header Blade put in the page.
 */

const TOKEN = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** The team being viewed, when an admin is previewing rather than bidding as themselves. */
function teamQuery() {
    const teamId = new URLSearchParams(window.location.search).get('team_id');

    return teamId ? `team_id=${encodeURIComponent(teamId)}` : '';
}

function withTeam(url) {
    const q = teamQuery();

    if (!q) {
        return url;
    }

    return url + (url.includes('?') ? '&' : '?') + q;
}

/**
 * A GET that supersedes itself.
 *
 * Reconciles can overlap — a push nudge arriving while the 15-second safety fetch is in flight —
 * and a slow earlier response landing after a newer one would show the room a stale price. The
 * caller passes a key; a second call with the same key aborts the first.
 */
const inFlight = new Map();

export async function get(url, key = url) {
    inFlight.get(key)?.abort();

    const controller = new AbortController();
    inFlight.set(key, controller);

    try {
        const response = await fetch(withTeam(url), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw Object.assign(new Error(`HTTP ${response.status}`), { status: response.status });
        }

        return await response.json();
    } finally {
        if (inFlight.get(key) === controller) {
            inFlight.delete(key);
        }
    }
}

export async function post(url, body = {}) {
    const response = await fetch(withTeam(url), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': TOKEN,
        },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        // The server's own message is better than anything invented here: it says "you are over
        // your ceiling" or "the auction is paused", which is what the manager needs to read.
        throw Object.assign(new Error(data.error || data.message || `HTTP ${response.status}`), {
            status: response.status,
            data,
        });
    }

    return data;
}
