<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Ticker | {{ $auction->name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    {{-- Same CDN versions the LED wall uses. This is a standalone document with no Vite
         bundle, so Echo comes from the CDN rather than resources/js. --}}
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.11.3/echo.iife.js"></script>
    <style>
        /* ── OBS browser source ──
           Transparent background and a fixed 1920x1080 canvas, matching the match
           ticker so both composite the same way in a mixer. Add as a Browser Source at
           1920x1080; everything not drawn here stays see-through. */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            font-family: 'Roboto', system-ui, -apple-system, sans-serif;
            background: transparent !important;
            background-color: transparent !important;
            width: 1920px;
            height: 1080px;
            overflow: hidden;
            color: #fff;
        }

        :root {
            --primary: {{ $auction->primary_color ?? '#00bcd4' }};
            --secondary: {{ $auction->secondary_color ?? '#22c55e' }};
            --panel:  rgba(4, 22, 34, 0.94);
            --panel2: rgba(2, 14, 22, 0.96);
            --edge:   rgba(34, 211, 238, 0.35);
        }

        .glass {
            background: rgba(10, 10, 30, 0.92);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.10);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.55);
        }

        .hidden { display: none !important; }

        /* ── Top-right: live badge + pool progress ── */
        #top-bar {
            position: fixed; top: 28px; right: 28px;
            display: flex; align-items: center; gap: 14px;
        }
        .live-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #ef4444; animation: pulseLive 1.1s ease-in-out infinite;
        }
        @keyframes pulseLive { 0%,100% { opacity: 1; } 50% { opacity: 0.25; } }

        /* ── Lower third ─────────────────────────────────────────────────────────
           Photo, slab and stats strip live inside one positioned shell so they move
           as a single unit and their left indents stay in step.                     */
        #lt-wrap {
            position: fixed; left: 64px; bottom: 132px; width: 1150px;
            animation: slideUp 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes slideUp {
            from { transform: translateY(28px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }

        /* The circle overlaps the slab's top edge. */
        #lt-photo {
            position: absolute; left: 24px; bottom: 100%; margin-bottom: -56px;
            width: 196px; height: 196px; border-radius: 50%; overflow: hidden;
            border: 3px solid var(--primary); background: var(--panel2); z-index: 3;
        }
        /* Player cutouts are 3:4 portraits; a centred square crop eats the head. */
        #lt-photo img { width: 100%; height: 100%; object-fit: cover; object-position: 50% 12%; }

        #lt-slab {
            position: relative; z-index: 2; height: 112px;
            display: flex; align-items: stretch;
            padding-left: 244px;               /* 24 + 196 circle + 24 gap */
            background: linear-gradient(180deg, var(--panel) 0%, var(--panel2) 100%);
            border: 1px solid var(--edge); border-top: 2px solid var(--primary);
        }
        #lt-slab.no-photo, #lt-stats.no-photo { padding-left: 0; }
        #lt-slab.no-stats { border-bottom: 1px solid var(--edge); }

        .lt-cell { padding: 0 26px; display: flex; flex-direction: column; justify-content: center; }
        .lt-label { font-size: 13px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; opacity: 0.55; }
        .lt-figure { font-size: 38px; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; }

        #lt-base { flex: 0 0 264px; }
        #lt-bid  { flex: 0 0 300px; align-items: flex-end; text-align: right; border-left: 1px solid rgba(255,255,255,0.10); }
        #lt-bid-val { color: var(--secondary); font-size: 44px; }
        #lt-bid-team { font-size: 15px; font-weight: 700; opacity: 0.85; margin-top: 4px; }

        /* Nameplate. clip-path cannot clip a border, so the edge and the fill are two
           stacked clipped layers rather than one bordered element. */
        #lt-name-plate { flex: 1; min-width: 0; position: relative; display: flex; align-items: center; justify-content: center; }
        #lt-shield-edge, #lt-shield-fill {
            position: absolute; inset: 0;
            clip-path: polygon(7% 0, 93% 0, 100% 50%, 93% 100%, 7% 100%, 0 50%);
        }
        #lt-shield-edge { background: var(--primary); }
        #lt-shield-fill { inset: 2px; background: linear-gradient(180deg, #0d3b52, #072330); }
        #lt-name-text { position: relative; z-index: 1; text-align: center; padding: 0 18px; }
        #lt-name-1, #lt-name-2 { font-weight: 900; line-height: 1.02; letter-spacing: 1px; text-transform: uppercase; }
        #lt-name-1 { font-size: 34px; }
        #lt-name-2 { font-size: 42px; }
        #lt-name-text.long #lt-name-1 { font-size: 27px; }
        #lt-name-text.long #lt-name-2 { font-size: 33px; }

        /* Career strip, flush beneath the slab. */
        #lt-stats {
            position: relative; z-index: 2; height: 40px;
            display: flex; align-items: center; padding-left: 244px;
            background: var(--panel2);
            border: 1px solid rgba(34,211,238,0.22); border-top: none;
        }
        #lt-stats .st-head { padding: 0 22px; font-size: 12px; font-weight: 900; letter-spacing: 3px; text-transform: uppercase; color: var(--primary); }
        #lt-stats .st { padding: 0 22px; border-left: 1px solid rgba(255,255,255,0.12); display: flex; align-items: baseline; gap: 9px; }
        #lt-stats .st b { font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; opacity: 0.5; }
        #lt-stats .st span { font-size: 20px; font-weight: 900; font-variant-numeric: tabular-nums; }

        /* Clock pill, above the slab's right edge, so the slab keeps three cells. */
        #lt-clock {
            position: absolute; right: 0; bottom: 100%; margin-bottom: 14px; z-index: 3;
            display: flex; align-items: center; gap: 12px;
            padding: 8px 18px; border-radius: 999px;
            background: var(--panel); border: 1px solid var(--edge);
        }
        #lt-seconds { font-size: 36px; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; }
        #lt-call { font-size: 15px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; padding: 3px 12px; border-radius: 999px; }

        /* ── Teams table, bottom right ── */
        #teams-panel {
            position: fixed; right: 64px; bottom: 132px; width: 520px;
            border: 1px solid var(--edge); overflow: hidden;
            font-variant-numeric: tabular-nums;
            /* Slides in from the right when something changes and back out when its window
               closes, rather than sitting on the broadcast permanently. */
            transition: transform 0.55s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.45s ease;
            will-change: transform, opacity;
        }
        #teams-panel.tp-out { transform: translateX(calc(100% + 80px)); opacity: 0; pointer-events: none; }
        #teams-panel.tp-in  { transform: translateX(0); opacity: 1; }
        /* The name column takes the slack. Short names were being cut to three characters
           upstream, so two differently-named teams both read "Bac"; the full name is shown
           and only ellipsised if it genuinely will not fit. */
        #teams-panel .row { display: grid; grid-template-columns: minmax(0, 1fr) 152px 92px; }
        #teams-panel .hd { background: var(--primary); color: #02121c; font-size: 12px; font-weight: 900; letter-spacing: 2.5px; text-transform: uppercase; }
        #teams-panel .hd > div, #teams-panel .tr > div { padding: 9px 14px; }
        #teams-panel .tr { background: var(--panel); border-top: 1px solid rgba(34,211,238,0.16); font-size: 17px; font-weight: 700; align-items: center; }
        #teams-panel .tr:nth-child(even) { background: var(--panel2); }
        #teams-panel .tr .amt { color: var(--secondary); font-weight: 900; text-align: right; }
        #teams-panel .tr .cnt { text-align: center; }
        #teams-panel .team { display: flex; align-items: center; gap: 10px; min-width: 0; }
        #teams-panel .team .nm { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #teams-panel .team img, #teams-panel .team .initials {
            width: 26px; height: 26px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
            background: rgba(255,255,255,0.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 900;
        }
        #teams-panel .sq {
            display: flex; gap: 26px;
            background: rgba(34,211,238,0.13); border-top: 1px solid var(--edge);
            padding: 9px 14px; font-size: 13px; font-weight: 900;
            letter-spacing: 2px; text-transform: uppercase;
        }
        #teams-panel.dense .tr > div, #teams-panel.dense .hd > div { padding: 5px 14px; }
        #teams-panel.dense .tr { font-size: 15px; }

        /* ── Scrolling recent sales, along the very bottom ── */
        #sales-strip {
            position: fixed; bottom: 46px; left: 60px; right: 60px;
            height: 62px; border-radius: 14px; overflow: hidden;
            display: flex; align-items: center;
        }
        #sales-label {
            flex-shrink: 0; padding: 0 22px; height: 100%;
            display: flex; align-items: center;
            background: var(--primary); color: #001018;
            font-size: 14px; font-weight: 900; letter-spacing: 3px; text-transform: uppercase;
        }
        #sales-viewport { flex: 1; overflow: hidden; position: relative; height: 100%; }
        #sales-track {
            display: flex; align-items: center; gap: 48px;
            height: 100%; white-space: nowrap;
            position: absolute; left: 0; top: 0;
            will-change: transform;
        }
        .sale { display: flex; align-items: center; gap: 11px; font-size: 19px; }
        .sale img, .sale .initials {
            width: 30px; height: 30px; border-radius: 50%; object-fit: cover;
            background: rgba(255,255,255,0.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 900;
        }
        .sale .who { font-weight: 700; }
        .sale .to { opacity: 0.5; font-size: 15px; }
        .sale .price { font-weight: 900; color: var(--secondary); font-variant-numeric: tabular-nums; }

        #idle {
            position: fixed; bottom: 132px; left: 64px;
            padding: 22px 40px;
            font-size: 30px; font-weight: 900; letter-spacing: 1px;
            background: var(--panel);
            border: 1px solid var(--edge); border-top: 2px solid var(--primary);
        }
    </style>
