@php
/*
 * The waiting stage, defined once and used on both the waiting screen and the restart notice.
 *
 * It was two copies of a bat-and-ball SVG that had already drifted apart — the restart copy had
 * lost the blade gradient and the ball's stitching. One string cannot drift from itself.
 */
$gavelStage = <<<'HTML'
<div class="gavel-stage" style="z-index:1;">
    <div class="gavel-base"></div>
    <div class="gavel-block"></div>
    <div class="gavel-flash"></div>

    <svg class="auction-gavel" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
        {{-- Solid fills, no gradients and no `url(#id)` at all.
             This stage is rendered TWICE on the page — the waiting screen and the restart notice —
             so both copies carried the same gradient ids, and the first copy lives inside a screen
             that is display:none most of the time. A paint server in a hidden subtree does not
             reliably resolve, so on the restart screen the head lost its fill entirely and the
             gavel came apart into loose bars with a grey stripe through it. Flat colour cannot
             fail that way, and at wall size the gradient was never the thing doing the work. --}}
        <g transform="rotate(-38 16 92)">
            <rect x="12" y="85" width="80" height="14" rx="7" fill="#8d571f"/>
            <rect x="12" y="86.5" width="80" height="3" rx="1.5" fill="#b07c3c"/>
            <rect x="12" y="85" width="14" height="14" rx="7" fill="#4a2709"/>
        </g>
        <g transform="rotate(52 78 44)">
            <rect x="55" y="28" width="46" height="32" rx="8" fill="#a9682f"/>
            <rect x="55" y="29" width="46" height="8" rx="4" fill="#c98d4d"/>
            <rect x="55" y="52" width="46" height="8" rx="4" fill="#7d4718"/>
            <rect x="53" y="26" width="7" height="36" rx="3.5" fill="#4a2709"/>
            <rect x="96" y="26" width="7" height="36" rx="3.5" fill="#4a2709"/>
        </g>
    </svg>
