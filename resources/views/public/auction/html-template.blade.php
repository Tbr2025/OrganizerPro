@php
    /**
     * A standalone document for an HTML-mode auction template.
     *
     * Deliberately NOT rendered inside public/auction/live.blade.php: that page carries
     * a Tailwind CDN build, a confetti library and ~625 lines of generated CSS that an
     * author's rules would collide with, plus platform chrome they could hide. "Author
     * the whole screen" only means anything if the screen is theirs alone.
     */
    // Passed in with the response that also sets the matching CSP header.
    $nonce = $nonce ?? '';
    $transparent = (bool) $template->html_transparent_bg;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $auction->name }}</title>
    <style nonce="{{ $nonce }}">
        html, body {
            margin: 0; padding: 0; width: 100%; min-height: 100vh; overflow: hidden;
            @if($transparent)
                /* OBS browser source: let the stream show through. */
                background: transparent !important;
            @else
                background: #000;
            @endif
        }
        #html-canvas { width: 100%; min-height: 100vh; }
    </style>

    {{-- The authored stylesheet. Escaped so a stray </style> cannot break out of the
         block and start writing markup of its own. --}}
    <style nonce="{{ $nonce }}">{!! str_replace('</style', '<\/style', (string) $template->html_css) !!}</style>
</head>
<body>
    <div id="html-canvas"></div>

    {{-- The template travels as JSON data, never as live markup, so nothing in it is
         parsed until the runtime has escaped every value substituted into it. --}}
    <script type="application/json" id="tpl-source">@json((string) $template->html_body)</script>
    <script type="application/json" id="tpl-static">@json($staticTokens)</script>

    <script nonce="{{ $nonce }}">
        const auctionId = {{ $auction->id }};
        const refreshMs = {{ $template->htmlRefreshMs() }};
        const RAW = JSON.parse(document.getElementById('tpl-source').textContent);
        const STATIC_TOKENS = JSON.parse(document.getElementById('tpl-static').textContent);

        let amountUnit = { label: 'Points', prefix: false };
        let mounted = null;

        /* Attribute-safe, not merely text-safe. Player names come from public
           registration, so a name containing a quote is a stored-XSS payload
           regardless of who wrote the template. */
        function esc(v) {
            return String(v ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        /* K/M/B ladder, matching the rest of the auction. */
        function figure(value) {
            if (value === null || value === undefined || value === '') return '';
            const n = Number(value);
            if (!isFinite(n)) return '';
            const abs = Math.abs(n);
            if (abs >= 1e15) return '∞';
            let scaled = n, suffix = '';
            if (abs >= 1e9) { scaled = n / 1e9; suffix = 'B'; }
            else if (abs >= 1e6) { scaled = n / 1e6; suffix = 'M'; }
            else if (abs >= 1e3) { scaled = n / 1e3; suffix = 'K'; }
            return scaled.toFixed(2).replace(/\.?0+$/, '') + suffix;
        }

        function amount(value) {
            const f = figure(value);
            if (f === '' || f === '∞') return f;
            return amountUnit.prefix ? amountUnit.label + f : f + ' ' + amountUnit.label;
        }

        function tokensFrom(d) {
            const p = d.current_player;
            const stats = (p && p.stats) || {};
            const parts = String((p && p.name) || '').trim().split(/\s+/);
            const last = parts.length > 1 ? parts[parts.length - 1] : '';
            const first = parts.length > 1 ? parts.slice(0, -1).join(' ') : (parts[0] || '');

            const hasBid = !!(p && p.leading_team);
            const status = p ? 'on_auction' : 'idle';

            return Object.assign({}, STATIC_TOKENS, {
                player_name: p ? p.name : '',
                player_first_name: first,
                player_last_name: last,
                player_role: p ? (p.role || '') : '',
                player_image: p ? (p.image || '') : '',
                // Paired flags so an author can drive display:none without conditionals.
                has_player_image: p && p.image ? '1' : '',
                player_matches: stats.matches ?? '',
                player_runs: stats.runs ?? '',
                player_wickets: stats.wickets ?? '',
                player_lot: p ? (p.lot_number ?? '') : '',

                base_price: p ? amount(p.base_price) : '',
                current_bid: p ? amount(hasBid ? p.current_price : p.base_price) : '',
                base_price_raw: p ? (p.base_price ?? '') : '',
                current_bid_raw: p ? (p.current_price ?? '') : '',
                amount_unit: amountUnit.label,

                leading_team: p ? (p.leading_team || '') : '',
                leading_team_short: p ? (p.leading_team_short || p.leading_team || '') : '',
                team_logo: '',
                has_team_logo: '',
                status: status,
                status_label: hasBid ? 'Current Bid' : (p ? 'No Bids' : 'Up Next'),

                pool_name: d.active_pool ? d.active_pool.name : '',
                pool_done: d.active_pool ? d.active_pool.done : '',
                pool_total: d.active_pool ? d.active_pool.total : '',
                sold_count: d.stats ? d.stats.sold : '',
                unsold_count: d.stats ? d.stats.unsold : '',
                total_count: d.stats ? d.stats.total : '',

                timer_seconds: d.timer && d.timer.enabled && d.timer.remaining !== null
                    ? d.timer.remaining : '',
                final_call_label: '',
                squad_min: d.squad ? d.squad.min : '',
                squad_max: d.squad && d.squad.max ? d.squad.max : '',
            });
        }

        function render(d) {
            const map = tokensFrom(d);
            const out = RAW.replace(/\{([a-z0-9_]+)\}/g, (m, key) =>
                Object.prototype.hasOwnProperty.call(map, key) ? esc(map[key]) : ''
            );

            // Only touch the DOM when something actually changed. Reassigning innerHTML
            // restarts every CSS animation on the page, so an unconditional swap would
            // make a template with any transition flicker twice a second.
            if (out === mounted) return;
            mounted = out;
            document.getElementById('html-canvas').innerHTML = out;
        }

        function poll() {
            fetch(`/auction/${auctionId}/ticker-feed`)
                .then(r => r.json())
                .then(d => {
                    if (!d || !d.success) return;
                    if (d.amount_unit) amountUnit = d.amount_unit;
                    render(d);
                })
                .catch(() => { /* a dropped poll is not worth blanking the screen for */ });
        }

        poll();
        setInterval(poll, refreshMs);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'r') location.reload();
            if (e.key === 'f') {
                document.fullscreenElement
                    ? document.exitFullscreen().catch(() => {})
                    : document.documentElement.requestFullscreen().catch(() => {});
            }
        });
    </script>
</body>
</html>
