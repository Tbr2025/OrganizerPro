<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Ticker | {{ $auction->name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
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

        /* ── Lower third: the player on the block ── */
        #lower-third {
            position: fixed; bottom: 150px; left: 60px; right: 60px;
            display: flex; align-items: stretch; gap: 0;
            border-radius: 18px; overflow: hidden;
            animation: slideUp 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes slideUp {
            from { transform: translateY(28px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }
        #lt-photo {
            width: 118px; flex-shrink: 0;
            background: rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: center;
        }
        #lt-photo img { width: 100%; height: 100%; object-fit: cover; }
        #lt-name-block { padding: 18px 30px; flex: 1; min-width: 0; }
        #lt-name { font-size: 46px; font-weight: 900; line-height: 1; letter-spacing: -0.5px; }
        #lt-role { font-size: 17px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; opacity: 0.62; margin-top: 8px; }

        #lt-bid-block {
            padding: 18px 36px; flex-shrink: 0;
            display: flex; flex-direction: column; justify-content: center; align-items: flex-end;
            border-left: 1px solid rgba(255,255,255,0.10);
            min-width: 300px;
        }
        #lt-bid-label { font-size: 13px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; opacity: 0.55; }
        #lt-bid { font-size: 52px; font-weight: 900; line-height: 1; color: var(--secondary); margin-top: 4px; }
        #lt-team { font-size: 18px; font-weight: 700; margin-top: 6px; opacity: 0.9; }

        /* Clock, riding the lower third's right edge. */
        #lt-clock {
            padding: 0 30px; flex-shrink: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            border-left: 1px solid rgba(255,255,255,0.10);
            min-width: 150px;
        }
        #lt-seconds { font-size: 60px; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; }
        #lt-call {
            margin-top: 6px; font-size: 15px; font-weight: 900;
            letter-spacing: 2px; text-transform: uppercase;
            padding: 3px 12px; border-radius: 999px;
        }

        /* ── Team purses, above the lower third ── */
        #purses {
            position: fixed; bottom: 268px; left: 60px; right: 60px;
            display: flex; gap: 10px; justify-content: flex-start; flex-wrap: wrap;
        }
        .purse {
            display: flex; align-items: center; gap: 9px;
            padding: 8px 15px 8px 8px; border-radius: 999px;
        }
        .purse img, .purse .initials {
            width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
            background: rgba(255,255,255,0.10);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 900; flex-shrink: 0;
        }
        .purse .meta { line-height: 1.15; }
        .purse .nm { font-size: 13px; font-weight: 700; }
        .purse .amt { font-size: 14px; font-weight: 900; color: var(--secondary); font-variant-numeric: tabular-nums; }
        .purse .sq { font-size: 11px; opacity: 0.55; font-variant-numeric: tabular-nums; }

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
            position: fixed; bottom: 150px; left: 60px;
            padding: 22px 40px; border-radius: 18px;
            font-size: 30px; font-weight: 900; letter-spacing: 1px;
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

    {{-- Team purses --}}
    <div id="purses"></div>

    {{-- The player on the block --}}
    <div id="lower-third" class="glass hidden">
        <div id="lt-photo"><img id="lt-img" src="" alt=""></div>
        <div id="lt-name-block">
            <div id="lt-name"></div>
            <div id="lt-role"></div>
        </div>
        <div id="lt-bid-block">
            <div id="lt-bid-label">Current Bid</div>
            <div id="lt-bid"></div>
            <div id="lt-team"></div>
        </div>
        <div id="lt-clock" class="hidden">
            <div id="lt-seconds"></div>
            <div id="lt-call" class="hidden"></div>
        </div>
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

    function renderClock() {
        const wrap = document.getElementById('lt-clock');
        const secs = document.getElementById('lt-seconds');
        const call = document.getElementById('lt-call');

        if (!clockEnabled || clockRemaining === null) {
            wrap.classList.add('hidden');
            return;
        }

        wrap.classList.remove('hidden');
        const s = Math.max(0, clockRemaining);
        secs.textContent = s;

        const c = finalCallFor(s, callStages);
        if (c) {
            call.classList.remove('hidden');
            call.textContent = c.label;
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

    function renderSales(sales) {
        const strip = document.getElementById('sales-strip');
        const track = document.getElementById('sales-track');

        if (!sales.length) {
            strip.classList.add('hidden');
            return;
        }
        strip.classList.remove('hidden');

        const html = sales.map(s => `
            <div class="sale">
                ${s.team_logo
                    ? `<img src="${s.team_logo}" alt="">`
                    : `<span class="initials">${initials(s.team_name)}</span>`}
                <span class="who">${s.player_name}</span>
                <span class="to">to</span>
                <span class="who">${s.team_name}</span>
                <span class="price">${amount(s.price)}</span>
            </div>`).join('');

        // Two copies so scrolling past the first reveals the second, seamlessly.
        track.innerHTML = html + html;
        salesWidth = track.scrollWidth / 2;

        if (!salesAnim) {
            const step = () => {
                salesOffset -= 1.1;
                if (salesWidth > 0 && Math.abs(salesOffset) >= salesWidth) salesOffset = 0;
                track.style.transform = `translateX(${salesOffset}px)`;
                salesAnim = requestAnimationFrame(step);
            };
            salesAnim = requestAnimationFrame(step);
        }
    }

    function renderPurses(teams) {
        document.getElementById('purses').innerHTML = teams.map(t => `
            <div class="purse glass">
                ${t.logo ? `<img src="${t.logo}" alt="">` : `<span class="initials">${initials(t.short_name || t.name)}</span>`}
                <div class="meta">
                    <div class="nm">${t.short_name || t.name}</div>
                    <div class="amt">${t.remaining === null ? '—' : amount(t.remaining)}</div>
                    <div class="sq">${t.players}/${t.squad_required} squad</div>
                </div>
            </div>`).join('');
    }

    function renderCurrent(p) {
        const lt = document.getElementById('lower-third');
        const idle = document.getElementById('idle');

        if (!p) {
            lt.classList.add('hidden');
            idle.classList.remove('hidden');
            return;
        }

        idle.classList.add('hidden');
        lt.classList.remove('hidden');

        document.getElementById('lt-name').textContent = p.name;
        document.getElementById('lt-role').textContent = p.role || '';

        const img = document.getElementById('lt-img');
        const photo = document.getElementById('lt-photo');
        if (p.image) { img.src = p.image; photo.style.display = 'flex'; }
        else { photo.style.display = 'none'; }

        const hasBid = !!p.leading_team;
        document.getElementById('lt-bid-label').textContent = hasBid ? 'Current Bid' : 'Base Price';
        document.getElementById('lt-bid').textContent = amount(hasBid ? p.current_price : p.base_price);
        document.getElementById('lt-team').textContent = p.leading_team || '';
    }

    function poll() {
        fetch(`/auction/${auctionId}/ticker-feed`)
            .then(r => r.json())
            .then(d => {
                if (!d?.success) return;

                if (d.amount_unit) AMOUNT_UNIT = d.amount_unit;

                renderCurrent(d.current_player);
                renderPurses(d.teams || []);
                renderSales(d.recent_sales || []);

                // Clock only runs while someone is actually on the block.
                if (d.current_player && d.auction_status !== 'paused') {
                    clockEnabled = !!d.timer?.enabled;
                    clockRemaining = d.timer?.remaining ?? null;
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

    poll();
    setInterval(poll, 2000);

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
