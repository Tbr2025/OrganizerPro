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

        /* Sized to its content, not to a fixed 300px.
           "1.2M Points" at 44px needs about 316px including the cell's own padding, so a
           fixed 300px cell wrapped the amount onto a second line — and the slab is only 112px
           tall, so the wrap pushed CURRENT BID off the top edge and the team name off the
           bottom, clipping both. The amount is the one figure on this strip that must never
           wrap, and its width is unknowable in advance: "1M" and "12.5M" differ by a lot, and
           the unit word is configurable per auction. So the cell takes what it needs and the
           nameplate beside it (flex:1, min-width:0) gives up the difference. */
        #lt-bid  {
            flex: 0 0 auto;
            min-width: 300px;          /* keeps a short amount from looking cramped */
            max-width: 520px;          /* and a long one from starving the nameplate */
            align-items: flex-end; text-align: right;
            border-left: 1px solid rgba(255,255,255,0.10);
        }
        /* Every line in this cell stays on one line — see above. */
        #lt-bid .lt-label, #lt-bid-val, #lt-bid-team { white-space: nowrap; }
        #lt-bid-val { color: var(--secondary); font-size: 44px; line-height: 1.05; }
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
        #lt-name-badges {
            display: flex; align-items: center; justify-content: center;
            gap: 6px; margin-top: 4px; white-space: nowrap;
        }
        #lt-name-badges:empty { display: none; }
        #lt-name-badges .lt-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 1px 7px; border-radius: 5px;
            font-size: 13px; font-weight: 800; letter-spacing: 0.5px;
        }
        #lt-name-badges .lt-badge svg { width: 12px; height: 12px; }
        #lt-name-badges .lt-wk {
            background: rgba(251, 146, 60, 0.22); color: #fdba74;
            border: 1px solid rgba(251, 146, 60, 0.45);
        }
        /* The aeroplane flies here too. This chip was drawing a path that fills to a triangle at
           badge size, so a player's travel dates read as a WARNING on the ticker — the wall and
           the panel were fixed and this screen was missed, which is exactly how one player ends
           up looking different on two screens in the same room. */
        #lt-name-badges .lt-travel svg {
            animation: ltFlightDrift 2.6s ease-in-out infinite;
        }
        @keyframes ltFlightDrift {
            0%, 100% { transform: translate(-0.06em, 0.05em); }
            50%      { transform: translate(0.12em, -0.09em); }
        }
        @media (prefers-reduced-motion: reduce) {
            #lt-name-badges .lt-travel svg { animation: none; }
        }

        #lt-name-badges .lt-travel {
            background: rgba(56, 189, 248, 0.18); color: #7dd3fc;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }
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

        /* ── Sold board ── */
        #sold-board {
            position: fixed; inset: 0; z-index: 90;
            background: rgba(6, 10, 20, 0.97);
            display: flex; flex-direction: column; padding: 34px 46px;
        }
        #sold-board.hidden { display: none; }
        #sold-board-head {
            display: flex; align-items: baseline; justify-content: space-between;
            font-size: 34px; font-weight: 900; letter-spacing: 1px; margin-bottom: 20px;
        }
        #sold-board-count { font-size: 20px; font-weight: 700; color: var(--primary); }
        #sold-board-grid {
            flex: 1; overflow-y: auto; display: grid; gap: 14px; align-content: start;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        }
        .sb-card {
            display: flex; align-items: center; gap: 12px; min-width: 0;
            background: var(--panel); border: 1px solid var(--edge);
            border-left: 3px solid var(--primary); padding: 12px 14px;
        }
        .sb-card img, .sb-card .blank {
            width: 58px; height: 58px; object-fit: cover; flex-shrink: 0;
            background: rgba(255,255,255,0.07);
        }
        .sb-card .blank {
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 900; opacity: 0.5;
        }
        .sb-card .who { min-width: 0; }
        .sb-card .nm { font-size: 17px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sb-card .tm { display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; opacity: 0.6; white-space: nowrap; overflow: hidden; }
        .sb-card .tm span { overflow: hidden; text-overflow: ellipsis; }
        .sb-card .tm .crest { width: 16px; height: 16px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .sb-card .amt { font-size: 18px; font-weight: 900; color: var(--secondary); font-variant-numeric: tabular-nums; }

        #sb-sponsors {
            display: none; align-items: center; justify-content: center; gap: 26px;
            flex-wrap: wrap; padding-top: 14px; margin-top: 12px;
            border-top: 1px solid var(--edge);
        }
        #sb-sponsors img { height: 34px; width: auto; object-fit: contain; opacity: 0.85; }
        #sb-sponsors img.ev { height: 44px; opacity: 1; }

        #idle {
            position: fixed; bottom: 132px; left: 64px;
            padding: 22px 40px;
            font-size: 30px; font-weight: 900; letter-spacing: 1px;
            background: var(--panel);
            border: 1px solid var(--edge); border-top: 2px solid var(--primary);
        }

        /*
         * ── The sealed / draw band ──
         *
         * A strip is read at a glance from across a hall, so this is a band rather than a
         * takeover: one line of what is happening, and — during a draw — the tied teams cycling
         * through the middle of it before it settles on the winner.
         *
         * The cycle and the landing are driven from the SERVER's `drawn_at` and `spin_ms`, the
         * same two numbers the organizer's panel and the LED wall use, so all three screens run
         * one window from one instant. Without that the ticker would settle the moment it saw a
         * winner and give the result away before the room watching the wall had it.
         */
        #lt-overlay {
            position: fixed; inset: 0; z-index: 60;
            display: none; align-items: center; justify-content: center; gap: 34px;
            padding: 0 60px;
            background: linear-gradient(90deg, rgba(2,6,23,0.97) 0%, rgba(30,10,60,0.95) 50%, rgba(2,6,23,0.97) 100%);
            backdrop-filter: blur(8px);
        }
        #lt-overlay.is-on { display: flex; animation: ltOverlayIn 0.35s ease-out; }
        @keyframes ltOverlayIn { from { opacity: 0; } to { opacity: 1; } }

        #lt-overlay-logos { display: flex; align-items: center; gap: 20px; flex-shrink: 0; }
        #lt-overlay-logos img { height: 76px; width: auto; object-fit: contain; }

        #lt-overlay-title {
            font-size: 40px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase;
            color: #fff; line-height: 1.05; white-space: nowrap;
        }
        #lt-overlay-sub {
            margin-top: 6px;
            font-size: 18px; font-weight: 700; letter-spacing: 1px;
            color: rgba(233,213,255,0.75); white-space: nowrap;
        }

        /* The team being shown right now — cycling during the draw, then held on the winner. */
        #lt-overlay-team {
            display: none; align-items: center; gap: 16px; flex-shrink: 0;
            padding: 12px 26px; border-radius: 999px;
            background: rgba(2,6,23,0.6); border: 2px solid rgba(192,132,252,0.5);
        }
        #lt-overlay-team.is-on { display: flex; }
        #lt-overlay-team img { height: 62px; width: 62px; object-fit: contain; }
        #lt-overlay-team img[src=""] { display: none; }
        #lt-overlay-name {
            font-size: 34px; font-weight: 900; color: #fff; white-space: nowrap;
        }
        /* Each name arrives with a short flip, so the cycle reads as chance being taken rather
           than a list being scrolled. */
        #lt-overlay-team.cycling { animation: ltCycle 0.16s ease-out; }
        @keyframes ltCycle {
            from { opacity: 0.35; transform: rotateX(38deg) scale(0.96); }
            to   { opacity: 1; transform: rotateX(0deg) scale(1); }
        }
        #lt-overlay-team.winner {
            border-color: #fde68a;
            box-shadow: 0 0 60px rgba(253,230,138,0.5);
            animation: ltLand 0.6s cubic-bezier(0.2, 1.6, 0.3, 1);
        }
        @keyframes ltLand {
            0%   { transform: scale(0.8); opacity: 0; }
            60%  { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        @media (prefers-reduced-motion: reduce) {
            #lt-overlay.is-on, #lt-overlay-team.cycling, #lt-overlay-team.winner { animation: none; }
        }
    </style>
</head>
<body>

    {{-- The sealed round and the draw, over the strip.
         Asked for after the sealed OVERLAY was deliberately kept off this screen — and the two
         are different things. A full-bleed takeover would blank the one line a ticker exists to
         show; this is a band across it that says what is happening and, for a draw, shows the
         teams going round before landing on one. During a sealed round the cells behind it are
         showing "—" anyway, because there is no public figure to show. --}}
    <div id="lt-overlay">
        <div id="lt-overlay-logos"></div>
        <div id="lt-overlay-body">
            <div id="lt-overlay-title">Sealed Bid In Progress</div>
            <div id="lt-overlay-sub">Amounts are revealed once every team has submitted</div>
        </div>
        <div id="lt-overlay-team">
            <img id="lt-overlay-crest" alt="">
            <span id="lt-overlay-name"></span>
        </div>
    </div>

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

    {{-- The board of players sold, put up from the organizer's panel between lots. Covers the
         whole frame, because on a stream the lower third is not enough room to read a list. --}}
    <div id="sold-board" class="hidden">
        <div id="sold-board-head">
            <span>SOLD SO FAR</span>
            <span id="sold-board-count"></span>
        </div>
        <div id="sold-board-grid"></div>
    </div>

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
                    {{-- Wicket keeper and travel plan, under the name inside the same plate so
                         they travel with it and cannot overlap the cells either side. --}}
                    <div id="lt-name-badges"></div>
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

    /* ── The board of players sold ──
       Fetched only while it is up: it is every sale in the auction, and there is no reason to
       pull it on the two-second tick when nothing is showing it. */
    let soldBoardShowing = null;
    /* Seconds left in the break, from the server, ticked down locally between polls. Re-seeded
       every poll so drift cannot accumulate and every screen lands on the same figure. */
    let _breakRemaining = null;

    function breakClock() {
        const head = document.getElementById('sold-board-count');
        if (! head) return;

        if (! soldBoardShowing || _breakRemaining === null) {
            head.textContent = '';
            return;
        }

        if (_breakRemaining <= 0) {
            head.textContent = 'BACK ANY MOMENT';
            return;
        }

        const m = Math.floor(_breakRemaining / 60);
        head.textContent = `BACK IN ${m}:${String(Math.floor(_breakRemaining % 60)).padStart(2, '0')}`;
        _breakRemaining -= 1;
    }

    setInterval(breakClock, 1000);

    /* The strip and the event's mark, on for any board. A sponsor shown on one slide in twelve
       has been shown far less than the deal said; a strip is on screen for the whole break. */
    function renderSponsors(sponsors, logoUrl) {
        const head = document.getElementById('sold-board-head');
        if (! head) return;

        let strip = document.getElementById('sb-sponsors');

        if (! strip) {
            strip = document.createElement('div');
            strip.id = 'sb-sponsors';
            head.parentNode.appendChild(strip);
        }

        const list = Array.isArray(sponsors) ? sponsors : [];

        strip.innerHTML = (logoUrl ? `<img class="ev" src="${esc(logoUrl)}" alt="">` : '')
            + list.map((a) => `<img src="${esc(a.url)}" alt="">`).join('');
        strip.style.display = (list.length || logoUrl) ? 'flex' : 'none';
    }

    function renderSoldBoard(rows) {
        const grid = document.getElementById('sold-board-grid');
        const count = document.getElementById('sold-board-count');
        if (! grid) return;

        const list = Array.isArray(rows) ? rows : [];

        if (count) count.textContent = list.length === 1 ? '1 player' : `${list.length} players`;

        if (! list.length) {
            grid.innerHTML = '<div style="opacity:0.5;font-size:18px;">Nothing sold yet.</div>';
            return;
        }

        grid.innerHTML = list.map((row) => {
            const nm = row?.player?.name || 'Player';
            const tm = row?.sold_to_team?.name || '';
            const crest = row?.sold_to_team?.logo_path;
            const face = row?.player?.image
                ? `<img src="${esc(row.player.image)}" alt="">`
                : `<div class="blank">${esc(nm.substring(0, 2).toUpperCase())}</div>`;

            // The buying team's badge beside its name — a crest reads faster than a name.
            const team = tm
                ? '<div class="tm">'
                    + (crest ? `<img class="crest" src="${esc(crest)}" alt="">` : '')
                    + `<span>${esc(tm)}</span></div>`
                : '';

            return '<div class="sb-card">' + face + '<div class="who">'
                + `<div class="nm">${esc(nm)}</div>`
                + team
                + `<div class="amt">${amount(row?.final_price)}</div>`
                + '</div></div>';
        }).join('');
    }

    function fetchSoldBoard(board) {
        fetch(`/auction/${auctionId}/sold-players`)
            .then((res) => res.json())
            .then((data) => {
                renderSponsors(data?.sponsors, data?.tournamentLogo);

                const rows = data?.soldPlayers || [];

                /* The reel is the same cards, cut down to the biggest buys and shuffled — a
                   stream has less room than a wall, so the top of the market is all that fits
                   and all that is worth showing during a pause. */
                /* Top buys descend by price; the full board keeps the feed's own order, which
                   is most-recent first — the two answer different questions. */
                renderSoldBoard(board === 'highlights'
                    ? rows.filter(r => Number(r?.final_price) > 0)
                        .sort((a, b) => Number(b.final_price) - Number(a.final_price))
                        .slice(0, 10)
                    : rows);
            })
            .catch(() => {});
    }

    /* Idempotent: called by every poll with whatever the server says, and by the pushed frame
       the moment the button is pressed. Only a CHANGE refetches. */
    function applySoldBoard(board) {
        const el = document.getElementById('sold-board');
        if (! el) return;

        // Tolerates the old boolean as well as the board name, so a screen still running
        // yesterday's script is not left with a board it cannot turn off.
        const next = board === true ? 'sold' : (board || null);

        el.classList.toggle('hidden', ! next);

        const head = document.getElementById('sold-board-head');
        if (head) head.firstElementChild.textContent = next === 'highlights' ? 'TOP BUYS' : 'SOLD SO FAR';

        if (next && next !== soldBoardShowing) fetchSoldBoard(next);

        soldBoardShowing = next;
    }

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

        /*
         * What the hall needs beside the name: whether they keep wicket, and when they are here.
         * Same two fields the LED wall and the pools list read, so no screen can disagree.
         */
        const badges = [];

        if (p.is_wicket_keeper) {
            badges.push(
                '<span class="lt-badge lt-wk">'
                + '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a4 4 0 00-4 4v1.2A3 3 0 004 10v4a4 4 0 004 4h4a4 4 0 004-4v-4a3 3 0 00-2-2.8V6a4 4 0 00-4-4zm-2 4a2 2 0 114 0v1H8V6z"/></svg>'
                + 'WK</span>'
            );
        }

        if (p.travel_plan_label) {
            badges.push(
                '<span class="lt-badge lt-travel">'
                + '<svg viewBox="0 0 20 20" fill="currentColor"><g transform="rotate(45 10 10)"><path d="M17.5 13.3v-1.6l-6.6-4.2V3.1c0-.7-.6-1.2-1.2-1.2S8.4 2.4 8.4 3.1v4.4L1.8 11.7v1.6l6.6-2v4.4l-1.7 1.2v1.2l2.9-.8 2.9.8v-1.2l-1.7-1.2v-4.4l6.7 2z"/></g></svg>'
                + esc(p.travel_plan_label) + '</span>'
            );
        }

        document.getElementById('lt-name-badges').innerHTML = badges.join('');

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

        renderTickerOverlay(sealed);
    }

    /*
     * ── The sealed band, and the draw inside it ──
     *
     * Three states, and only three:
     *   · bidding has gone private   → say so, and say when it will be revealed
     *   · a lot is being drawn       → cycle the tied teams, on the SERVER's clock
     *   · the lot has landed         → hold the winner
     *
     * `drawn_at` and `spin_ms` come from the server and are the same two numbers the panel and
     * the LED wall use. The winner is recorded the instant DRAW LOT is pressed, so a screen that
     * settles as soon as it sees one gives the result away — here that would be to the stream,
     * ahead of the room.
     */
    let ltDrawCycle = null;
    let ltDrawSettledFor = null;

    function renderTickerOverlay(sealed) {
        const bar = document.getElementById('lt-overlay');
        if (! bar) return;

        const title = document.getElementById('lt-overlay-title');
        const sub = document.getElementById('lt-overlay-sub');
        const teamBox = document.getElementById('lt-overlay-team');
        const crest = document.getElementById('lt-overlay-crest');
        const nameEl = document.getElementById('lt-overlay-name');

        const stop = () => {
            if (ltDrawCycle) { clearInterval(ltDrawCycle); ltDrawCycle = null; }
        };

        if (! sealed || ! sealed.active) {
            stop();
            ltDrawSettledFor = null;
            bar.classList.remove('is-on');
            teamBox.classList.remove('is-on', 'winner', 'cycling');
            return;
        }

        bar.classList.add('is-on');
        renderOverlayLogos();

        const tie = sealed.tie;
        const teams = tie?.teams || [];

        // Sealed, no draw: the band is one sentence.
        if (! teams.length) {
            stop();
            ltDrawSettledFor = null;
            teamBox.classList.remove('is-on', 'winner', 'cycling');
            title.textContent = 'Sealed Bid In Progress';
            sub.textContent = sealed.round_number > 1
                ? `Round ${sealed.round_number} of ${sealed.total_rounds || 1} — revealed once every team has submitted`
                : 'Amounts are revealed once every team has submitted';
            return;
        }

        teamBox.classList.add('is-on');

        const winner = tie.lot_winner_team_id
            ? teams.find((t) => Number(t.id) === Number(tie.lot_winner_team_id))
            : null;

        const spinMs = Number(tie.spin_ms) || 0;
        const drawnAt = tie.drawn_at ? Date.parse(tie.drawn_at) : null;
        const stillSpinning = winner && drawnAt && spinMs > 0 && (Date.now() - drawnAt) < spinMs;

        if (winner && ! stillSpinning) {
            // Once. Every feed tick would otherwise replay the landing for the rest of the round.
            if (ltDrawSettledFor === winner.id) return;
            ltDrawSettledFor = winner.id;

            stop();
            title.textContent = 'Lot Drawn';
            sub.textContent = tie.amount ? `${teams.length} teams matched at ${amount(tie.amount)}` : '';
            crest.src = winner.logo || '';
            nameEl.textContent = winner.name;
            teamBox.classList.remove('cycling');
            teamBox.classList.add('winner');
            return;
        }

        ltDrawSettledFor = null;
        title.textContent = 'Drawing A Lot';
        sub.textContent = tie.amount ? `${teams.length} teams matched at ${amount(tie.amount)}` : `${teams.length} teams tied`;
        teamBox.classList.remove('winner');

        if (ltDrawCycle) return;   // already cycling — restarting it every tick judders

        let i = 0;
        const tick = () => {
            const team = teams[i % teams.length];
            crest.src = team.logo || '';
            nameEl.textContent = team.name;
            teamBox.classList.remove('cycling');
            void teamBox.offsetWidth;
            teamBox.classList.add('cycling');
            i++;
        };

        tick();
        ltDrawCycle = setInterval(tick, 160);
    }

    /** The event's marks on the band: whatever the ticker already has. */
    function renderOverlayLogos() {
        const wrap = document.getElementById('lt-overlay-logos');
        if (! wrap || wrap.dataset.filled) return;

        const sources = [
            document.querySelector('#lt-tournament-logo img'),
            document.querySelector('#lt-auction-logo img'),
            document.getElementById('lt-logo'),
        ].filter((el) => el && el.src);

        if (! sources.length) return;

        wrap.innerHTML = sources.map((el) => `<img src="${el.src}" alt="">`).join('');
        wrap.dataset.filled = '1';
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

                // The board, if the organizer has it up. From the feed, so a ticker opened or
                // reloaded mid-board comes back to the board.
                _breakRemaining = (d.break_remaining ?? null);
                /* A player on the block outranks any board: the auction is what the stream is
                   for, and a reel over a live lot hides the bidding from everyone watching. The
                   stored flag is untouched, so the board returns when the lot ends. */
                applySoldBoard(d.current_player ? null : d.public_board);

                lastCurrentPlayer = d.current_player || null;
                lastSealed = d.closed_bid || null;
                // Sealed transitions are pushed now (.sealed.changed), but the strip still
                // tracks this: the poll is the backstop for a screen that missed the frame.
                // scheduleTickerPoll() reads it after every fetch.
                sealedActive = !!(lastSealed && lastSealed.active);
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
    /* Adaptive, and it starts pessimistic.
       2s until push has actually proved itself, then 10s while the socket is up, and straight
       back to 2s the moment it drops. A fixed slow heartbeat would mean a dead socket costs
       ten seconds of staleness on air before anyone notices; this way a socket failure
       degrades to exactly the behaviour that was running before push existed. */
    /* No periodic requests while push is healthy — see the matching note on the LED wall.
       A timer is used only where nothing else can do the job: push being down (2s, the
       pre-push cadence) and a live sealed round, whose state transitions are the one part of
       the auction nothing broadcasts. Recovery comes from the reconnect, visibility and
       online handlers rather than from a heartbeat. */
    const POLL_MS = 2000;
    let pushConnected = false;
    let sealedActive = false;
    let _tickerTimer = null;

    function scheduleTickerPoll() {
        clearTimeout(_tickerTimer);
        if (pushConnected && !sealedActive) return;   // silence is the normal state
        _tickerTimer = setTimeout(tickerLoop, POLL_MS);
    }

    function tickerLoop() {
        Promise.resolve(poll())
            .catch(() => {})
            .finally(scheduleTickerPoll);
    }

    tickerLoop();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshNow('tab-visible');
    });
    window.addEventListener('online', () => refreshNow('network-online'));

    /* Refetch because something actually changed.
       Events trigger a refetch rather than each patching the DOM itself: the feed is the
       shape every renderer here already understands, so there is one render path instead of
       one per event. Debounced, because a sale arrives as two events and a bid burst as
       several. */
    let _refreshTimer = null;

    function refreshNow(reason) {
        clearTimeout(_refreshTimer);
        _refreshTimer = setTimeout(() => {
            console.info('[Ticker] refresh:', reason);
            poll();
        }, 150);
    }

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
    /*
     * On-screen transport badge, only with ?debug=1 in the URL.
     *
     * Push and poll run together by design, so the Network tab cannot answer "is this live?"
     * unless you know that WebSockets hide behind the Socket/WS filter and never appear under
     * Fetch/XHR. This says it in one glance instead. Absent unless asked for, so it can never
     * appear on air.
     */
    const showTransportBadge = new URLSearchParams(location.search).has('debug');
    let transportBadge = null;
    let pushedFrames = 0;

    if (showTransportBadge) {
        transportBadge = document.createElement('div');
        transportBadge.style.cssText = 'position:fixed;top:8px;left:8px;z-index:99999;'
            + 'font:700 12px/1 Roboto,sans-serif;padding:6px 10px;border-radius:6px;'
            + 'background:#7f1d1d;color:#fff;letter-spacing:.08em';
        transportBadge.textContent = 'POLLING (no push)';
        document.body.appendChild(transportBadge);
    }

    function setTransport(live, detail) {
        if (!transportBadge) return;
        transportBadge.style.background = live ? '#065f46' : '#7f1d1d';
        transportBadge.textContent = live ? `PUSHER LIVE — ${detail}` : `POLLING — ${detail}`;
    }

    (function subscribeToRaises() {
        const key = @json(config('broadcasting.connections.pusher.key'));
        const cluster = @json(config('broadcasting.connections.pusher.options.cluster'));

        // config(), not env(): env() returns null under `php artisan config:cache`, which
        // would take this down silently.
        if (!key) {
            console.warn('[Ticker] no Pusher key configured — polling only.');
            setTransport(false, 'no key configured');
            return;
        }

        /*
         * Say so, loudly.
         *
         * This returned silently when Echo was missing, so a blocked CDN script and a working
         * connection looked identical from the outside — the strip just carried on polling
         * with nothing to explain why. An ad blocker or privacy extension blocking
         * js.pusher.com is the most common cause, and it is invisible unless something says
         * it happened.
         */
        if (typeof Echo === 'undefined') {
            console.warn('[Ticker] Echo failed to load (js.pusher.com or cdnjs blocked by an '
                + 'extension?) — polling only.');
            setTransport(false, 'Echo script blocked');
            return;
        }

        try {
            /* On window, like the LED wall, so the connection can be inspected from the
               console on any of the live screens the same way:
                 window.Echo.connector.pusher.connection.state   // "connected"
               A local instance works identically but leaves no way to check whether the strip
               is actually live during a broadcast. */
            window.Echo = new Echo({ broadcaster: 'pusher', key, cluster, forceTLS: true });

            /* The connection reports itself, so "is this on push or polling?" is answerable
               from the console instead of by reading WebSocket frames. */
            const conn = window.Echo.connector.pusher.connection;
            conn.bind('connected', () => {
                console.info('[Ticker] LIVE — pusher connected (' + cluster + ')');
                setTransport(true, cluster);
                pushConnected = true;
                // Catch up on whatever was missed while down, THEN go quiet. This replaces
                // the heartbeat as the recovery path.
                refreshNow('pusher-connected');
                scheduleTickerPoll();
            });
            conn.bind('unavailable', () => {
                console.warn('[Ticker] pusher unavailable — polling only.');
                setTransport(false, 'unavailable');
                pushConnected = false;
                scheduleTickerPoll();   // back to the pre-push cadence
            });
            conn.bind('failed', () => {
                console.warn('[Ticker] pusher failed — polling only.');
                setTransport(false, 'failed');
                pushConnected = false;
                scheduleTickerPoll();   // back to the pre-push cadence
            });
            conn.bind('disconnected', () => {
                console.warn('[Ticker] pusher disconnected — polling only.');
                setTransport(false, 'disconnected');
                pushConnected = false;
                scheduleTickerPoll();   // back to the pre-push cadence
            });

            window.Echo.channel(`auction.${auctionId}`).listen('.bid.raised', (e) => {
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
                pushedFrames++;
                setTransport(true, cluster + ' · ' + pushedFrames + ' raise(s) pushed');

                // Price is on screen already; this follows with the restarted clock.
                refreshNow('bid');
            });

            /* The other things the admin changes. Each only triggers a refetch — the feed
               carries the authoritative shape, and these events publish different payload
               shapes to each other, so reading them directly would mean three more render
               paths to keep in step. */
            window.Echo.channel(`auction.${auctionId}`)
                .listen('.player.onbid', () => refreshNow('player-onbid'))
                .listen('.player-on-sold', () => refreshNow('sold'))
                // A sealed round opening, locking, revealing or drawing its lot. Nothing was
                // pushed for any of it before, so the ticker only caught up on its own poll.
                .listen('.sealed.changed', (e) => refreshNow('sealed:' + (e?.state ?? '?')))
                // The board going up or coming down, applied at once rather than on the next tick.
                /* Targeted: a board sent to the wall alone must not flash up here and then be
                   taken away by the next feed read, which does respect the target. */
                .listen('.board.changed', (e) => {
                    const target = e?.target ?? 'both';
                    if (target !== 'both' && target !== 'ticker') return;

                    applySoldBoard(e?.board);
                });

            /* Pause, resume, end and restart publish on their own channel. Without this the
               strip would count down through a pause until the heartbeat came round — on air. */
            window.Echo.channel(`auction.public.${auctionId}`)
                .listen('.auction.status', (e) => refreshNow('status:' + (e?.status ?? '?')));
        } catch (err) {
            console.warn('[Ticker] live updates unavailable, polling only:', err);
            setTransport(false, 'init error');
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
