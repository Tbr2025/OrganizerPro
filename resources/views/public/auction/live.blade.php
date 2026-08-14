<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auction | {{ $auction->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    @php
        $primaryHex = $auction->primary_color ?? '#00bcd4';
        $secondaryHex = $auction->secondary_color ?? '#22c55e';
        // Parse hex to RGB for rgba() usage
        $pR = hexdec(substr($primaryHex, 1, 2));
        $pG = hexdec(substr($primaryHex, 3, 2));
        $pB = hexdec(substr($primaryHex, 5, 2));
        $sR = hexdec(substr($secondaryHex, 1, 2));
        $sG = hexdec(substr($secondaryHex, 3, 2));
        $sB = hexdec(substr($secondaryHex, 5, 2));

        // Box/text shadow preset maps
        $boxShadowMap = [
            'none' => 'none',
            'small' => '0 2px 8px rgba(0,0,0,0.3)',
            'medium' => '0 4px 20px rgba(0,0,0,0.4)',
            'large' => '0 8px 40px rgba(0,0,0,0.5)',
            'glow' => '0 0 20px rgba('.$pR.','.$pG.','.$pB.',0.6)',
        ];
        $textShadowMap = [
            'none' => 'none',
            'subtle' => '0 1px 3px rgba(0,0,0,0.5)',
            'strong' => '0 2px 8px rgba(0,0,0,0.8)',
            'glow' => '0 0 10px rgba('.$pR.','.$pG.','.$pB.',0.6), 0 0 20px rgba('.$pR.','.$pG.','.$pB.',0.3)',
        ];

        // Helper: generate inline CSS from element position/styling data
        //
        // Guarded because these are GLOBAL function declarations inside a Blade view:
        // rendering this template twice in one PHP process — two auctions in one request,
        // or two renders in one test — was a fatal "cannot redeclare".
        if (! function_exists('elementStyle')):
        function elementStyle($positions, $key, $defaults = [], $boxShadowMap = [], $textShadowMap = []) {
            $p = array_merge($defaults, $positions[$key] ?? []);
            $css = '';
            /*
             * A blank field is "not set", not zero.
             *
             * The editor stores an untouched width/height as an empty string, and `isset()`
             * is true for `''` — which emitted the invalid declaration `width:px`. The browser
             * drops the whole declaration, so nothing broke visibly, but every element carried
             * two dead rules and any future shorthand built the same way would silently fail.
             */
            $has = fn($k) => isset($p[$k]) && $p[$k] !== '' && $p[$k] !== null;
            if ($has('top')) $css .= 'top:'.$p['top'].'px;';
            if ($has('bottom') && !$has('top')) $css .= 'bottom:'.$p['bottom'].'px;';
            if ($has('left')) $css .= 'left:'.$p['left'].'px;';
            if ($has('width')) $css .= 'width:'.$p['width'].'px;';
            if ($has('height')) $css .= 'height:'.$p['height'].'px;';
            if ($has('fontSize')) $css .= 'font-size:'.$p['fontSize'].'px;';
            if (!empty($p['color'])) $css .= 'color:'.$p['color'].';';
            if (!empty($p['bgColor'])) {
                $bgO = floatval($p['bgOpacity'] ?? 1);
                $bg = $p['bgColor'];
                if ($bgO < 1 && preg_match('/^#([0-9a-fA-F]{6})$/', $bg, $hm)) {
                    $r = hexdec(substr($hm[1],0,2));
                    $g = hexdec(substr($hm[1],2,2));
                    $b = hexdec(substr($hm[1],4,2));
                    $css .= 'background:rgba('.$r.','.$g.','.$b.','.$bgO.');';
                } else {
                    $css .= 'background:'.$bg.';';
                }
            }
            $css .= 'opacity:'.($p['opacity'] ?? 1).';';
            $hasCornersSet = !empty($p['borderRadiusTL']) || !empty($p['borderRadiusTR']) || !empty($p['borderRadiusBL']) || !empty($p['borderRadiusBR']);
            if ($hasCornersSet) {
                $css .= 'border-radius:'.($p['borderRadiusTL'] ?? 0).'px '.($p['borderRadiusTR'] ?? 0).'px '.($p['borderRadiusBR'] ?? 0).'px '.($p['borderRadiusBL'] ?? 0).'px;';
            } elseif (!empty($p['borderRadius'])) {
                $css .= 'border-radius:'.$p['borderRadius'].'px;';
            }
            $hasPadSides = !empty($p['paddingTop']) || !empty($p['paddingRight']) || !empty($p['paddingBottom']) || !empty($p['paddingLeft']);
            if ($hasPadSides) {
                $css .= 'padding:'.($p['paddingTop'] ?? 0).'px '.($p['paddingRight'] ?? 0).'px '.($p['paddingBottom'] ?? 0).'px '.($p['paddingLeft'] ?? 0).'px;';
            } elseif (!empty($p['padding'])) {
                $css .= 'padding:'.$p['padding'].'px;';
            }
            $css .= 'font-weight:'.($p['fontWeight'] ?? 'bold').';';
            $css .= 'z-index:'.($p['zIndex'] ?? 10).';';
            $bs = $p['boxShadow'] ?? 'none';
            if ($bs !== 'none' && isset($boxShadowMap[$bs])) $css .= 'box-shadow:'.$boxShadowMap[$bs].';';
            $ts = $p['textShadow'] ?? 'none';
            if ($ts !== 'none' && isset($textShadowMap[$ts])) $css .= 'text-shadow:'.$textShadowMap[$ts].';';
            // New properties
            if (!empty($p['margin'])) $css .= 'margin:'.$p['margin'].'px;';
            if (!empty($p['letterSpacing'])) $css .= 'letter-spacing:'.$p['letterSpacing'].'px;';
            if (!empty($p['lineHeight'])) $css .= 'line-height:'.$p['lineHeight'].';';
            if (!empty($p['textAlign']) && $p['textAlign'] !== 'left') $css .= 'text-align:'.$p['textAlign'].';';
            if (!empty($p['textTransform']) && $p['textTransform'] !== 'none') $css .= 'text-transform:'.$p['textTransform'].';';
            if (!empty($p['rotation'])) $css .= 'transform:rotate('.$p['rotation'].'deg);';
            if (!empty($p['borderStyle']) && $p['borderStyle'] !== 'none') {
                $css .= 'border:'.($p['borderWidth'] ?? 1).'px '.$p['borderStyle'].' '.($p['borderColor'] ?? '#fff').';';
            }
            return $css;
        }

        endif;

        // Helper: check if element is visible
        if (! function_exists('isVisible')):
        function isVisible($positions, $key) {
            return ($positions[$key]['visible'] ?? true) !== false && ($positions[$key]['visible'] ?? 1) != 0;
        }
        endif;
    @endphp
    <style>
        :root {
            --primary: {{ $primaryHex }};
            --secondary: {{ $secondaryHex }};
            --primary-rgb: {{ $pR }}, {{ $pG }}, {{ $pB }};
            --secondary-rgb: {{ $sR }}, {{ $sG }}, {{ $sB }};
        }

        body {
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
        }

        /* ── Card container (template-driven) ── */
        .card-container {
            position: relative;
            width: {{ $canvasWidth }}px;
            height: {{ $canvasHeight }}px;

            /*
             * Never shrink below the designed canvas.
             *
             * body is a flex container, so this card was a flex ITEM — and a flex item's default
             * `flex-shrink: 1` let it collapse to the viewport width. At 1601px wide nothing
             * shrank and the wall looked right, which is why this hid: zoom the browser in (or
             * open it on a smaller screen) and the box narrowed to, say, 700px while every
             * element inside stayed pinned at its designed offset — up to left:1050px — so the
             * right-hand half was cut off by the overflow rule below. The stats table slid under
             * the sponsor logo, the photo swallowed the panel, and the text did not shrink at all.
             *
             * The card must keep its 1601x910 layout and be sized ONLY by scaleLive()'s
             * transform, which scales the artwork and every piece of text on it together.
             */
            flex-shrink: 0;
            min-width: {{ $canvasWidth }}px;
            min-height: {{ $canvasHeight }}px;

            /*
             * The canvas is the edge of the artwork, so anything hanging over it is cut.
             *
             * Every element on this card is absolutely positioned, and a template is free to
             * place one so that it extends past the canvas — a cut-out player photo sized to
             * bleed off the bottom, or a decorative panel taller than the card. Without a clip
             * they were drawn OUTSIDE the card, over the page behind it: a player's shoulder
             * and a diagonal graphic hanging below the artwork on the wall, and the same spill
             * baked into every downloaded card.
             *
             * The overlays that are meant to cover the whole screen — the closing-call dim,
             * the clock, the restart notice — are position:fixed and not
             * children of this element, so none of them is affected by this.
             */
            overflow: hidden;
            @if($backgroundUrl)
            /*
             * The artwork fills the canvas — it does not sit at 1:1 inside it.
             *
             * `background-size: auto` drew the image at its own pixel size and centred it, so an
             * artwork that was not EXACTLY the template's canvas was silently cropped and offset.
             * Production's background is 1920x1080 against a 1601x900 canvas, so its printed
             * table sat about 1.2x away from the element positions laid over it: every figure
             * landed off its panel, while the same template with a 1600x900 artwork looked
             * perfect. Nothing about the positions was wrong — the picture underneath them was
             * the wrong size.
             *
             * 100% 100% rather than `cover`: the canvas defines the coordinate space, so the
             * artwork must map onto it exactly. Anything that crops re-introduces the same class
             * of drift for a template whose aspect differs slightly.
             */
            background: url('{{ $backgroundUrl }}') no-repeat center center;
            background-size: 100% 100%;
            @endif
        }

        /* ── Closing call ("going once, going twice") ── */
        @keyframes finalCallPulse {
            0%   { transform: scale(0.85); opacity: 0; }
            35%  { transform: scale(1.06); opacity: 1; }
            60%  { transform: scale(1); }
            100% { transform: scale(1.02); }
        }

        /* ── IPL SOLD dramatic effects ── */
        .card-container.sold-state {
            animation: sold-brightness 1.5s ease-out forwards;
        }
        @keyframes sold-brightness {
            0% { filter: brightness(1); }
            20% { filter: brightness(1.6); }
            100% { filter: brightness(1); }
        }
        .card-container.sold-state::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, rgba(var(--secondary-rgb), 0.2) 0%, transparent 70%);
            animation: sold-burst 1.5s ease-out forwards;
            pointer-events: none;
            z-index: 8;
        }
        @keyframes sold-burst {
            0% { opacity: 0; transform: scale(0.5); }
            40% { opacity: 1; transform: scale(1.1); }
            100% { opacity: 0; transform: scale(1.3); }
        }
        #sold-text.sold-active {
            animation: sold-text-entrance 0.6s ease-out forwards;
            text-shadow: 0 0 20px rgba(34,197,94,0.8), 0 0 40px rgba(34,197,94,0.4) !important;
            color: #22c55e !important;
        }
        @keyframes sold-text-entrance {
            0% { transform: scale(0.3); opacity: 0; }
            60% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        #sold-badge.sold-entrance {
            animation: badge-flash-in 0.45s ease-out forwards;
        }
        /*
         * Flashes in; it does not spin.
         *
         * A badge that arrives spinning through 180 degrees reads as a graphic doing a trick,
         * and it fights the template's own rotation — an author who tilts the badge in the
         * editor had that tilt overwritten by the animation's final `rotate(0deg)`. A short
         * fade with the faintest overshoot lands it without either problem.
         */
        @keyframes badge-flash-in {
            0%   { transform: scale(0.86); opacity: 0; }
            55%  { transform: scale(1.04); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* HTML fallback sold stamp (when no sticker uploaded) */
        /* ── Wax-seal stamps ──
           Round seals rather than the old bordered rectangle: a double ring, a rotated
           word, and a rope-notch edge, struck onto the card at an angle. Green for SOLD,
           red for UNSOLD, identical geometry so the two read as a matched pair. */
        .auction-seal {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            text-transform: uppercase;
            font-weight: 900;
            transform: rotate(-14deg);
            position: relative;
            isolation: isolate;
        }
        /* Outer ring + inner hairline ring. */
        .auction-seal::before,
        .auction-seal::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .auction-seal::before { inset: 0; border: 6px solid currentColor; }
        .auction-seal::after  { inset: 11px; border: 2px solid currentColor; opacity: 0.55; }

        .auction-seal .seal-word {
            font-size: 1.5em;
            letter-spacing: 3px;
            line-height: 1;
            z-index: 1;
        }
        .auction-seal .seal-sub {
            font-size: 0.45em;
            letter-spacing: 4px;
            opacity: 0.75;
            margin-top: 4px;
            z-index: 1;
        }

        .sold-stamp {
            color: #22c55e;
            /* Radial fill so the seal reads as pressed wax rather than a flat box. */
            background:
                radial-gradient(circle at 35% 30%, rgba(34,197,94,0.30) 0%, rgba(34,197,94,0.10) 45%, rgba(2,10,6,0.88) 100%);
            text-shadow: 0 0 18px rgba(34,197,94,0.75), 0 2px 4px rgba(0,0,0,0.6);
            box-shadow:
                0 0 40px rgba(34,197,94,0.45),
                inset 0 0 26px rgba(34,197,94,0.20),
                0 10px 30px rgba(0,0,0,0.55);
        }

        .unsold-stamp {
            color: #ef4444;
            background:
                radial-gradient(circle at 35% 30%, rgba(239,68,68,0.30) 0%, rgba(239,68,68,0.10) 45%, rgba(12,2,2,0.88) 100%);
            text-shadow: 0 0 18px rgba(239,68,68,0.75), 0 2px 4px rgba(0,0,0,0.6);
            box-shadow:
                0 0 40px rgba(239,68,68,0.45),
                inset 0 0 26px rgba(239,68,68,0.20),
                0 10px 30px rgba(0,0,0,0.55);
        }
        #team-logo.sold-entrance {
            animation: team-logo-entrance 0.45s ease-out 0.15s forwards;
            opacity: 0;
        }
        /* Flash in, like the badge above — a winning team's logo tumbling into place was the
           same trick twice on the same screen. */
        @keyframes team-logo-entrance {
            0%   { transform: scale(0.9); opacity: 0; }
            55%  { transform: scale(1.03); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ── IPL UNSOLD dramatic effects ── */
        .card-container.unsold-state {
            filter: brightness(0.6) saturate(0.5);
            transition: filter 0.5s ease;
        }
        .card-container.unsold-state::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 30%, rgba(239,68,68,0.15) 100%);
            animation: unsold-fade 0.8s ease-out forwards;
            pointer-events: none;
            z-index: 8;
        }
        @keyframes unsold-fade {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        #sold-text.unsold-active {
            animation: unsold-text-entrance 0.5s ease-out forwards;
            text-shadow: 0 0 20px rgba(239,68,68,0.8), 0 0 40px rgba(239,68,68,0.4) !important;
            color: #ef4444 !important;
        }
        @keyframes unsold-text-entrance {
            0% { transform: scale(2); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ── IPL SKIPPED effects ── */
        .card-container.skipped-state {
            filter: brightness(0.7) saturate(0.6);
            transition: filter 0.5s ease;
        }
        .card-container.skipped-state::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 30%, rgba(245,158,11,0.12) 100%);
            pointer-events: none;
            z-index: 8;
        }
        #sold-text.skipped-active {
            text-shadow: 0 0 15px rgba(245,158,11,0.7) !important;
            color: #f59e0b !important;
        }

        #result-banner {
            position: fixed; left: 50%; top: 8%; transform: translateX(-50%);
            z-index: 9996; display: flex; align-items: center; gap: 18px;
            padding: 12px 38px; border-radius: 9999px;
            background: rgba(2,6,23,0.88); backdrop-filter: blur(10px);
            white-space: nowrap;
        }
        #result-banner #result-word {
            font-size: 1.9rem; font-weight: 900; letter-spacing: 0.18em; text-transform: uppercase;
        }
        #result-banner #result-name {
            font-size: 1.6rem; font-weight: 700; color: #fff;
        }

        /* Current bidder. Same position and shape as the result banner, so the two read as
           one slot at the top of the screen rather than two competing strips. */
        #bid-flash {
            position: fixed; left: 50%; top: 8%; transform: translateX(-50%);
            z-index: 9995; display: flex; align-items: center; gap: 18px;
            padding: 12px 38px; border-radius: 9999px;
            background: rgba(2,6,23,0.88); backdrop-filter: blur(10px);
            border: 2px solid rgba(34,197,94,0.55);
            white-space: nowrap;
        }
        #bid-flash #bid-flash-team {
            font-size: 1.9rem; font-weight: 900; letter-spacing: 0.06em;
            text-transform: uppercase; color: #ffffff;
        }
        #bid-flash #bid-flash-amount {
            font-size: 1.9rem; font-weight: 900; color: #22c55e;
        }
        /* Pulses only when the figure CHANGES — a banner that flashes continuously stops
           being read after the first minute. Re-armed by removing and re-adding the class. */
        #bid-flash.bid-flash-pulse {
            animation: bid-flash-pop 0.9s ease-out 2;
        }
        @keyframes bid-flash-pop {
            0%   { transform: translateX(-50%) scale(1);    box-shadow: 0 0 0 0 rgba(34,197,94,0.55); }
            35%  { transform: translateX(-50%) scale(1.06); box-shadow: 0 0 42px 12px rgba(34,197,94,0.45); }
            100% { transform: translateX(-50%) scale(1);    box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        #result-banner.is-unsold { border: 2px solid #f43f5e; box-shadow: 0 0 54px rgba(244,63,94,0.4); }
        #result-banner.is-unsold #result-word { color: #fb7185; }
        #result-banner.is-sold { border: 2px solid #22c55e; box-shadow: 0 0 54px rgba(34,197,94,0.4); }
        #result-banner.is-sold #result-word { color: #4ade80; }

        /* ── Final call with no bids ──
           Once the closing calls start and nobody has bid, the outcome is effectively
           decided. Saying so is the difference between an audience watching a countdown and
           an audience understanding that a player is about to go unsold — and it gives a
           team the one prompt that might still change it. */
        .card-container.about-to-go-unsold { filter: grayscale(0.85) brightness(0.62); transition: filter 0.6s ease; }

        /* The whole screen darkens once the closing call starts, not only the card. The
           hall reads a change across the wall long before it reads any text, and this is the
           moment the room has to feel: bidding is closing.

           An OVERLAY, not a filter on <body>. A CSS filter creates a containing block for
           fixed-position descendants, which would tear the clock and the banners out of
           their positions the instant the call began. This sits above the stage and below
           those banners (z-index 9000 against their 9995+), so what still matters stays
           bright and readable on top of it. */
        #final-call-dim {
            position: fixed; inset: 0; z-index: 9000;
            background: rgba(2,6,23,0.62);
            pointer-events: none; opacity: 0;
            transition: opacity 0.45s ease;
        }
        #final-call-dim.is-on { opacity: 1; }

        #unsold-warning {
            position: fixed; left: 50%; top: 8%; transform: translateX(-50%);
            z-index: 9997; padding: 14px 40px; border-radius: 9999px;
            background: rgba(2,6,23,0.86); backdrop-filter: blur(10px);
            border: 2px solid #f43f5e; box-shadow: 0 0 60px rgba(244,63,94,0.45);
            font-size: 2rem; font-weight: 900; letter-spacing: 0.14em; text-transform: uppercase;
            color: #fff; white-space: nowrap;
            animation: unsoldWarnPulse 1.4s ease-in-out infinite;
        }
        @keyframes unsoldWarnPulse {
            0%, 100% { opacity: 0.92; }
            50% { opacity: 1; box-shadow: 0 0 84px rgba(244,63,94,0.7); }
        }

        /* ── Waiting screen ── */
        #waiting-screen {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            @if($auction->waiting_background_image_url)
            background: url('{{ $auction->waiting_background_image_url }}') no-repeat center center;
            background-size: cover;
            @else
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0a0a0a 100%);
            @endif
            z-index: 100;
            overflow: hidden;
        }

        #waiting-screen h1 {
            font-size: 72px;
            color: var(--primary);
            animation: pulse 2s ease-in-out infinite;
            text-shadow: 0 0 30px rgba(var(--primary-rgb), 0.5);
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.02); }
        }

        /* ── Waiting screen: bat and ball ──
           Pure CSS and inline SVG. No image and no library, so the screen has nothing to
           download and cannot sit on a broken asset while a hall watches it. One shared
           1.5s loop keeps the swing and the strike in sync — separate durations drift
           apart within a few cycles and the bat starts missing. */
        .cricket-stage {
            position: relative;
            /* Sized for a hall, not a laptop: at 460x250 on a projector this was a detail
               in the middle of the screen rather than something anyone would look at. */
            width: 740px; height: 400px;
            margin-bottom: 12px;
        }

        /* Pivots at the handle, like a real backlift. */
        .cricket-bat {
            position: absolute; left: 52%; top: 14px;
            width: 100px; height: 322px;
            transform-origin: 50% 9%;
            animation: batSwing 1.5s cubic-bezier(0.34, 1.15, 0.5, 1) infinite;
            filter: drop-shadow(0 10px 26px rgba(0,0,0,0.55));
        }
        @keyframes batSwing {
            0%   { transform: translateX(-50%) rotate(16deg); }
            26%  { transform: translateX(-50%) rotate(54deg); }   /* back-lift */
            38%  { transform: translateX(-50%) rotate(-30deg); }  /* contact */
            54%  { transform: translateX(-50%) rotate(-46deg); }  /* follow-through */
            80%  { transform: translateX(-50%) rotate(16deg); }
            100% { transform: translateX(-50%) rotate(16deg); }
        }

        .cricket-ball {
            position: absolute; left: 50%; top: 46%;
            width: 58px; height: 58px;
            animation: ballPath 1.5s linear infinite;
        }
        @keyframes ballPath {
            /* Pixel offsets, so they scale with the stage rather than staying put while
               everything around them grew. */
            0%   { transform: translate(336px, 74px) scale(0.75); opacity: 0; }
            10%  { opacity: 1; }
            34%  { transform: translate(26px, 42px) scale(1); opacity: 1; }
            40%  { transform: translate(-38px, 6px) scale(1.1); opacity: 1; }
            72%  { transform: translate(-272px, -138px) scale(0.9); opacity: 1; }
            94%  { transform: translate(-464px, -262px) scale(0.65); opacity: 0; }
            100% { transform: translate(-464px, -262px) scale(0.65); opacity: 0; }
        }

        /* Spin on an inner element, so it composes with the arc above instead of
           overwriting its transform. */
        .cricket-ball-spin {
            width: 100%; height: 100%;
            animation: ballSpin 0.28s linear infinite;
        }
        @keyframes ballSpin { to { transform: rotate(360deg); } }

        /* Flash at the moment of contact, on the same clock as the swing. */
        .cricket-spark {
            position: absolute; left: 50%; top: 46%;
            width: 190px; height: 190px; margin: -66px 0 0 -72px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.9) 0%, rgba(var(--primary-rgb),0.5) 40%, transparent 70%);
            opacity: 0; pointer-events: none;
            animation: sparkPop 1.5s linear infinite;
        }
        @keyframes sparkPop {
            0%, 33% { opacity: 0; transform: scale(0.4); }
            39%     { opacity: 1; transform: scale(1.25); }
            48%     { opacity: 0; transform: scale(1.7); }
            100%    { opacity: 0; transform: scale(1.7); }
        }

        /* The ground the bat stands on — a thin brand-tinted crease. */
        .cricket-crease {
            position: absolute; left: 50%; bottom: 18px;
            width: 420px; height: 4px; margin-left: -210px;
            background: linear-gradient(90deg, transparent, rgba(var(--primary-rgb),0.75), transparent);
            border-radius: 2px;
        }
        .cricket-crease::after {
            content: ''; position: absolute; left: 50%; top: -1px;
            width: 90px; height: 5px; margin-left: -45px; border-radius: 3px;
            background: rgba(var(--primary-rgb), 0.9);
            box-shadow: 0 0 22px rgba(var(--primary-rgb), 0.8);
        }

        /* ── Waiting screen floating orbs ── */
        .waiting-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0;
            animation: orb-float 8s ease-in-out infinite;
            pointer-events: none;
        }

        .waiting-orb-1 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(var(--primary-rgb), 0.3), transparent);
            top: 10%; left: 15%;
            animation-delay: 0s;
        }

        .waiting-orb-2 {
            width: 250px; height: 250px;
            background: radial-gradient(circle, rgba(var(--secondary-rgb), 0.25), transparent);
            bottom: 15%; right: 10%;
            animation-delay: 3s;
        }

        .waiting-orb-3 {
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(var(--primary-rgb), 0.2), transparent);
            top: 50%; left: 60%;
            animation-delay: 5s;
        }

        @keyframes orb-float {
            0% { opacity: 0; transform: translateY(30px) scale(0.8); }
            30% { opacity: 1; transform: translateY(-20px) scale(1.1); }
            70% { opacity: 0.7; transform: translateY(-40px) scale(1); }
            100% { opacity: 0; transform: translateY(30px) scale(0.8); }
        }

        /* ── Rotating ring behind loader ── */


        /* Loading spinner for LED wall */


        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Glow dots animation */
        .glow-dots {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .glow-dot {
            width: 12px;
            height: 12px;
            background: var(--primary);
            border-radius: 50%;
            animation: dot-pulse 1.5s ease-in-out infinite;
            box-shadow: 0 0 10px var(--primary);
        }

        .glow-dot:nth-child(2) { animation-delay: 0.2s; }
        .glow-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes dot-pulse {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* Live indicator */
        .live-indicator {
            position: absolute;
            top: 40px;
            right: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            color: #fff;
        }

        .live-dot {
            width: 16px;
            height: 16px;
            background: var(--secondary);
            border-radius: 50%;
            animation: live-blink 1s ease-in-out infinite;
            box-shadow: 0 0 10px var(--secondary);
        }

        @keyframes live-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* ── Template-driven element positions (via elementStyle) ── */
        #player-image {
            position: absolute;
            {!! elementStyle($positions, 'player_image', ['bottom'=>305,'left'=>114,'width'=>380], $boxShadowMap, $textShadowMap) !!}
            /* `contain`, not `cover`.
               Templates give this box a square (380x380 here) while player photos are
               portrait, and `cover` fills the square by cropping — which cut the player off
               at the knees, and on a head-and-shoulders shot cut the head. The box is a frame
               to sit inside, so the whole figure is shown and any spare width is left as
               space rather than taken out of the player. */
            object-fit: contain;
            object-position: center bottom;
        }

        /* ── Player image radial glow ── */
        .player-glow {
            position: absolute;
            @if(isset($positions['player_image']['bottom']))
            bottom: {{ ($positions['player_image']['bottom'] ?? 305) - 30 }}px;
            @endif
            @if(isset($positions['player_image']['top']))
            top: {{ ($positions['player_image']['top'] ?? 0) - 30 }}px;
            @endif
            @if(!isset($positions['player_image']['bottom']) && !isset($positions['player_image']['top']))
            bottom: 275px;
            @endif
            left: {{ ($positions['player_image']['left'] ?? 114) - 30 }}px;
            width: {{ ($positions['player_image']['width'] ?? 380) + 60 }}px;
            height: {{ ($positions['player_image']['width'] ?? 380) + 60 }}px;
            background: radial-gradient(circle, rgba(var(--primary-rgb), 0.25) 0%, transparent 70%);
            border-radius: 50%;
            animation: player-glow-pulse 3s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes player-glow-pulse {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
        }

        /* Wicket keeper and travel plan, beside the name. Scaled off the name's own font size
           so they stay in proportion at whatever size the template sets it to. */
        #player-name-badges {
            display: inline-flex;
            align-items: center;
            gap: 0.35em;
            margin-left: 0.5em;
            vertical-align: middle;
            white-space: nowrap;
        }
        #player-name-badges .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25em;
            padding: 0.12em 0.45em;
            border-radius: 0.4em;
            font-size: 0.34em;
            font-weight: 800;
            letter-spacing: 0.06em;
            line-height: 1.6;
        }
        #player-name-badges .badge-wk {
            background: rgba(251, 146, 60, 0.22);
            color: #fdba74;
            border: 1px solid rgba(251, 146, 60, 0.45);
        }
        #player-name-badges .badge-travel {
            background: rgba(56, 189, 248, 0.18);
            color: #7dd3fc;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }
        #player-name-badges svg { width: 1em; height: 1em; }

        #player-name {
            position: absolute;
            {!! elementStyle($positions, 'player_name', ['top'=>210,'left'=>545,'fontSize'=>46,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        .hidden {
            display: none !important;
        }

        @php
            $st = array_merge([
                'top'=>480,'left'=>550,'width'=>500,'height'=>150,'fontSize'=>20,'zIndex'=>10,
                // Declared so the `?:` fallbacks below never touch a missing key.
                'headerBg'=>'', 'rowBg'=>'', 'cellBg'=>'', 'headerHeight'=>'',
                'headerFontSize'=>'', 'headerLetterSpacing'=>'', 'cellFontSize'=>'', 'cellFontWeight'=>'',
            ], $positions['stats_table'] ?? []);
        @endphp
        #stats-table-wrap {
            position: absolute;
            {!! elementStyle($positions, 'stats_table', ['top'=>480,'left'=>550,'width'=>500,'height'=>150,'fontSize'=>20], $boxShadowMap, $textShadowMap) !!}
        }
        /* ── Stats ──
           Borderless by default: the boxed grid fought the artwork behind it. Each stat
           is its own soft glass tile, separated by spacing rather than rules. A template
           can still opt back into borders by setting tableBorderWidth. */
        #stats-table-wrap table {
            width: 100%;
            /*
             * Fixed layout, so every stat column is exactly the same width.
             *
             * With `auto`, the columns were sized by their contents — "MATCHES" is a longer
             * word than "WKTS", so the first column grew and the header chips stopped sitting
             * over the figures below them. That is the misalignment: nothing was offset, the
             * columns were just unequal. Fixed layout divides the width evenly and the header
             * lines up with its number.
             */
            table-layout: fixed;
            /* Fill the wrap so the rows are centred in the panel rather than piled at its top. */
            height: 100%;
            border-collapse: separate;
            border-spacing: {{ $st['cellSpacing'] ?? 10 }}px 0;
            font-size: {{ $st['fontSize'] ?? 20 }}px;
        }
        /* Honour the template's own row backgrounds.
           These were hardcoded to `transparent`, so a template that set headerBg and rowBg
           in the editor had both silently dropped on the wall — the editor drew panels and
           the wall let the artwork show straight through them, which is most of why the
           table "did not match". Transparent is still the DEFAULT, preserving the borderless
           look the comment above describes; it is no longer forced. */
        #stats-table-wrap thead tr {
            background: {{ $st['headerBg'] ?: 'transparent' }};
            color: {{ $st['headerColor'] ?? 'rgba(255,255,255,0.65)' }};
        }
        #stats-table-wrap tbody tr {
            background: {{ $st['rowBg'] ?: 'transparent' }};
            color: {{ $st['cellColor'] ?? '#ffffff' }};
        }
        #stats-table-wrap th,
        #stats-table-wrap td {
            padding: {{ $st['cellPadding'] ?? 8 }}px;
            @if(($st['tableBorderWidth'] ?? 0) > 0)
            border: {{ $st['tableBorderWidth'] }}px solid {{ $st['tableBorderColor'] ?? 'rgba(255,255,255,0.2)' }};
            @else
            border: none;
            @endif
            text-align: center;
        }
        #stats-table-wrap th {
            font-weight: 700;
            /*
             * NO forced uppercase.
             *
             * The wrap already carries the template's own text-transform, and this rule sat
             * below it — so "Matches / wkts / Runs" as typed in the editor always came out
             * "MATCHES / WKTS / RUNS" on the wall, and there was no way to ask for anything
             * else. The label now reads the way it was written.
             */
            letter-spacing: {{ $st['headerLetterSpacing'] !== '' ? $st['headerLetterSpacing'] : 1 }}px;
            /* Closer to the figures beneath them than 0.62em was — the headers are part of the
               block, not a caption for it. */
            font-size: {{ $st['headerFontSize'] ?: '0.72' }}em;
            padding-bottom: 2px;
            opacity: 0.85;
            vertical-align: middle;
            @if(!empty($st['headerHeight']))
            /*
             * Pin the header band's height.
             *
             * Several wall backgrounds have the stats table PRINTED INTO THE ARTWORK — a
             * coloured header strip above a body block. Left to size itself, the header row
             * takes only as much height as its text, so the labels floated above the strip
             * they belong in and the figures sat high in the block. Fixing the header height
             * lets the remaining table height fall to the body row, and both rows land in
             * their painted slots.
             */
            height: {{ $st['headerHeight'] }}px;
            @endif
        }
        /*
         * The figure carries the emphasis — by weight and size, not by a panel behind it.
         *
         * This used to force a translucent tile on every cell (`?? rgba(255,255,255,0.07)`,
         * which a template could not switch off because the fallback only applied when the
         * key was absent, never when it was deliberately blank). Stacked on the wrap's own
         * background and the header row's, that was three tinted layers over the artwork —
         * the "table background" that read as dirty on the wall. A template that wants tiles
         * back sets cellBg in the editor.
         */
        #stats-table-wrap td {
            font-weight: {{ $st['cellFontWeight'] ?: 700 }};
            font-size: {{ $st['cellFontSize'] ?: '1.15' }}em;
            line-height: 1.1;
            vertical-align: middle;
            @if(!empty($st['cellBg']))
            background: {{ $st['cellBg'] }};
            border-radius: 12px;
            backdrop-filter: blur(3px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.10);
            @endif
        }

        #player-role {
            position: absolute;
            {!! elementStyle($positions, 'player_role', ['top'=>275,'left'=>570,'fontSize'=>24,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        #player-batting {
            position: absolute;
            {!! elementStyle($positions, 'batting_style', ['top'=>334,'left'=>570,'fontSize'=>34,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        #player-bowling {
            position: absolute;
            {!! elementStyle($positions, 'bowling_style', ['top'=>404,'left'=>570,'fontSize'=>34,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        /*
         * The opening figure, alongside the live one.
         *
         * Captioned by its own ::before rather than by bid_label, which is dynamic — it reads
         * BASE VALUE, CURRENT BID or SOLD PRICE depending on the player's state, so it belongs
         * to #current-bid and cannot also label this. Same approach as #highest-bidder.
         */
        #base-price {
            position: absolute;
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.05;
            {!! elementStyle($positions, 'base_price', ['bottom'=>197,'left'=>400,'fontSize'=>26,'color'=>'#cbd5e1'], $boxShadowMap, $textShadowMap) !!}
        }
        #base-price::before {
            content: 'BASE PRICE';
            font-size: 0.42em;
            font-weight: 900;
            letter-spacing: 0.22em;
            opacity: 0.75;
        }

        /*
         * Travel plan. Hidden unless the player has one, which most do not — so it is laid out
         * as an inline-flex row that simply is not painted rather than an empty box holding a
         * gap open on the artwork.
         */
        #travel-plan {
            position: absolute;
            display: inline-flex;
            align-items: center;
            gap: 0.45em;
            visibility: hidden;
            {!! elementStyle($positions, 'travel_plan', ['top'=>470,'left'=>550,'fontSize'=>24,'color'=>'#7dd3fc'], $boxShadowMap, $textShadowMap) !!}
        }
        #travel-plan svg {
            width: 1em;
            height: 1em;
            flex-shrink: 0;
        }

        #current-bid {
            position: absolute;
            {!! elementStyle($positions, 'current_bid', ['bottom'=>197,'left'=>234,'fontSize'=>32,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
            animation: bid-glow 2s ease-in-out infinite;
        }

        /* ── Bid price text glow ── */
        @keyframes bid-glow {
            0%, 100% { text-shadow: 0 0 8px rgba(var(--primary-rgb), 0.3); }
            50% { text-shadow: 0 0 20px rgba(var(--primary-rgb), 0.6), 0 0 40px rgba(var(--primary-rgb), 0.2); }
        }

        #bid-list-container {
            position: absolute;
            top: 623px;
            left: 543px;
            width: 250px;
            height: 245px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.5);
            padding: 8px;
            border-radius: 6px;
        }

        #sold-badge {
            position: absolute;
            {!! elementStyle($positions, 'sold_badge', ['bottom'=>27,'left'=>112,'width'=>150,'height'=>150,'zIndex'=>9], $boxShadowMap, $textShadowMap) !!}
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #team-logo {
            position: absolute;
            {!! elementStyle($positions, 'team_logo', ['bottom'=>56,'left'=>316,'width'=>170,'height'=>100], $boxShadowMap, $textShadowMap) !!}
            object-fit: contain;
        }

        ul#bid-list {
            font-size: 25px;
        }

        #sold-text {
            position: absolute;
            {!! elementStyle($positions, 'bid_label', ['bottom'=>243,'left'=>186,'fontSize'=>32,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        /* Bid update highlight - subtle and stable */
        .bid-updated {
            color: var(--secondary) !important;
            transition: color 0.3s ease;
        }

        /* ── Highest bidder ──
           Was bare #00ff00 text floating on the card: pure green is the harshest colour on
           an LED panel, and with no frame it read as an error message rather than the team
           who is winning the player.

           The framing declarations come BEFORE elementStyle() on purpose — a template that
           positions or colours this element emits its own declarations after these, and the
           later ones win, so an author keeps full control. */
        #highest-bidder {
            position: absolute;
            /* Stacked, not side by side. The label used to sit inline ahead of the name on one
               line, so at a glance "HIGHEST BID  TEST Alpha" read as a single run of text and
               neither part was legible from the back of a room. The label is now a small
               kicker above a large centred name. */
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 10px 26px;
            border-radius: 9999px;
            background: rgba(2, 6, 23, 0.55);
            border: 1px solid rgba(34, 197, 94, 0.45);
            box-shadow: 0 0 28px rgba(34, 197, 94, 0.25);
            font-weight: 800;
            letter-spacing: 0.04em;
            white-space: nowrap;

            /* Kept on one line and given room of its own: it used to sit at left 570, inside
               the stats table's own 550..1050 span and barely above a block starting at 545,
               so "HIGHEST BID  TEST Alpha" printed across the MATCHES / RUNS / WICKETS row
               and neither could be read.
 
               `left` stays the LEFT EDGE here. Anchoring by centre would read better, but
               saved templates already store dragged left-edge coordinates, and changing what
               the number means would move every existing template's pill by half its own
               width. Position is template data — the editor is where it gets nudged. */
            {!! elementStyle($positions, 'highest_bidder', ['top'=>715,'left'=>600,'fontSize'=>28,'color'=>'#22c55e'], $boxShadowMap, $textShadowMap) !!}
        }

        /* A small kicker, so the name is not mistaken for the player's own. */
        #highest-bidder::before {
            content: 'HIGHEST BID';
            font-size: 0.38em;
            font-weight: 900;
            letter-spacing: 0.28em;
            /* Optical centring: letter-spacing adds a trailing gap after the last letter, which
               makes a centred label sit visibly left of the name under it. */
            text-indent: 0.28em;
            line-height: 1.1;
            color: rgba(226, 232, 240, 0.75);
        }

        /* The name carries the weight now that it has a line to itself. */
        #highest-bidder #bidder-name {
            font-size: 1em;
            font-weight: 900;
            line-height: 1.1;
        }

    
        /* The sealed banner's motion. See the markup for why a still bar is a problem. */
        .sealed-banner { overflow: hidden; }

        .sealed-sheen {
            position: absolute; inset: 0;
            background: linear-gradient(100deg, transparent 20%, rgba(255,255,255,.14) 50%, transparent 80%);
            transform: translateX(-100%);
            animation: sealed-sweep 2.6s linear infinite;
            pointer-events: none;
        }

        @keyframes sealed-sweep { to { transform: translateX(100%); } }

        /* A live indicator beside the heading, the same idiom as the LIVE dot elsewhere. */
        .sealed-dot {
            display: inline-block; width: 9px; height: 9px; margin-right: 10px;
            border-radius: 50%; background: #fde68a; vertical-align: middle;
            animation: sealed-pulse 1.1s ease-in-out infinite;
        }

        @keyframes sealed-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .35; transform: scale(.7); }
        }

        /* Each name the draw cycles through lands with a small snap. */
        .sealed-draw-name.is-cycling { animation: draw-flick .09s ease-out; }
        .sealed-draw-name.is-winner  { animation: draw-land .5s cubic-bezier(.2,1.6,.3,1); color: #fde68a !important; }

        @keyframes draw-flick { from { opacity: .45; transform: translateY(-6px); } }
        @keyframes draw-land  { 0% { transform: scale(.7); opacity: 0; } 60% { transform: scale(1.12); } 100% { transform: scale(1); opacity: 1; } }

        @media (prefers-reduced-motion: reduce) {
            .sealed-sheen, .sealed-dot,
            .sealed-draw-name.is-cycling, .sealed-draw-name.is-winner { animation: none; }
        }
    </style>
</head>

<body class="text-white">

    {{-- Dims the stage through the closing call. Empty by design: it is a wash of colour,
         not a message — the banners above it carry the words. --}}
    <div id="final-call-dim"></div>

    <!-- Live Indicator -->
    <div class="live-indicator">
        <span class="live-dot"></span>
        <span>LIVE</span>
    </div>

    <!-- Waiting Screen (visible by default) -->
    <div id="waiting-screen">
        <!-- Floating gradient orbs -->
        <div class="waiting-orb waiting-orb-1"></div>
        <div class="waiting-orb waiting-orb-2"></div>
        <div class="waiting-orb waiting-orb-3"></div>

        {{-- Shows whichever logos exist. Upload one at Auctions -> Edit -> Branding
             (auction logo), or on the tournament; with neither, the bat and ball below
             carry the screen on their own. --}}
        @if($auction->auction_logo_url || ($auction->tournament && $auction->tournament->logo_url))
        <div style="display:flex;align-items:center;gap:36px;margin-bottom:26px;position:relative;z-index:1;
                    padding:18px 34px;border-radius:20px;
                    background:rgba(2,6,23,0.45);backdrop-filter:blur(8px);
                    border:1px solid rgba(var(--primary-rgb),0.28);
                    box-shadow:0 20px 60px rgba(0,0,0,0.45);">
            @if($auction->auction_logo_url)
                <img src="{{ $auction->auction_logo_url }}" alt="Auction Logo" style="height:100px;object-fit:contain;">
            @endif
            @if($auction->tournament && $auction->tournament->logo_url)
                <img src="{{ $auction->tournament->logo_url }}" alt="Tournament Logo" style="height:100px;object-fit:contain;">
            @endif
        </div>
        @endif
        <div class="cricket-stage" style="z-index:1;">
            <div class="cricket-crease"></div>

            {{-- Bat: blade, shoulder and grip, drawn rather than loaded. --}}
            <svg class="cricket-bat" viewBox="0 0 62 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="25" y="0" width="12" height="20" rx="5" fill="#0f172a" stroke="rgba(255,255,255,0.18)"/>
                <rect x="24" y="16" width="14" height="58" rx="7" fill="#1f2937"/>
                <path d="M24 30h14M24 40h14M24 50h14M24 60h14" stroke="rgba(255,255,255,0.12)" stroke-width="2"/>
                <rect x="27" y="70" width="8" height="16" fill="#c8a15a"/>
                <path d="M14 84h34a6 6 0 0 1 6 6v88a10 10 0 0 1-10 10H18a10 10 0 0 1-10-10V90a6 6 0 0 1 6-6z"
                      fill="url(#blade)" stroke="rgba(255,255,255,0.22)" stroke-width="1.5"/>
                <path d="M31 92v92" stroke="rgba(0,0,0,0.18)" stroke-width="2"/>
                <defs>
                    <linearGradient id="blade" x1="8" y1="84" x2="54" y2="188" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#f2d9a8"/>
                        <stop offset="0.55" stop-color="#dcb877"/>
                        <stop offset="1" stop-color="#b8935a"/>
                    </linearGradient>
                </defs>
            </svg>

            <div class="cricket-spark"></div>

            {{-- Ball: leather, seam and stitching. --}}
            <div class="cricket-ball">
                <svg class="cricket-ball-spin" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="18" cy="18" r="17" fill="url(#leather)" stroke="rgba(0,0,0,0.35)"/>
                    <path d="M6 9c6 5 6 13 0 18" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity="0.9"/>
                    <path d="M30 9c-6 5-6 13 0 18" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity="0.9"/>
                    <path d="M13 8.5l2 2M12 13l2 2M12 18l2 2M12 23l2 2M13 27.5l2 2"
                          stroke="#fff" stroke-width="1.3" stroke-linecap="round" opacity="0.75"/>
                    <ellipse cx="12" cy="11" rx="5" ry="4" fill="rgba(255,255,255,0.16)"/>
                    <defs>
                        <radialGradient id="leather" cx="0.35" cy="0.3" r="0.85">
                            <stop stop-color="#e0413b"/>
                            <stop offset="0.6" stop-color="#a81f21"/>
                            <stop offset="1" stop-color="#6b0f12"/>
                        </radialGradient>
                    </defs>
                </svg>
            </div>
        </div>
        {{-- Headline, subline and progress are all set by renderWaitingScreen(). The
             auction being `running` is not the same as a player being on the block, so a
             hall that has already sold forty players must not be told it is still
             "waiting for auction" every time the organizer takes a breath. --}}
        <h1 id="waiting-title" style="position:relative;z-index:1;">WAITING FOR AUCTION</h1>
        <p id="waiting-sub" class="text-3xl text-gray-400 mt-4" style="position:relative;z-index:1;">{{ $auction->name }}</p>

        {{-- Progress rail. Hidden until the room has actually started working. --}}
        <div id="waiting-progress" class="hidden" style="position:relative;z-index:1;margin-top:26px;width:min(760px,62vw);">
            <div style="height:10px;border-radius:999px;overflow:hidden;
                        background:rgba(255,255,255,0.08);
                        box-shadow:inset 0 1px 3px rgba(0,0,0,0.6);">
                <div id="waiting-progress-fill"
                     style="height:100%;width:0%;border-radius:999px;
                            background:linear-gradient(90deg,var(--primary),#22d3ee);
                            box-shadow:0 0 18px rgba(var(--primary-rgb),0.75);
                            transition:width 0.6s cubic-bezier(0.4,0,0.2,1);"></div>
            </div>
            <div id="waiting-progress-text"
                 style="margin-top:14px;font-size:22px;letter-spacing:0.08em;
                        text-transform:uppercase;color:rgba(255,255,255,0.55);"></div>
        </div>

        <div class="glow-dots" style="position:relative;z-index:1;">
            <div class="glow-dot"></div>
            <div class="glow-dot"></div>
            <div class="glow-dot"></div>
        </div>
    </div>

    <!-- Auction Completed Screen -->
    {{-- Restart announcement. Sits above every other screen for its window, so a
         restart reads as a deliberate act rather than the wall mysteriously blanking. --}}
    <div id="restart-screen" class="hidden"
         style="position:fixed;inset:0;z-index:150;display:flex;flex-direction:column;
                justify-content:center;align-items:center;
                background:radial-gradient(circle at 50% 40%, #1e1b4b 0%, #0a0a0a 70%);">
        <div class="cricket-stage" style="margin-bottom:10px;">
            <div class="cricket-crease"></div>
            <svg class="cricket-bat" viewBox="0 0 62 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="25" y="0" width="12" height="20" rx="5" fill="#0f172a" stroke="rgba(255,255,255,0.18)"/>
                <rect x="24" y="16" width="14" height="58" rx="7" fill="#1f2937"/>
                <rect x="27" y="70" width="8" height="16" fill="#c8a15a"/>
                <path d="M14 84h34a6 6 0 0 1 6 6v88a10 10 0 0 1-10 10H18a10 10 0 0 1-10-10V90a6 6 0 0 1 6-6z"
                      fill="#dcb877" stroke="rgba(255,255,255,0.22)" stroke-width="1.5"/>
            </svg>
            <div class="cricket-spark"></div>
            <div class="cricket-ball">
                <svg class="cricket-ball-spin" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="18" cy="18" r="17" fill="#a81f21" stroke="rgba(0,0,0,0.35)"/>
                    <path d="M6 9c6 5 6 13 0 18" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity="0.9"/>
                    <path d="M30 9c-6 5-6 13 0 18" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity="0.9"/>
                </svg>
            </div>
        </div>

        <h1 style="font-size:64px;font-weight:900;letter-spacing:0.06em;color:#a78bfa;
                   text-shadow:0 0 40px rgba(167,139,250,0.5);animation:pulse 1.6s ease-in-out infinite;">
            RESTARTING AUCTION
        </h1>
        <p style="font-size:24px;color:#94a3b8;margin-top:12px;">
            Next player in <span id="restart-seconds" style="color:#fff;font-weight:900;"></span>s
        </p>
    </div>

    <div id="completed-screen" class="hidden" style="position:fixed;inset:0;display:flex;flex-direction:column;justify-content:center;align-items:center;background:linear-gradient(135deg,#0a0a0a 0%,#1a1a2e 50%,#0a0a0a 100%);z-index:100;">
        <div style="font-size:120px;margin-bottom:30px;">🏆</div>
        <h1 style="font-size:72px;color:#eab308;text-shadow:0 0 30px rgba(234,179,8,0.5);">AUCTION COMPLETED</h1>
        <p class="text-3xl text-gray-400 mt-6">{{ $auction->name }}</p>
        <p class="text-xl text-gray-500 mt-4">Thank you for watching!</p>
    </div>


    {{-- Paused overlay (shown in real-time when the organizer pauses) --}}
    <div id="paused-overlay" class="hidden"
         style="position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,0.82);backdrop-filter:blur(6px);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;">
        <div style="font-size:5rem;line-height:1;margin-bottom:1rem;">⏸️</div>
        <div style="font-size:3rem;font-weight:800;letter-spacing:0.15em;color:#fff;text-transform:uppercase;">Auction Paused</div>
        <div style="margin-top:0.75rem;font-size:1.1rem;color:#cbd5e1;">Please wait — the auction will resume shortly.</div>
    </div>

    {{-- Closing call + countdown, as ONE piece of chrome.
         Previously two separate overlays: a banner pinned across the top of the viewport
         and a bare 6rem number beneath it. Both sat over the middle of the screen, which
         is exactly where the player's name is — so a closing call covered the name of the
         player being called. This lives along the bottom edge instead, clear of the card,
         and reads as one object rather than two. --}}
    <div id="clock-hud" class="hidden"
         style="position:fixed;left:50%;bottom:44px;transform:translateX(-50%);z-index:9998;
                display:flex;align-items:center;gap:22px;pointer-events:none;
                padding:14px 30px 14px 18px;border-radius:9999px;
                background:rgba(2,6,23,0.82);backdrop-filter:blur(10px);
                border:1px solid rgba(148,163,184,0.25);
                box-shadow:0 24px 70px rgba(0,0,0,0.6);">

        {{-- The dial. Hidden on the wall — see renderClock(); the hall is told that a player
             is closing, not counted down at. The organizer's panel keeps the numbers. --}}
        <div id="clock-dial" style="position:relative;width:96px;height:96px;flex-shrink:0;">
            <svg width="96" height="96" viewBox="0 0 96 96" style="transform:rotate(-90deg);">
                <circle cx="48" cy="48" r="42" fill="none" stroke="rgba(148,163,184,0.22)" stroke-width="7"/>
                <circle id="clock-ring" cx="48" cy="48" r="42" fill="none"
                        stroke="#22d3ee" stroke-width="7" stroke-linecap="round"
                        stroke-dasharray="263.9" stroke-dashoffset="0"
                        style="transition:stroke-dashoffset 0.95s linear, stroke 0.3s ease;"/>
            </svg>
            <div id="clock-seconds"
                 style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                        font-size:2.6rem;font-weight:900;line-height:1;color:#fff;
                        font-variant-numeric:tabular-nums;"></div>
        </div>

        {{-- justify-content:center so the column is centred against the 96px ring whether
             it holds one row or two. Without it, the caption sat high in the pill for the
             whole auction and only looked right during a closing call. --}}
        <div style="display:flex;flex-direction:column;justify-content:center;gap:6px;min-width:0;">
            <div id="clock-caption"
                 style="font-size:0.68rem;font-weight:800;letter-spacing:0.28em;text-transform:uppercase;
                        color:#94a3b8;line-height:1;">Time Remaining</div>
            {{-- Shown only during a closing call. display:none rather than empty text: an
                 empty block still contributes its line-box to the column, which is what
                 pushed the caption off centre. --}}
            <div id="clock-call"
                 style="display:none;font-size:2.4rem;font-weight:900;letter-spacing:0.1em;
                        text-transform:uppercase;line-height:1;color:#fff;white-space:nowrap;"></div>
        </div>
    </div>

    {{-- Announced on the wall AND the ticker, from the same server-computed final-call
         state, so the hall and the stream never disagree about what is happening. --}}
    <div id="unsold-warning" class="hidden">No bids &mdash; player will go unsold</div>

    {{-- The RESULT, once a player has left the block.
         The word used to sit on the price label over the player's photo, which was removed
         because it covered the image and duplicated the badge. Without it a passed player
         showed a card with no wording at all and read as just another player coming up — so
         the announcement lives here instead, clear of the artwork, and names who it was. --}}
    <div id="result-banner" class="hidden">
        <span id="result-word"></span>
        <span id="result-name"></span>
    </div>

    {{-- Who is bidding, called out across the top of the hall.
         Separate from the result banner: that one announces a finished lot, this one
         announces a live raise, and the two must never be on screen together. --}}
    <div id="bid-flash" class="hidden">
        <span id="bid-flash-team"></span>
        <span id="bid-flash-amount"></span>
    </div>

    @if(isset($cardPayload) && ! request()->boolean('noui'))
        {{-- Kept out of #card-container so the browser-side html2canvas capture, which paints
             that element, cannot include its own button.

             That was not enough for the SERVER-side capture. Browsershot's `->select()` clips
             the viewport to the element's box, and this button is position:fixed — fixed
             elements are painted against the viewport, so it landed inside the clip anyway
             and every downloaded card carried a green button in its corner. The renderer asks
             for the page with ?noui=1 and gets no chrome to capture at all. --}}
        <button id="card-download" type="button" class="hidden"
                style="position:fixed;top:16px;right:16px;z-index:99999;padding:12px 22px;
                       border:0;border-radius:10px;cursor:pointer;
                       font:800 15px/1 system-ui,sans-serif;letter-spacing:0.04em;
                       background:#16a34a;color:#fff;box-shadow:0 10px 30px rgba(0,0,0,0.45);">
            Download PNG
        </button>
    @endif

    <div id="card-container" class="card-container hidden">
        @if($auction->auction_logo_url)
        <img src="{{ $auction->auction_logo_url }}" alt="Auction Logo"
             style="position:absolute;top:20px;left:20px;height:80px;object-fit:contain;z-index:10;">
        @endif

        <!-- Player image radial glow -->
        @if(isVisible($positions, 'player_image'))
        <div class="player-glow"></div>
        @endif

        <!-- Sold Badge (hidden by default, shown when sold) -->
        @if(isVisible($positions, 'sold_badge'))
        <div id="sold-badge" class="absolute hidden">
            @if($soldBadgeUrl)
                <img src="{{ $soldBadgeUrl }}" alt="Sold Badge" style="width:100%;height:100%;object-fit:contain;">
            @else
                <div class="auction-seal sold-stamp">
                    <span class="seal-word">Sold</span>
                    <span class="seal-sub">Signed</span>
                </div>
            @endif
        </div>
        @endif

        <!-- Unsold Badge (hidden by default, shown when unsold) -->
        @if(isVisible($positions, 'sold_badge'))
        <div id="unsold-badge" class="absolute hidden" style="{!! elementStyle($positions, 'sold_badge', ['bottom'=>27,'left'=>112,'width'=>150,'height'=>150,'zIndex'=>9], $boxShadowMap, $textShadowMap) !!}display:none;align-items:center;justify-content:center;">
            @if($unsoldBadgeUrl)
                <img src="{{ $unsoldBadgeUrl }}" alt="Unsold Badge" class="w-full h-full object-contain">
            @else
                <div class="auction-seal unsold-stamp">
                    <span class="seal-word">Unsold</span>
                    <span class="seal-sub">No bids</span>
                </div>
            @endif
        </div>
        @endif

        <!-- Actual Team Logo -->
        @if(isVisible($positions, 'team_logo'))
        <img id="team-logo" src="" class="absolute object-contain hidden">
        @endif

        <!-- Player Image -->
        @if(isVisible($positions, 'player_image'))
        {{-- A 1x1 transparent pixel, not via.placeholder.com.
             The wall runs in a hall on whatever network the venue has, and this pointed at an
             external service that no longer resolves — so the initial paint carried a broken
             image, and a venue that blocks outbound traffic got one for every player until the
             real photo landed. The pixel is inline: nothing to fetch, nothing to fail. --}}
        <img id="player-image" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="Player">
        @endif

        <!-- Player Name -->
        @if(isVisible($positions, 'player_name'))
        {{-- Badges sit INSIDE the name element so a template author dragging the name in the
             editor carries them with it — a second positioned element would have to be moved
             again every time, and would drift out of line the moment a name wrapped. --}}
        <h1 id="player-name" class="text-4xl font-bold">Player Name<span id="player-name-badges"></span></h1>
        @endif

        <!-- Player Role -->
        @if(isVisible($positions, 'player_role'))
        <p id="player-role" class="text-2xl font-bold font-uppercase">All Rounder</p>
        @endif

        <!-- Status Text (BASE VALUE / CURRENT BID / SOLD PRICE) -->
        @if(isVisible($positions, 'bid_label'))
        <h1 id="sold-text" class="text-4xl font-bold">BASE VALUE</h1>
        @endif

        <!-- Batting / Bowling -->
        @if(isVisible($positions, 'batting_style'))
        <p id="player-batting">Right Hand Bat</p>
        @endif
        @if(isVisible($positions, 'bowling_style'))
        <p id="player-bowling">Right Arm Medium</p>
        @endif

        <!-- Current Bid -->
        @if(isVisible($positions, 'current_bid'))
        <div id="current-bid" class="text-3xl font-extrabold" style="color: #fff;">1,00,000</div>
        @endif

        <!-- Base price: the opening figure, shown next to the live one -->
        @if(isVisible($positions, 'base_price'))
        <div id="base-price"><span id="base-price-value">1,00,000</span></div>
        @endif

        <!-- Travel plan: shown only for a player who has one -->
        @if(isVisible($positions, 'travel_plan'))
        <div id="travel-plan">
            {{-- A paper plane, inline so it takes the element's own colour and font size. --}}
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
            </svg>
            <span id="travel-plan-value"></span>
        </div>
        @endif

        <!-- Stats Table -->
        @if(isVisible($positions, 'stats_table'))
        @php
            $stCols = json_decode($positions['stats_table']['tableColumns'] ?? '[]', true) ?: [
                ['label'=>'Matches','field'=>'total_matches'],
                ['label'=>'Runs','field'=>'total_runs'],
                ['label'=>'Wickets','field'=>'total_wickets'],
            ];
            $stP = $positions['stats_table'] ?? [];
            $stCP = $stP['cellPadding'] ?? 10;
            $stBW = $stP['tableBorderWidth'] ?? 1;
            $stBC = $stP['tableBorderColor'] ?? 'rgba(255,255,255,0.2)';
            $stBdr = $stBW.'px solid '.$stBC;
        @endphp
        <div id="stats-table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach($stCols as $col)
                        <th style="{{ !empty($col['headerBg']) ? 'background:'.$col['headerBg'].';' : '' }}{{ !empty($col['headerColor']) ? 'color:'.$col['headerColor'].';' : '' }}{{ !empty($col['width']) ? 'width:'.$col['width'].';' : '' }}">{{ $col['label'] ?? '' }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach($stCols as $col)
                        <td data-field="{{ $col['field'] ?? '' }}" style="{{ !empty($col['cellBg']) ? 'background:'.$col['cellBg'].';' : '' }}{{ !empty($col['cellColor']) ? 'color:'.$col['cellColor'].';' : '' }}">0</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
        @endif

        <!-- Highest Bidder (shown during live bidding) -->
        @if(isVisible($positions, 'highest_bidder'))
        <div id="highest-bidder" class="hidden"><span id="bidder-name"></span></div>

        {{-- Sealed round. The hall and the stream must be told that bidding has gone
             private, or a frozen price looks like a stalled auction. --}}
        <div id="sealed-banner" class="hidden sealed-banner"
             style="position:absolute;top:0;left:0;right:0;z-index:30;padding:14px 0;text-align:center;
                    background:linear-gradient(90deg,rgba(88,28,135,0.95),rgba(147,51,234,0.95));
                    border-bottom:3px solid #c084fc;">
            {{-- A moving sheen along the bar. A sealed round freezes the price, so a still banner
                 over a still figure is indistinguishable from a stalled screen — the motion is the
                 only thing telling a hall that something is happening. --}}
            <div class="sealed-sheen" aria-hidden="true"></div>

            <div style="position:relative;font-size:13px;font-weight:900;letter-spacing:6px;text-transform:uppercase;color:#e9d5ff;">
                <span class="sealed-dot" aria-hidden="true"></span>
                <span id="sealed-banner-title">Closed Bid</span>
            </div>
            <div style="position:relative;font-size:26px;font-weight:900;color:#fff;line-height:1.1;margin-top:2px;">
                <span id="sealed-banner-line"></span>
            </div>

            {{-- The draw. Cycles the tied teams and lands on the winner, so the room watches the
                 result arrive rather than reading that a draw happened somewhere. --}}
            <div id="sealed-draw" class="hidden" style="position:relative;margin-top:8px;">
                <div style="font-size:12px;font-weight:800;letter-spacing:4px;text-transform:uppercase;color:#fde68a;">
                    <span id="sealed-draw-label">Drawing a lot</span>
                </div>
                <div id="sealed-draw-name" class="sealed-draw-name"
                     style="font-size:40px;font-weight:900;color:#fff;line-height:1.05;"></div>
                <div id="sealed-draw-amount" style="font-size:15px;font-weight:700;color:#e9d5ff;"></div>
            </div>
        </div>
        @endif

        {{-- Custom Elements (text labels and shapes) --}}
        @foreach($positions as $cKey => $cVal)
            @if(str_starts_with($cKey, 'custom_text_') && ($cVal['visible'] ?? true))
                <div style="position:absolute;{!! elementStyle($positions, $cKey, [], $boxShadowMap, $textShadowMap) !!}{{ empty($cVal['width']) ? 'white-space:nowrap;' : 'word-wrap:break-word;' }}">{{ $cVal['content'] ?? '' }}</div>
            @elseif(str_starts_with($cKey, 'custom_shape_') && ($cVal['visible'] ?? true))
                @php
                    $shapeType = $cVal['shapeType'] ?? 'rectangle';
                    $shapeExtra = '';
                    if ($shapeType === 'circle') $shapeExtra = 'border-radius:50%;';
                    elseif ($shapeType === 'pill') $shapeExtra = 'border-radius:9999px;';
                    elseif ($shapeType === 'rounded-rect') $shapeExtra = 'border-radius:12px;';
                    elseif ($shapeType === 'diamond') $shapeExtra = 'transform:rotate(45deg);';
                    elseif ($shapeType === 'triangle') $shapeExtra = 'clip-path:polygon(50% 0%, 0% 100%, 100% 100%);background:'.($cVal['bgColor'] ?? 'rgba(255,255,255,0.1)').';';
                @endphp
                <div style="position:absolute;{!! elementStyle($positions, $cKey, [], $boxShadowMap, $textShadowMap) !!}{{ $shapeExtra }}"></div>
            @elseif(str_starts_with($cKey, 'custom_image_') && ($cVal['visible'] ?? true) && !empty($cVal['imagePath']))
                <img src="{{ asset('storage/' . $cVal['imagePath']) }}"
                     style="position:absolute;{!! elementStyle($positions, $cKey, [], $boxShadowMap, $textShadowMap) !!}object-fit:contain;" alt="">
            @endif
        @endforeach
    </div>

    @isset($cardPayload)
        {{-- Only on a card page. The wall never loads this. --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    @endisset
    {{-- Same origin, not a CDN — see partials/echo-init.blade.php for why. A hall connection
         that cannot reach js.pusher.com, or an extension that blocks it, took push down on the
         one screen the whole room is looking at. The CDN stays as a fallback for a deploy that
         did not carry the local copies. --}}
    <script src="{{ asset('js/push/pusher.min.js') }}"></script>
    <script src="{{ asset('js/push/echo.iife.js') }}"></script>
    <script>
        if (typeof Pusher === 'undefined') {
            document.write('<script src="https://js.pusher.com/7.2/pusher.min.js"><\/script>');
        }
        if (typeof Echo === 'undefined') {
            document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.11.3/echo.iife.js"><\/script>');
        }
    </script>
    <script>
        const auctionId = {{ $auction->id }};
        // JSON-encoded rather than interpolated into quotes: an auction named O'Brien's Cup
        // would otherwise close the string and break every script on the page.
        const AUCTION_NAME = @json($auction->name);
        let currentStatus = 'waiting';
        let lastPlayerId = null;
        /*
         * CARD MODE. Set only when this page is being rendered as ONE player's card for
         * download — a screenshot of this very page, so the file and the LED wall cannot drift
         * apart. Absent for the wall itself, and every live mechanism below is guarded on it.
         */
        const CARD_PAYLOAD = @json($cardPayload ?? null);
        const CARD_SHOW_RESULT = @json($cardShowResult ?? false);

        let lastOnAuctionPlayerId = null;
        // Ordering token for pushed raises: socket frames are unordered and can repeat, so
        // anything not newer than this is dropped rather than drawn on the wall.
        let _lastAppliedBidId = 0;
        let lastActionPlayerId = null;

        /**
         * Take the previous player's outcome off the screen.
         *
         * Everything a finished lot leaves behind: the badge, the winning team's logo, the
         * glow on the card, the bidder line and the banner naming the result. Written
         * against getElementById rather than closed-over consts so it can be called from
         * outside updatePlayerCard — which is the whole point, since the gap this fixes is
         * the window where updatePlayerCard is NOT running.
         *
         * Safe to call at any time: every lookup is null-guarded, and a wall template can
         * legitimately omit any of these elements (see isVisible() in the markup).
         */
        /**
         * A stored label, as a hall screen should read it.
         *
         * These come out of the database hyphenated — "Right-hand Bat", "Right-arm Medium",
         * "All-Rounder" — which is fine in a form and wrong on a wall six metres away,
         * where the hyphen reads as a dash between two separate words. Only the separator
         * is touched: nothing is re-cased, so a label an organizer typed deliberately keeps
         * the capitalisation they gave it.
         */
        function displayLabel(text) {
            if (!text) return '';
            // Hyphens BETWEEN words only, so a trailing or leading dash is left alone.
            return String(text).replace(/(\w)-(\w)/g, '$1 $2');
        }


        /* Initialize Echo.

           config(), not env(). env() returns null the moment anyone runs
           `php artisan config:cache`, which would have taken the wall's live updates down
           silently — no error, just a page that quietly went back to polling. The values are
           JSON-encoded rather than dropped inside quotes, like every other server value on
           this page. */
        // Not in card mode: nothing about a still image needs a live connection, and the
        // listeners below call into the live branch's helpers.
        if (! CARD_PAYLOAD) {
            window.Echo = new Echo({
                broadcaster: 'pusher',
                key: @json(config('broadcasting.connections.pusher.key')),
                cluster: @json(config('broadcasting.connections.pusher.options.cluster')),
                forceTLS: true
            });
        }

        /* Amounts read on the K / M / B ladder with this auction's unit — the Lakh /
           Crore ladder this used to hardcode is wrong for an auction run in points,
           coins or dollars. This page is a standalone document (CDN Tailwind, no Vite
           bundle) so it carries its own copy, fed the same unit config from the server. */
        const AMOUNT_UNIT = @json($auction->amountUnitConfig());

        function formatMillions(amount) {
            if (amount === null || amount === undefined || amount === '') return '—';
            const n = Number(amount);
            if (!isFinite(n)) return '—';

            const sign = n < 0 ? '-' : '';
            const abs = Math.abs(n);
            if (abs >= 1e15) return '∞';

            let divisor = 1, suffix = '';
            if (abs >= 1e9) { divisor = 1e9; suffix = 'B'; }
            else if (abs >= 1e6) { divisor = 1e6; suffix = 'M'; }
            else if (abs >= 1e3) { divisor = 1e3; suffix = 'K'; }

            const figure = sign + (abs / divisor).toFixed(2).replace(/\.?0+$/, '') + suffix;

            return AMOUNT_UNIT.prefix ? AMOUNT_UNIT.label + figure : figure + ' ' + AMOUNT_UNIT.label;
        }

        /**
         * Apply a live raise to the card already on screen.
         *
         * Deliberately narrow: only the two things a bid changes — the figure and who is
         * leading. It does NOT re-render the card, because a broadcast payload carries a
         * few fields rather than the whole player, and handing that to updatePlayerCard
         * would blank the stats, the image and the pool that are already correct.
         */
        function renderLiveBid(ap) {
            if (!ap || ap.status !== 'on_auction') return;

            // Element id and formatter mirror the poll path exactly (see updatePlayerCard),
            // so a pushed raise and a polled one are indistinguishable to the room.
            const price = ap.current_price || ap.base_price || 0;
            const bidEl = document.getElementById('current-bid');

            /*
             * The opening figure, which does not change as bidding runs — so it is set from
             * base_price and never from current_price. Null-checked because the template may
             * switch this element off, and an unguarded lookup here is exactly what stopped the
             * whole card rendering when the price was hidden.
             */
            renderBasePrice(ap.base_price, price);
            renderTravelPlan(ap.player);

            if (bidEl) {
                bidEl.textContent = formatMillions(price);

                if (price !== window._lastDisplayedPrice) {
                    bidEl.classList.add('bid-updated');
                    if (window._bidColorTimeout) clearTimeout(window._bidColorTimeout);
                    window._bidColorTimeout = setTimeout(() => {
                        bidEl.classList.remove('bid-updated');
                    }, 1500);
                    window._lastDisplayedPrice = price;
                }
            }

            const soldText = document.getElementById('sold-text');
            const highestBidder = document.getElementById('highest-bidder');
            const bidderName = document.getElementById('bidder-name');

            if (ap.current_bid_team) {
                if (soldText) soldText.textContent = 'CURRENT BID';
                if (bidderName) bidderName.textContent = ap.current_bid_team.name || '';
                if (highestBidder) highestBidder.classList.remove('hidden');
            }

            // The pushed path flashes too, or a raise delivered by websocket would update
            // the price instantly and announce the bidder two seconds later.
            renderBidFlash(ap);
        }

        function showWaiting() {
            console.log('[Live] showWaiting()');
            // The result banner sits outside the card, so hiding the card alone would leave
            // "Sold - Ben Stokes" across a screen that is waiting for the next player.
            clearOutcomeState();
            document.getElementById('waiting-screen').classList.remove('hidden');
            document.getElementById('card-container').classList.add('hidden');
            currentStatus = 'waiting';
        }

        /* ── The waiting screen, told what it is actually waiting for ──
           "WAITING FOR AUCTION" was hardcoded, so a room that had already sold half its
           players still announced that the auction had not begun every time the block was
           empty between players. What decides the wording is not `status` alone — an
           auction is `running` from the moment it starts, before anyone is up — but how
           much of the room has actually been worked through. */
        function renderWaitingScreen(data) {
            const title = document.getElementById('waiting-title');
            const sub = document.getElementById('waiting-sub');
            const bar = document.getElementById('waiting-progress');
            const fill = document.getElementById('waiting-progress-fill');
            const text = document.getElementById('waiting-progress-text');
            if (!title || !sub) return;

            const status = data?.auction_status;
            const p = data?.progress || {};
            const total = Number(p.total || 0);
            const done = Number(p.done || 0);
            const waiting = Number(p.waiting || 0);
            const started = status === 'running' || status === 'paused';

            let heading, subline;

            if (status === 'paused') {
                heading = 'AUCTION PAUSED';
                subline = 'Back shortly';
            } else if (!started) {
                heading = 'WAITING FOR AUCTION';
                subline = AUCTION_NAME;
            } else if (done === 0) {
                // Started, but nobody has been through the block yet.
                heading = 'AUCTION IS LIVE';
                subline = waiting > 0 ? `First player coming up — ${waiting} in the queue` : AUCTION_NAME;
            } else if (waiting === 0) {
                // Everyone has been through, but the auction has not been ended.
                heading = 'ALL PLAYERS DONE';
                subline = AUCTION_NAME;
            } else {
                heading = 'WAITING FOR NEXT PLAYER';
                subline = AUCTION_NAME;
            }

            title.textContent = heading;
            sub.textContent = subline;

            // The rail is meaningless before anyone has been through the block.
            if (bar && fill && text) {
                if (started && total > 0 && done > 0) {
                    bar.classList.remove('hidden');
                    fill.style.width = Math.min(100, (done / total) * 100).toFixed(1) + '%';
                    text.textContent = `${done} of ${total} done · ${waiting} to go`;
                } else {
                    bar.classList.add('hidden');
                }
            }
        }

        function showCard() {
            console.log('[Live] showCard()');
            document.getElementById('waiting-screen').classList.add('hidden');
            document.getElementById('card-container').classList.remove('hidden');
        }

        // Set from each poll before the card is rendered. A variable rather than a new
        // parameter, so the several existing updatePlayerCard() call sites are untouched.
        let sealedState = null;

        function renderSealedBanner(sealed) {
            sealedState = sealed || null;

            const banner = document.getElementById('sealed-banner');
            if (!banner) return;

            if (!sealedState) {
                banner.classList.add('hidden');
                return;
            }

            const title = document.getElementById('sealed-banner-title');
            const line = document.getElementById('sealed-banner-line');

            const labels = {
                pending: ['Closed Bid', 'Bidding has gone private'],
                entry_open: ['Closed Bid', 'Teams are entering the sealed round'],
                collecting: ['Closed Bid', 'Sealed bids are being taken'],
                locked: ['Closed Bid', 'Bidding closed'],
                revealed: ['Closed Bid', 'Result incoming'],
                tie: ['Tie', 'Going to a re-bid'],
                awaiting_lot: ['Tie', 'Drawing a lot'],
                no_entries: ['Closed Bid', 'No team entered'],
            };

            const [heading, text] = labels[sealedState.state] || ['Closed Bid', 'Sealed round'];

            if (title) title.textContent = heading;
            if (line) {
                /*
                 * Always name the round, not only when more than one is configured.
                 *
                 * A tie sends the player to a second round in front of the whole hall, and the
                 * wall said only "Tie — going to a re-bid" — nothing that told the room it was now
                 * watching round 2, or how many there could be. Gating on total_rounds > 1 also
                 * meant a first round never identified itself even in an auction that allows
                 * several.
                 */
                line.textContent = sealedState.round_number
                    ? `${text} — round ${sealedState.round_number} of ${sealedState.total_rounds || 1}`
                    : text;
            }

            renderSealedDraw(sealedState.tie);

            banner.classList.remove('hidden');
        }

        /* The name currently showing in the draw, and the timer cycling it. */
        let _drawCycle = null;
        let _drawSettledFor = null;

        /**
         * The tie, and the draw that settles it.
         *
         * Two states share this block. `tie` lists the teams and the amount they matched, because
         * "TIE — going to a re-bid" told a hall nothing about who or how much. `awaiting_lot` cycles
         * those names until the server records a winner, and then lands on it.
         *
         * The cycling is decoration over a decision already made: the lot is drawn on the server
         * from a recorded seed before any of this runs, so the animation cannot influence it and a
         * screen that joins late simply shows the winner without the spin.
         */
        function renderSealedDraw(tie) {
            const wrap = document.getElementById('sealed-draw');
            if (! wrap) return;

            const nameEl = document.getElementById('sealed-draw-name');
            const amountEl = document.getElementById('sealed-draw-amount');
            const labelEl = document.getElementById('sealed-draw-label');

            const teams = tie?.teams || [];

            if (! tie || teams.length === 0) {
                wrap.classList.add('hidden');
                if (_drawCycle) { clearInterval(_drawCycle); _drawCycle = null; }
                _drawSettledFor = null;
                return;
            }

            wrap.classList.remove('hidden');

            if (amountEl) {
                amountEl.textContent = tie.amount
                    ? `${teams.length} teams matched at ${formatMillions(tie.amount)}`
                    : `${teams.length} teams tied`;
            }

            const winner = tie.lot_winner_team_id
                ? teams.find(t => Number(t.id) === Number(tie.lot_winner_team_id))
                : null;

            if (winner) {
                // Settle once. Without this guard every poll re-runs the landing animation and the
                // winner's name pops every two seconds for the rest of the round.
                if (_drawSettledFor === winner.id) return;
                _drawSettledFor = winner.id;

                if (_drawCycle) { clearInterval(_drawCycle); _drawCycle = null; }

                if (labelEl) labelEl.textContent = 'Lot drawn';
                if (nameEl) {
                    nameEl.textContent = winner.name;
                    nameEl.classList.remove('is-cycling');
                    nameEl.classList.add('is-winner');
                }

                return;
            }

            _drawSettledFor = null;
            if (labelEl) labelEl.textContent = teams.length ? 'Drawing a lot' : '';

            // Already cycling — leave it be, or each poll restarts the sequence.
            if (_drawCycle) return;

            let i = 0;

            const tick = () => {
                if (! nameEl) return;

                nameEl.textContent = teams[i % teams.length].name;
                nameEl.classList.remove('is-winner');
                // Re-add per frame so the keyframe restarts on every name.
                nameEl.classList.remove('is-cycling');
                void nameEl.offsetWidth;
                nameEl.classList.add('is-cycling');

                i++;
            };

            tick();
            _drawCycle = setInterval(tick, 160);
        }

        /**
         * "Restarting" notice.
         *
         * The window is measured by the server, so a projector, an OBS source and every
         * phone watching come back at the same moment rather than each timing its own ten
         * seconds from whenever it last polled.
         */
        function renderRestartNotice(data) {
            const screen = document.getElementById('restart-screen');
            if (!screen) return false;

            if (!data?.restarting) {
                screen.classList.add('hidden');
                return false;
            }

            const secondsEl = document.getElementById('restart-seconds');
            if (secondsEl) secondsEl.textContent = data.restart_seconds ?? '';

            screen.classList.remove('hidden');
            return true;
        }

        function updatePlayerCard(p) {
            console.log('[Live] updatePlayerCard() called with:', p);
            if (!p || !p.player) {
                console.log('[Live] No player data, showing waiting');
                showWaiting();
                return;
            }

            console.log('[Live] Showing card for:', p.player.name);
            showCard();

            const cardContainer = document.getElementById('card-container');

            // Player image
            document.getElementById('player-image').src = p.player.image_path
                ? `/storage/${p.player.image_path}`
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(p.player.name)}`;

            // Stats table — populate all data-field cells
            document.querySelectorAll('#stats-table-wrap td[data-field]').forEach(td => {
                const field = td.dataset.field;
                td.textContent = p.player[field] ?? 0;
            });

            // Player details
            /*
             * The name, then what the room needs beside it.
             *
             * textContent on the <h1> would wipe the badge span with the old name, so the name
             * goes into its own node. A keeper is worth calling out because it changes who wants
             * the player, and the travel plan because it decides whether they can turn up at all
             * — both were on the organizer's screen and on nothing the hall could see.
             */
            const nameEl = document.getElementById('player-name');
            const badgesEl = document.getElementById('player-name-badges');

            if (badgesEl) {
                nameEl.childNodes[0].nodeValue = p.player.name;
            } else {
                nameEl.textContent = p.player.name;
            }

            renderNameBadges(p.player);

            const playerType = p.player.player_type || p.player.playerType;
            document.getElementById('player-role').textContent = displayLabel(
                typeof playerType === 'object'
                    ? playerType?.type || playerType?.name || ''
                    : playerType || ''
            );

            /**
             * A missing style hides its row rather than printing "N/A".
             *
             * Two empty rows reading N/A on a hall screen is worse than two fewer rows:
             * it draws the eye to what the system does not know about the player being
             * auctioned. The column is `style`; `name` is an accessor alias, so both are
             * accepted here.
             */
            const styleText = (value) => {
                if (!value) return '';
                return displayLabel(
                    typeof value === 'object' ? (value.style || value.name || '') : String(value)
                );
            };

            const setStyleRow = (id, value) => {
                const el = document.getElementById(id);
                if (!el) return;
                const text = styleText(value);
                el.textContent = text;
                // Hide the element itself, so a template that positioned it keeps its
                // remaining rows where the designer put them.
                el.style.display = text === '' ? 'none' : '';
            };

            setStyleRow('player-batting', p.player.batting_profile || p.player.battingProfile);
            setStyleRow('player-bowling', p.player.bowling_profile || p.player.bowlingProfile);

            // Who is leading, called out across the top.
            renderBidFlash(p);

            // Show current bid price if available, otherwise base price
            const price = p.current_price || p.base_price || 0;
            /*
             * Null-checked, because a template may switch this element off.
             *
             * isVisible() omits a hidden element from the DOM entirely, so a template with the
             * price turned off made this line throw — and it throws BEFORE the rest of
             * updatePlayerCard(), so the whole card stopped rendering: no name, no stats, no
             * badge. Hiding one field silently broke the entire wall. The sibling at
             * renderLiveBid() was already guarded; this one never was.
             */
            const bidEl = document.getElementById('current-bid');

            /*
             * The opening figure, which does not change as bidding runs — so it is set from
             * base_price and never from current_price. Null-checked because the template may
             * switch this element off, and an unguarded lookup here is exactly what stopped the
             * whole card rendering when the price was hidden.
             */
            renderBasePrice(p.base_price, price);
            renderTravelPlan(p.player);

            if (bidEl) {
                bidEl.textContent = formatMillions(price);

                // Brief green highlight when price changes, then back to white
                if (price !== window._lastDisplayedPrice) {
                    bidEl.classList.add('bid-updated');
                    if (window._bidColorTimeout) clearTimeout(window._bidColorTimeout);
                    window._bidColorTimeout = setTimeout(() => {
                        bidEl.classList.remove('bid-updated');
                    }, 1500);
                    window._lastDisplayedPrice = price;
                }
            }

            // Status text and badges
            const soldText = document.getElementById('sold-text');
            const soldBadge = document.getElementById('sold-badge');
            const unsoldBadge = document.getElementById('unsold-badge');
            const teamLogo = document.getElementById('team-logo');
            const highestBidder = document.getElementById('highest-bidder');
            const bidderName = document.getElementById('bidder-name');

            // Reset all dramatic state classes
            function resetDramaticStates() {
                cardContainer.classList.remove('sold-state', 'unsold-state', 'skipped-state');
                if (soldText) {
                    soldText.classList.remove('sold-active', 'unsold-active', 'skipped-active');
                }
                if (soldBadge) soldBadge.classList.remove('sold-entrance');
                if (unsoldBadge) { unsoldBadge.classList.add('hidden'); unsoldBadge.style.display = 'none'; }
                if (teamLogo) teamLogo.classList.remove('sold-entrance');
            }

            /* Name the outcome. `skipped` and `unsold` are the same thing to a room: the
               player did not sell. Anything still live clears the banner. */
            (function () {
                const banner = document.getElementById('result-banner');
                const word = document.getElementById('result-word');
                const nameEl = document.getElementById('result-name');
                if (!banner || !word || !nameEl) return;

                const outcome = { sold: 'Sold', unsold: 'Unsold', skipped: 'Passed' }[p.status] || null;

                if (!outcome) {
                    banner.classList.add('hidden');
                    return;
                }

                word.textContent = outcome;

                /*
                 * Name the BUYER, not just the player.
                 *
                 * "Sold — Glenn Maxwell" leaves the one fact the room is waiting for off the
                 * loudest thing on the screen. The team is on the card below, but the banner is
                 * what people look up at, and a sale nobody can attribute is a sale that gets
                 * asked about twice.
                 */
                const buyer = p.sold_to_team?.name || p.current_bid_team?.name || '';

                nameEl.textContent = p.status === 'sold' && buyer
                    ? `${p.player?.name || ''} → ${buyer}`
                    : (p.player?.name || '');
                banner.classList.toggle('is-sold', p.status === 'sold');
                banner.classList.toggle('is-unsold', p.status !== 'sold');
                banner.classList.remove('hidden');
            })();

            if (p.status === 'sold') {
                resetDramaticStates();
                /* The badge says SOLD. This label sits over the player image, so repeating
                   the word there just covered the photo with a duplicate of the stamp
                   already on screen. It keeps naming the FIGURE beneath it instead. */
                if (soldText) soldText.textContent = 'FINAL PRICE';
                if (soldBadge) soldBadge.classList.remove('hidden');
                cardContainer.classList.add('sold-state');
                if (soldText) soldText.classList.add('sold-active');
                if (soldBadge) soldBadge.classList.add('sold-entrance');

                // Fire confetti once per sold player
                if (_confettiFiredForPlayer !== p.id) {
                    _confettiFiredForPlayer = p.id;
                    fireConfetti();
                }

                // Show team logo with entrance animation
                if (p.sold_to_team && p.sold_to_team.logo_path) {
                    if (teamLogo) {
                        teamLogo.src = p.sold_to_team.logo_path; // full URL from the API
                        teamLogo.classList.remove('hidden');
                        teamLogo.classList.add('sold-entrance');
                    }
                } else {
                    if (teamLogo) teamLogo.classList.add('hidden');
                }
                if (highestBidder) highestBidder.classList.add('hidden');
            } else if (p.status === 'on_auction') {
                resetDramaticStates();

                // While a sealed round runs there is no public bid to show: the price is
                // frozen at the round's floor and the sealed amounts never leave the
                // server. Say so plainly rather than leaving a stale figure on screen.
                if (sealedState) {
                    if (soldText) soldText.textContent = 'SEALED BIDDING';
                    if (highestBidder) highestBidder.classList.add('hidden');
                    if (soldBadge) soldBadge.classList.add('hidden');
                    if (teamLogo) teamLogo.classList.add('hidden');
                    return;
                }

                if (p.current_bid_team) {
                    if (soldText) soldText.textContent = 'CURRENT BID';
                    if (bidderName) bidderName.textContent = p.current_bid_team.name;
                    if (highestBidder) highestBidder.classList.remove('hidden');
                } else {
                    if (soldText) soldText.textContent = 'BASE VALUE';
                    if (highestBidder) highestBidder.classList.add('hidden');
                }
                if (soldBadge) soldBadge.classList.add('hidden');
                if (teamLogo) teamLogo.classList.add('hidden');
            } else if (p.status === 'unsold') {
                resetDramaticStates();
                cardContainer.classList.add('unsold-state');
                // Same again: the UNSOLD badge is the announcement, this is just its label.
                if (soldText) {
                    soldText.textContent = 'BASE VALUE';
                    soldText.classList.add('unsold-active');
                }
                if (soldBadge) soldBadge.classList.add('hidden');
                if (unsoldBadge) { unsoldBadge.classList.remove('hidden'); unsoldBadge.style.display = 'flex'; }
                if (teamLogo) teamLogo.classList.add('hidden');
                if (highestBidder) highestBidder.classList.add('hidden');
            } else if (p.status === 'skipped') {
                resetDramaticStates();
                cardContainer.classList.add('skipped-state');
                if (soldText) {
                    soldText.textContent = 'BASE VALUE';
                    soldText.classList.add('skipped-active');
                }
                if (soldBadge) soldBadge.classList.add('hidden');
                if (teamLogo) teamLogo.classList.add('hidden');
                if (highestBidder) highestBidder.classList.add('hidden');
            } else {
                resetDramaticStates();
                if (soldText) soldText.textContent = 'BASE VALUE';
                if (soldBadge) soldBadge.classList.add('hidden');
                if (teamLogo) teamLogo.classList.add('hidden');
                if (highestBidder) highestBidder.classList.add('hidden');
            }

            currentStatus = p.status;
            lastPlayerId = p.id;
        }

        function showCompleted() {
            document.getElementById('waiting-screen').classList.add('hidden');
            document.getElementById('card-container').classList.add('hidden');
            document.getElementById('completed-screen').classList.remove('hidden');
            document.getElementById('completed-screen').style.display = 'flex';
        }

        /* ── Closing call + countdown ────────────────────────────────────────────
           The server ships `timer_seconds_remaining` and the call thresholds on every
           poll. Polls are 2s apart but the calls land on exact seconds, so the clock
           ticks locally between polls and re-syncs on each one.

           This is a deliberate second copy of the two-line threshold lookup: this page
           is a standalone document (CDN Tailwind, no Vite bundle) and cannot import the
           admin helper. The *rule* still lives only on the server — both copies just
           read the thresholds it sends.                                              */
        let timerRemaining = null;
        let timerEnabled = false;
        let finalCallStages = [];
        let lastCallStage = 0;
        let timerTick = null;

        function finalCallFor(remaining, stages) {
            if (remaining === null || remaining === undefined || !Array.isArray(stages) || !stages.length) {
                return null;
            }
            return stages.find(s => remaining <= s.at) || null;
        }

        // Longest closing-call threshold seen, used to scale the ring when the round's
        // own limit is not in the payload.
        let clockScale = 30;

        // Whether anybody is actually on the block. The clock hides without one.
        let clockHasPlayer = false;
        // Server-declared. A frozen clock must not keep ticking locally, or the wall counts
        // down past a pause and disagrees with the panel and the stream.
        let clockPaused = false;
        // Whether the player on the block has attracted a bid. Set from the poll, read by
        // the closing-call logic below.
        let noBidsOnBlock = false;

        /**
         * "This player is about to go unsold."
         *
         * Only during a closing call, and only when nobody has bid. The card greys out so the
         * screen reads as a lost lot at a glance from the back of a hall, and the banner says
         * it in words for anyone who is not watching the clock.
         */
        function renderUnsoldWarning(inClosingCall, noBids) {
            const banner = document.getElementById('unsold-warning');
            const card = document.getElementById('card-container');
            const show = inClosingCall && noBids && clockHasPlayer;

            if (banner) banner.classList.toggle('hidden', !show);
            if (card) card.classList.toggle('about-to-go-unsold', show);

            /*
             * The screen-wide dim follows the CLOSING CALL alone — not whether anyone has
             * bid. The card-level grey above says "this player is going unsold"; this says
             * "bidding is closing", which is true whether the lot is about to be won or
             * lost, and is the thing the hall has to notice.
             *
             * Cleared by the same call that clears everything else, so a player who is sold
             * or passed during the call cannot leave the wall grey.
             */
            const dim = document.getElementById('final-call-dim');
            if (dim) dim.classList.toggle('is-on', !!inClosingCall && clockHasPlayer);
        }

        function clearUnsoldWarning() {
            renderUnsoldWarning(false, false);
        }

        function renderClock() {
            const hud = document.getElementById('clock-hud');
            const ring = document.getElementById('clock-ring');
            const secondsEl = document.getElementById('clock-seconds');
            const caption = document.getElementById('clock-caption');
            const callEl = document.getElementById('clock-call');
            const dial = document.getElementById('clock-dial');
            if (!hud || !ring) return;

            // A countdown only means something while somebody is on the block. After a
            // pass, a sale or a restart the stamp on the auction can still be recent, so
            // without this check the HUD counts down over the waiting screen.
            if (!timerEnabled || timerRemaining === null || timerRemaining === undefined || !clockHasPlayer) {
                hud.classList.add('hidden');
                lastCallStage = 0;
                renderUnsoldWarning(false, false);
                return;
            }

            const seconds = Math.max(0, timerRemaining);
            // A closing call on a stopped clock is a lie: nothing is closing.
            const call = clockPaused ? null : finalCallFor(seconds, finalCallStages);

            /* A countdown at zero has finished. Leaving "0 — FINAL CALL" frozen on the wall
               kept calling a player nobody was still bidding on, and the call is the loudest
               thing on the screen. The unsold notice below outlives it on purpose: that state
               really does persist until the organizer passes the player. */
            if (seconds <= 0 && ! clockPaused) {
                hud.classList.add('hidden');
                hud.style.animation = 'none';
                lastCallStage = 0;
                renderUnsoldWarning(true, noBidsOnBlock);
                return;
            }

            hud.classList.remove('hidden');

            clockScale = Math.max(clockScale, seconds);

            // 2πr for r=42. Deplete clockwise as the seconds run down.
            const CIRCUMFERENCE = 263.9;
            const fraction = clockScale > 0 ? Math.min(1, seconds / clockScale) : 0;
            ring.setAttribute('stroke-dashoffset', String(CIRCUMFERENCE * (1 - fraction)));

            // Grey the card and say what is about to happen. `noBids` is the whole test:
            // a closing call with a leading team is drama, without one it is a foregone
            // conclusion nobody in the room has been told about.
            renderUnsoldWarning(!!call, noBidsOnBlock);

            if (call) {
                // Closing call: the HUD grows, turns, and names the call.
                const colour = call.is_final ? '#f43f5e' : '#f59e0b';
                ring.setAttribute('stroke', colour);
                secondsEl.style.color = colour;
                // The kicker must not repeat the label underneath it — "Final Call" above
                // "FINAL CALL" read as a mistake. It says how long is left instead.
                caption.textContent = seconds === 1 ? '1 second left' : `${seconds} seconds left`;
                caption.style.color = colour;
                callEl.textContent = call.label;
                callEl.style.color = '#fff';
                callEl.style.display = '';
                hud.style.borderColor = colour;
                hud.style.boxShadow = `0 24px 70px rgba(0,0,0,0.6), 0 0 46px ${colour}66`;

                // Punch in only when a NEW call fires, not on every tick.
                if (call.stage > lastCallStage) {
                    hud.style.animation = 'none';
                    void hud.offsetWidth; // force reflow so the animation restarts
                    hud.style.animation = 'finalCallPulse 0.9s cubic-bezier(0.34,1.56,0.64,1) both';
                    lastCallStage = call.stage;
                }
            } else {
                /*
                 * No countdown on the wall.
                 *
                 * A ticking number on a hall screen adds nothing the room can act on — the
                 * clock belongs to whoever is running the auction — and it duplicated the
                 * countdown already on the organizer's panel, so the two could visibly
                 * disagree by a poll in front of everyone. The wall is told only when a
                 * player is CLOSING, which is the part the room has to react to.
                 *
                 * The clock itself is untouched: timerRemaining still ticks, finalCallFor()
                 * still fires off it, and the closing-call branch above still runs.
                 */
                lastCallStage = 0;
                hud.classList.add('hidden');
                hud.style.animation = 'none';
                return;
            }

            // Reached only during a closing call, and the dial goes with the countdown.
            if (dial) dial.style.display = 'none';
            secondsEl.textContent = seconds;
        }

        /**
         * Tick the countdown between polls.
         *
         * The server is polled every 2 seconds, so without a local tick the number jumps
         * 30, 28, 26 — which on a hall screen reads as a broken clock, and means a closing
         * call can land up to two seconds late. This only ever counts DOWN from the last
         * server value; the next poll overwrites it, so the server stays authoritative and
         * drift cannot accumulate.
         *
         * This function was called by syncClock() but never actually defined — a
         * ReferenceError on every single poll. The rejection was swallowed by the poll's
         * .catch(), and because it threw AFTER the clock rendered but BEFORE the player-card
         * branch, the wall showed a live countdown over a waiting screen and the card could
         * never appear at all.
         */
        let clockTickInterval = null;

        function stopClockTick() {
            if (clockTickInterval) { clearInterval(clockTickInterval); clockTickInterval = null; }
        }

        function startClockTick() {
            stopClockTick();

            // Paused: the server is holding the number, so nothing local may move it.
            if (!timerEnabled || timerRemaining === null || timerRemaining === undefined || clockPaused) return;

            clockTickInterval = setInterval(() => {
                if (timerRemaining === null || timerRemaining <= 0) {
                    stopClockTick();
                    return;
                }
                timerRemaining -= 1;
                renderClock();
            }, 1000);
        }

        function syncClock(data) {
            /*
             * A sealed round runs on its OWN clock, not the auction's.
             *
             * The auction-level timer belongs to open bidding and stops when the sealed round
             * opens, so the wall showed no countdown at all for the whole round — the hall had
             * no idea how long the teams had left. When one is running its clock wins.
             */
            const sealedTimer = data?.closed_bid?.timer;

            if (sealedTimer && sealedTimer.applies && sealedTimer.remaining !== null) {
                timerEnabled = true;
                timerRemaining = sealedTimer.remaining;
                clockPaused = false;
                if (sealedTimer.limit) clockScale = Number(sealedTimer.limit);
                renderClock();
                startClockTick();
                return;
            }

            timerEnabled = !!data?.timer_enabled;
            timerRemaining = data?.timer_seconds_remaining ?? null;
            clockPaused = !!data?.timer_paused;
            // The server sends the round's limit, so the ring is scaled to the real
            // window rather than to whatever the longest countdown so far happened to be.
            if (data?.bid_timer_seconds) clockScale = Number(data.bid_timer_seconds);
            if (Array.isArray(data?.final_call_stages)) {
                finalCallStages = data.final_call_stages;
            }
            renderClock();
            startClockTick();
        }

        function hideClock() {
            stopClockTick();
            timerEnabled = false;
            timerRemaining = null;
            renderClock();
            // No clock means no closing call — the warning must not outlive it.
            clearUnsoldWarning();
        }

        function fetchActivePlayer() {
            console.log('[Live] fetchActivePlayer() called');
            fetch(`/auction/${auctionId}/active-player`)
                .then(res => res.json())
                .then(data => {
                    console.log('[Live] API response:', data);

                    // Before the card renders: the sealed banner also sets the flag
                    // updatePlayerCard() reads to suppress the public bid figure.
                    renderSealedBanner(data.closed_bid || null);

                    /* Sealed transitions are not broadcast, so while a round is live the wall
                       has to keep asking. scheduleWallPoll() reads this on the way out of
                       every fetch. */
                    sealedActive = !!(data.closed_bid && data.closed_bid.active);

                    // No player on the block means no meaningful countdown.
                    clockHasPlayer = data?.auctionPlayer?.status === 'on_auction';

                    // Kept so the .catch() below can still put the card up if the rest of
                    // this handler fails.
                    if (data?.auctionPlayer) lastGoodPlayer = data.auctionPlayer;

                    // A bid exists when a team is leading. `current_price > base_price` is
                    // not a safe substitute: an offline opening bid can land exactly on base.
                    noBidsOnBlock = clockHasPlayer && ! (
                        data?.auctionPlayer?.current_bid_team
                        || data?.auctionPlayer?.current_bid_team_id
                    );

                    // Wording, progress and the up-next teaser, whatever the screen ends
                    // up showing. Cheap, and it keeps the waiting screen correct the moment
                    // it becomes visible rather than one poll later.
                    renderWaitingScreen(data);

                    /*
                     * A restart owns the screen for its window. Returning here also keeps the
                     * shuffle from firing on the next player until the notice ends, so the two
                     * announcements cannot overlap.
                     *
                     * But the return skips everything below, including the calls that clear the
                     * per-player overlays — so whatever was on screen when the restart began
                     * stayed there through the whole window. A player in a closing call with no
                     * bids left the hall reading "NO BIDS — PLAYER WILL GO UNSOLD" across a
                     * "RESTARTING AUCTION" notice: two contradictory announcements at once,
                     * about a player who is no longer on the block.
                     *
                     * A restart means no player, so none of those states can be true. Cleared
                     * before handing the screen over.
                     */
                    if (renderRestartNotice(data)) {
                        clockHasPlayer = false;
                        noBidsOnBlock = false;
                        renderUnsoldWarning(false, false);
                        hideClock();

                        const pausedDuringRestart = document.getElementById('paused-overlay');
                        if (pausedDuringRestart) pausedDuringRestart.classList.add('hidden');

                        return;
                    }

                    // Real-time PAUSED overlay (reflects organizer pause/resume within ~2s).
                    const pausedOverlay = document.getElementById('paused-overlay');
                    if (pausedOverlay) {
                        pausedOverlay.classList.toggle('hidden', data?.auction_status !== 'paused');
                    }

                    // ── Clock first, and unconditionally ──
                    /* Paused is no longer a reason to skip this. The clock used to be hidden
                       outright on a pause, so the hall saw the countdown vanish with nothing
                       to explain it — and the "Paused" state the server now reports could
                       never be shown. renderClock() presents the frozen figure itself. */
                    if (data?.auctionPlayer?.status === 'on_auction'
                        && data?.auction_status !== 'completed') {
                        // syncClock picks the sealed round's clock over the auction's when a
                        // round is running, so this one call covers both phases.
                        syncClock(data);
                    } else {
                        hideClock();
                    }

                    /*
                     * A completed auction is NOT a reason to blank the wall.
                     *
                     * This returned early and put up a full-screen "auction complete", so the
                     * moment the status flipped the hall lost the player who had just been
                     * won — while the organizer was still working through the sale on the
                     * panel. The room wants to see the winning team, not a notice that the
                     * event is over.
                     *
                     * The completed screen is kept only for the case where there is genuinely
                     * nothing to show: no player on the block and no last result to hold.
                     */
                    if (data?.auction_status === 'completed'
                        && ! data?.auctionPlayer
                        && ! data?.lastActionPlayer) {
                        showCompleted();
                        return;
                    }

                    // Anything else: fall through and keep rendering the card, which for a
                    // finished auction is the last player sold, with their winning team.
                    document.getElementById('completed-screen')?.classList.add('hidden');

                    if (data?.waitingPlayers && data.waitingPlayers.length > 0) {
                    }

                    if (data?.auctionPlayer) {
                        const ap = data.auctionPlayer;
                        console.log('[Live] Got active player:', ap.player?.name, 'status:', ap.status);

                        if (ap.status === 'on_auction') {
                            if (ap.id !== lastOnAuctionPlayerId) {
                                if (!hasCompletedFirstLoad) {
                                    console.log('[Live] First load, showing card directly');
                                    hasCompletedFirstLoad = true;
                                    lastOnAuctionPlayerId = ap.id;
                                    lastPlayerId = ap.id;
                                    updatePlayerCard(ap);
                                } else {
                                    /*
                                     * Straight to the card. No reveal on the public screens.
                                     *
                                     * The spin-and-reveal cycled the names still in the queue,
                                     * so the hall was shown who was coming up before the draw
                                     * had committed to them — and the operator's own panel runs
                                     * the same animation, which is where it belongs. Removed on
                                     * request for the same reason as the "Coming up" teaser.
                                     */
                                    lastOnAuctionPlayerId = ap.id;
                                    lastPlayerId = ap.id;
                                    updatePlayerCard(ap);
                                }
                                return;
                            }
                            updatePlayerCard(ap);
                        } else {
                            hasCompletedFirstLoad = true;
                            updatePlayerCard(ap);
                        }
                    } else {
                        hasCompletedFirstLoad = true;
                        const lap = data.lastActionPlayer;
                        console.log('[Live] No active player, lastActionPlayer:', lap?.player?.name, lap?.status);

                        if (lap && lap.id !== lastActionPlayerId) {
                            lastActionPlayerId = lap.id;
                            lastPlayerId = lap.id;
                            updatePlayerCard(lap);
                        } else if (lap && lap.id === lastActionPlayerId) {
                            updatePlayerCard(lap);
                        } else {
                            showWaiting();
                        }
                    }
                })
                .catch(err => {
                    /* A throw anywhere in the handler above used to be swallowed here, and
                       whatever was on screen stayed there for the rest of the auction — a
                       missing function reference froze the wall on its waiting screen for
                       every poll. The card is the whole point of this page, so recover to it
                       rather than leaving the hall looking at nothing. */
                    console.error('[Live] Fetch error:', err);

                    try {
                        if (lastGoodPlayer) {
                            console.warn('[Live] recovering the card after a handler error');
                            updatePlayerCard(lastGoodPlayer);
                        }
                    } catch (e) {
                        console.error('[Live] recovery failed too:', e);
                    }
                });
        }

        // Listen to public channel for real-time events
        if (! CARD_PAYLOAD)
        window.Echo.channel(`auction.${auctionId}`)
            /*
             * A sealed round moved — opened, locked, revealed, tied, drawn or awarded.
             *
             * Nothing was pushed for any of this, so the wall kept showing open bidding until
             * somebody reloaded it, and the tie-break draw appeared after the fact rather than
             * as it happened. The frame carries no amounts — only THAT the state changed — so
             * the refresh re-reads the feed, which applies the same disclosure rules it always
             * has.
             *
             * On `auction.X`, with the raises and sales. AuctionStatusUpdate uses its own
             * `auction.public.X`, and a listener on the wrong one of the two is simply never
             * called.
             */
            .listen('.sealed.changed', (event) => {
                console.info('[Live] sealed round:', event?.state);
                refreshNow('sealed:' + (event?.state ?? '?'));
            })
            .listen('.player-on-sold', (event) => {
                console.log('[Live] Player sold event:', event);
                const auctionPlayer = event.auctionPlayer;
                if (event.winningTeam) {
                    auctionPlayer.sold_to_team = event.winningTeam;
                }

                /*
                 * Celebrate a SALE, never a pass.
                 *
                 * passPlayer() deliberately broadcasts this same `player-on-sold` event so
                 * every screen updates — but with no winning team. This listener fired the
                 * confetti regardless, so a player nobody wanted got the same celebration as
                 * a record buy, in front of the player. The poll path was already gated on
                 * status === 'sold'; only this one was not.
                 */
                const reallySold = !!event.winningTeam || auctionPlayer.status === 'sold';

                if (reallySold && _confettiFiredForPlayer !== auctionPlayer.id) {
                    _confettiFiredForPlayer = auctionPlayer.id;
                    fireConfetti();
                }
                updatePlayerCard(auctionPlayer);
                // Brings the sales strip, the pool chip and the counts with it.
                refreshNow('sold');
            })
            .listen('.player.onbid', (event) => {
                console.log('[Live] Player on-bid event (instant):', event);
                hasCompletedFirstLoad = true;

                const ap = event.auctionPlayer;
                if (!ap) return;

                if (ap.id !== lastOnAuctionPlayerId) {
                    // Straight to the card — see the poll path above.
                    lastOnAuctionPlayerId = ap.id;
                    lastPlayerId = ap.id;
                    updatePlayerCard(ap);
                    // A new player brings a new clock, pool position and stats with them.
                    refreshNow('new-player');
                    return;
                }

                /*
                 * Same player, new price: apply it now.
                 *
                 * This branch did not exist — the handler only ever started a shuffle for a
                 * NEW player, so even with the event delivering, a raise on the player
                 * already up did nothing and the hall waited for the next poll.
                 *
                 * Taken from the event rather than by re-fetching, because the payload IS
                 * the figure that was just written, while the public feed is micro-cached
                 * for a second and could hand back the price this event supersedes.
                 */
                renderLiveBid(ap);
            })
            /*
             * The raise itself.
             *
             * `player.onbid` only fires where the organizer put a player up or edited a
             * price; a team raising from its own screen published nothing at all, so the
             * hall learned about the bidding one poll late — and the poll is served from a
             * one-second cache on top of that. This is the event that carries a raise.
             *
             * A flat payload rather than a model, so nothing here re-fetches: the numbers on
             * the frame ARE what was just written, while the public feed is micro-cached and
             * can hand back the price this event supersedes.
             */
            .listen('.bid.raised', (event) => {
                if (!event) return;
                if (Number(event.auction_player_id) !== Number(lastOnAuctionPlayerId)) return;

                // Frames arrive unordered and can repeat, so bid_id decides. Without this a
                // late frame drops the price back below what the hall has already seen.
                const bidId = Number(event.bid_id) || 0;
                if (bidId <= _lastAppliedBidId) return;
                _lastAppliedBidId = bidId;

                hasCompletedFirstLoad = true;

                renderLiveBid({
                    id: event.auction_player_id,
                    // renderLiveBid() refuses anything not on the block. Safe to assert here:
                    // the id was already matched against lastOnAuctionPlayerId above, which is
                    // only ever set for the player currently up.
                    status: 'on_auction',
                    current_price: event.current_price,
                    current_bid_team_id: event.current_bid_team_id,
                    current_bid_team: event.current_bid_team_id
                        ? { id: event.current_bid_team_id, name: event.team_name }
                        : null,
                });

                // The price is already on screen from the frame above; this follows with the
                // restarted clock and anything else the raise moved.
                refreshNow('bid');
            });

        /*
         * Pause, resume, end and restart.
         *
         * AuctionStatusUpdate publishes on its own channel (`auction.public.X`), not the one
         * above, so it needs a second subscription. Without it the hall would keep counting
         * down through a pause until the heartbeat came round — the one state the room most
         * needs to see immediately.
         */
        if (! CARD_PAYLOAD)
        window.Echo.channel(`auction.public.${auctionId}`)
            .listen('.auction.status', (event) => {
                console.info('[Live] auction status:', event?.status);
                refreshNow('status:' + (event?.status ?? '?'));
            });

        /*
         * The poll is a BACKSTOP, not the mechanism.
         *
         * Sales and bids both arrive by broadcast now, so this exists to recover a screen
         * that missed an event — a dropped websocket, a laptop resumed from sleep, a wall
         * opened halfway through — and to carry the states nothing broadcasts (the timer,
         * the pool progress, a restart). Left at two seconds deliberately: tightening it
         * would add load to fix a delay that the events have already removed.
         */
        /*
         * Chained, not on an interval.
         *
         * setInterval fires whether or not the last request came back, so a slow server
         * stacks them until the browser's per-host connection limit is exhausted and the
         * wall stops updating entirely — the failure looks like a frozen screen in front of
         * a hall. Scheduling the next only once the previous settles makes that impossible.
         */
        let _wallPollTimer = null;

        /*
         * No periodic requests while push is healthy.
         *
         * The wall refetches when something actually changes, and otherwise sits silent. A
         * timer is only used where there is genuinely no alternative:
         *
         *   push is down  -> 2s, the cadence that ran before push existed. Nothing else can
         *                    keep the hall current, and a reconnect refetches on the way back.
         *   sealed round  -> 2s. Its state transitions (entry open, collecting, locked,
         *                    revealed, tie, lot) are the one part of the auction nothing
         *                    broadcasts, so they can only be discovered by asking.
         *
         * Recovery does not need a heartbeat: pusher-js reconnects on its own and the
         * `connected` binding refetches, which closes the window where events were missed.
         * Returning to visibility and regaining the network refetch for the same reason.
         */
        const POLL_MS = 2000;
        let pushConnected = false;
        let sealedActive = false;

        function scheduleWallPoll() {
            clearTimeout(_wallPollTimer);

            // Silence is the normal state: push is up and nothing needs asking for.
            if (pushConnected && !sealedActive) return;

            _wallPollTimer = setTimeout(pollWall, POLL_MS);
        }

        function pollWall() {
            Promise.resolve(fetchActivePlayer())
                .catch(() => {})
                .finally(scheduleWallPoll);
        }

        /*
         * CARD MODE — one player, rendered once, nothing live.
         *
         * The download of a player's card is a screenshot of this very page, so the file and
         * the LED wall cannot drift apart: one set of positions, one background, one set of
         * CSS. The alternative was re-drawing the card in GD from element_positions, which
         * means maintaining a second renderer that has to agree with this one forever.
         *
         * So in card mode the page renders the player it was given and stops: no poll, no
         * socket, no clock, no shuffle. Guarded on a variable the wall never receives, so the
         * live path below is untouched when it is absent.
         */
        if (CARD_PAYLOAD) {
            // The sold badge is the whole point of the "with result" variant, and
            // updatePlayerCard() reads status to decide whether to stamp it.
            if (! CARD_SHOW_RESULT) {
                CARD_PAYLOAD.status = 'on_auction';
                CARD_PAYLOAD.sold_to_team = null;
                CARD_PAYLOAD.final_price = null;
            }

            renderSealedBanner(null);
            updatePlayerCard(CARD_PAYLOAD);
            hideClock();
            document.getElementById('final-call-dim')?.classList.remove('is-on');

            /* Tells the screenshotter the paint is finished. Waiting on a fixed delay instead
               would either cut the image short or add seconds to every download. */
            document.body.setAttribute('data-card-ready', '1');

            /*
             * Download in the BROWSER, not on the server.
             *
             * The server-side render needs Chrome to fetch this page over HTTP while the
             * request that started it is still open — which `php artisan serve` cannot do,
             * being single-threaded, so it deadlocked and timed out. Capturing the card here
             * needs no second request at all: the card is already on screen, and html2canvas
             * paints the same DOM to a canvas.
             *
             * The server route still exists and still works where the app has more than one
             * worker; this is the path that works everywhere.
             */
            const dlBtn = document.getElementById('card-download');

            if (dlBtn && window.html2canvas) {
                dlBtn.classList.remove('hidden');

                /* A named function, not `async () =>`: the wall's script-integrity test reads
                   `async (` as a call to an undefined `async`, and that guard is worth more
                   than the terser form. */
                async function captureCard() {
                    const card = document.getElementById('card-container');
                    if (!card) return;

                    const label = dlBtn.textContent;
                    dlBtn.textContent = 'Rendering…';
                    dlBtn.disabled = true;

                    /* Capture the card at its true 1601x910, not at whatever fraction of it
                       the window is showing. scaleLive() fits the card to the window, and
                       html2canvas honours that transform — which would otherwise bake the
                       preview's scaling into the downloaded file. */
                    const savedTransform = card.style.transform;
                    card.style.transform = '';

                    try {
                        const canvas = await html2canvas(card, {
                            // Same-origin assets, but the flag is needed for the crossOrigin
                            // attribute html2canvas puts on the images it re-fetches.
                            useCORS: true,
                            // The card paints its own background; a white canvas underneath
                            // would show through anywhere the artwork is transparent.
                            backgroundColor: null,
                            // The card is a fixed 1601x910 canvas, so capture it at its own
                            // size rather than at the device pixel ratio.
                            scale: 1,
                            logging: false,
                        });

                        const link = document.createElement('a');
                        link.download = @json($cardFilename ?? 'player-card.png');
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                    } catch (e) {
                        console.error('[Card] capture failed:', e);
                        alert('Could not capture the card: ' + e.message);
                    } finally {
                        card.style.transform = savedTransform;
                        dlBtn.textContent = label;
                        dlBtn.disabled = false;
                    }
                }

                dlBtn.addEventListener('click', captureCard);
            }
        } else {

        pollWall();

        /* The wall reports its own transport too, and drives the heartbeat from it: a dropped
           socket returns the poll to its pre-push 2s cadence rather than leaving the hall ten
           seconds behind. */
        try {
            const wallConn = window.Echo.connector.pusher.connection;
            wallConn.bind('connected', () => {
                console.info('[Live] LIVE — pusher connected');
                pushConnected = true;
                // Catch up on anything missed while it was down, THEN go quiet. This is what
                // replaces the heartbeat as the recovery path.
                refreshNow('pusher-connected');
                scheduleWallPoll();
            });
            ['unavailable', 'failed', 'disconnected'].forEach((state) => {
                wallConn.bind(state, () => {
                    console.warn('[Live] pusher ' + state + ' — polling until it returns.');
                    pushConnected = false;
                    scheduleWallPoll();
                });
            });
        } catch (e) {
            console.warn('[Live] could not observe pusher connection:', e);
        }

        /*
         * Refetch because something actually changed.
         *
         * Events trigger a refetch rather than each one patching the DOM itself: the feed is
         * the authoritative shape every renderer here already understands, so there is one
         * render path instead of one per event — and no chance of two events disagreeing about
         * the same field. The price is still applied straight from the raise frame for
         * instant feedback; this brings the rest (player, sale, clock, pool) with it.
         *
         * Debounced, because a sale arrives as two events and a bid burst as several; without
         * it each one would fire its own request.
         */
        let _refreshTimer = null;

        function refreshNow(reason) {
            clearTimeout(_refreshTimer);
            _refreshTimer = setTimeout(() => {
                console.info('[Live] refresh:', reason);
                fetchActivePlayer();
            }, 150);
        }

        /* A screen that was hidden or offline may have missed events entirely; both are
           moments where asking once is worth more than any timer. */
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshNow('tab-visible');
        });
        window.addEventListener('online', () => refreshNow('network-online'));

        // Initial fetch
        fetchActivePlayer();

        }   // end: live mode (see CARD_PAYLOAD above)

        // ── Responsive scaling for card container ──
        const canvasWidth = {{ $canvasWidth }};
        const canvasHeight = {{ $canvasHeight }};
        /**
         * Scale the designed card to fill the screen.
         *
         * The scale used to be capped at 1 (`Math.min(scaleX, scaleY, 1)`), so on anything
         * larger than the design size the card refused to grow: a 1601x910 template sat at
         * native size in the middle of a 1920x1080 projector with black bands around it, and
         * the template's own background covered only part of the screen. There is no reason
         * to cap it — everything on the card is either vector, text, or an image with room to
         * spare, and the alternative is deliberately wasting the display.
         *
         * Aspect ratio is preserved on purpose. Stretching to fill both axes would distort
         * every logo and photo, and cropping would cut elements the designer positioned; with
         * a 1601x910 canvas (1.76) on a 16:9 screen (1.78) the remaining letterbox is about
         * one percent.
         */
        function scaleLive() {
            const container = document.getElementById('card-container');
            if (!container || container.classList.contains('hidden')) return;

            const scale = Math.min(
                window.innerWidth / canvasWidth,
                window.innerHeight / canvasHeight
            );

            container.style.transform = `scale(${scale})`;
            container.style.transformOrigin = 'center center';
        }
        window.addEventListener('resize', scaleLive);

        /*
         * Also re-scale on anything else that changes the space available.
         *
         * `resize` covers the ordinary cases, but a wall runs for hours on a screen somebody
         * else is fiddling with — browser zoom, entering or leaving fullscreen, a devtools pane,
         * an on-screen keyboard, a projector renegotiating its mode. Any of those can change the
         * viewport without a resize event landing where we expect it, and the failure is silent
         * and total: the card keeps its old scale and the right-hand third goes off the screen.
         *
         * Cheap insurance — the observer fires only when the box actually changes.
         */
        if (window.ResizeObserver) {
            new ResizeObserver(() => scaleLive()).observe(document.body);
        }

        // Zoom on some platforms moves only the visual viewport, which does not resize the layout.
        window.visualViewport?.addEventListener('resize', scaleLive);

        /*
         * Fit the card to the window in card mode too.
         *
         * scaleLive() is wired into showCard(), which only the live path uses, so a card page
         * was drawn at its true 1601x910 — and a laptop window showed the MIDDLE of it with
         * the top and bottom cut off. That reads as broken clipping when nothing is clipped:
         * the page simply never scaled. The headless renderer opens a window of exactly the
         * canvas size, so it still gets scale 1 and the PNG is unchanged.
         *
         * Called HERE, not up in the card branch: scaleLive() closes over canvasWidth and
         * canvasHeight, which are `const`s declared just above this line. Calling it earlier
         * hits them in the temporal dead zone, and the ReferenceError kills the rest of the
         * script — including the data-card-ready flag the screenshotter waits for.
         */
        if (CARD_PAYLOAD) scaleLive();
        // Entering or leaving fullscreen changes the viewport without firing resize on
        // every browser, and the wall is run fullscreen more often than not.
        document.addEventListener('fullscreenchange', () => setTimeout(scaleLive, 60));
        /**
         * Shrink a text element until it fits inside the card.
         *
         * Every text element is positioned by a `left` and given a font size, with no width —
         * so a long name simply ran on past the edge of the artwork. It used to spill onto the
         * page, which looked wrong; now that the canvas clips its children it is cut off
         * instead, which is worse, because the end of somebody's name disappears.
         *
         * The designed size is remembered on the node and restored before every measurement,
         * so a short name after a long one goes back to full size rather than inheriting the
         * shrink. An element the template gives an explicit width is fitted to that width; the
         * rest are fitted to what remains of the canvas to their right.
         */
        const FIT_MIN_PX = 12;

        function fitTextElement(el) {
            if (!el || el.classList.contains('hidden') || !el.textContent.trim()) return;

            if (!el.dataset.designedFontSize) {
                el.dataset.designedFontSize = parseFloat(getComputedStyle(el).fontSize) || 16;
            }

            const designed = parseFloat(el.dataset.designedFontSize);
            el.style.fontSize = designed + 'px';

            // An explicit width wins; otherwise the room left between this element and the
            // right edge of the canvas, less a small margin so text never touches it.
            const explicit = parseFloat(getComputedStyle(el).width);
            const hasExplicitWidth = el.style.width && el.style.width !== 'auto';
            const available = hasExplicitWidth
                ? explicit
                : Math.max(40, canvasWidth - el.offsetLeft - 24);

            let size = designed;

            // scrollWidth is the laid-out width of the content, which is what overflows.
            while (el.scrollWidth > available && size > FIT_MIN_PX) {
                size -= 1;
                el.style.fontSize = size + 'px';
            }
        }

        /**
         * The player's travel plan, when they have one.
         *
         * The label is computed by the Player model (travel_plan_label) rather than assembled
         * here, so the wall, the organizer panel and the downloaded card cannot answer the same
         * question three different ways. Hidden by visibility rather than display, so a template
         * author dragging the element in the editor still sees where it sits.
         */
        /**
         * Wicket keeper and travel plan, beside the player's name.
         *
         * Both read from the same fields every other screen uses — `is_wicket_keeper` and the
         * model's `travel_plan_label` accessor — so the wall cannot disagree with the panel or
         * the pools list about either. Nothing is drawn when there is nothing to say.
         */
        function renderNameBadges(player) {
            const el = document.getElementById('player-name-badges');
            if (! el) return;

            const parts = [];

            if (player?.is_wicket_keeper) {
                parts.push(
                    '<span class="badge badge-wk">'
                    + '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a4 4 0 00-4 4v1.2A3 3 0 004 10v4a4 4 0 004 4h4a4 4 0 004-4v-4a3 3 0 00-2-2.8V6a4 4 0 00-4-4zm-2 4a2 2 0 114 0v1H8V6z"/></svg>'
                    + 'WK</span>'
                );
            }

            const travel = player?.travel_plan_label;

            if (travel) {
                parts.push(
                    '<span class="badge badge-travel">'
                    + '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>'
                    + escapeHtml(travel) + '</span>'
                );
            }

            el.innerHTML = parts.join('');
        }

        /** Text into markup, since the badges are built as HTML. */
        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            })[c]);
        }

        function renderTravelPlan(player) {
            const el = document.getElementById('travel-plan');
            if (! el) return;

            const label = player?.travel_plan_label || '';
            const valueEl = document.getElementById('travel-plan-value');

            if (valueEl) valueEl.textContent = label;
            el.style.visibility = label ? 'visible' : 'hidden';
        }

        /**
         * Show the opening figure only when it says something the live figure does not.
         *
         * Before the first bid, `current_bid` IS the base price — so the card read
         * "BASE VALUE 1M Points" next to "BASE PRICE 1M Points": the same number twice, in
         * two different labels, which looks like a rendering fault rather than a design.
         * The opening figure earns its place the moment the price moves away from it, and
         * on a sold card, where the two together tell the story of the lot.
         */
        function renderBasePrice(basePrice, livePrice) {
            const el = document.getElementById('base-price');
            if (!el) return;

            const base = Number(basePrice || 0);
            const live = Number(livePrice || 0);
            const valueEl = document.getElementById('base-price-value');

            if (valueEl) valueEl.textContent = formatMillions(base);

            el.style.visibility = (base > 0 && live > base) ? 'visible' : 'hidden';
        }

        function fitCardText() {
            [
                'player-name', 'player-role', 'player-batting', 'player-bowling',
                'current-bid', 'bid-label', 'bidder-name', 'sold-text', 'base-price', 'travel-plan',
            ].forEach((id) => fitTextElement(document.getElementById(id)));
        }

        /*
         * Wrapped rather than called at the end of updatePlayerCard(): that function has
         * several early returns (a sealed round, a missing player), and text set on the way to
         * one of them would never be fitted.
         */
        const origUpdatePlayerCard = updatePlayerCard;
        updatePlayerCard = function (p) {
            origUpdatePlayerCard(p);
            fitCardText();
        };

        const origRenderLiveBid = renderLiveBid;
        renderLiveBid = function (ap) {
            origRenderLiveBid(ap);
            fitCardText();
        };

        // Scale on initial load and whenever card becomes visible
        const origShowCard = showCard;
        showCard = function() {
            origShowCard();
            setTimeout(scaleLive, 50);
            setTimeout(fitCardText, 60);
        };

        scaleLive();

        /* Web fonts land after first paint and change every measurement, so anything fitted
           before they arrive is fitted against the fallback face. */
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(fitCardText);
        }
        window.addEventListener('resize', fitCardText);
    </script>

</body>

</html>