</head>
<body>

    {{-- Live badge + pool progress --}}
    <div id="top-bar">
        <div class="glass" id="pool-chip" style="padding:10px 18px;border-radius:999px;display:none;align-items:center;gap:10px;">
            <span style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;opacity:0.55;">Pool</span>
            <span id="pool-name" style="font-size:16px;font-weight:900;"></span>
            <span id="pool-progress" style="font-size:14px;font-weight:700;opacity:0.65;font-variant-numeric:tabular-nums;"></span>
        </div>
        <div class="glass" style="padding:10px 18px;border-radius:999px;display:flex;align-items:center;gap:9px;">
            <span class="live-dot"></span>
            <span style="font-size:13px;font-weight:900;letter-spacing:2px;text-transform:uppercase;">Live</span>
            <span id="sold-count" style="font-size:13px;font-weight:700;opacity:0.6;font-variant-numeric:tabular-nums;"></span>
        </div>
    </div>

    {{-- Teams: purse remaining and squad count --}}
    <div id="teams-panel" class="hidden"></div>

    {{-- The player on the block --}}
    <div id="lt-wrap" class="hidden">
        <div id="lt-photo"><img id="lt-img" src="" alt=""></div>

        <div id="lt-clock" class="hidden">
            <div id="lt-seconds"></div>
            <div id="lt-call" class="hidden"></div>
        </div>

        <div id="lt-slab">
            <div class="lt-cell" id="lt-base">
                <div class="lt-label">Base Price</div>
                <div class="lt-figure" id="lt-base-val"></div>
            </div>
            <div id="lt-name-plate">
                <div id="lt-shield-edge"></div>
                <div id="lt-shield-fill"></div>
                <div id="lt-name-text">
                    <div id="lt-name-1"></div>
                    <div id="lt-name-2"></div>
                </div>
            </div>
            <div class="lt-cell" id="lt-bid">
                <div class="lt-label" id="lt-bid-label">Current Bid</div>
                <div class="lt-figure" id="lt-bid-val"></div>
                <div id="lt-bid-team"></div>
            </div>
        </div>

        <div id="lt-stats" class="hidden"></div>
    </div>

    {{-- Between players --}}
    <div id="idle" class="glass hidden">Next player coming up…</div>

    {{-- Recent sales --}}
    <div id="sales-strip" class="glass hidden">
        <div id="sales-label">Recent Sales</div>
        <div id="sales-viewport"><div id="sales-track"></div></div>
    </div>