</div>
HTML;
@endphp

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

        /*
         * ── The sale: one hammer, one popper, nothing else ──
         *
         * This was five animations firing on the same frame — the card flashed brighter, a green
         * glow burst out of the middle, the label popped in from a third of its size, the price
         * bounced, the badge and the buyer's logo each faded in on their own delay. Any one of
         * them is defensible; all of them together read as a screensaver, and the thing the room
         * actually needs to register — the stamp landing on the card — was the hardest part to
         * see in the noise.
         *
         * What is left is the gesture that means "sold" in every hall in the world: the gavel
         * comes down on the tag, the tag takes the hit, the poppers go off. The colour and the
         * dimming are static now. Only the strike moves.
         */
        #sold-text.sold-active {
            text-shadow: 0 0 20px rgba(34,197,94,0.8), 0 0 40px rgba(34,197,94,0.4) !important;
            color: #22c55e !important;
        }
        /*
         * ── The gavel ──
         *
         * A real wooden gavel, drawn as inline SVG: a hall reads a mallet coming down as "sold"
         * before it reads any word on the screen. Inline rather than an uploaded image so it
         * costs nothing on a venue uplink and cannot be the one asset that fails to load.
         *
         * It is placed against the SOLD badge at strike time rather than at a fixed spot on the
         * page, because the badge is positioned by whoever laid out the template — a hard-coded
         * `top: 18%` had the hammer swinging at empty artwork on any template that put the stamp
         * anywhere else.
         *
         * Transform and opacity only, so the browser composites it off the main thread. Venue
         * hardware is usually also driving a projector.
         */
        #sold-hammer {
            position: fixed; z-index: 60;
            width: 220px; height: 220px;
            pointer-events: none; opacity: 0;
            /* The butt of the handle: a gavel pivots where the hand holds it, not at its centre. */
            transform-origin: 13% 78%;
            will-change: transform, opacity;
        }
        #sold-hammer svg { width: 100%; height: 100%; display: block;
            filter: drop-shadow(0 14px 22px rgba(0,0,0,0.55)); }

        /* Up, held for a beat, down hard, one small bounce off the tag, gone. The impact is at
           45% — everything else on the sale is timed to that moment. */
        #sold-hammer.strike { animation: hammer-strike 1.05s cubic-bezier(0.4, 0, 0.9, 1) forwards; }
        @keyframes hammer-strike {
            0%   { opacity: 0; transform: rotate(-62deg) scale(0.92); }
            18%  { opacity: 1; transform: rotate(-62deg) scale(1); }
            30%  { opacity: 1; transform: rotate(-66deg) scale(1); }   /* the wind-up */
            45%  { opacity: 1; transform: rotate(4deg)   scale(1.02); } /* contact */
            56%  { opacity: 1; transform: rotate(-13deg) scale(1); }   /* rebound off the tag */
            66%  { opacity: 1; transform: rotate(1deg)   scale(1); }   /* settles on it */
            100% { opacity: 0; transform: rotate(-10deg) scale(0.97); }
        }

        /*
         * The tag takes the hit: driven under, then back. Starts on the frame the gavel lands.
         *
         * The rotation is carried through `--badge-rot`, set from the badge's own computed
         * transform before the strike. A bare `scale()` here would replace that transform for the
         * length of the animation — an author who tilted the stamp in the editor would watch it
         * snap upright the moment it was hit, then snap back. Animations beat inline styles, so
         * the tilt has to be written into the keyframes rather than left underneath them.
         */
        #sold-badge.hammer-hit,
        #unsold-badge.hammer-hit { animation: badge-punched 0.42s cubic-bezier(0.22, 1.4, 0.5, 1) 0.47s backwards; }
        @keyframes badge-punched {
            0%   { transform: rotate(var(--badge-rot, 0deg)) scale(1.06); }
            22%  { transform: rotate(var(--badge-rot, 0deg)) scale(0.9); }
            60%  { transform: rotate(var(--badge-rot, 0deg)) scale(1.04); }
            100% { transform: rotate(var(--badge-rot, 0deg)) scale(1); }
        }

        /* One ring off the point of contact. Sized and placed in JS from the badge's own box. */
        #sold-impact {
            position: fixed; z-index: 59; pointer-events: none;
            border-radius: 999px; opacity: 0;
            border: 6px solid rgba(255,255,255,0.85);
            box-shadow: 0 0 40px rgba(255,255,255,0.5);
        }
        #sold-impact.pop { animation: impact-ring 0.5s ease-out 0.47s forwards; }
        @keyframes impact-ring {
            0%   { opacity: 0.95; transform: scale(0.25); }
            100% { opacity: 0; transform: scale(1.9); }
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
        .auction-seal::before { inset: 0; border: 9px solid currentColor; }
        .auction-seal::after  { inset: 15px; border: 3px solid rgba(255,255,255,0.65); opacity: 0.9; }

        /*
         * The word is the stamp.
         *
         * At 1.5em against a translucent fill this was a pale outline sitting on top of the card's
         * own artwork — from the back of a hall it read as a smudge over the stats rather than as
         * SOLD. The word is now the size of the seal, in white on a solid disc, which is what a
         * stamp is: something pressed ON TO the design, not tinted into it.
         */
        .auction-seal .seal-word {
            font-size: 2.35em;
            letter-spacing: 2px;
            line-height: 0.95;
            color: #fff;
            z-index: 1;
        }
        .auction-seal .seal-sub {
            font-size: 0.5em;
            letter-spacing: 5px;
            opacity: 0.85;
            color: #fff;
            margin-top: 6px;
            z-index: 1;
        }

        /* Solid, not tinted: a stamp is opaque or it is a watermark. */
        .sold-stamp {
            color: #16a34a;
            background: radial-gradient(circle at 34% 28%, #22c55e 0%, #15803d 55%, #064e2b 100%);
            text-shadow: 0 2px 6px rgba(0,0,0,0.55);
            box-shadow:
                0 0 46px rgba(34,197,94,0.5),
                inset 0 3px 18px rgba(255,255,255,0.22),
                0 14px 34px rgba(0,0,0,0.6);
        }

        .unsold-stamp {
            color: #dc2626;
            background: radial-gradient(circle at 34% 28%, #ef4444 0%, #b91c1c 55%, #4c0519 100%);
            text-shadow: 0 2px 6px rgba(0,0,0,0.55);
            box-shadow:
                0 0 46px rgba(239,68,68,0.5),
                inset 0 3px 18px rgba(255,255,255,0.2),
                0 14px 34px rgba(0,0,0,0.6);
        }
        /*
         * The buyer's logo simply appears. It had its own fade-in on a delay, which was one more
         * thing moving during the strike — and the `opacity: 0` that fade started from is a trap:
         * remove the animation and forget the rule, and the logo never shows up at all.
         */
        #team-logo.sold-entrance { opacity: 1; }

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

        /*
         * The result banner sits at the FOOT of the screen, and leaves.
         *
         * At 8% from the top it was landing straight on the card's own base price — a pill
         * announcing the sale on top of the figure the sale was made at. Any fixed spot near the
         * top will collide with something, because the card above it is laid out by whoever drew
         * the template and every template puts different things there.
         *
         * The foot is the one band the templates leave alone (the sponsor strip owns the very
         * bottom, and this clears it), and it fades itself out after a few seconds because it is
         * an ANNOUNCEMENT, not a label: the stamp, the final price and the buyer's crest are all
         * still on the card once it has gone.
         */
        #result-banner {
            position: fixed; left: 50%; bottom: 96px; top: auto; transform: translateX(-50%);
            z-index: 9996; display: flex; align-items: center; gap: 18px;
            padding: 12px 38px; border-radius: 9999px;
            background: rgba(2,6,23,0.92); backdrop-filter: blur(10px);
            white-space: nowrap;
            animation: resultBannerLife 7s ease-out forwards;
        }
        @keyframes resultBannerLife {
            0%   { opacity: 0; transform: translateX(-50%) translateY(14px); }
            6%   { opacity: 1; transform: translateX(-50%) translateY(0); }
            85%  { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(8px); }
        }
        @media (prefers-reduced-motion: reduce) {
            #result-banner { animation: none; }
        }
        #result-banner #result-word {
            font-size: 1.9rem; font-weight: 900; letter-spacing: 0.18em; text-transform: uppercase;
        }
        #result-banner #result-name {
            font-size: 1.6rem; font-weight: 700; color: #fff;
        }

        /* Current bidder. Same position and shape as the result banner, so the two read as
           one slot at the top of the screen rather than two competing strips. */
        /*
         * The raise notice — small, and only for a moment.
         *
         * It used to be a full-width pill carrying the team AND the figure in 1.9rem type, which
         * is why it was switched off: the card already prints CURRENT BID where the template puts
         * it, so the wall answered one question twice and covered the artwork doing it.
         *
         * What was missing is smaller than that. When a raise lands the room wants to know WHO
         * just bid, for a second, and then wants its screen back. So this is a chip: one line, in
         * a corner, up on the raise and gone before the next one — and it names the team, with
         * the amount only as a small trailing figure, because the big figure is on the card.
         */
        #bid-flash {
            position: fixed; left: 50%; top: 4%; transform: translateX(-50%);
            z-index: 9995; display: flex; align-items: center; gap: 10px;
            padding: 7px 18px; border-radius: 9999px;
            background: rgba(2,6,23,0.86); backdrop-filter: blur(8px);
            border: 1px solid rgba(34,197,94,0.5);
            box-shadow: 0 8px 30px rgba(0,0,0,0.45);
            white-space: nowrap;
        }
        #bid-flash.hidden { display: none !important; }
        /* Fades itself out rather than vanishing — see the hide timer in renderBidFlash(). */
        #bid-flash { transition: opacity 0.45s ease-out; }
        #bid-flash.fading { opacity: 0; }
        #bid-flash #bid-flash-team {
            font-size: 0.95rem; font-weight: 900; letter-spacing: 0.08em;
            text-transform: uppercase; color: #ffffff;
        }
        #bid-flash #bid-flash-team::after {
            content: 'bidding';
            margin-left: 8px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.22em;
            color: rgba(255,255,255,0.5);
        }
        #bid-flash #bid-flash-amount {
            font-size: 0.95rem; font-weight: 900; color: #22c55e;
        }
        /* Pulses only when the figure CHANGES — a banner that flashes continuously stops
           being read after the first minute. Re-armed by removing and re-adding the class. */
        #bid-flash.bid-flash-pulse {
            animation: bid-flash-pop 0.85s cubic-bezier(0.22, 1.3, 0.4, 1) 1;
        }
        /* Zooms in from nothing, then settles — it appears WITH the raise, so it has to arrive
           rather than simply be there. */
        @keyframes bid-flash-pop {
            0%   { transform: translateX(-50%) scale(0.6);  opacity: 0; box-shadow: 0 0 0 0 rgba(34,197,94,0.55); }
            30%  { transform: translateX(-50%) scale(1.12); opacity: 1; box-shadow: 0 0 42px 12px rgba(34,197,94,0.45); }
            55%  { transform: translateX(-50%) scale(0.98); }
            100% { transform: translateX(-50%) scale(1);    opacity: 1; box-shadow: 0 8px 30px rgba(0,0,0,0.45); }
        }
        @media (prefers-reduced-motion: reduce) {
            #bid-flash.bid-flash-pulse { animation: none; }
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
        /*
         * The closing call TINTS the wall; it never puts it out.
         *
         * At 0.62 the card went dark enough that a hall reading a player's stats lost them, and
         * when the clock ran out the wall stayed like that with nothing happening. A closing call
         * is a warning, not a curtain: it is now a light wash, and it lifts the moment the clock
         * stops rather than sitting over a frozen screen.
         */
        #final-call-dim {
            position: fixed; inset: 0; z-index: 9000;
            background: rgba(2,6,23,0.32);
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

        /* ── Sold board ── */
        .sold-card {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(var(--primary-rgb), 0.25);
            border-radius: 16px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        /* A 62px square thumbnail: `cover` is right here — a face at this size wants filling, not
           letterboxing — but anchored high, so the crop comes off the shirt and not the head. */
        .sold-card img.face {
            width: 62px; height: 62px; border-radius: 12px;
            object-fit: cover; object-position: 50% 12%;
            flex-shrink: 0; background: rgba(255,255,255,0.06);
        }
        .sold-card .face-blank {
            width: 62px; height: 62px; border-radius: 12px; flex-shrink: 0;
            background: rgba(255,255,255,0.06); display: flex; align-items: center;
            justify-content: center; font-size: 20px; font-weight: 800; color: rgba(255,255,255,0.45);
        }
        .sold-card .who { min-width: 0; }
        .sold-card .nm {
            font-size: 18px; font-weight: 800; color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sold-card .tm {
            font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.65);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;
        }
        .sold-card .amt {
            font-size: 20px; font-weight: 900; margin-top: 4px;
            color: rgb(var(--primary-rgb));
        }

        @keyframes boardPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.35; transform: scale(0.82); }
        }

        /* While a board is up, the live overlays stay off it.
           The board sits at z-index 140 and every live overlay is far above — the raise banner
           at 9995, the closing-call dim at 9000, the result banner and the unsold warning with
           them. So a reel meant to fill the wall during a break was being written over by
           "JUSTICE TK CC 1M POINTS" and dimmed blue by a countdown for a lot nobody is
           watching. They belong to the live card; a board replaces the live card. */
        body.board-up #bid-flash,
        body.board-up #result-banner,
        body.board-up #final-call-dim,
        body.board-up #unsold-warning {
            display: none !important;
        }

        /* ── Highlights reel: a layered carousel ──
         *
         * This cross-faded a grid of five buys at a time. Five faces at once means five small
         * faces, and a fade gives the eye nothing to follow — from the back of a hall it read as
         * a slideshow of thumbnails.
         *
         * It is now one card at a time, big in the middle, with its neighbours turned away behind
         * it and sliding through: the middle card is the one being shown and the ones either side
         * say more is coming. Ads ride the same ring, so they slide in horizontally like the
         * players rather than interrupting as a separate full-screen frame.
         *
         * Every layer is transform + opacity, composited off the main thread. The whole ring is
         * re-transformed on one tick — no per-frame JavaScript, no layout, nothing that touches
         * the live auction's budget while a break runs.
         */
        #reel {
            perspective: 1800px;
            perspective-origin: 50% 45%;
            overflow: hidden;
        }
        #reel .slide {
            position: absolute; top: 50%; left: 50%;
            width: min(30%, 460px);
            /* The card is sized off the stage rather than its contents, or a player with a long
               name would be a different size from the one beside them. */
            margin: 0;
            transform-origin: 50% 50%;
            transition: transform 0.85s cubic-bezier(0.22, 0.85, 0.28, 1),
                        opacity 0.85s ease, filter 0.85s ease;
            will-change: transform, opacity;
            /* Placed by JS through these three custom properties; see positionReel(). */
            transform:
                translate(-50%, -50%)
                translateX(calc(var(--reel-x, 0) * 1px))
                scale(var(--reel-s, 1))
                rotateY(calc(var(--reel-r, 0) * 1deg));
            opacity: var(--reel-o, 0);
            filter: brightness(var(--reel-b, 1)) saturate(var(--reel-b, 1));
            pointer-events: none;
        }

        /* One buy. The card IS the layer — image, name, crest, price, stacked and lit. */
        #reel .rp {
            text-align: center; min-width: 0;
            padding: 20px 20px 24px;
            border-radius: 26px;
            background: linear-gradient(180deg, rgba(15,23,42,0.92), rgba(2,6,23,0.96));
            border: 1px solid rgba(var(--primary-rgb), 0.35);
            box-shadow: 0 34px 90px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.08);
        }
        /*
         * `contain`, not `cover`.
         *
         * A 3:4 box with `cover` crops whatever does not fit, and player photographs are not all
         * the same shape — portrait shots lost the top of the head and the bottom of the shirt at
         * once. A reel of half-cropped faces is worse than a reel of letterboxed ones, and the
         * letterboxing is invisible against the card's own dark panel.
         *
         * Anchored to the top, so when a photograph is taller than the box the crop that IS made
         * takes it off the feet rather than off the face.
         */
        #reel .rp img, #reel .rp .blank {
            width: 100%; aspect-ratio: 3 / 4;
            object-fit: contain; object-position: 50% 0%;
            border-radius: 18px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(var(--primary-rgb), 0.3);
        }
        #reel .rp .blank { display: flex; align-items: center; justify-content: center;
            font-size: 56px; font-weight: 900; color: rgba(255,255,255,0.4); }
        #reel .rp .nm { margin-top: 16px; font-size: 34px; font-weight: 900; color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.01em; }
        #reel .rp .tm {
            display: flex; align-items: center; justify-content: center; gap: 9px;
            margin-top: 6px;
            font-size: 19px; font-weight: 700; color: rgba(255,255,255,0.72);
            white-space: nowrap; overflow: hidden; }
        #reel .rp .tm span { overflow: hidden; text-overflow: ellipsis; }
        /* The buying team's crest. A hall reads a badge faster than a name, and on a reel of
           top buys the team is half the story. */
        #reel .rp .tm .crest {
            width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
            flex-shrink: 0; background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.18); aspect-ratio: auto; }
        #reel .rp .amt { margin-top: 10px; font-size: 46px; font-weight: 900;
            color: rgb(var(--primary-rgb)); font-variant-numeric: tabular-nums;
            text-shadow: 0 0 42px rgba(var(--primary-rgb), 0.5); }

        /* A ribbon on the dearest buy in the reel: on a top-buys board, which one is the top buy
           is the fact the room is actually looking for. */
        #reel .rp .top-tag {
            display: inline-block; margin-bottom: 10px;
            padding: 4px 14px; border-radius: 999px;
            font-size: 13px; font-weight: 900; letter-spacing: 0.22em; text-transform: uppercase;
            background: rgba(var(--primary-rgb), 0.9); color: #08111f;
        }

        /*
         * ── "Loading next player" ──
         *
         * Bounce, zoom and turn on the event's own mark: three transforms on one element, which
         * the browser composites on its own thread, so a wall driving a projector pays nothing for
         * it. Deliberately says nothing about the player — the whole point is that the room does
         * not learn who is next before the card arrives.
         */
        #next-loader {
            position: fixed; inset: 0; z-index: 205;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 30px;
            /* Fully opaque. At 94% the previous player was still visible underneath — the room
               saw the face they had just finished with sitting behind "loading next player",
               which is worse than no loader: it looks like the wall has failed to move on. */
            background:
                radial-gradient(circle at 50% 42%, rgba(var(--primary-rgb),0.30) 0%, #020617 62%),
                #020617;
            animation: sealedOverlayIn 0.3s ease-out;
        }
        #next-loader.hidden { display: none; }

        #next-loader-mark {
            width: 260px; height: 260px;
            display: flex; align-items: center; justify-content: center;
            animation: loaderMark 2.4s cubic-bezier(0.45, 0, 0.35, 1) infinite;
        }
        #next-loader-mark img { max-width: 100%; max-height: 100%; object-fit: contain;
            filter: drop-shadow(0 18px 44px rgba(0,0,0,0.65)); }
        #next-loader-mark span { font-size: 150px; line-height: 1; color: rgb(var(--primary-rgb)); }

        @keyframes loaderMark {
            0%   { transform: translateY(0) scale(1) rotate(0deg); }
            25%  { transform: translateY(-38px) scale(1.12) rotate(8deg); }
            50%  { transform: translateY(0) scale(1) rotate(0deg); }
            75%  { transform: translateY(-16px) scale(1.06) rotate(-6deg); }
            100% { transform: translateY(0) scale(1) rotate(0deg); }
        }

        #next-loader-text {
            font-size: 46px; font-weight: 900; letter-spacing: 0.16em; text-transform: uppercase;
            color: #fff; text-shadow: 0 0 50px rgba(var(--primary-rgb),0.5);
        }
        #next-loader-dots { display: flex; gap: 14px; }
        #next-loader-dots span {
            width: 16px; height: 16px; border-radius: 50%;
            background: rgb(var(--primary-rgb)); opacity: 0.3;
            animation: sealedWorking 1.3s ease-in-out infinite;
        }
        #next-loader-dots span:nth-child(2) { animation-delay: 0.16s; }
        #next-loader-dots span:nth-child(3) { animation-delay: 0.32s; }

        @media (prefers-reduced-motion: reduce) {
            #next-loader-mark, #next-loader-dots span { animation: none; }
        }

        /* ── The draw's own surface ── */
        #draw-overlay {
            position: fixed; inset: 0; z-index: 210;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 6px; text-align: center; padding: 40px;
            background:
                radial-gradient(circle at 50% 38%, rgba(88,28,135,0.75) 0%, rgba(2,6,23,0.96) 62%),
                rgba(2,6,23,0.94);
            backdrop-filter: blur(12px);
            animation: sealedOverlayIn 0.4s ease-out;
        }
        #draw-overlay.hidden { display: none; }
        #draw-overlay-kicker {
            font-size: 18px; font-weight: 900; letter-spacing: 0.42em; text-transform: uppercase;
            color: #fde68a;
        }
        #draw-overlay-title {
            font-size: 64px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase;
            color: #fff; line-height: 1; margin-bottom: 8px;
            text-shadow: 0 0 60px rgba(192,132,252,0.5);
        }
        /* The label, name and amount underneath the ring, at wall size now that there is room. */
        #draw-overlay #sealed-draw-label { font-size: 16px; letter-spacing: 0.4em; }
        #draw-overlay #sealed-draw-name { font-size: 64px !important; margin-top: 6px; }
        #draw-overlay #sealed-draw-amount { font-size: 22px !important; margin-top: 6px; }

        /*
         * ── The draw: a ring of the tied teams, turning ──
         *
         * A coin was an honest picture of a two-way draw and a misleading one for five, and the
         * cycling name beside it was a list being scrolled rather than chance being taken. The
         * teams themselves now turn on a ring — crest and name on each card, the tournament's mark
         * standing in the middle of it — and the ring slows and stops with the winner facing the
         * hall.
         *
         * One transform per card, set once when the ring is built, and a single rotation animated
         * on the parent. The browser composites the lot on its own thread: a fifteen-second spin
         * costs the wall nothing while it is also driving a projector.
         */
        #draw-ring {
            position: relative;
            width: 100%; height: 260px;
            perspective: 1500px;
            perspective-origin: 50% 45%;
        }
        #draw-ring.hidden { display: none; }

        .draw-ring-inner {
            position: absolute; inset: 0;
            transform-style: preserve-3d;
            animation: drawRingSpin 2.8s linear infinite;
        }
        /* Settling: the infinite spin stops and the ring turns to the winner's angle instead. */
        .draw-ring-inner.settling {
            animation: none;
            transition: transform 1.65s cubic-bezier(0.16, 0.9, 0.2, 1);
        }
        @keyframes drawRingSpin {
            from { transform: rotateY(0deg); }
            to   { transform: rotateY(-360deg); }
        }

        .draw-card {
            position: absolute; top: 50%; left: 50%;
            width: 210px; height: 150px; margin: -75px 0 0 -105px;
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(15,23,42,0.96), rgba(2,6,23,0.98));
            border: 2px solid rgba(192,132,252,0.45);
            box-shadow: 0 24px 60px rgba(0,0,0,0.6);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 10px; padding: 12px;
            backface-visibility: hidden;
            transition: border-color 0.5s ease, box-shadow 0.5s ease, opacity 0.5s ease;
        }
        .draw-card img {
            width: 66px; height: 66px; object-fit: contain;
            filter: drop-shadow(0 6px 14px rgba(0,0,0,0.6));
        }
        .draw-card .draw-card-name {
            font-size: 21px; font-weight: 900; letter-spacing: 0.03em; color: #fff;
            text-align: center; line-height: 1.1;
            max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        /* The one the lot landed on. */
        .draw-card.is-winner {
            border-color: #fde68a;
            box-shadow: 0 0 60px rgba(253,230,138,0.55), 0 24px 60px rgba(0,0,0,0.6);
        }
        .draw-card.is-loser { opacity: 0.25; }

        /* The tournament's mark, standing inside the ring while it turns. */
        #draw-ring-logo {
            position: absolute; top: 50%; left: 50%;
            width: 120px; height: 120px; margin: -60px 0 0 -60px;
            display: flex; align-items: center; justify-content: center;
            z-index: 2; pointer-events: none;
        }
        #draw-ring-logo img { max-width: 100%; max-height: 100%; object-fit: contain;
            filter: drop-shadow(0 8px 24px rgba(0,0,0,0.7)); }
        #draw-ring-logo.hidden { display: none; }

        @media (prefers-reduced-motion: reduce) {
            .draw-ring-inner { animation: none; }
        }

        /* ── The draw coin ── */
        #draw-coin {
            width: 74px; height: 74px; margin: 0 auto 10px;
            border-radius: 50%;
            background: linear-gradient(145deg, #fde68a 0%, #d97706 55%, #92400e 100%);
            box-shadow: 0 0 26px rgba(251,191,36,0.55), inset 0 2px 6px rgba(255,255,255,0.45);
            display: flex; align-items: center; justify-content: center;
            color: rgba(120,53,15,0.65); font-size: 30px;
            /* Rotating the FACE, not the box: transform on its own compositor layer, so a coin
               turning for fifteen seconds costs the wall nothing while a lot is being drawn. */
            transform-style: preserve-3d;
            animation: coin-flip 0.7s linear infinite;
        }
        #draw-coin.hidden { display: none; }
        /* Settles face-on rather than stopping mid-turn, which reads as a dropped frame. */
        #draw-coin.settled { animation: coin-settle 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards; }
        @keyframes coin-flip {
            0%   { transform: rotateY(0deg) rotateX(8deg); }
            100% { transform: rotateY(360deg) rotateX(8deg); }
        }
        @keyframes coin-settle {
            0%   { transform: rotateY(320deg) scale(1); }
            70%  { transform: rotateY(360deg) scale(1.12); }
            100% { transform: rotateY(360deg) scale(1); }
        }

        /* ── Ads on the reel ──
           A card on the same ring as the players, so a sponsor slides in horizontally with the
           rest instead of taking the whole wall for a beat. Wider than a player card, because
           artwork is landscape and a portrait frame letterboxed every ad it was given. */
        #reel .slide.ad { width: min(42%, 660px); }
        #reel .slide.ad .frame {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 14px; padding: 22px;
            border-radius: 26px;
            background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(2,6,23,0.9));
            border: 1px solid rgba(255,255,255,0.14);
            box-shadow: 0 34px 90px rgba(0,0,0,0.7);
        }
        #reel .slide.ad img {
            width: 100%; max-height: 46vh; object-fit: contain;
            border-radius: 16px;
        }
        #reel .slide.ad .cap {
            font-size: 20px; font-weight: 800; letter-spacing: 0.16em;
            text-transform: uppercase; color: rgba(255,255,255,0.8);
        }
        /* Said once, small: an ad that is not labelled as one on a live auction wall is the kind
           of thing a sponsor and an organizer end up arguing about. */
        #reel .slide.ad .tag {
            font-size: 11px; font-weight: 800; letter-spacing: 0.3em; text-transform: uppercase;
            color: rgba(255,255,255,0.45);
        }

        /*
         * The sponsor strip, moving.
         *
         * A still centred row fitted whatever number of logos it was given by squeezing them, so
         * an auction with twelve sponsors showed twelve unreadable marks. It now travels: the list
         * is rendered TWICE and the track is translated by exactly half its width, which is what
         * makes the wrap seamless — anything else shows a gap crossing the screen every cycle.
         *
         * Duration is set from the logo count in JS, so twenty sponsors move at the same speed as
         * four rather than sprinting.
         */
        #reel-sponsors {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 145;
            overflow: hidden; padding: 10px 0;
            background: linear-gradient(to top, rgba(2,6,23,0.82), rgba(2,6,23,0));
            /* Fades the logos out at both edges instead of clipping them mid-mark. */
            mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
        }
        #reel-sponsors.hidden { display: none; }
        #reel-sponsors .sponsor-track {
            display: flex; align-items: center; gap: 60px; width: max-content;
            animation: sponsorMarch var(--sponsor-secs, 40s) linear infinite;
        }
        #reel-sponsors img {
            height: 52px; width: auto; object-fit: contain;
            opacity: 0.9; flex-shrink: 0;
        }
        @keyframes sponsorMarch {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }
        @media (prefers-reduced-motion: reduce) {
            #reel-sponsors .sponsor-track { animation: none; justify-content: center; width: 100%; }
        }

        /*
         * The event marks, turning over as each player arrives.
         *
         * transform + opacity only, so it composites off the main thread — this fires on every
         * new lot for the whole evening and must cost the wall nothing while it is also driving a
         * projector. It rotates about Y rather than scaling, because a mark that grows shoves the
         * card's own artwork and a mark that turns does not move at all.
         */
        .card-mark {
            transform-origin: 50% 50%;
            backface-visibility: visible;
        }
        #card-marks.flip .card-mark {
            animation: markFlip 1.15s cubic-bezier(0.3, 0.9, 0.25, 1) both;
        }
        @keyframes markFlip {
            0%   { transform: perspective(600px) rotateY(0deg);   opacity: 1; }
            35%  { transform: perspective(600px) rotateY(90deg);  opacity: 0.35; }
            36%  { transform: perspective(600px) rotateY(-90deg); opacity: 0.35; }
            70%  { transform: perspective(600px) rotateY(12deg);  opacity: 1; }
            100% { transform: perspective(600px) rotateY(0deg);   opacity: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
            #card-marks.flip .card-mark { animation: none; }
        }

        /* A new player arriving. Short, and opacity plus a small rise only — nothing that
           reflows the card or moves the artwork a template author positioned. */
        @keyframes cardArrived {
            0%   { opacity: 0; transform: translateY(14px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        #card-container.card-arrived { animation: cardArrived 0.45s ease-out; }

        /*
         * ── Waiting screen: the gavel ──
         *
         * This was a cricket bat playing a shot at a ball, which is a fine thing to watch and the
         * wrong sport: nothing is being bowled here, lots are being knocked down. A gavel striking
         * a block says "auction" from the back of a hall without a word on the screen.
         *
         * Pure CSS and inline SVG. No image and no library, so the screen has nothing to download
         * and cannot sit on a broken asset while a hall watches it. One shared 1.7s loop keeps the
         * swing and the impact together — separate durations drift apart within a few cycles and
         * the hammer starts landing on nothing.
         */
        .gavel-stage {
            position: relative;
            /* Sized for a hall, not a laptop. */
            width: 740px; height: 400px;
            margin-bottom: 12px;
        }

        /* Pivots at the butt of the handle, where a hand would hold it. */
        .auction-gavel {
            position: absolute; left: 118px; top: -24px;
            width: 320px; height: 320px;
            transform-origin: 13% 78%;
            animation: gavelStrike 1.7s cubic-bezier(0.4, 0, 0.7, 1) infinite;
            filter: drop-shadow(0 14px 30px rgba(0,0,0,0.6));
        }
        /* Raised, a beat of wind-up, down onto the block, one rebound, and back up. The contact is
           at 48% — the flash and the block's recoil are on the same clock. */
        @keyframes gavelStrike {
            0%   { transform: rotate(0deg); }
            26%  { transform: rotate(-9deg); }
            48%  { transform: rotate(45deg); }
            57%  { transform: rotate(31deg); }
            68%  { transform: rotate(42deg); }
            88%  { transform: rotate(0deg); }
            100% { transform: rotate(0deg); }
        }

        /* The sound block the gavel lands on, positioned where the head arrives. */
        .gavel-block {
            position: absolute; left: 280px; top: 262px;
            width: 180px; height: 34px; border-radius: 8px;
            background: linear-gradient(180deg, #b4763c 0%, #8b5220 45%, #5c3211 100%);
            box-shadow: 0 12px 26px rgba(0,0,0,0.55), inset 0 2px 0 rgba(255,255,255,0.22);
            animation: blockTakeHit 1.7s ease-out infinite;
        }
        .gavel-block::after {
            content: ''; position: absolute; left: 8%; right: 8%; bottom: -9px; height: 9px;
            border-radius: 0 0 6px 6px;
            background: linear-gradient(180deg, #4a2709, #2f1805);
        }
        @keyframes blockTakeHit {
            0%, 44%   { transform: translateY(0) scaleY(1); }
            50%       { transform: translateY(3px) scaleY(0.9); }
            60%, 100% { transform: translateY(0) scaleY(1); }
        }

        /* Flash at the moment of contact, on the same clock as the swing. */
        .gavel-flash {
            position: absolute; left: 370px; top: 250px;
            width: 260px; height: 260px; margin: -130px 0 0 -130px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.9) 0%, rgba(var(--primary-rgb),0.5) 40%, transparent 70%);
            opacity: 0; pointer-events: none;
            animation: gavelFlash 1.7s linear infinite;
        }
        @keyframes gavelFlash {
            0%, 44% { opacity: 0; transform: scale(0.4); }
            50%     { opacity: 1; transform: scale(1.2); }
            62%     { opacity: 0; transform: scale(1.7); }
            100%    { opacity: 0; transform: scale(1.7); }
        }

        /* The bench it all stands on — a thin brand-tinted line. */
        .gavel-base {
            position: absolute; left: 50%; bottom: 42px;
            width: 420px; height: 4px; margin-left: -210px;
            background: linear-gradient(90deg, transparent, rgba(var(--primary-rgb),0.75), transparent);
            border-radius: 2px;
        }
        .gavel-base::after {
            content: ''; position: absolute; left: 50%; top: -1px;
            width: 90px; height: 5px; margin-left: -45px; border-radius: 3px;
            background: rgba(var(--primary-rgb), 0.9);
            box-shadow: 0 0 22px rgba(var(--primary-rgb), 0.8);
        }

        /* Which pool is on the block, named. A hall follows an evening by its pools, and the wall
           never said which one had been selected — see #stage-pool, set from the live feed. */
        .stage-pool {
            display: inline-flex; align-items: center; gap: 12px;
            padding: 12px 28px; border-radius: 999px;
            background: rgba(2,6,23,0.55);
            border: 1px solid rgba(var(--primary-rgb),0.45);
            box-shadow: 0 0 40px rgba(var(--primary-rgb),0.25);
            font-size: 30px; font-weight: 900; letter-spacing: 0.08em;
            color: #fff; text-transform: uppercase;
        }
        .stage-pool .stage-pool-dot {
            width: 14px; height: 14px; border-radius: 50%;
            background: rgba(var(--primary-rgb), 1);
            box-shadow: 0 0 18px rgba(var(--primary-rgb), 0.9);
            animation: pulse 1.6s ease-in-out infinite;
        }
        .stage-pool .stage-pool-sub {
            font-size: 15px; font-weight: 700; letter-spacing: 0.16em;
            color: rgba(255,255,255,0.6);
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
            /* Not affected by the name's case. These sit INSIDE the name element, so the
               uppercase above would otherwise reach the travel window and print
               "19 AUG – 29 AUG" — a date is not a headline. The WK pill sets its own case. */
            text-transform: none;
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

        /*
         * The aeroplane flies.
         *
         * This chip was drawing Heroicons' arrow-ish glyph, which at badge size is a filled
         * triangle — so a player's travel dates read as a WARNING on the wall. It is now an
         * aircraft, and it drifts: a still plane beside two dates still looks like a status icon,
         * and the one thing this chip means is that somebody is flying in.
         *
         * Transform only, on a 2.6s loop. Slow enough to be calm behind a live auction.
         */
        #player-name-badges .badge-travel svg,
        #travel-plan svg {
            animation: flightDrift 2.6s ease-in-out infinite;
        }
        @keyframes flightDrift {
            0%, 100% { transform: translate(-0.06em, 0.05em); }
            50%      { transform: translate(0.12em, -0.09em); }
        }
        @media (prefers-reduced-motion: reduce) {
            #player-name-badges .badge-travel svg, #travel-plan svg { animation: none; }
        }

        #player-name {
            position: absolute;
            /*
             * The name is UPPERCASE on the wall unless the template asks for something else.
             *
             * A hall reads this from the back of the room and the artwork is built around a
             * block of capitals — a name typed "Sanju New" at registration came out that way,
             * next to CURRENT BID and DUBAI STRIKERS, and looked like a mistake. The case is
             * whatever the player typed into a form months earlier, which is not a design
             * decision anybody made about this screen.
             *
             * Written BEFORE elementStyle(), so a template that explicitly picks lowercase or
             * "Capitalise Each Word" still wins — the default only fills the gap.
             */
            text-transform: uppercase;
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
        #travel-plan svg,
        #playing-team svg {
            width: 1em;
            height: 1em;
            flex-shrink: 0;
        }

        /* Hidden by VISIBILITY, not display, so a template author dragging it in the editor can
           still see where it sits on a player who has no club recorded. */
        #playing-team {
            position: absolute;
            display: inline-flex;
            align-items: center;
            gap: 0.45em;
            visibility: hidden;
            {!! elementStyle($positions, 'playing_team', ['top'=>510,'left'=>550,'fontSize'=>24,'color'=>'#fcd34d'], $boxShadowMap, $textShadowMap) !!}
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

        /*
         * ── The raise, flashed on the figure itself ──
         *
         * This was a colour swap held for a second and a half, which on a wall across a hall is
         * not a flash — if the template's bid colour is near the secondary it is not even a
         * change. The raise is the single most important event on this screen and it was the
         * quietest thing on it.
         *
         * Flashed where the figure already is, rather than in the floating pill that used to sit
         * over the artwork: the card carries CURRENT BID where the template puts it, and two
         * answers to one question is what got that banner switched off.
         *
         * It now ZOOMS as well as glows.
         *
         * Brightness alone was still missable from the back of a hall — the figure is one line of
         * text among several and nothing about it moved. A scale bounce is the one change the eye
         * catches without reading.
         *
         * Safe here for a reason worth writing down: `#current-bid` is positioned by the
         * template, absolutely, so a transform on it cannot shove a neighbour or reflow the card
         * — transforms are painted, not laid out. The earlier "no transform" rule was about
         * elements in flow.
         *
         * The template's own rotation rides through `--bid-rot`, read off the computed transform
         * before the class goes on. Without it a tilted figure would snap upright for the length
         * of the animation and back, because an animation beats an inline style.
         */
        .bid-updated {
            animation: bid-figure-flash 0.95s cubic-bezier(0.22, 1.3, 0.4, 1) 2;
            transform-origin: center center;
        }
        /*
         * Brightness and glow, NOT colour.
         *
         * This animated `color` to green — and the templates already paint the live figure green,
         * so the flash was green over green: the class was applied, the animation ran, and nothing
         * on the wall changed. A flash cannot be defined in terms of a colour the template is free
         * to choose.
         *
         * `filter: brightness()` lifts whatever colour the author picked, and the halo is drawn in
         * white so it reads against any of them. Neither affects layout, which is the constraint
         * that started this: the figure is positioned by the template and must not move.
         */
        @keyframes bid-figure-flash {
            0%   { filter: none;             text-shadow: none;
                   transform: rotate(var(--bid-rot, 0deg)) scale(1); }
            15%  { filter: brightness(2.1);  text-shadow: 0 0 34px rgba(255,255,255,0.95), 0 0 78px rgba(255,255,255,0.55);
                   transform: rotate(var(--bid-rot, 0deg)) scale(1.22); }
            38%  { filter: brightness(1.7);  text-shadow: 0 0 28px rgba(255,255,255,0.7);
                   transform: rotate(var(--bid-rot, 0deg)) scale(0.96); }
            60%  { filter: brightness(1.35); text-shadow: 0 0 22px rgba(255,255,255,0.5);
                   transform: rotate(var(--bid-rot, 0deg)) scale(1.07); }
            100% { filter: none;             text-shadow: none;
                   transform: rotate(var(--bid-rot, 0deg)) scale(1); }
        }
        @media (prefers-reduced-motion: reduce) {
            /* No bounce, but keep the lift — the raise still has to be visible. */
            .bid-updated { animation: none; filter: brightness(1.4); }
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

    
        /*
         * ── The sealed round, over the whole wall ──
         *
         * A bar across the top of the card was not enough. When bidding goes private the price
         * freezes, the teams go quiet and the card stops changing — from the back of a hall that is
         * indistinguishable from a screen that has crashed, and the one thing a room needs to know
         * is that something IS happening. So the wall says it at the size of the wall.
         *
         * The banner stays underneath for the states where there is a RESULT to watch — the tie,
         * the draw, the reveal. Those have their own animation and covering them with a logo would
         * hide the only part anybody is waiting for.
         *
         * Wall only. The ticker is a strip read at a glance and a full-bleed takeover there would
         * blank the one line it exists to show.
         */
        #sealed-overlay {
            position: fixed; inset: 0; z-index: 200;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 26px; text-align: center;
            background:
                radial-gradient(circle at 50% 42%, rgba(88,28,135,0.72) 0%, rgba(2,6,23,0.94) 62%),
                rgba(2,6,23,0.9);
            backdrop-filter: blur(10px);
            opacity: 0;
            animation: sealedOverlayIn 0.45s ease-out forwards;
        }
        #sealed-overlay.hidden { display: none; }
        @keyframes sealedOverlayIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        /* The logo, breathing inside two rings. Slow on purpose: a sealed round can run for
           minutes and anything quick becomes irritating long before it ends. */
        .sealed-crest {
            position: relative;
            width: 520px; height: 320px;
            display: flex; align-items: center; justify-content: center;
        }
        /* One or two logos, breathing together as a single object rather than each on its own
           clock — two logos pulsing out of step read as a glitch. */
        .sealed-crest-logos {
            display: flex; align-items: center; justify-content: center; gap: 26px;
            animation: sealedBreathe 3.4s ease-in-out infinite;
        }
        .sealed-crest img {
            max-height: 170px; max-width: 210px; object-fit: contain;
            filter: drop-shadow(0 12px 34px rgba(0,0,0,0.6));
        }
        /* No logo uploaded anywhere: a padlock rather than an empty circle. */
        .sealed-crest .sealed-crest-fallback {
            font-size: 120px; line-height: 1;
            animation: sealedBreathe 3.4s ease-in-out infinite;
        }
        @keyframes sealedBreathe {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.06); }
        }
        .sealed-ring {
            position: absolute; top: 50%; left: 50%;
            width: 300px; height: 300px; margin: -150px 0 0 -150px;
            border-radius: 50%;
            border: 3px solid rgba(192,132,252,0.55);
            animation: sealedRing 3s ease-out infinite;
        }
        .sealed-ring:nth-of-type(2) { animation-delay: 1.5s; }
        @keyframes sealedRing {
            0%   { opacity: 0.85; transform: scale(0.72); }
            100% { opacity: 0;    transform: scale(1.25); }
        }

        #sealed-overlay .sealed-heading {
            font-size: 78px; font-weight: 900; line-height: 1;
            letter-spacing: 0.06em; text-transform: uppercase; color: #fff;
            text-shadow: 0 0 60px rgba(192,132,252,0.55);
        }
        #sealed-overlay .sealed-sub {
            font-size: 30px; font-weight: 700; color: #e9d5ff;
        }
        #sealed-overlay .sealed-round {
            font-size: 17px; font-weight: 800; letter-spacing: 0.34em;
            text-transform: uppercase; color: rgba(233,213,255,0.7);
        }

        /* Three dots working through, so the screen is never completely still. */
        .sealed-working { display: flex; gap: 14px; }
        .sealed-working span {
            width: 16px; height: 16px; border-radius: 50%;
            background: #c084fc; opacity: 0.3;
            animation: sealedWorking 1.35s ease-in-out infinite;
        }
        .sealed-working span:nth-child(2) { animation-delay: 0.18s; }
        .sealed-working span:nth-child(3) { animation-delay: 0.36s; }
        @keyframes sealedWorking {
            0%, 100% { opacity: 0.28; transform: scale(0.85); }
            45%      { opacity: 1;    transform: scale(1.15); }
        }

        @media (prefers-reduced-motion: reduce) {
            #sealed-overlay, .sealed-crest img, .sealed-crest .sealed-crest-fallback,
            .sealed-ring, .sealed-working span { animation: none; }
            #sealed-overlay { opacity: 1; }
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
        {{-- Which pool is on the block. A hall follows an evening by its pools, and the wall
             never said which one had been selected. Hidden until the feed names one. --}}
        <div id="stage-pool" class="stage-pool hidden" style="margin-bottom:22px;position:relative;z-index:1;">
            <span class="stage-pool-dot"></span>
            <span id="stage-pool-name"></span>
            <span class="stage-pool-sub">NOW IN PLAY</span>
        </div>

        {!! $gavelStage !!}
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
        {!! $gavelStage !!}

        {{-- The pool being restarted, named. "RESTARTING AUCTION" over a wall of people who only
             care about one pool said less than it could. --}}
        <div id="restart-pool" class="stage-pool hidden" style="margin-bottom:18px;">
            <span class="stage-pool-dot"></span>
            <span id="restart-pool-name"></span>
            <span class="stage-pool-sub">BACK ON THE BLOCK</span>
        </div>

        <h1 style="font-size:64px;font-weight:900;letter-spacing:0.06em;color:#a78bfa;
                   text-shadow:0 0 40px rgba(167,139,250,0.5);animation:pulse 1.6s ease-in-out infinite;">
            RESTARTING AUCTION
        </h1>
        {{-- No countdown.
             It read "Next player in 8s" and sat there: the figure comes from the server's own
             restart window and is only refreshed when a poll lands, so between polls it does not
             move — a clock that does not tick reads as a frozen screen, which is the exact
             opposite of what this notice is for. The restart is a few seconds and the next player
             arriving is its own announcement. --}}
        <p style="font-size:24px;color:#94a3b8;margin-top:12px;letter-spacing:0.08em;">
            Next player coming up
        </p>
    </div>

    <div id="completed-screen" class="hidden" style="position:fixed;inset:0;display:flex;flex-direction:column;justify-content:center;align-items:center;background:linear-gradient(135deg,#0a0a0a 0%,#1a1a2e 50%,#0a0a0a 100%);z-index:100;">
        <div style="font-size:120px;margin-bottom:30px;">🏆</div>
        <h1 style="font-size:72px;color:#eab308;text-shadow:0 0 30px rgba(234,179,8,0.5);">AUCTION COMPLETED</h1>
        <p class="text-3xl text-gray-400 mt-6">{{ $auction->name }}</p>
        <p class="text-xl text-gray-500 mt-4">Thank you for watching!</p>
    </div>


    {{-- The board of players sold.
         Put up from the organizer's panel between lots, when the room wants to see where the
         money has gone. z-index sits above the card and below the restart notice, which must
         still be able to interrupt anything. --}}
    <div id="sold-board" class="hidden"
         style="position:fixed;inset:0;z-index:140;background:rgba(2,6,23,0.97);
                display:flex;flex-direction:column;padding:36px 44px;">
        <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:22px;gap:24px;">
            <div style="display:flex;align-items:center;gap:18px;min-width:0;">
                {{-- The event's own mark, so a break still carries the tournament's identity
                     rather than reading as a generic slideshow. --}}
                <img id="reel-logo" class="hidden" alt=""
                     style="height:60px;width:auto;object-fit:contain;flex-shrink:0;">
                <div id="sold-board-title" style="font-size:38px;font-weight:900;letter-spacing:0.04em;color:#fff;">SOLD SO FAR</div>
            </div>

            {{-- What the room is waiting for.
                 A board with no explanation reads as the auction having stopped. If a sealed
                 round is running, that is the answer; otherwise it is a break, and a break with
                 a clock on it is the difference between "they have gone" and "they are coming
                 back". Counts up rather than down, because nobody knows when a break ends and a
                 countdown that reaches zero with an empty stage is worse than no countdown. --}}
            <div id="board-status" class="hidden" style="display:flex;align-items:center;gap:12px;">
                <span id="board-status-dot" style="width:12px;height:12px;border-radius:50%;
                      background:rgb(var(--primary-rgb));animation:boardPulse 1.6s ease-in-out infinite;"></span>
                <span id="board-status-text" style="font-size:24px;font-weight:800;
                      letter-spacing:0.06em;color:#fff;"></span>
                <span id="board-status-timer" style="font-size:24px;font-weight:900;
                      color:rgba(var(--primary-rgb),0.95);font-variant-numeric:tabular-nums;"></span>
            </div>

            <div id="sold-board-count" style="font-size:22px;font-weight:700;
                 color:rgba(var(--primary-rgb),0.95);"></div>
        </div>
        {{-- Scrolls rather than shrinking the cards: a face nobody can make out from the back
             of a hall is not worth fitting on. --}}
        <div id="sold-board-grid" style="flex:1;overflow-y:auto;display:grid;gap:16px;
             grid-template-columns:repeat(auto-fill,minmax(230px,1fr));align-content:start;"></div>

        {{-- The highlights reel: a handful of buys at a time, fading from one slide to the
             next. Separate from the grid above because these cards are large and centred —
             a reel that reads across a hall cannot be the same layout as a list that fits
             three hundred. --}}
        <div id="reel" class="hidden" style="flex:1;position:relative;"></div>

    </div>

    {{-- The sponsors, along the bottom of the WALL rather than inside the board.
         They were drawn only on the reel, so they appeared during a break and vanished the
         moment a player came up — which is the opposite of how a sponsorship is sold. A strip
         fixed to the screen is on for the whole auction, over the live card and the board
         alike. Hidden by itself when there is no artwork, so a wall with no sponsors is
         unchanged. --}}
    <div id="reel-sponsors" class="hidden"></div>

    {{-- Sound has to be switched on by a tap.
         Browsers refuse audio until the page has been interacted with, and a wall on a projector
         never has been — so the first chime would be silently ignored. Rather than pretend, the
         control says so and takes itself away once armed. --}}
    <button id="sound-arm" type="button"
            style="position:fixed;right:18px;top:18px;z-index:9999;padding:8px 14px;border-radius:999px;
                   background:rgba(2,6,23,0.8);border:1px solid rgba(var(--primary-rgb),0.5);
                   color:#fff;font-size:13px;font-weight:700;cursor:pointer;">
        &#128266; Enable sound
    </button>

    {{-- The gavel, struck once as a sale lands, on the SOLD tag itself. Positioned against that
         tag at strike time — see #sold-hammer in the stylesheet for why it is not fixed to a spot
         on the page. --}}
    <div id="sold-hammer" aria-hidden="true">
        <svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="gavel-wood" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%"   stop-color="#d09a5e"/>
                    <stop offset="38%"  stop-color="#a9682f"/>
                    <stop offset="70%"  stop-color="#7d4718"/>
                    <stop offset="100%" stop-color="#5c3211"/>
                </linearGradient>
                <linearGradient id="gavel-handle" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%"   stop-color="#c08c52"/>
                    <stop offset="55%"  stop-color="#8d571f"/>
                    <stop offset="100%" stop-color="#5a3010"/>
                </linearGradient>
            </defs>

            {{-- Handle, running down-left from the head to the hand. --}}
            <g transform="rotate(-38 16 92)">
                <rect x="12" y="85" width="80" height="14" rx="7" fill="url(#gavel-handle)"/>
                <rect x="12" y="88" width="80" height="3"  rx="1.5" fill="rgba(255,255,255,0.16)"/>
                <rect x="12" y="85" width="12" height="14" rx="6" fill="#4a2709"/>
            </g>

            {{-- Head, square to the handle. The lower face is what lands on the tag. --}}
            <g transform="rotate(52 78 44)">
                <rect x="55" y="28" width="46" height="32" rx="8" fill="url(#gavel-wood)"/>
                <rect x="55" y="28" width="46" height="7"  rx="4" fill="rgba(255,255,255,0.22)"/>
                <rect x="63" y="28" width="4"  height="32" fill="rgba(0,0,0,0.18)"/>
                <rect x="89" y="28" width="4"  height="32" fill="rgba(0,0,0,0.18)"/>
                <rect x="53" y="26" width="6"  height="36" rx="3" fill="#3f2208"/>
                <rect x="97" y="26" width="6"  height="36" rx="3" fill="#3f2208"/>
            </g>
        </svg>
    </div>

    {{-- The ring thrown off the point of contact. --}}
    <div id="sold-impact" aria-hidden="true"></div>

    {{-- The sealed round, said at the size of the wall. Wall only — see #sealed-overlay in the
         stylesheet for why this is not on the ticker. --}}
    <div id="sealed-overlay" class="hidden" aria-hidden="true">
        {{-- Both logos when both exist: the tournament is what the room came for and the auction
             is what it is watching. Whichever is uploaded shows; with neither, a padlock rather
             than an empty circle. --}}
        <div class="sealed-crest">
            <span class="sealed-ring"></span>
            <span class="sealed-ring"></span>
            @php
                /*
                 * The organizer's own mark first, when one has been set on the Sealed Bid Screen
                 * page — that setting exists precisely so this screen can carry an event's own
                 * branding rather than whatever logos happen to be on the auction.
                 *
                 * With nothing set it falls back to the tournament's and the auction's, as before.
                 */
                $sealedLogos = $auction->sealed_logo
                    ? [$auction->sealed_logo_url]
                    : array_values(array_filter([
                        $auction->tournament->logo_url ?? null,
                        $auction->auction_logo_url ?: null,
                    ]));
            @endphp
            @if(count($sealedLogos))
                <div class="sealed-crest-logos">
                    @foreach($sealedLogos as $logo)
                        <img src="{{ $logo }}" alt="">
                    @endforeach
                </div>
            @else
                <span class="sealed-crest-fallback">&#128274;</span>
            @endif
        </div>

        <div>
            {{-- Set on the Sealed Bid Screen page; the built-in wording is the fallback. The JS
                 below overwrites these per state, and reads its own defaults from these nodes. --}}
            <div class="sealed-heading" id="sealed-overlay-heading"
                 data-default="{{ $auction->sealedHeading() }}">{{ $auction->sealedHeading() }}</div>
            <div class="sealed-sub" id="sealed-overlay-sub" style="margin-top:10px;"
                 data-default="{{ $auction->sealedMessage() }}">{{ $auction->sealedMessage() }}</div>
            <div class="sealed-round" id="sealed-overlay-round" style="margin-top:14px;"></div>

            {{-- How long the teams have left.
                 The wall's own clock is driven by the OPEN bid timer, which is frozen while a
                 sealed round runs — so the one countdown that mattered during a sealed round was
                 the one screen in the room that could not show it. The sealed round has its own
                 timer on the server; this is it. --}}
            <div id="sealed-overlay-timer" class="hidden" style="margin-top:22px;">
                <div style="font-size:15px;font-weight:800;letter-spacing:0.34em;text-transform:uppercase;color:rgba(233,213,255,0.6);">
                    Time left
                </div>
                <div id="sealed-overlay-clock"
                     style="font-size:78px;font-weight:900;line-height:1;color:#fff;font-variant-numeric:tabular-nums;"></div>
            </div>
        </div>

        <div class="sealed-working" aria-hidden="true"><span></span><span></span><span></span></div>
    </div>


    {{-- The draw, on the whole wall.
         It lived inside the sealed banner — a slim strip absolutely positioned across the top of
         the CARD — and a 3D ring of team crests does not fit in a strip. It grew the banner to
         half the screen, the card's own text carried on rendering underneath it, and "RIGHT HAND
         BAT" ended up printed through "Delhi Capitals".

         A draw is the whole room's attention for fifteen seconds. It gets its own surface, with
         nothing behind it to collide with, and the banner goes back to being the one line of text
         it was designed as. --}}
    <div id="draw-overlay" class="hidden" aria-hidden="true">
        <div id="draw-overlay-kicker">Tie</div>
        <div id="draw-overlay-title">Drawing A Lot</div>

        <div id="sealed-draw" class="hidden" style="position:relative;width:100%;">
                {{-- A coin, turning while the draw runs.
                     The names were already cycling, but a cycling list reads as a menu being
                     scrolled rather than as chance being taken. A coin says what is happening
                     without a word of explanation, and it stops when the draw does. --}}
                {{-- The teams themselves, turning. The coin stays in the markup but is never shown
                     now — see renderSealedDraw: a coin is an honest picture of a two-way draw and
                     a misleading one for five, and the room is watching teams, not currency. --}}
                <div id="draw-ring" class="hidden">
                    <div id="draw-ring-logo" class="hidden"><img id="draw-ring-logo-img" alt=""></div>
                    <div class="draw-ring-inner" id="draw-ring-inner"></div>
                </div>
                <div id="draw-coin" class="hidden"><span>&#9679;</span></div>
                <div style="font-size:12px;font-weight:800;letter-spacing:4px;text-transform:uppercase;color:#fde68a;">
                    <span id="sealed-draw-label">Drawing a lot</span>
                </div>
                <div id="sealed-draw-name" class="sealed-draw-name"
                     style="font-size:40px;font-weight:900;color:#fff;line-height:1.05;"></div>
                <div id="sealed-draw-amount" style="font-size:15px;font-weight:700;color:#e9d5ff;"></div>
            </div>
    </div>

    {{-- Loading the next player.
         Put up the moment the organizer starts choosing and taken down when the card arrives, so
         the wall says something is coming rather than sitting on an empty waiting screen. It says
         nothing about WHO: the room finding out early is the one thing a reveal must not do, so
         this carries the event's own mark and no player data at all. --}}
    <div id="next-loader" class="hidden" aria-hidden="true">
        @php
            $loaderMark = $auction->auction_logo_url ?: ($auction->tournament->logo_url ?? null);
        @endphp
        <div id="next-loader-mark">
            @if($loaderMark)
                <img src="{{ $loaderMark }}" alt="">
            @else
                <span>&#9673;</span>
            @endif
        </div>
        <div id="next-loader-text">Loading next player</div>
        <div id="next-loader-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    </div>

    {{-- Paused overlay (shown in real-time when the organizer pauses) --}}    {{-- Paused overlay (shown in real-time when the organizer pauses) --}}
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
        {{-- The event's marks, top-left, turned over on every new player.
             A logo that never moves stops being seen after ten minutes; a short flip as each
             player arrives puts the tournament's name back in front of the room without taking
             any space from the card. Both marks when both are uploaded — the auction's own logo
             is set under Auctions → Edit → Branding, the tournament's on the tournament. --}}
        @php
            $cardMarks = array_values(array_filter([
                $auction->auction_logo_url ?: null,
                $auction->tournament->logo_url ?? null,
            ]));
        @endphp
        @if(count($cardMarks))
        <div id="card-marks" style="position:absolute;top:20px;left:20px;z-index:10;display:flex;align-items:center;gap:18px;">
            @foreach($cardMarks as $i => $mark)
                <img src="{{ $mark }}" alt="" class="card-mark" style="height:80px;object-fit:contain;
                     animation-delay: {{ $i * 0.18 }}s;">
            @endforeach
        </div>
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
            {{-- An aeroplane, inline so it takes the element's own colour and font size, and
                 animated in the stylesheet — see flightDrift. --}}
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <g transform="rotate(45 10 10)"><path d="M17.5 13.3v-1.6l-6.6-4.2V3.1c0-.7-.6-1.2-1.2-1.2S8.4 2.4 8.4 3.1v4.4L1.8 11.7v1.6l6.6-2v4.4l-1.7 1.2v1.2l2.9-.8 2.9.8v-1.2l-1.7-1.2v-4.4l6.7 2z"/></g>
            </svg>
            <span id="travel-plan-value"></span>
        </div>
        @endif

        <!-- The club they currently play for: shown only when one is recorded -->
        @if(isVisible($positions, 'playing_team'))
        <div id="playing-team">
            {{-- A shirt, inline so it takes the element's own colour and font size. --}}
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M7.5 2 4 3.6 2.4 7l2.3 1.2.7-1.4V18h9V6.8l.7 1.4L17.6 7 16 3.6 12.5 2a2.5 2.5 0 0 1-5 0z"/>
            </svg>
            <span id="playing-team-value"></span>
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
        @endif

        {{-- Sealed round, OUTSIDE the highest-bidder gate.
             This lived inside `@if(isVisible($positions, 'highest_bidder'))`, so a template with
             the highest-bidder element switched off — which auction 11's is — shipped a wall with
             no sealed banner and no draw in the DOM at all. Nothing appeared when bidding went
             private and nothing appeared when a lot was drawn, and no amount of fixing the
             animation could have shown it: the markup was never on the page.

             This is not part of the template's design. It is the system saying what is happening
             to the auction, and a template author switching off a bid label cannot mean "never
             tell the hall a sealed round is running".
         --}}
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
        </div>

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
        /* One pending re-read for the end of the last-result hold — see the feed handler. */
        let _stageHoldTimer = null;

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


        /**
         * Call out who is bidding, and pulse when the figure moves.
         *
         * The leading team was only shown small, beside the price on the card. Across a
         * hall that is unreadable, and the room needs to see who is in front the moment it
         * changes. This says it across the top, in the same slot the result banner uses.
         *
         * Pulses on CHANGE only. A banner that flashes on every poll stops being read
         * within a minute, and the whole point is that a raise catches the eye.
         */
        let _lastFlashKey = null;

        /**
         * Roll the price up to a new figure, the way a departure board turns over.
         *
         * A raise used to appear as a straight substitution — 1M became 2.5M between two frames
         * — which from the back of a hall is easy to miss entirely, and gives no sense of how
         * far the bidding just moved. Stepping through the figures in between makes the size of
         * the jump legible: the board counts 1.1, 1.2 … 2.5 and stops on what the organizer
         * actually entered.
         *
         * It always lands exactly on the target. The steps are cosmetic and the final assignment
         * is the real number, so a rounding artefact cannot survive on the wall — and a raise
         * arriving mid-roll cancels the one in flight and starts again from wherever the board
         * had got to, rather than queueing behind it.
         *
         * Skipped for a reduction, for a first paint, and for anyone who has asked their system
         * for reduced motion: a price falling back through an undo should simply be the new
         * figure, not a countdown.
         */
        let _bidRollFrame = null;

        function rollBidTo(el, from, to) {
            if (_bidRollFrame) {
                cancelAnimationFrame(_bidRollFrame);
                _bidRollFrame = null;
            }

            const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

            if (reduced || !(from > 0) || !(to > from)) {
                el.textContent = formatMillions(to);
                return;
            }

            /* Long enough to read as movement, short enough that the next raise is never
               waiting on it — an auctioneer can take raises faster than this. */
            const DURATION = 520;
            const started = performance.now();

            const step = (now) => {
                const t = Math.min(1, (now - started) / DURATION);
                // Ease-out: quick off the mark, settling onto the figure rather than stopping
                // dead on it.
                const eased = 1 - Math.pow(1 - t, 3);

                el.textContent = formatMillions(from + (to - from) * eased);

                if (t < 1) {
                    _bidRollFrame = requestAnimationFrame(step);
                } else {
                    // The real number, never a rounding of it.
                    el.textContent = formatMillions(to);
                    _bidRollFrame = null;
                }
            };

            _bidRollFrame = requestAnimationFrame(step);
        }

        /**
         * Copy an element's current rotation into a custom property.
         *
         * A keyframe that sets `transform` replaces whatever the template put there, so any
         * animation that scales has to write the author's rotation back into every frame. Read
         * from the computed matrix rather than the inline style, because a template can set the
         * tilt from either.
         */
        function carryRotation(el, prop) {
            if (! el) return;

            let rot = 0;
            const matrix = getComputedStyle(el).transform || '';

            if (matrix.startsWith('matrix')) {
                const n = matrix.slice(matrix.indexOf('(') + 1, -1).split(',').map(Number);
                if (n.length >= 4 && ! n.slice(0, 4).some(isNaN)) {
                    rot = Math.atan2(n[1], n[0]) * 180 / Math.PI;
                }
            }

            el.style.setProperty(prop, rot.toFixed(2) + 'deg');
        }

        /* How long the raise chip stays up. Long enough to read a team name from the back of a
           hall, short enough that it is gone before the next raise in a busy exchange. */
        const BID_CHIP_MS = 3200;
        let _bidChipTimer = null;
        let _bidChipHide = null;

        function hideBidChip(el) {
            if (_bidChipTimer) { clearTimeout(_bidChipTimer); _bidChipTimer = null; }
            if (_bidChipHide) { clearTimeout(_bidChipHide); _bidChipHide = null; }

            el.classList.add('hidden');
            el.classList.remove('fading', 'bid-flash-pulse');
        }

        function renderBidFlash(p) {
            const el = document.getElementById('bid-flash');
            if (!el) return;

            const team = p?.current_bid_team;
            const live = p && p.status === 'on_auction' && team && !sealedState;

            // Not on the block, nobody leading, or a sealed round where the leader is
            // frozen and the amounts are secret — nothing honest to announce.
            if (!live) {
                hideBidChip(el);
                _lastFlashKey = null;
                return;
            }

            const amount = p.current_price ?? p.base_price ?? 0;
            const key = `${team.id ?? team.name}:${amount}`;

            /*
             * Only on a NEW raise.
             *
             * This element is re-rendered on every poll and every push, and it used to be shown
             * unconditionally — so once a team led, the chip sat there for the rest of the lot.
             * It is a notice, not a status: it belongs on screen for the moment after a bid
             * lands and nowhere else. A repeat of the same figure re-renders nothing.
             */
            if (key === _lastFlashKey) {
                return;
            }

            document.getElementById('bid-flash-team').textContent = team.name || '';
            document.getElementById('bid-flash-amount').textContent = formatMillions(amount);
            el.classList.remove('hidden', 'fading');

            // Any hide still pending from the PREVIOUS raise is cancelled, so a fast exchange
            // of bids cannot fade the chip out half a second after the newest one appeared.
            if (_bidChipTimer) clearTimeout(_bidChipTimer);
            if (_bidChipHide) clearTimeout(_bidChipHide);

            _bidChipTimer = setTimeout(() => {
                el.classList.add('fading');
                // Removed only once the fade has finished — `hidden` is display:none and would
                // cut the transition off at the first frame.
                _bidChipHide = setTimeout(() => hideBidChip(el), 500);
            }, BID_CHIP_MS);

            _lastFlashKey = key;

            // Removing, forcing a reflow, then re-adding restarts the animation; without
            // the reflow the browser coalesces the two changes and nothing plays.
            el.classList.remove('bid-flash-pulse');
            void el.offsetWidth;
            el.classList.add('bid-flash-pulse');
        }

        function clearOutcomeState() {
            const card = document.getElementById('card-container');
            if (card) card.classList.remove('sold-state', 'unsold-state', 'skipped-state');

            const soldText = document.getElementById('sold-text');
            if (soldText) soldText.classList.remove('sold-active', 'unsold-active', 'skipped-active');
            clearGavel();

            const soldBadge = document.getElementById('sold-badge');
            if (soldBadge) {
                soldBadge.classList.remove('sold-entrance');
                soldBadge.classList.add('hidden');
            }

            const unsoldBadge = document.getElementById('unsold-badge');
            if (unsoldBadge) {
                unsoldBadge.classList.add('hidden');
                unsoldBadge.style.display = 'none';
            }

            const teamLogo = document.getElementById('team-logo');
            if (teamLogo) {
                teamLogo.classList.remove('sold-entrance');
                teamLogo.classList.add('hidden');
            }

            document.getElementById('highest-bidder')?.classList.add('hidden');
            document.getElementById('result-banner')?.classList.add('hidden');

            // The two banners share a slot at the top; a finished lot must not be announced
            // underneath a live bid.
            const flash = document.getElementById('bid-flash');
            if (flash) {
                // Through the same helper, so its pending fade-out timers are cancelled too —
                // otherwise one left over from the last raise would re-hide (and un-fade) the
                // chip during the NEXT player's first bid.
                hideBidChip(flash);
            }
            _lastFlashKey = null;
        }
        let isShuffling = false;
        // The most recent payload that had a player on the block — the recovery path's input.
        let lastGoodPlayer = null;
        let hasCompletedFirstLoad = false;
        let _confettiFiredForPlayer = null;

        /*
         * ── The poppers ──
         *
         * Two party poppers, fired up and inwards from the bottom corners, throwing streamers.
         * It was three even bursts of small round confetti before, which fell like weather; a
         * popper has a direction and a moment, which is what a sale wants.
         *
         * The ribbons are wide flat squares thrown at low gravity with a lot of drift, so they
         * flutter and hang rather than dropping — the closest this library gets to streamer paper
         * without shipping a second animation engine to do it.
         */
        const POPPER_COLORS = ['#22c55e', '#4ade80', '#fbbf24', '#f59e0b', '#ffffff'];

        function fireConfetti() {
            if (typeof confetti !== 'function') return;

            const popper = (x, angle) => {
                // The streamers: few, large, slow to fall.
                confetti({
                    particleCount: 26, angle, spread: 46, startVelocity: 62,
                    decay: 0.91, gravity: 0.65, drift: x < 0.5 ? 0.7 : -0.7,
                    scalar: 2.6, ticks: 260, shapes: ['square'],
                    origin: { x, y: 1.05 }, colors: POPPER_COLORS,
                    disableForReducedMotion: true,
                });
                // The paper dust that goes with them.
                confetti({
                    particleCount: 45, angle, spread: 62, startVelocity: 55,
                    decay: 0.9, gravity: 1, scalar: 0.9, ticks: 180,
                    origin: { x, y: 1.05 }, colors: POPPER_COLORS,
                    disableForReducedMotion: true,
                });
            };

            popper(0.08, 62);
            popper(0.92, 118);
        }

        /*
         * ── The gavel ──
         *
         * Aimed at the SOLD badge rather than at a fixed point on the page. The badge is placed by
         * whoever laid the template out, so the only way to actually hit it is to measure it when
         * the sale lands.
         *
         * The geometry: in the SVG's own box the head sits at (65%, 37%) and the element pivots at
         * (13%, 78%). Offsetting the box by the head's position puts the striking face on the
         * badge at the moment of contact, whatever size the badge is or which corner it is in.
         */
        const GAVEL_IMPACT_MS = 470;   // the 45% keyframe of hammer-strike, and the delay on both
                                       // #sold-badge.hammer-hit and #sold-impact.pop
        const GAVEL_HEAD_X = 0.65;
        const GAVEL_HEAD_Y = 0.37;

        function clearGavel() {
            document.getElementById('sold-hammer')?.classList.remove('strike');
            document.getElementById('sold-badge')?.classList.remove('hammer-hit');
            document.getElementById('unsold-badge')?.classList.remove('hammer-hit');
            document.getElementById('sold-impact')?.classList.remove('pop');
        }

        /*
         * @param {string} badgeId  Which stamp to strike. Unsold gets the same hammer as sold —
         *                          the gavel is what ENDS a lot, and a lot that nobody bought is
         *                          just as ended — but no poppers: there is nothing to celebrate,
         *                          and streamers over an unsold player reads as mockery.
         */
        function strikeGavel(badgeId = 'sold-badge') {
            const hammer = document.getElementById('sold-hammer');
            const badge = document.getElementById(badgeId);
            if (! hammer) return;

            /*
             * No badge — a template may switch the stamp off entirely — means there is nothing to
             * strike, so the gavel stays away rather than swinging at the middle of the artwork.
             */
            if (! badge || badge.classList.contains('hidden')) { clearGavel(); return; }

            const box = badge.getBoundingClientRect();
            if (! box.width || ! box.height) { clearGavel(); return; }

            const size = hammer.offsetWidth || 220;
            // Lands on the upper-left of the tag, the way a hand holding it from the left would.
            const targetX = box.left + box.width * 0.42;
            const targetY = box.top + box.height * 0.38;

            hammer.style.left = (targetX - size * GAVEL_HEAD_X) + 'px';
            hammer.style.top  = (targetY - size * GAVEL_HEAD_Y) + 'px';

            /*
             * Carry the badge's own tilt into the recoil. Read from the computed matrix rather
             * than the inline style, because a template can set the rotation from either — and
             * read with the class off, so a previous strike's frame cannot be mistaken for it.
             */
            badge.classList.remove('hammer-hit');
            let rot = 0;
            const matrix = getComputedStyle(badge).transform || '';
            if (matrix.startsWith('matrix')) {
                const n = matrix.slice(matrix.indexOf('(') + 1, -1).split(',').map(Number);
                if (n.length >= 4 && ! n.slice(0, 4).some(isNaN)) {
                    rot = Math.atan2(n[1], n[0]) * 180 / Math.PI;
                }
            }
            badge.style.setProperty('--badge-rot', rot.toFixed(2) + 'deg');

            const ring = document.getElementById('sold-impact');
            if (ring) {
                const d = Math.max(box.width, box.height) * 1.1;
                ring.style.width = d + 'px';
                ring.style.height = d + 'px';
                ring.style.left = (targetX - d / 2) + 'px';
                ring.style.top = (targetY - d / 2) + 'px';
            }

            /*
             * Removed, reflowed, re-added. A CSS animation will not replay while its class is
             * already on the element, so two sales in a row would otherwise animate only the
             * first — the second player would get the stamp with no strike at all.
             */
            [[hammer, 'strike'], [badge, 'hammer-hit'], [ring, 'pop']].forEach(([node, cls]) => {
                if (! node) return;
                node.classList.remove(cls);
                void node.offsetWidth;
                node.classList.add(cls);
            });

            // The knock, on the frame the head lands.
            setTimeout(() => {
                try { window.auctionSound?.playChime?.(); } catch (e) {}
            }, GAVEL_IMPACT_MS);
        }

        // ── Shuffle Animation Controller ──
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
            renderPlayingTeam(ap.player, ap);

            if (bidEl) {
                rollBidTo(bidEl, Number(window._lastDisplayedPrice) || 0, price);

                if (price !== window._lastDisplayedPrice) {
                    // Removed, reflowed, re-added — a CSS animation does not replay while its
                    // class is already there, so back-to-back raises flashed only the first.
                    bidEl.classList.remove('bid-updated');
                    // The template's own tilt, carried into the bounce. Read with the class
                    // off, so a previous flash's frame cannot be mistaken for it.
                    carryRotation(bidEl, '--bid-rot');
                    void bidEl.offsetWidth;
                    bidEl.classList.add('bid-updated');
                    /* Cleared after the animation has finished, not during it: two pulses of
                       1.05s run 2.1s, and the old 1.5s cut the second one off half way. */
                    if (window._bidColorTimeout) clearTimeout(window._bidColorTimeout);
                    window._bidColorTimeout = setTimeout(() => {
                        bidEl.classList.remove('bid-updated');
                    }, 2200);
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
           The wording used to be worked out here, from `status` and two counts, and that is
           not enough to tell three different silences apart: between players, between pools,
           and after the auction has been closed. The server decides it now
           (AuctionStageService) so the wall, the strip and every manager's phone say the same
           sentence at the same moment — including the two this screen could never reach,
           "<pool> complete" and "auction complete".

           The old branches are kept as the fallback for a feed served from a cache built
           before this shipped, and for a wall left open across the deploy. */
        function renderWaitingScreen(data) {
            const title = document.getElementById('waiting-title');
            const sub = document.getElementById('waiting-sub');
            const bar = document.getElementById('waiting-progress');
            const fill = document.getElementById('waiting-progress-fill');
            const text = document.getElementById('waiting-progress-text');
            if (!title || !sub) return;

            const status = data?.auction_status;
            const p = data?.progress || {};
            const stage = data?.stage || null;
            const total = Number(p.total || 0);
            const done = Number(p.done || 0);
            const waiting = Number(p.waiting || 0);
            const started = status === 'running' || status === 'paused';

            let heading, subline;

            if (stage?.heading) {
                heading = stage.heading;
                subline = stage.subline || AUCTION_NAME;
            } else if (status === 'paused') {
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

            /* The pool chip is the ACTIVE pool. A pool that has just ended is already named in
               the headline, and showing "Pool 1" as the running pool underneath "POOL 1
               COMPLETE" would contradict it. */
            renderStagePool(stage && stage.key === 'pool_complete' ? null : p.pool_name);

            // The rail is meaningless before anyone has been through the block.
            if (bar && fill && text) {
                if (started && total > 0 && done > 0) {
                    bar.classList.remove('hidden');
                    fill.style.width = Math.min(100, (done / total) * 100).toFixed(1) + '%';
                    /* Named, because "3 of 17" means nothing to anyone who has not seen the
                       pools screen — and a pool is how a hall follows an evening. */
                    const poolName = p.pool_name ? `${p.pool_name} · ` : '';
                    text.textContent = `${poolName}${done} of ${total} done · ${waiting} to go`;
                } else {
                    bar.classList.add('hidden');
                }
            }
        }

        /*
         * ── "Pool A is selected" ──
         *
         * The wall counted lots and never said which pool they belonged to, so a room that follows
         * an evening by its pools — marquee players, then the rest — had to be told out loud.
         *
         * The chime fires only when the NAME changes, not on every poll: this runs every two
         * seconds, and a bell every two seconds is worse than no bell. Guarded on having a
         * previous value too, so opening the wall mid-auction does not announce a pool that has
         * been running for an hour.
         */
        let _lastStagePool = undefined;

        function renderStagePool(poolName) {
            const name = (poolName || '').trim();

            [['stage-pool', 'stage-pool-name'], ['restart-pool', 'restart-pool-name']].forEach(([wrapId, nameId]) => {
                const wrap = document.getElementById(wrapId);
                const label = document.getElementById(nameId);
                if (! wrap || ! label) return;

                if (name) {
                    label.textContent = name;
                    wrap.classList.remove('hidden');
                } else {
                    wrap.classList.add('hidden');
                }
            });

            if (name && _lastStagePool !== undefined && _lastStagePool !== name) {
                // Ting-tong: two tones, synthesised — no file to fail to load on a venue uplink.
                try { window.auctionSound?.playChime?.(); } catch (e) {}
            }

            _lastStagePool = name;
        }

        function showCard() {
            console.log('[Live] showCard()');
            document.getElementById('waiting-screen').classList.add('hidden');
            document.getElementById('card-container').classList.remove('hidden');
        }

        /**
         * Mark a new player arriving, now that the reveal no longer does it.
         *
         * The spin-and-reveal was what told a hall the lot had changed. With it gone the card
         * simply swaps, and two players in the same kit at the same base price can look like
         * nothing happened at all — especially from the back of a room. A short fade-and-rise
         * on the card is enough to read as "next", without naming anybody in advance, which is
         * the thing that had to go.
         *
         * Re-triggered by removing the class and forcing a reflow: a CSS animation will not
         * replay while its class is already on the element.
         */
        /**
         * The board of players sold, drawn from the feed the wall already publishes.
         *
         * Fetched only while the board is up: it is a list of every sale in the auction and
         * there is no reason to carry it on the two-second tick when nothing is showing it.
         */
        let soldBoardShowing = null;

        function renderSoldBoard(rows) {
            const grid = document.getElementById('sold-board-grid');
            const count = document.getElementById('sold-board-count');
            if (! grid) return;

            const list = Array.isArray(rows) ? rows : [];

            if (count) {
                count.textContent = list.length === 1 ? '1 player' : `${list.length} players`;
            }

            if (! list.length) {
                grid.innerHTML = '<div style="color:rgba(255,255,255,0.5);font-size:20px;">'
                    + 'Nothing sold yet.</div>';
                return;
            }

            grid.innerHTML = list.map((row) => {
                const name = row?.player?.name || 'Player';
                const team = row?.sold_to_team?.name || '';
                const face = row?.player?.image
                    ? `<img class="face" src="${escapeHtml(row.player.image)}" alt="">`
                    : `<div class="face-blank">${escapeHtml(name.substring(0, 2).toUpperCase())}</div>`;

                return '<div class="sold-card">'
                    + face
                    + '<div class="who">'
                    + `<div class="nm">${escapeHtml(name)}</div>`
                    + (team ? `<div class="tm">${escapeHtml(team)}</div>` : '')
                    + `<div class="amt">${formatMillions(Number(row?.final_price) || 0)}</div>`
                    + '</div></div>';
            }).join('');
        }

        /**
         * The highlights reel: a few of the biggest buys at a time, fading between slides.
         *
         * Sorted by price, ribboned at the top, and then SHUFFLED, so a pause does not replay the
         * same faces in the same order every time it is put up — the room sees a different cut of
         * the same story. Capped at the top of the market because a reel of everybody is a list,
         * and a list is what the sold board is for.
         */
        let _reelTimer = null;
        let _reelSlides = [];
        let _reelIndex = 0;

        const REEL_POOL = 20;
        /* Cards between one ad and the next. A sponsor still comes round two or three times in a
           normal break; the reel still reads as an auction recap rather than a commercial. */
        const REEL_AD_EVERY = 6;
        /* One card at a time now, so each gets less of the break than a slide of five did — 4.2s
           is long enough to read a name, a crest and a figure, and short enough that a twenty-card
           ring comes round inside a normal interval. */
        const REEL_MS = 4200;

        function shuffled(list) {
            const out = list.slice();

            for (let i = out.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [out[i], out[j]] = [out[j], out[i]];
            }

            return out;
        }

        function reelCard(row) {
            const nm = row?.player?.name || 'Player';
            const tm = row?.sold_to_team?.name || '';
            const face = row?.player?.image
                ? `<img src="${escapeHtml(row.player.image)}" alt="">`
                : `<div class="blank">${escapeHtml(nm.substring(0, 2).toUpperCase())}</div>`;

            /* The buying team's badge beside its name — a hall reads a crest faster than a
               name, and on a board of top buys the team is half the story. */
            const crest = row?.sold_to_team?.logo_path;
            const team = tm
                ? '<div class="tm">'
                    + (crest ? `<img class="crest" src="${escapeHtml(crest)}" alt="">` : '')
                    + `<span>${escapeHtml(tm)}</span></div>`
                : '';

            /* Which one is the top buy is the fact a room looks for on a top-buys board, and a
               list sorted by price cannot say it once the cards are shown one at a time. */
            const tag = row?._isTop ? '<div class="top-tag">Top buy</div>' : '';

            return '<div class="rp">' + tag + face
                + `<div class="nm">${escapeHtml(nm)}</div>`
                + team
                + `<div class="amt">${formatMillions(Number(row?.final_price) || 0)}</div>`
                + '</div>';
        }

        function stopReel() {
            if (_reelTimer) { clearInterval(_reelTimer); _reelTimer = null; }
            _reelSlides = [];
            _reelIndex = 0;
        }

        /*
         * ── The ring ──
         *
         * Every buy is its own card and they all sit on one ring: the middle card is shown big and
         * face-on, its neighbours are turned away and set back, and the ring turns by one card at a
         * time. Positions are written as three custom properties per card and the CSS does the rest,
         * so a turn is one style write per card and no layout at all.
         */
        const REEL_VISIBLE = 3;      // cards either side of the middle that stay on screen
        const REEL_GAP = 0.62;       // how far apart they sit, as a fraction of the stage width

        function positionReel(el) {
            const nodes = el.querySelectorAll('.slide');
            if (! nodes.length) return;

            const stage = el.clientWidth || 1200;
            const total = nodes.length;

            nodes.forEach((node, i) => {
                /*
                 * Signed shortest distance around the ring, so a card at the end travels to the
                 * front through the nearest side. Measured the naive way, the last card flew the
                 * whole width of the wall to get back to the start.
                 */
                let offset = i - _reelIndex;
                if (offset > total / 2) offset -= total;
                if (offset < -total / 2) offset += total;

                const away = Math.abs(offset);
                const beyond = away > REEL_VISIBLE;

                node.style.setProperty('--reel-x', (offset * stage * REEL_GAP * 0.5).toFixed(1));
                node.style.setProperty('--reel-s', (Math.max(0.52, 1 - away * 0.17)).toFixed(3));
                node.style.setProperty('--reel-r', (-offset * 26).toFixed(1));
                node.style.setProperty('--reel-o', beyond ? '0' : (away === 0 ? '1' : (0.78 - away * 0.2).toFixed(2)));
                node.style.setProperty('--reel-b', away === 0 ? '1' : (0.72 - away * 0.08).toFixed(2));
                node.style.zIndex = String(100 - away);
            });
        }

        function renderReel(rows) {
            const el = document.getElementById('reel');
            if (! el) return;

            stopReel();

            const top = (Array.isArray(rows) ? rows : [])
                .filter((r) => Number(r?.final_price) > 0)
                .sort((a, b) => Number(b.final_price) - Number(a.final_price))
                .slice(0, REEL_POOL);

            if (! top.length) {
                el.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;'
                    + 'height:100%;color:rgba(255,255,255,0.5);font-size:24px;">Nothing sold yet.</div>';
                return;
            }

            // Marked before the shuffle, so the ribbon follows the dearest buy wherever it lands.
            if (top[0]) top[0]._isTop = true;

            /*
             * Shuffled, so a break does not open on the same three faces every time — the reel is
             * a recap of the evening, not a leaderboard. The ribbon carries the ranking that
             * matters.
             */
            const order = shuffled(top);

            order.forEach((row) => _reelSlides.push({ kind: 'player', row }));

            /*
             * Ads interleaved BETWEEN cards, one after every few players.
             *
             * Not grouped at the end, where a break cut short shows none of them; not between
             * every card, which turns the reel into an ad break. Spaced means a sponsor comes round
             * as often as the players do and the reel still reads as an auction recap.
             */
            if (_reelAds.length) {
                /*
                 * One ad every REEL_AD_EVERY cards — or once a cycle, whichever comes first.
                 *
                 * Spacing them by ads-to-players meant that uploading more artwork made the reel
                 * MORE of an ad break, so the gap became a floor. That floor then swallowed the ads
                 * entirely on a short reel: five sold players against a gap of six means `(i + 1) %
                 * 6` never comes true, and an auction early in its evening showed no sponsor at all
                 * while the feed was serving two. A gap wider than the reel is not a gap, it is an
                 * off switch.
                 *
                 * Clamped to the number of cards, so the sponsor lands after the last one instead.
                 */
                const players = _reelSlides.length;
                const every = Math.min(
                    Math.max(REEL_AD_EVERY, Math.round(players / (_reelAds.length + 1)) || REEL_AD_EVERY),
                    players
                );
                const mixed = [];
                let adIndex = 0;

                _reelSlides.forEach((slide, i) => {
                    mixed.push(slide);
                    if ((i + 1) % every === 0) {
                        const ad = _reelAds[adIndex % _reelAds.length];
                        if (ad) { mixed.push({ kind: 'ad', ad }); adIndex++; }
                    }
                });

                _reelSlides = mixed;
            }

            el.innerHTML = _reelSlides.map((slide) => {
                if (slide.kind === 'ad') {
                    return '<div class="slide ad"><div class="frame">'
                        + `<img src="${escapeHtml(slide.ad.url)}" alt="">`
                        + (slide.ad.caption ? `<div class="cap">${escapeHtml(slide.ad.caption)}</div>` : '')
                        + '<div class="tag">Sponsor</div>'
                        + '</div></div>';
                }

                return '<div class="slide">' + reelCard(slide.row) + '</div>';
            }).join('');

            _reelIndex = 0;
            positionReel(el);

            // Only worth turning if there is more than one card to turn to.
            if (_reelSlides.length > 1) {
                _reelTimer = setInterval(() => {
                    _reelIndex = (_reelIndex + 1) % _reelSlides.length;
                    positionReel(el);
                }, REEL_MS);
            }

            /*
             * A projector that changes resolution, or a browser window resized mid-break, changes
             * how far apart the cards should sit. Re-measured rather than left at whatever the
             * stage was when the reel started, which stranded the ring off-centre.
             */
            if (! _reelResizeBound) {
                _reelResizeBound = true;
                window.addEventListener('resize', () => {
                    const reel = document.getElementById('reel');
                    if (reel && ! reel.classList.contains('hidden')) positionReel(reel);
                });
            }
        }

        let _reelResizeBound = false;

        /**
         * The sponsor strip, and the event's own mark above it.
         *
         * Drawn for the whole auction rather than only during a break: a logo that appears when
         * bidding stops has been shown far less than the deal said. Renders nothing at all when
         * there is no artwork, so a wall with no sponsors is unchanged.
         */
        function renderSponsors(sponsors, logoUrl) {
            const strip = document.getElementById('reel-sponsors');
            const logo = document.getElementById('reel-logo');

            if (logo) {
                if (logoUrl) {
                    logo.src = logoUrl;
                    logo.classList.remove('hidden');
                } else {
                    logo.classList.add('hidden');
                }
            }

            if (! strip) return;

            const list = Array.isArray(sponsors) ? sponsors : [];

            /*
             * Rendered TWICE inside one track, which is what makes the march seamless: the CSS
             * translates the track by exactly half its width, so the second copy is arriving as the
             * first leaves. One copy leaves a gap crossing the wall every cycle.
             *
             * Few enough to stand still stay still — sliding three logos across an empty strip is
             * motion for its own sake.
             */
            /*
             * Always a carousel, however few logos there are.
             *
             * A short list used to stand still, which is defensible and not what was asked for: a
             * moving strip is what makes a sponsor row read as part of the broadcast rather than a
             * footer. Two sponsors cannot fill a wall, so the list is REPEATED until it does, and
             * then the whole run is doubled — the CSS translates the track by exactly half its
             * width, so the second run arrives as the first leaves and the loop has no seam.
             */
            const one = list.map((a) => `<img src="${escapeHtml(a.url)}" alt="">`).join('');

            // Enough marks to cross a wall before repeating, without knowing their widths.
            const repeats = Math.max(1, Math.ceil(10 / list.length || 1));
            const run = one.repeat(repeats);

            strip.innerHTML = list.length ? `<div class="sponsor-track">${run}${run}</div>` : '';

            // Paced off the marks actually on the track, so a two-logo strip does not sprint.
            strip.style.setProperty('--sponsor-secs', Math.max(28, list.length * repeats * 4) + 's');
            strip.classList.toggle('hidden', ! list.length);
        }

        /*
         * The sponsors, fetched once at start-up.
         *
         * They used to arrive only when a board went up, so a wall that never showed a board
         * never showed a sponsor — and the strip appeared mid-evening rather than being there
         * from the opening lot. One request on load, and the board's own fetch refreshes it.
         */
        fetch(`/auction/${auctionId}/sold-players`)
            .then((res) => res.json())
            .then((data) => {
                _reelAds = Array.isArray(data?.adSlides) ? data.adSlides : [];
                renderSponsors(data?.sponsors, data?.tournamentLogo);
            })
            .catch(() => {});

        /* What the artwork switches were last set to, so a change to them can be noticed. */
        let _lastArtwork = null;

        /*
         * Re-read the sponsors on their own.
         *
         * Turning the strip off has to take it down even when no board is up — the strip is drawn
         * for the whole auction, not only during a break, so it cannot wait for the next board
         * fetch to notice.
         */
        function refreshSponsors() {
            fetch(`/auction/${auctionId}/sold-players`)
                .then((res) => res.json())
                .then((data) => {
                    if (Array.isArray(data?.adSlides)) _reelAds = data.adSlides;
                    renderSponsors(data?.sponsors, data?.tournamentLogo);
                })
                .catch(() => {});
        }

        function fetchSoldBoard(board) {
            fetch(`/auction/${auctionId}/sold-players`)
                .then((res) => res.json())
                .then((data) => {
                    /*
                     * Take the ads from THIS response, not from whatever the start-up fetch left
                     * behind.
                     *
                     * Both requests hit the same endpoint, but the reel is usually built from this
                     * one — and if the board was already up when the wall loaded, renderReel ran
                     * before the start-up fetch resolved, so `_reelAds` was still empty and the
                     * reel was built with no sponsors in it at all. That is the whole bug behind
                     * "the ads are not showing": they were uploaded, enabled, and never read.
                     */
                    if (Array.isArray(data?.adSlides)) _reelAds = data.adSlides;
                    renderSponsors(data?.sponsors, data?.tournamentLogo);

                    if (board === 'highlights') {
                        renderReel(data?.soldPlayers);
                    } else {
                        renderSoldBoard(data?.soldPlayers);
                    }
                })
                .catch(() => {});
        }

        /**
         * Show or hide the board.
         *
         * Idempotent: the poll calls this on every tick with whatever the server says, and the
         * pushed frame calls it the moment the button is pressed. Only a CHANGE refetches, so
         * the list is not pulled thirty times a minute while it sits on screen.
         */
        /**
         * The status line on a board: what the room is waiting for, and for how long.
         *
         * A board with nothing said over it reads as the auction having stopped. A sealed round
         * is an answer in itself; anything else is a break, and a break with a clock on it is
         * the difference between "they have gone" and "they are coming back".
         *
         * The clock counts UP from when the board went up. Nobody knows when a break ends, and a
         * countdown that reaches zero over an empty stage is worse than no countdown at all.
         */
        let _boardSince = null;
        let _boardStatusTimer = null;
        /* Seconds left in the break, from the server, ticked down locally between polls so the
           clock moves every second rather than every two. The poll re-seeds it, so drift cannot
           accumulate and every screen lands on the same figure. */
        let _breakRemaining = null;

        function clockText(secs) {
            const s = Math.max(0, Math.floor(secs));
            const m = Math.floor(s / 60);

            return `${m}:${String(s % 60).padStart(2, '0')}`;
        }

        function boardElapsed() {
            if (! _boardSince) return '';

            return clockText((Date.now() - _boardSince) / 1000);
        }

        function paintBoardStatus() {
            const wrap = document.getElementById('board-status');
            const text = document.getElementById('board-status-text');
            const timer = document.getElementById('board-status-timer');
            if (! wrap || ! text || ! timer) return;

            if (! soldBoardShowing) {
                wrap.classList.add('hidden');
                return;
            }

            /*
             * Shown for the reel as well as the board.
             *
             * I hid this during the reel on the reasoning that a "BACK IN 4:32" clock is a caption
             * about waiting placed over the content put up so the room would not feel like it was
             * waiting. That was wrong about the geometry: this line lives in the board's HEADER
             * strip, above the stage, and never covered a single card. Hiding it only took away the
             * one thing a hall actually wants during a break — when the auction comes back.
             */
            wrap.classList.remove('hidden');

            // `sealedState` is set by the poll before the card renders; a live round outranks a
            // break, because it says what the delay actually is.
            if (sealedState && sealedState.active) {
                text.textContent = 'SEALED BID IN PROGRESS';
                timer.textContent = '';
                return;
            }

            /*
             * A countdown when the organizer set one, and only then.
             *
             * "Back in 6:00" is the question a hall is actually asking; the elapsed clock this
             * replaced answered "they have been gone four minutes", which is the same fact from
             * the wrong end. With no break length set, that elapsed clock is still better than
             * nothing — it at least shows the screen is live.
             */
            if (_breakRemaining !== null) {
                if (_breakRemaining > 0) {
                    text.textContent = 'BACK IN';
                    timer.textContent = clockText(_breakRemaining);
                    _breakRemaining -= 1;
                } else {
                    text.textContent = 'BACK ANY MOMENT';
                    timer.textContent = '';
                }

                return;
            }

            text.textContent = 'BACK SHORTLY';
            timer.textContent = boardElapsed();
        }

        function applySoldBoard(board) {
            const el = document.getElementById('sold-board');
            if (! el) return;

            // Tolerates the old boolean payload as well as the board name, so a screen still
            // running yesterday's script is not left with a board it cannot turn off.
            const next = board === true ? 'sold' : (board || null);

            el.classList.toggle('hidden', ! next);
            // Marks the whole page, so the live overlays can stand down in CSS rather than each
            // renderer having to know a board might be up.
            document.body.classList.toggle('board-up', !! next);

            const grid = document.getElementById('sold-board-grid');
            const reel = document.getElementById('reel');
            const title = document.getElementById('sold-board-title');
            const count = document.getElementById('sold-board-count');

            if (grid) grid.classList.toggle('hidden', next !== 'sold');
            if (reel) reel.classList.toggle('hidden', next !== 'highlights');
            if (title) title.textContent = next === 'highlights' ? 'TOP BUYS' : 'SOLD SO FAR';
            if (count) count.style.visibility = next === 'highlights' ? 'hidden' : 'visible';

            if (! next) stopReel();

            // The break clock starts when the board goes up, and only then — re-applying the
            // same board on every poll must not keep resetting it to 0:00.
            if (next && ! soldBoardShowing) {
                _boardSince = Date.now();
                if (_boardStatusTimer) clearInterval(_boardStatusTimer);
                _boardStatusTimer = setInterval(paintBoardStatus, 1000);
            }

            if (! next) {
                _boardSince = null;
                if (_boardStatusTimer) { clearInterval(_boardStatusTimer); _boardStatusTimer = null; }
            }

            // Only a CHANGE refetches — the poll calls this every tick and the list is every
            // sale in the auction.
            if (next && next !== soldBoardShowing) fetchSoldBoard(next);

            soldBoardShowing = next;

            paintBoardStatus();
        }

        /*
         * The chime, when a new player comes up.
         *
         * Tied to the same moment the card changes, so the room's attention is asked for once
         * per lot and not on every price move — an alert that fires on every raise is one the
         * hall stops hearing within a minute.
         */
        function announceNewPlayer() {
            window.auctionSound?.playChime();
        }

        (function initSound() {
            const btn = document.getElementById('sound-arm');
            if (! btn) return;

            const hide = () => btn.style.display = 'none';

            // Already armed by an earlier interaction on this page.
            if (window.auctionSound?.soundArmed()) return hide();

            btn.addEventListener('click', () => {
                if (window.auctionSound?.armSound()) {
                    // Confirms it worked, which a silent success cannot.
                    window.auctionSound.playChime();
                    hide();
                }
            });
        })();

        /*
         * The "loading next player" surface.
         *
         * Held for a fixed beat rather than until the card is ready: the card is usually ready
         * immediately (the push carries it), so hiding on arrival would flash the loader for a
         * frame and read as a glitch. LOADER_MS is what a hall actually perceives as "something is
         * coming", and the card is painted underneath while it runs.
         */
        const LOADER_MS = 1600;
        let _loaderTimer = null;

        function showNextLoader() {
            const el = document.getElementById('next-loader');
            if (! el) return;

            /*
             * The previous player goes down as the loader goes up.
             *
             * The card is repainted underneath while this runs — the push already carries the new
             * player — so without hiding it the sequence was: old face, loader over old face, new
             * face. Hiding it makes the three beats the room should see: the old one leaves,
             * something is coming, the new one arrives.
             */
            clearOutcomeState();
            document.getElementById('card-container')?.classList.add('hidden');

            el.classList.remove('hidden');

            if (_loaderTimer) clearTimeout(_loaderTimer);
            _loaderTimer = setTimeout(() => {
                _loaderTimer = null;
                el.classList.add('hidden');

                // Back to whatever the wall should be showing now — the card if a player is up,
                // and markCardChanged so it arrives rather than appears.
                if (lastOnAuctionPlayerId) {
                    document.getElementById('card-container')?.classList.remove('hidden');
                    markCardChanged();
                }
            }, LOADER_MS);
        }

        /** Taken down at once when something more important needs the wall. */
        function hideNextLoader() {
            if (_loaderTimer) { clearTimeout(_loaderTimer); _loaderTimer = null; }
            document.getElementById('next-loader')?.classList.add('hidden');
        }

        function markCardChanged() {
            const card = document.getElementById('card-container');
            if (! card) return;

            card.classList.remove('card-arrived');
            void card.offsetWidth;
            card.classList.add('card-arrived');

            /*
             * Turn the event's marks over with the player.
             *
             * Same removal-and-reflow as the card itself: a CSS animation will not replay while
             * its class is still on the element, so back-to-back lots would flip only the first.
             */
            const marks = document.getElementById('card-marks');
            if (marks) {
                marks.classList.remove('flip');
                void marks.offsetWidth;
                marks.classList.add('flip');
            }
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
                renderSealedOverlay(null);

                /*
                 * Clear the DRAW surface too.
                 *
                 * This returned without touching it, so "DRAWING A LOT" stayed on the wall for
                 * ever: the round retires the moment its spin is over, sealed goes null, and the
                 * one function that hides the draw was never reached. Nothing the organizer
                 * pressed afterwards — draw, next player, sell — could take it down, because none
                 * of them produce a sealed state either.
                 */
                renderSealedDraw(null);
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

            /* A sealed round or a draw outranks the loader — both take the whole wall. */
            hideNextLoader();

            renderSealedDraw(sealedState.tie);
            renderSealedOverlay(sealedState);

            banner.classList.remove('hidden');
        }

        /*
         * ── The wall-sized version ──
         *
         * A bar across the top of the card was not enough. When bidding goes private the price
         * freezes, the chips go quiet and the card stops changing — from the back of a hall that is
         * indistinguishable from a screen that has crashed, and the one thing the room needs to
         * know is that something IS happening and to wait.
         *
         * It steps aside for the states where there is a RESULT to watch. A tie, a draw and a
         * reveal each have their own animation in the banner, and covering those with a logo would
         * hide the only part anybody in the hall is waiting for.
         *
         * Wall only, deliberately: the ticker is one strip read at a glance, and a full-bleed
         * takeover there would blank the line it exists to show.
         */
        const SEALED_OVERLAY_STATES = ['pending', 'entry_open', 'collecting', 'locked'];

        /*
         * The organizer's own headline and message, from the Sealed Bid Screen settings.
         *
         * Read off the nodes rather than hard-coded here, so an auction that has set its own
         * wording keeps it in every state — a tournament that does not run in English was
         * otherwise handed four sentences it could not change.
         *
         * `locked` is the one state that says something different: the bids are IN and the result
         * is coming, which is a fact about the round rather than a piece of branding.
         */
        function sealedCopyFor(state) {
            const heading = document.getElementById('sealed-overlay-heading')?.dataset.default
                || 'Sealed Bid In Progress';
            const message = document.getElementById('sealed-overlay-sub')?.dataset.default
                || 'Amounts are revealed once every team has submitted';

            if (state === 'locked') {
                return [heading, 'Bids are in — result coming up'];
            }

            return [heading, message];
        }

        function renderSealedOverlay(sealed) {
            const overlay = document.getElementById('sealed-overlay');
            if (! overlay) return;

            const state = sealed?.state;

            /* A draw has its own surface (#draw-overlay) and outranks this one: the states are
               mutually exclusive on the server, and this guard keeps them so on the screen. */
            if (! state || ! SEALED_OVERLAY_STATES.includes(state) || (sealed?.tie?.teams || []).length) {
                overlay.classList.add('hidden');
                return;
            }

            const [heading, sub] = sealedCopyFor(state);
            const headingEl = document.getElementById('sealed-overlay-heading');
            const subEl = document.getElementById('sealed-overlay-sub');
            const roundEl = document.getElementById('sealed-overlay-round');

            if (headingEl) headingEl.textContent = heading;
            if (subEl) subEl.textContent = sub;
            if (roundEl) {
                roundEl.textContent = sealed.round_number
                    ? `Round ${sealed.round_number} of ${sealed.total_rounds || 1}`
                    : '';
            }

            /*
             * Ticked locally between polls.
             *
             * The server's figure arrives every couple of seconds; a clock that only moved when a
             * poll landed would jump 30, 28, 26 in front of a hall. `_sealedClockUntil` is the wall
             * clock instant the round ends, computed once per poll from the server's remaining
             * seconds, and the interval counts down against it.
             */
            const timerWrap = document.getElementById('sealed-overlay-timer');
            const clockEl = document.getElementById('sealed-overlay-clock');
            const remaining = sealed.timer?.remaining;

            if (timerWrap && clockEl) {
                if (remaining === null || remaining === undefined) {
                    timerWrap.classList.add('hidden');
                    _sealedClockUntil = null;
                } else {
                    _sealedClockUntil = Date.now() + Math.max(0, Number(remaining)) * 1000;
                    timerWrap.classList.remove('hidden');
                    paintSealedClock();
                }
            }

            overlay.classList.remove('hidden');
        }

        let _sealedClockUntil = null;
        let _sealedClockTimer = null;

        function paintSealedClock() {
            const clockEl = document.getElementById('sealed-overlay-clock');
            if (! clockEl || _sealedClockUntil === null) return;

            const left = Math.max(0, Math.round((_sealedClockUntil - Date.now()) / 1000));
            clockEl.textContent = left > 0 ? `${left}s` : 'TIME UP';
            clockEl.style.color = left > 0 && left <= 10 ? '#fca5a5' : '#fff';
        }

        // One interval for the page, started once: a timer per render would stack up.
        if (! _sealedClockTimer) {
            _sealedClockTimer = setInterval(paintSealedClock, 1000);
        }

        /* The name currently showing in the draw, and the timer cycling it. */
        let _drawCycle = null;
        /* Fires once, when the spin is due to end. See renderSealedDraw(). */
        let _drawSettleTimer = null;
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
        /*
         * ── The ring of tied teams ──
         *
         * Rebuilt only when the SET of teams changes: this runs on every poll, and tearing the
         * cards down and putting them back twice a second would restart the spin from zero each
         * time — a ring that judders in place rather than turning.
         */
        let _drawRingKey = null;

        function buildDrawRing(teams) {
            const ring = document.getElementById('draw-ring');
            const inner = document.getElementById('draw-ring-inner');
            if (! ring || ! inner) return;

            const key = teams.map((t) => t.id).join(',');

            if (_drawRingKey !== key) {
                _drawRingKey = key;

                /*
                 * Radius from the count, so three cards do not overlap and eight do not fly apart.
                 * Half the card width over tan(half the angle between them) is the exact distance
                 * at which neighbours just touch; a little more leaves them breathing room.
                 */
                const step = 360 / Math.max(teams.length, 1);
                const radius = Math.max(230, Math.round(118 / Math.tan(Math.PI / Math.max(teams.length, 2))));

                inner.innerHTML = teams.map((team, i) => {
                    const angle = i * step;
                    const crest = team.logo
                        ? `<img src="${escapeHtml(team.logo)}" alt="">`
                        : '';

                    return `<div class="draw-card" data-team="${team.id}" data-angle="${angle}"
                                 style="transform: rotateY(${angle}deg) translateZ(${radius}px);">`
                        + crest
                        + `<div class="draw-card-name">${escapeHtml(team.name)}</div>`
                        + '</div>';
                }).join('');

                inner.classList.remove('settling');
                inner.style.transform = '';
            }

            // The tournament's own mark in the middle of the ring, when the wall has one.
            const logoWrap = document.getElementById('draw-ring-logo');
            const logoImg = document.getElementById('draw-ring-logo-img');
            const source = document.getElementById('reel-logo');

            if (logoWrap && logoImg) {
                if (source && source.src && ! source.classList.contains('hidden')) {
                    logoImg.src = source.src;
                    logoWrap.classList.remove('hidden');
                } else {
                    logoWrap.classList.add('hidden');
                }
            }

            ring.classList.remove('hidden');
        }

        /** Stop the ring with the winner facing the hall, and dim everybody else. */
        function settleDrawRing(teams, winner) {
            const inner = document.getElementById('draw-ring-inner');
            if (! inner || ! winner) return;

            const card = inner.querySelector(`.draw-card[data-team="${winner.id}"]`);
            const angle = Number(card?.dataset.angle) || 0;

            /*
             * Turned the LONG way round, deliberately: two more full revolutions before landing.
             * Stopping from wherever the loop happens to be reads as a dropped frame — the extra
             * turns are what make it read as slowing down and settling.
             */
            inner.classList.add('settling');
            inner.style.transform = `rotateY(${-angle - 720}deg)`;

            inner.querySelectorAll('.draw-card').forEach((node) => {
                const isWinner = node.dataset.team === String(winner.id);
                node.classList.toggle('is-winner', isWinner);
                node.classList.toggle('is-loser', ! isWinner);
            });
        }

        function clearDrawRing() {
            const ring = document.getElementById('draw-ring');
            const inner = document.getElementById('draw-ring-inner');

            _drawRingKey = null;

            if (inner) {
                inner.innerHTML = '';
                inner.classList.remove('settling');
                inner.style.transform = '';
            }

            ring?.classList.add('hidden');
        }

        function renderSealedDraw(tie) {
            const wrap = document.getElementById('sealed-draw');
            const surface = document.getElementById('draw-overlay');
            if (! wrap) return;

            /* The draw owns the wall while it runs, and gives it back the moment it does not.
               Hidden whenever there is no tie to show — including when the round has gone
               entirely, which is how it used to get stuck up there. */
            surface?.classList.toggle('hidden', ! (tie?.teams || []).length);

            const nameEl = document.getElementById('sealed-draw-name');
            const amountEl = document.getElementById('sealed-draw-amount');
            const labelEl = document.getElementById('sealed-draw-label');

            const teams = tie?.teams || [];

            if (! tie || teams.length === 0) {
                wrap.classList.add('hidden');
                document.getElementById('draw-coin')?.classList.remove('settled');
                clearDrawRing();

                if (_drawSettleTimer !== null) {
                    clearTimeout(_drawSettleTimer);
                    _drawSettleTimer = null;
                }
                if (_drawCycle) { clearInterval(_drawCycle); _drawCycle = null; }
                _drawSettledFor = null;
                return;
            }

            wrap.classList.remove('hidden');

            /*
             * A coin for two teams; the cycling names for more.
             *
             * A coin has two faces, so it is an honest picture of a two-way draw and a
             * misleading one for five — the room would be watching a toss decide something a
             * toss cannot. With more than two tied, the names circling IS the draw.
             */
            const coin = document.getElementById('draw-coin');
            if (coin) coin.classList.add('hidden');

            buildDrawRing(teams);

            if (amountEl) {
                amountEl.textContent = tie.amount
                    ? `${teams.length} teams matched at ${formatMillions(tie.amount)}`
                    : `${teams.length} teams tied`;
            }

            const winner = tie.lot_winner_team_id
                ? teams.find(t => Number(t.id) === Number(tie.lot_winner_team_id))
                : null;

            /*
             * Hold the spin for the same window the organizer's panel is spinning.
             *
             * The winner is recorded the instant DRAW LOT is pressed, so this payload carries it
             * immediately — and the wall used to settle at once, showing the hall the result
             * fifteen seconds before the person who drew it. `drawn_at` and `spin_ms` come from
             * the server, so both screens run one window from one instant.
             */
            /*
             * The server says how long is LEFT; this screen does no clock arithmetic.
             *
             * It used to diff `Date.now()` against the server's `drawn_at`, which is a browser
             * clock measured against a server timestamp — and this application runs its PHP in
             * Asia/Dubai while its database and OS are UTC, so those agree only while every writer
             * goes through PHP's now(). Any drift settled the ring instantly or left it turning
             * for ever.
             *
             * The winner is also withheld from this payload until the spin is over, so there is
             * nothing here to leak even if the timing were wrong.
             */
            const remainingMs = Number(tie.spin_remaining_ms) || 0;
            const stillSpinning = remainingMs > 0;

            /*
             * One timer, armed once: it settles the ring at the exact moment the spin ends and
             * then refetches, which is what makes the reveal land together on every screen
             * without waiting for a poll. Re-armed only if the round changes underneath it.
             */
            if (stillSpinning && _drawSettleTimer === null) {
                _drawSettleTimer = setTimeout(() => {
                    _drawSettleTimer = null;
                    refreshNow('lot-landed');
                }, remainingMs + 120);
            }

            if (winner && ! stillSpinning) {
                // Settle once. Without this guard every poll re-runs the landing animation and the
                // winner's name pops every two seconds for the rest of the round.
                if (_drawSettledFor === winner.id) return;
                _drawSettledFor = winner.id;

                if (_drawCycle) { clearInterval(_drawCycle); _drawCycle = null; }

                settleDrawRing(teams, winner);

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

            /* No seconds to paint any more — see the markup for why the countdown went. The
               server still measures the window; it is what decides when this notice comes down,
               and every screen watching still comes back on the same beat. */

            screen.classList.remove('hidden');
            return true;
        }

        /*
         * ── The player's photo ──
         *
         * Two ways the wall used to get stuck on the wrong face, both fixed here.
         *
         * 1. The fallback for a player with no photo was a ui-avatars.com URL — a THIRD-PARTY
         *    request from a hall's uplink, on the one screen in the building that must not wait
         *    for the internet. When it is slow the box stays empty; when it is blocked the
         *    browser paints its broken-image mark and leaves it there for the rest of the
         *    auction. The initials are drawn locally now, as an inline SVG: no request, no
         *    dependency, and a different mark for every player.
         *
         * 2. There was no `onerror`. A path that 404s (a file moved, a bad import) leaves the
         *    PREVIOUS player's photo on screen in several browsers — they keep the last decoded
         *    frame when the new source fails — so the hall reads the wrong face under the right
         *    name. A failure now falls back to that player's own initials.
         *
         * The handler is re-attached every time rather than set once, because it has to close
         * over the player it is a fallback FOR: a stale handler would draw the previous
         * player's initials.
         */
        function setPlayerImage(player) {
            const el = document.getElementById('player-image');
            if (! el || ! player) return;

            const fallback = initialsAvatar(player.name);

            el.onerror = () => {
                // Cleared first, or a failing fallback would re-enter this forever.
                el.onerror = null;
                el.src = fallback;
            };

            el.src = player.image_path ? `/storage/${player.image_path}` : fallback;
        }

        /** A player's initials on a neutral disc, drawn inline — never a network request. */
        function initialsAvatar(name) {
            const initials = (String(name || '?')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map(part => part.charAt(0))
                .join('')
                .toUpperCase() || '?')
                /* The initials go into SVG markup, so a name beginning with < or & would
                   produce a document that does not parse — and an image that never loads.
                   Quotes are left alone deliberately: they are legal in element text, and a
                   regex literal carrying one breaks AuctionWallScriptIntegrityTest's parser,
                   which strips strings without understanding regexes. */
                .replace(/[<>&]/g, '');

            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
                <rect width="400" height="400" rx="24" fill="#0f172a"/>
                <circle cx="200" cy="200" r="150" fill="#1e293b"/>
                <text x="200" y="200" font-family="Inter, Arial, sans-serif" font-size="140" font-weight="700"
                      fill="#94a3b8" text-anchor="middle" dominant-baseline="central">${initials}</text>
            </svg>`;

            // encodeURIComponent rather than btoa: a name can carry characters outside Latin-1
            // and btoa throws on those, which would take the whole card render down with it.
            return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
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

            setPlayerImage(p.player);

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
            renderPlayingTeam(p.player, p);

            if (bidEl) {
                // Rolls up to the new figure, exactly as the pushed path does — a raise must
                // look the same to the room whichever route delivered it.
                rollBidTo(bidEl, Number(window._lastDisplayedPrice) || 0, price);

                // Brief green highlight when price changes, then back to white
                if (price !== window._lastDisplayedPrice) {
                    // Removed, reflowed, re-added — a CSS animation does not replay while its
                    // class is already there, so back-to-back raises flashed only the first.
                    bidEl.classList.remove('bid-updated');
                    // The template's own tilt, carried into the bounce. Read with the class
                    // off, so a previous flash's frame cannot be mistaken for it.
                    carryRotation(bidEl, '--bid-rot');
                    void bidEl.offsetWidth;
                    bidEl.classList.add('bid-updated');
                    /* Cleared after the animation has finished, not during it: two pulses of
                       1.05s run 2.1s, and the old 1.5s cut the second one off half way. */
                    if (window._bidColorTimeout) clearTimeout(window._bidColorTimeout);
                    window._bidColorTimeout = setTimeout(() => {
                        bidEl.classList.remove('bid-updated');
                    }, 2200);
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
                clearGavel();
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

                /*
                 * Re-armed only when the RESULT changes, not on every poll.
                 *
                 * The banner now fades itself out after a few seconds, and a CSS animation does
                 * not replay while its class is still on the element — so without a key the second
                 * sale of the evening would show a banner that was already finished, and with a
                 * naive re-arm every two-second poll would restart the fade and the banner would
                 * never leave.
                 */
                const key = `${p.id}:${p.status}`;

                if (banner.dataset.resultKey !== key) {
                    banner.dataset.resultKey = key;
                    banner.classList.remove('hidden');
                    banner.style.animation = 'none';
                    void banner.offsetWidth;
                    banner.style.animation = '';
                } else {
                    banner.classList.remove('hidden');
                }
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

                /*
                 * The gavel comes down on the tag, and the poppers go off when it lands. One
                 * gesture; nothing else on the card moves.
                 */
                strikeGavel();

                // Once per sold player: a re-render must not set the poppers off again.
                if (_confettiFiredForPlayer !== p.id) {
                    _confettiFiredForPlayer = p.id;
                    setTimeout(fireConfetti, GAVEL_IMPACT_MS);
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

                /* The hammer falls on an unsold lot too — it is what ends a lot, and this one is
                   just as ended. No poppers: nothing was bought, and streamers over a player
                   nobody wanted reads as mockery rather than drama. */
                strikeGavel('unsold-badge');
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
            /*
             * Off the moment the clock stops.
             *
             * The wall was left tinted after the timer expired — the call had ended, nothing was
             * counting, and the screen sat dimmed over a live card until the next lot. A closing
             * call that is no longer closing anything must not still be dimming the room.
             */
            const dim = document.getElementById('final-call-dim');
            const clockRunning = Number(timerRemaining) > 0;

            if (dim) dim.classList.toggle('is-on', !!inClosingCall && clockHasPlayer && clockRunning);
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

                    // The board, if the organizer has it up. Read from the feed so a wall
                    // opened or reloaded mid-board comes back to the board.
                    // Re-seeded every poll, so the local tick cannot drift and a screen that
                    // joins mid-break picks the countdown up where the room is.
                    _breakRemaining = (data?.break_remaining ?? null);

                    /*
                     * A player on the block outranks any board.
                     *
                     * The auction is the thing the room is here for, and a reel left up over a
                     * live lot hides the bidding from everyone watching. The organizer does not
                     * have to remember to take it down: putting somebody up IS taking it down.
                     * The stored flag is untouched, so ending the lot brings the board back
                     * without another press.
                     */
                    const somebodyUp = data?.auctionPlayer?.status === 'on_auction';

                    applySoldBoard(somebodyUp ? null : data?.public_board);

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
                                    /*
                                     * Wipe the PREVIOUS player's result first.
                                     *
                                     * The sold badge, the winning team's logo, the sold glow and
                                     * the result banner live outside the card, so drawing a new
                                     * card does not clear them. The shuffle used to do this on
                                     * its way past; with the shuffle gone it has to be done here,
                                     * or the hall sees the last player's SOLD across the next
                                     * player's face.
                                     */
                                    clearOutcomeState();
                                    updatePlayerCard(ap);
                                    markCardChanged();
                                    announceNewPlayer();
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

                        /*
                         * ── The end of a pool, announced ──
                         *
                         * With nobody on the block the wall holds the last result on screen —
                         * deliberately, so the hall keeps seeing who won the player just sold
                         * instead of a blank waiting screen. But it held it for as long as the
                         * gap lasted, which meant a pool ending, and an auction being closed,
                         * were never announced on the wall at all: the room sat looking at a
                         * card of a player sold ten minutes earlier.
                         *
                         * So the hold is now a hold, not a state: once the result has had its
                         * moment, a stage the room needs told about takes the screen. An
                         * ordinary gap between two players is not one of those — that still
                         * keeps the card up, which is the behaviour this had.
                         *
                         * Both timestamps come from the server. The app runs on Asia/Dubai and
                         * the database on UTC, so a browser clock cannot be part of this sum.
                         */
                        const ANNOUNCE_STAGES = ['pool_complete', 'all_done', 'completed', 'not_started', 'paused', 'no_pool'];
                        const RESULT_HOLD_S = 10;
                        const stageKey = data?.stage?.key;
                        const heldFor = (lap && data?.server_time && lap.updated_at)
                            ? (Number(data.server_time) - Number(lap.updated_at))
                            : Infinity;
                        const announce = ANNOUNCE_STAGES.includes(stageKey) && heldFor >= RESULT_HOLD_S;

                        /*
                         * Nothing else will wake the wall.
                         *
                         * It stops polling while push is healthy and refetches on events, and
                         * the end of a hold is not an event — so without this one-shot the
                         * announcement would wait for whatever the organizer did next, which
                         * during a break between pools is nothing at all.
                         */
                        if (! announce && ANNOUNCE_STAGES.includes(stageKey) && heldFor !== Infinity && ! _stageHoldTimer) {
                            _stageHoldTimer = setTimeout(() => {
                                _stageHoldTimer = null;
                                refreshNow('stage-hold');
                            }, Math.max(500, (RESULT_HOLD_S - heldFor) * 1000 + 250));
                        }

                        if (announce && stageKey === 'completed') {
                            // A closed auction has its own screen, and it is the one the hall
                            // should end on — not the waiting screen wearing a different caption.
                            clearOutcomeState();
                            showCompleted();
                            return;
                        }

                        if (announce) {
                            showWaiting();
                        } else if (lap && lap.id !== lastActionPlayerId) {
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
            /*
             * The sold board going up or coming down. Applied straight away rather than waiting
             * for the next tick — on a wall the whole room is looking at, two seconds of nothing
             * after the button is pressed reads as a fault.
             */
            .listen('.board.changed', (event) => {
                console.info('[Live] public board:', event?.board, 'for', event?.target);

                /* Only when this screen is one of the targets. The event used to carry the board
                   alone, so a reel meant for the ticker appeared here too and then vanished on the
                   next feed read — the feed has always respected the target. */
                const target = event?.target ?? 'both';
                if (target !== 'both' && target !== 'wall') return;

                /*
                 * The artwork switches change what the FEED sends, not what this screen decides —
                 * so a change to them needs a refetch, and pressing Apply with only a checkbox
                 * touched leaves the board name identical. Without this the sponsors stayed on the
                 * wall until something unrelated happened to refetch.
                 */
                const artwork = `${event?.adSlides ? 1 : 0}${event?.adSponsors ? 1 : 0}`;

                if (_lastArtwork !== null && artwork !== _lastArtwork) {
                    _lastArtwork = artwork;
                    refreshSponsors();
                    if (soldBoardShowing) fetchSoldBoard(soldBoardShowing);
                } else {
                    _lastArtwork = artwork;
                }

                applySoldBoard(event?.board);

                /*
                 * And re-read the feed, always.
                 *
                 * The dialog changes more than the board and the artwork — the BREAK LENGTH is
                 * on it too, and that lives on the feed as `break_remaining`. Applying a new
                 * break with the same board showing changed nothing here until something else
                 * happened to refetch. One request per press of Apply is not a cost worth
                 * reasoning about; a wall showing a stale clock in front of a hall is.
                 */
                refreshNow('board');
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
                    /*
                     * A beat before the card, so the wall says something is coming.
                     *
                     * The push arrives with the whole player on it, so the card could be painted
                     * on the same frame — and a hall would see the previous result blink straight
                     * into the next face with nothing in between. The loader carries the event's
                     * mark and NO player data: the room finding out who is next before the reveal
                     * is the one thing this must not do.
                     */
                    showNextLoader();

                    // Straight to the card — see the poll path above, including why the
                    // previous player's result has to be cleared here.
                    lastOnAuctionPlayerId = ap.id;
                    lastPlayerId = ap.id;
                    clearOutcomeState();
                    updatePlayerCard(ap);
                    markCardChanged();
                                    announceNewPlayer();
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
                    + '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><g transform="rotate(45 10 10)"><path d="M17.5 13.3v-1.6l-6.6-4.2V3.1c0-.7-.6-1.2-1.2-1.2S8.4 2.4 8.4 3.1v4.4L1.8 11.7v1.6l6.6-2v4.4l-1.7 1.2v1.2l2.9-.8 2.9.8v-1.2l-1.7-1.2v-4.4l6.7 2z"/></g></svg>'
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

        /**
         * The club the player currently turns out for.
         *
         * From the model's own `playing_team_label`, which is what the poster, the pools list
         * and the players list all read — the wall had no element for this at all, so a card
         * could not show where a player comes from however the template was drawn.
         */
        /**
         * The club they come from — until they are bought, when it becomes who bought them.
         *
         * @param player      the Player, for the club they currently turn out for
         * @param auctionPlayer  the lot, for the result
         *
         * One element, two facts, because they answer the same question at different moments: who
         * does this player belong to? Before the hammer that is their current club; after it, it is
         * the team that just paid for them, and the old club stops being the interesting half.
         *
         * The buyer's name is only ever read from a payload that HAS one — during a lot draw the
         * server withholds it, so this cannot leak a winner the room has not been shown.
         */
        function renderPlayingTeam(player, auctionPlayer = null) {
            const el = document.getElementById('playing-team');
            if (! el) return;

            const valueEl = document.getElementById('playing-team-value');
            const buyer = auctionPlayer?.status === 'sold'
                ? (auctionPlayer.sold_to_team?.name || auctionPlayer.current_bid_team?.name || '')
                : '';

            const label = buyer ? `Sold to ${buyer}` : (player?.playing_team_label || '');

            if (valueEl) valueEl.textContent = label;

            // Marked, so a template can colour the two states differently if it wants to.
            el.classList.toggle('is-sold-to', !! buyer);
            el.style.visibility = label ? 'visible' : 'hidden';
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