<script>
    const auctionId = {{ $auction->id }};

    /* Amounts read on the K / M / B ladder with this auction's unit. This page is a
       standalone document (no Vite bundle), so it carries its own copy — fed the same
       unit config the server gives every other screen. */
    let AMOUNT_UNIT = @json($auction->amountUnitConfig());

    function figure(value) {
        if (value === null || value === undefined || value === '') return '—';
        const n = Number(value);
        if (!isFinite(n)) return '—';

        const sign = n < 0 ? '-' : '';
        const abs = Math.abs(n);
        if (abs >= 1e15) return '∞';

        let d = 1, suffix = '';
        if (abs >= 1e9) { d = 1e9; suffix = 'B'; }
        else if (abs >= 1e6) { d = 1e6; suffix = 'M'; }
        else if (abs >= 1e3) { d = 1e3; suffix = 'K'; }

        return sign + (abs / d).toFixed(2).replace(/\.?0+$/, '') + suffix;
    }

    function amount(value) {
        const f = figure(value);
        if (f === '—' || f === '∞') return f;
        return AMOUNT_UNIT.prefix ? AMOUNT_UNIT.label + f : f + ' ' + AMOUNT_UNIT.label;
    }

    /* The closing call uses the server's thresholds, exactly like every other screen. */
    function finalCallFor(remaining, stages) {
        if (remaining === null || remaining === undefined || !Array.isArray(stages) || !stages.length) return null;
        return stages.find(s => remaining <= s.at) || null;
    }

    const initials = (name) => (name || '?').substring(0, 3).toUpperCase();

    /* ── Clock, ticking locally between polls so calls land on exact seconds ── */
    let clockRemaining = null, clockEnabled = false, callStages = [], clockTick = null;
    // Whether anyone has bid on the player currently on the block. Drives the unsold notice.
    let tickerNoBids = false;
    // Server-declared, same flag the wall and the panel read.
    let clockPaused = false;

    function renderClock() {
        const wrap = document.getElementById('lt-clock');
        const secs = document.getElementById('lt-seconds');
        const call = document.getElementById('lt-call');

        if (!clockEnabled || clockRemaining === null) {
            wrap.classList.add('hidden');
            return;
        }

        const s = Math.max(0, clockRemaining);

        // Finished. A frozen "0 · FINAL CALL" on a broadcast keeps calling a player whose
        // clock ran out, so the whole cell goes rather than sitting there stale. A PAUSED
        // clock at zero is different — it is being held, and the stream should say so.
        if (s <= 0 && ! clockPaused) {
            wrap.classList.add('hidden');
            call.classList.add('hidden');
            return;
        }

        wrap.classList.remove('hidden');
        secs.textContent = s;

        if (clockPaused) {
            // No closing call while the clock is stopped: nothing is closing.
            call.classList.remove('hidden');
            call.textContent = 'Paused';
            call.style.background = '#facc15';
            call.style.color = '#111827';
            secs.style.color = '#facc15';
            return;
        }

        const c = finalCallFor(s, callStages);
        if (c) {
            call.classList.remove('hidden');
            /* With nobody bidding, the closing call has a foregone conclusion — say it,
               rather than showing the stream a countdown whose outcome only the room knows.
               Matches the wall, which greys the card at the same moment from the same
               server-computed state. */
            call.textContent = tickerNoBids ? `${c.label} · GOING UNSOLD` : c.label;
            call.style.background = c.is_final ? '#dc2626' : '#f59e0b';
            call.style.color = c.is_final ? '#fff' : '#111827';
            secs.style.color = c.is_final ? '#f87171' : '#fcd34d';
        } else {
            call.classList.add('hidden');
            secs.style.color = s <= 5 ? '#f87171' : '#fff';
        }
    }

    function startClock() {
        if (clockTick) return;
        clockTick = setInterval(() => {
            if (clockRemaining !== null && clockRemaining > 0) clockRemaining--;
            renderClock();
        }, 1000);
    }

    /* ── Marquee: duplicated track so the loop has no visible seam ── */
    let salesOffset = 0, salesAnim = null, salesWidth = 0;

    /**
     * Attribute-safe escaping for anything interpolated into innerHTML.
     *
     * Player and team names arrive through PUBLIC tournament registration, so a name
     * containing markup is stored XSS on a page that is, by design, open to anyone and
     * usually running unattended on a projector or in an OBS browser source. None of the
     * template strings below escaped anything.
     */
    function esc(v) {
        return String(v ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Only rebuild the strip when the sales actually change: it was re-rendered on every
    // 2-second poll, which replaced the DOM mid-scroll and made the marquee stutter.
    let salesSignature = null;

    function renderSales(sales) {
        const strip = document.getElementById('sales-strip');
        const track = document.getElementById('sales-track');

        if (!sales.length) {
            strip.classList.add('hidden');
            return;
        }
        strip.classList.remove('hidden');

        const signature = sales.map(s => `${s.id}:${s.price}`).join('|');
        if (signature === salesSignature) return;      // nothing new — leave the scroll alone
        salesSignature = signature;

        const html = sales.map(s => `
            <div class="sale">
                ${s.team_logo
                    ? `<img src="${esc(s.team_logo)}" alt="">`
                    : `<span class="initials">${esc(initials(s.team_name))}</span>`}
                <span class="who">${esc(s.player_name)}</span>
                <span class="to">to</span>
                <span class="who">${esc(s.team_name)}</span>
                <span class="price">${esc(amount(s.price))}</span>
            </div>`).join('');

        /* Measure one copy first.
           The strip used to be built as `html + html` unconditionally, so the second copy
           is what the marquee scrolls into — but with only a handful of sales the doubled
           content still fits on screen and every entry was simply visible twice at once,
           side by side. Duplicate only when one copy genuinely overflows. */
        track.innerHTML = html;
        const oneCopy = track.scrollWidth;
        const visible = strip.clientWidth;

        if (oneCopy > visible) {
            track.innerHTML = html + html;
            salesWidth = oneCopy;
        } else {
            // Fits: show it once and park it. A marquee with nothing to scroll past just
            // slides the list off the edge and back for no reason.
            salesWidth = 0;
            salesOffset = 0;
            track.style.transform = 'translateX(0)';
        }

        if (!salesAnim) {
            const step = () => {
                // salesWidth of 0 means one copy fits, so there is nothing to scroll.
                if (salesWidth > 0) {
                    salesOffset -= 1.1;
                    if (Math.abs(salesOffset) >= salesWidth) salesOffset = 0;
                    track.style.transform = `translateX(${salesOffset}px)`;
                }
                salesAnim = requestAnimationFrame(step);
            };
            salesAnim = requestAnimationFrame(step);
        }
    }

    /* Teams are sorted and capped here, not in the feed: the feed's name ordering is
       relied on elsewhere, and the broadcast wants the biggest purse on top. */
    const VISIBLE_TEAMS = 10;
    let teamWindow = 0;

    function sortTeams(teams) {
        return [...teams].sort((a, b) => {
            const au = a.remaining === null, bu = b.remaining === null;
            if (au !== bu) return au ? -1 : 1;                       // uncapped first
            if (!au && a.remaining !== b.remaining) return b.remaining - a.remaining;
            if (a.players !== b.players) return a.players - b.players;
            // A total order matters: without it two identical teams swap places on
            // alternate polls and the table visibly flickers every two seconds.
            return String(a.short_name || a.name).localeCompare(String(b.short_name || b.name));
        });
    }

    /* ── When the teams table is on screen ──
       It used to sit there for the whole auction. On a broadcast that is dead weight: the
       numbers only matter at the moment they change. It now slides in when a purse or a
       squad count actually moves, holds for 20s, and slides away again. */
    const TEAMS_VISIBLE_MS = 20000;
    let teamsSignature = null;
    let teamsHideTimer = null;

    /** Only the figures worth interrupting the broadcast for. Order included, since the
        table is sorted by purse and a reorder is itself the news. */
    function teamsFingerprint(teams) {
        return (teams || []).map(t => `${t.id}:${t.remaining}:${t.players}`).join('|');
    }

    function showTeamsPanel() {
        const panel = document.getElementById('teams-panel');
        panel.classList.remove('hidden');
        // Next frame, so the browser has a starting transform to animate FROM.
        requestAnimationFrame(() => {
            panel.classList.remove('tp-out');
            panel.classList.add('tp-in');
        });

        if (teamsHideTimer) clearTimeout(teamsHideTimer);
        teamsHideTimer = setTimeout(hideTeamsPanel, TEAMS_VISIBLE_MS);
    }

    function hideTeamsPanel() {
        const panel = document.getElementById('teams-panel');
        panel.classList.remove('tp-in');
        panel.classList.add('tp-out');
        if (teamsHideTimer) { clearTimeout(teamsHideTimer); teamsHideTimer = null; }
    }

    function renderTeams(teams, squad) {
        const panel = document.getElementById('teams-panel');

        if (!teams || !teams.length) { panel.classList.add('hidden'); return; }

        const signature = teamsFingerprint(teams);
        const changed = teamsSignature !== null && signature !== teamsSignature;
        const first = teamsSignature === null;
        teamsSignature = signature;

        // Painted every poll so the markup is ready, but only *revealed* on a change. The
        // first poll paints it hidden: nothing has happened yet worth announcing.
        if (first) {
            panel.classList.add('tp-out');
            panel.classList.remove('hidden');
        } else if (changed) {
            showTeamsPanel();
        }

        const sorted = sortTeams(teams);
        const dense = sorted.length > 8;
        panel.classList.toggle('dense', dense);

        let shown = sorted, caption = 'Teams';
        if (sorted.length > VISIBLE_TEAMS) {
            const pages = Math.ceil(sorted.length / VISIBLE_TEAMS);
            teamWindow = teamWindow % pages;                          // re-clamp: teams can be added mid-auction
            const from = teamWindow * VISIBLE_TEAMS;
            shown = sorted.slice(from, from + VISIBLE_TEAMS);
            caption = `Teams ${from + 1}-${Math.min(from + VISIBLE_TEAMS, sorted.length)} of ${sorted.length}`;
        }

        // Escaped: team names are organizer input and reach this public page verbatim.
        const rows = shown.map(t => `
            <div class="row tr">
                <div class="team">
                    ${t.logo ? `<img src="${esc(t.logo)}" alt="">` : `<span class="initials">${esc(initials(t.name || t.short_name))}</span>`}
                    <span class="nm">${esc(t.name || t.short_name)}</span>
                </div>
                <div class="amt">${t.remaining === null ? '—' : esc(amount(t.remaining))}</div>
                <div class="cnt">${esc(t.players)}</div>
            </div>`).join('');

        // `max` is omitted rather than shown as a dash when nothing is configured.
        const squadRow = squad
            ? `<div class="sq"><span>Squad Size</span><span>Min: ${esc(squad.min)}</span>${squad.max ? `<span>Max: ${esc(squad.max)}</span>` : ''}</div>`
            : '';

        panel.innerHTML = `
            <div class="row hd"><div>${caption}</div><div style="text-align:right">Purse Rem</div><div style="text-align:center">Players</div></div>
            ${rows}${squadRow}`;
    }

    /* Rotates on its own timer. Tying it to the 2s poll makes the table jump on a
       cadence the eye reads as a glitch. */
    setInterval(() => { teamWindow++; }, 8000);

    function renderStats(stats) {
        const strip = document.getElementById('lt-stats');
        const slab = document.getElementById('lt-slab');

        const cells = [];
        if (stats) {
            // 0 is a declared figure and renders; null means never entered and is dropped.
            if (stats.matches !== null && stats.matches !== undefined) cells.push(['Mts', stats.matches]);
            if (stats.runs !== null && stats.runs !== undefined) cells.push(['Runs', stats.runs]);
            if (stats.wickets !== null && stats.wickets !== undefined) cells.push(['Wkts', stats.wickets]);
        }

        if (!cells.length) {
            strip.classList.add('hidden');
            slab.classList.add('no-stats');       // close the slab off with its own border
            return;
        }

        strip.classList.remove('hidden');
        slab.classList.remove('no-stats');
        strip.innerHTML = `<div class="st-head">Career</div>` +
            // Values are self-declared career figures from registration, so escaped too.
            cells.map(([k, v]) => `<div class="st"><b>${esc(k)}</b><span>${esc(v)}</span></div>`).join('');
    }

    function renderCurrent(p, sealed = null) {
        const wrap = document.getElementById('lt-wrap');
        const idle = document.getElementById('idle');

        if (!p) {
            wrap.classList.add('hidden');
            idle.classList.remove('hidden');
            return;
        }

        idle.classList.add('hidden');
        wrap.classList.remove('hidden');

        // Split on the LAST space: "Venkatesh Iyer" → "VENKATESH" / "IYER".
        const parts = String(p.name || '').trim().split(/\s+/);
        const last = parts.length > 1 ? parts.pop() : '';
        const first = parts.join(' ');
        const nameText = document.getElementById('lt-name-text');
        document.getElementById('lt-name-1').textContent = first;
        document.getElementById('lt-name-2').textContent = last || first;
        nameText.classList.toggle('long', Math.max(first.length, (last || first).length) >= 13);

        const img = document.getElementById('lt-img');
        const photo = document.getElementById('lt-photo');
        const slab = document.getElementById('lt-slab');
        const strip = document.getElementById('lt-stats');
        if (p.image) {
            img.src = p.image;
            photo.style.display = 'block';
            // Both must lose the indent together, or the strip's cell boundaries stop
            // lining up with the slab's.
            slab.classList.remove('no-photo');
            strip.classList.remove('no-photo');
        } else {
            photo.style.display = 'none';
            slab.classList.add('no-photo');
            strip.classList.add('no-photo');
        }

        document.getElementById('lt-base-val').textContent = amount(p.base_price);

        const hasBid = !!p.leading_team;
        // Not "Base Price" when there are no bids — the left cell already says that.
        // A sealed round replaces the bid cell entirely. No amount can be shown, because
        // there is no public amount to show — the price is frozen at the round's floor and
        // the sealed figures never leave the server.
        if (sealed) {
            const lot = sealed.state === 'awaiting_lot';
            document.getElementById('lt-bid-label').textContent = lot ? 'Tie — Drawing' : 'Sealed Round';
            document.getElementById('lt-bid-val').textContent = lot ? amount(p.current_price) : '—';
            document.getElementById('lt-bid-team').textContent = sealed.total_rounds > 1
                ? `Round ${sealed.round_number} of ${sealed.total_rounds}`
                : '';
        } else {
            document.getElementById('lt-bid-label').textContent = hasBid ? 'Current Bid' : 'No Bids';
            document.getElementById('lt-bid-val').textContent = hasBid ? amount(p.current_price) : '—';
            document.getElementById('lt-bid-team').textContent = hasBid ? (p.leading_team_short || p.leading_team) : '';
        }

        renderStats(p.stats);
    }

    /* Retained so a pushed raise can patch and re-render without waiting for a feed. The
       strip renders straight from the response, so without keeping these there is nothing
       for an event to update. */
    let lastCurrentPlayer = null;
    let lastSealed = null;
    /* Ordering token for pushed raises: frames are unordered and can repeat, so anything not
       newer is dropped rather than put on air. */
    let lastAppliedBidId = 0;

    function poll() {
        fetch(`/auction/${auctionId}/ticker-feed`)
            .then(r => r.json())
            .then(d => {
                if (!d?.success) return;

                if (d.amount_unit) AMOUNT_UNIT = d.amount_unit;

                lastCurrentPlayer = d.current_player || null;
                lastSealed = d.closed_bid || null;
                renderCurrent(d.current_player, d.closed_bid || null);
                renderTeams(d.teams || [], d.squad || null);
                renderSales(d.recent_sales || []);

                // The ticker feed exposes the leading team's NAME (never an amount during a
                // sealed round), which is all that is needed to know whether anyone has bid.
                tickerNoBids = !!d.current_player && ! d.current_player.leading_team;

                // Clock only runs while someone is actually on the block.
                // Same as the wall: a pause is a state to SHOW, not a reason to blank the
                // clock. renderClock() presents the frozen figure and labels it Paused.
                if (d.current_player) {
                    /* A sealed round runs on its own clock; the auction's is stopped for the
                       duration, so without this the strip showed no countdown at all while
                       the teams were submitting. Matches the wall and the panel. */
                    const sealedTimer = d.closed_bid?.timer;

                    if (sealedTimer && sealedTimer.applies && sealedTimer.remaining !== null) {
                        clockEnabled = true;
                        clockRemaining = sealedTimer.remaining;
                        clockPaused = false;
                    } else {
                        clockEnabled = !!d.timer?.enabled;
                        clockRemaining = d.timer?.remaining ?? null;
                        clockPaused = !!d.timer?.paused;
                    }
                    if (Array.isArray(d.timer?.final_call_stages)) callStages = d.timer.final_call_stages;
                    startClock();
                } else {
                    clockEnabled = false;
                    clockRemaining = null;
                }
                renderClock();

                const chip = document.getElementById('pool-chip');
                if (d.active_pool) {
                    chip.style.display = 'flex';
                    document.getElementById('pool-name').textContent = d.active_pool.name;
                    document.getElementById('pool-progress').textContent = `${d.active_pool.done}/${d.active_pool.total}`;
                } else {
                    chip.style.display = 'none';
                }

                document.getElementById('sold-count').textContent =
                    `${d.stats.sold}/${d.stats.total} sold`;
            })
            .catch(e => console.error('[Ticker] poll failed', e));
    }

    /* Chained, not on an interval — an interval stacks requests when the server is slow
       until the browser runs out of connections and the strip freezes on air. */
    (function tickerLoop() {
        Promise.resolve(poll())
            .catch(() => {})
            .finally(() => setTimeout(tickerLoop, 2000));
    })();

    /*
     * Live raises, straight off the wire.
     *
     * The strip is on air, so a price two to three seconds behind the room is visible to
     * everyone watching the stream — the poll above runs every 2 s and the feed it reads is
     * itself cached for a second. This subscribes to the same public channel the wall uses.
     *
     * The poll is untouched and stays the source of truth: it carries the clock, the pool,
     * the sales and the team strip, none of which this event knows about. If the socket never
     * connects, the strip behaves exactly as it did before.
     */
    (function subscribeToRaises() {
        const key = @json(config('broadcasting.connections.pusher.key'));
        const cluster = @json(config('broadcasting.connections.pusher.options.cluster'));

        // config(), not env(): env() returns null under `php artisan config:cache`, which
        // would take this down silently.
        if (!key || typeof Echo === 'undefined') return;

        try {
            const echo = new Echo({ broadcaster: 'pusher', key, cluster, forceTLS: true });

            echo.channel(`auction.${auctionId}`).listen('.bid.raised', (e) => {
                if (!e || !lastCurrentPlayer) return;
                if (Number(e.auction_player_id) !== Number(lastCurrentPlayer.id)) return;

                const bidId = Number(e.bid_id) || 0;
                if (bidId <= lastAppliedBidId) return;
                lastAppliedBidId = bidId;

                /* Patch the retained player and re-render through the normal path, so a
                   pushed raise and a polled one look identical on air. renderCurrent() keeps
                   its own sealed-round branch, which is what stops an amount appearing while
                   a sealed round is running. */
                lastCurrentPlayer.current_price = e.current_price;
                lastCurrentPlayer.leading_team = e.team_name || lastCurrentPlayer.leading_team;
                lastCurrentPlayer.leading_team_short = e.team_name || lastCurrentPlayer.leading_team_short;

                renderCurrent(lastCurrentPlayer, lastSealed);
            });
        } catch (err) {
            console.warn('[Ticker] live updates unavailable, polling only:', err);
        }
    })();

    // Same shortcuts as the match ticker: r reloads, f goes fullscreen.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'r') location.reload();
        if (e.key === 'f') {
            document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen();
        }
    });
</script>
</body>
</html>
