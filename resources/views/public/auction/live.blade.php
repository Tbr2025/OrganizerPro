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
            if (isset($p['top'])) $css .= 'top:'.$p['top'].'px;';
            if (isset($p['bottom']) && !isset($p['top'])) $css .= 'bottom:'.$p['bottom'].'px;';
            if (isset($p['left'])) $css .= 'left:'.$p['left'].'px;';
            if (isset($p['width'])) $css .= 'width:'.$p['width'].'px;';
            if (isset($p['height'])) $css .= 'height:'.$p['height'].'px;';
            if (isset($p['fontSize'])) $css .= 'font-size:'.$p['fontSize'].'px;';
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
            @if($backgroundUrl)
            background: url('{{ $backgroundUrl }}') no-repeat center center;
            background-size: auto;
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
            animation: badge-spin-in 0.8s ease-out forwards;
        }
        @keyframes badge-spin-in {
            0% { transform: scale(0) rotate(-180deg); opacity: 0; }
            60% { transform: scale(1.2) rotate(10deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
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
            animation: team-logo-entrance 0.8s ease-out 0.2s forwards;
            opacity: 0;
        }
        @keyframes team-logo-entrance {
            0% { transform: scale(0) rotate(-180deg); opacity: 0; }
            60% { transform: scale(1.15) rotate(5deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
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

        /* "Coming up" name swap. Each new name is re-triggered by removing and re-adding
           the class, so the cycle reads as a deliberate change rather than a flicker. */
        @keyframes upNextSwap {
            0%   { opacity: 0; transform: translateY(10px); filter: blur(4px); }
            100% { opacity: 1; transform: translateY(0); filter: blur(0); }
        }
        .waiting-upnext-name.swapping { animation: upNextSwap 0.45s ease-out; }

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
                'headerBg'=>'', 'rowBg'=>'',
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
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.62em;
            padding-bottom: 2px;
            opacity: 0.8;
        }
        /* The figure carries the emphasis, on its own translucent tile. */
        #stats-table-wrap td {
            font-weight: 800;
            font-size: 1.25em;
            line-height: 1.1;
            background: {{ $st['rowBg'] ?? 'rgba(255,255,255,0.07)' }};
            border-radius: 12px;
            backdrop-filter: blur(3px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.10);
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
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 22px;
            border-radius: 9999px;
            background: rgba(2, 6, 23, 0.55);
            border: 1px solid rgba(34, 197, 94, 0.45);
            box-shadow: 0 0 28px rgba(34, 197, 94, 0.25);
            font-weight: 800;
            letter-spacing: 0.04em;
            white-space: nowrap;
            {!! elementStyle($positions, 'highest_bidder', ['top'=>470,'left'=>570,'fontSize'=>28,'color'=>'#22c55e'], $boxShadowMap, $textShadowMap) !!}
        }

        /* A small kicker, so the name is not mistaken for the player's own. */
        #highest-bidder::before {
            content: 'HIGHEST BID';
            font-size: 0.42em;
            font-weight: 900;
            letter-spacing: 0.24em;
            color: rgba(226, 232, 240, 0.75);
        }

        /* ── Shuffle / Reveal Animation ── */
        #shuffle-screen {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0a0a0a 0%, #0f172a 50%, #0a0a0a 100%);
            z-index: 200;
        }

        .shuffle-ring-outer {
            width: 280px;
            height: 280px;
            border-radius: 50%;
            border: 5px solid transparent;
            border-top-color: var(--primary);
            border-right-color: var(--secondary);
            position: absolute;
            animation: shuffleSpin 0.6s linear infinite;
            box-shadow: 0 0 30px rgba(var(--primary-rgb), 0.3), 0 0 60px rgba(var(--primary-rgb), 0.1);
        }

        .shuffle-ring-inner {
            width: 250px;
            height: 250px;
            border-radius: 50%;
            border: 3px solid transparent;
            border-bottom-color: var(--primary);
            border-left-color: var(--secondary);
            position: absolute;
            animation: shuffleSpin 0.4s linear infinite reverse;
        }

        .shuffle-center {
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: #1e293b;
            border: 3px solid #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }

        .shuffle-center.revealed {
            border-color: var(--secondary);
            animation: shuffleRevealPop 0.5s ease-out forwards;
            box-shadow: 0 0 40px rgba(var(--secondary-rgb), 0.4);
        }

        @keyframes shuffleSpin {
            to { transform: rotate(360deg); }
        }

        @keyframes shuffleGlowPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(var(--primary-rgb), 0.3), 0 0 60px rgba(var(--primary-rgb), 0.1); }
            50% { box-shadow: 0 0 40px rgba(var(--primary-rgb), 0.6), 0 0 80px rgba(var(--primary-rgb), 0.2); }
        }

        @keyframes shuffleRevealPop {
            0% { transform: scale(0.5); opacity: 0; }
            60% { transform: scale(1.08); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        .shuffle-name {
            font-size: 28px;
            font-weight: 700;
            color: #cbd5e1;
            text-align: center;
            padding: 0 16px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .shuffle-status-text {
            margin-top: 40px;
            font-size: 28px;
            color: var(--primary);
            font-weight: 600;
            letter-spacing: 4px;
            text-transform: uppercase;
            animation: shuffleGlowPulse 0.8s ease-in-out infinite;
        }

        .shuffle-reveal-name {
            margin-top: 30px;
            font-size: 40px;
            font-weight: 900;
            color: #fff;
            animation: shuffleRevealPop 0.5s ease-out forwards;
        }

        .shuffle-reveal-role {
            margin-top: 8px;
            font-size: 24px;
            color: #94a3b8;
            animation: shuffleRevealPop 0.5s ease-out 0.1s forwards;
            opacity: 0;
        }
    </style>
</head>

<body class="text-white">

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

        {{-- "Coming up" teaser. Cycles the names still in the queue, which the feed already
             sends for the shuffle animation, so a stalled screen still reads as live. --}}
        <div id="waiting-upnext" class="hidden"
             style="position:relative;z-index:1;margin-top:22px;display:flex;align-items:center;gap:14px;
                    padding:12px 26px;border-radius:999px;
                    background:rgba(2,6,23,0.55);backdrop-filter:blur(6px);
                    border:1px solid rgba(var(--primary-rgb),0.25);">
            <span style="font-size:15px;letter-spacing:0.22em;text-transform:uppercase;
                         color:rgba(var(--primary-rgb),0.9);">Coming up</span>
            <span id="waiting-upnext-name" class="waiting-upnext-name"
                  style="font-size:26px;font-weight:700;color:#fff;"></span>
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

    <!-- Shuffle / Random Selection Animation Screen -->
    <div id="shuffle-screen" class="hidden">
        <div style="position:relative;width:280px;height:280px;display:flex;align-items:center;justify-content:center;">
            <div class="shuffle-ring-outer" id="shuffle-ring-outer"></div>
            <div class="shuffle-ring-inner" id="shuffle-ring-inner"></div>
            <div class="shuffle-center" id="shuffle-center">
                <span class="shuffle-name" id="shuffle-name">—</span>
            </div>
        </div>
        <div class="shuffle-status-text" id="shuffle-status">Selecting Player...</div>
        <div class="shuffle-reveal-name hidden" id="shuffle-reveal-name"></div>
        <div class="shuffle-reveal-role hidden" id="shuffle-reveal-role"></div>
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

        {{-- A depleting ring reads as "running out" at a glance from the back of a hall,
             which a plain number does not. --}}
        <div style="position:relative;width:96px;height:96px;flex-shrink:0;">
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
        <img id="player-image" src="https://via.placeholder.com/300" alt="Player">
        @endif

        <!-- Player Name -->
        @if(isVisible($positions, 'player_name'))
        <h1 id="player-name" class="text-4xl font-bold">Player Name</h1>
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
        <p id="player-batting">Right-Hand Bat</p>
        @endif
        @if(isVisible($positions, 'bowling_style'))
        <p id="player-bowling">Right-Arm Medium</p>
        @endif

        <!-- Current Bid -->
        @if(isVisible($positions, 'current_bid'))
        <div id="current-bid" class="text-3xl font-extrabold" style="color: #fff;">1,00,000</div>
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
        <div id="sealed-banner" class="hidden"
             style="position:absolute;top:0;left:0;right:0;z-index:30;padding:14px 0;text-align:center;
                    background:linear-gradient(90deg,rgba(88,28,135,0.95),rgba(147,51,234,0.95));
                    border-bottom:3px solid #c084fc;">
            <div style="font-size:13px;font-weight:900;letter-spacing:6px;text-transform:uppercase;color:#e9d5ff;">
                <span id="sealed-banner-title">Closed Bid</span>
            </div>
            <div style="font-size:26px;font-weight:900;color:#fff;line-height:1.1;margin-top:2px;">
                <span id="sealed-banner-line"></span>
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

    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.11.3/echo.iife.js"></script>
    <script>
        const auctionId = {{ $auction->id }};
        // JSON-encoded rather than interpolated into quotes: an auction named O'Brien's Cup
        // would otherwise close the string and break every script on the page.
        const AUCTION_NAME = @json($auction->name);
        let currentStatus = 'waiting';
        let lastPlayerId = null;
        let lastOnAuctionPlayerId = null;
        let lastActionPlayerId = null;
        let isShuffling = false;
        // The most recent payload that had a player on the block — the recovery path's input.
        let lastGoodPlayer = null;
        let hasCompletedFirstLoad = false;
        let _confettiFiredForPlayer = null;

        function fireConfetti() {
            if (typeof confetti !== 'function') return;
            confetti({ particleCount: 80, spread: 70, origin: { x: 0.1, y: 0.6 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#ffffff'] });
            setTimeout(() => {
                confetti({ particleCount: 80, spread: 70, origin: { x: 0.9, y: 0.6 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#ffffff'] });
            }, 200);
            setTimeout(() => {
                confetti({ particleCount: 120, spread: 100, origin: { x: 0.5, y: 0.3 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#f59e0b', '#ffffff'] });
            }, 400);
        }

        // ── Shuffle Animation Controller ──
        const shuffleController = {
            namePool: ['Player 1', 'Player 2', 'Player 3', 'Player 4', 'Player 5'],
            interval: null,

            watchdog: null,

            /**
             * Give up and just show the card.
             *
             * `isShuffling` gates the whole poll loop, and it used to be cleared only by the
             * very last line of reveal()'s setTimeout — behind a dozen unguarded
             * getElementById dereferences. One throw anywhere in there left it stuck true
             * for the rest of the session: every later poll returned early, so the card never
             * appeared and the wall sat on the waiting screen while the clock (updated
             * deliberately BEFORE that guard) kept counting down over it.
             *
             * Every exit from the animation now goes through here, so the flag cannot stay
             * set. Losing the animation is a cosmetic disappointment; losing the player card
             * for the rest of the auction is not.
             */
            finish(playerData) {
                if (this.interval) { clearInterval(this.interval); this.interval = null; }
                if (this.watchdog) { clearTimeout(this.watchdog); this.watchdog = null; }

                const screen = document.getElementById('shuffle-screen');
                if (screen) {
                    screen.classList.add('hidden');
                    screen.style.display = 'none';
                }

                isShuffling = false;

                if (playerData) updatePlayerCard(playerData);
            },

            start(playerData, namePool) {
                if (namePool && namePool.length > 1) {
                    this.namePool = namePool;
                }

                // A second start while one is already running would orphan the first
                // interval and leave two writing to the same node.
                if (this.interval) { clearInterval(this.interval); this.interval = null; }
                if (this.watchdog) { clearTimeout(this.watchdog); this.watchdog = null; }

                isShuffling = true;

                /* Backstop, independent of anything inside the animation: 30 ticks x 80ms
                   plus the 1.8s reveal hold is ~4.2s, so 10s means something went wrong. */
                this.watchdog = setTimeout(() => {
                    console.warn('[Live] shuffle watchdog fired — showing the card directly');
                    this.finish(playerData);
                }, 10000);

                try {
                    const screen = document.getElementById('shuffle-screen');
                    const nameEl = document.getElementById('shuffle-name');
                    const statusEl = document.getElementById('shuffle-status');
                    const revealName = document.getElementById('shuffle-reveal-name');
                    const revealRole = document.getElementById('shuffle-reveal-role');
                    const center = document.getElementById('shuffle-center');
                    const ringOuter = document.getElementById('shuffle-ring-outer');
                    const ringInner = document.getElementById('shuffle-ring-inner');

                    // reveal() rebuilds #shuffle-center's children, so any of these can be
                    // missing if a previous run was interrupted part-way.
                    if (!screen || !nameEl || !statusEl || !revealName || !revealRole || !center) {
                        this.finish(playerData);
                        return;
                    }

                    center.classList.remove('revealed');
                    nameEl.classList.remove('hidden');
                    revealName.classList.add('hidden');
                    revealRole.classList.add('hidden');
                    statusEl.classList.remove('hidden');
                    if (ringOuter) ringOuter.style.display = '';
                    if (ringInner) ringInner.style.display = '';
                    statusEl.textContent = 'Selecting Player...';

                    document.getElementById('waiting-screen')?.classList.add('hidden');
                    document.getElementById('card-container')?.classList.add('hidden');
                    // The teaser cycles on a timer of its own; left running it would keep
                    // animating behind the card once this finishes.
                    stopUpNext();
                    screen.classList.remove('hidden');
                    screen.style.display = 'flex';

                    let tick = 0;
                    const totalTicks = 30;
                    this.interval = setInterval(() => {
                        tick++;
                        // Re-queried each tick: the node is replaced by reveal(), so a
                        // reference captured up front can be detached.
                        const el = document.getElementById('shuffle-name');
                        if (el) {
                            const idx = Math.floor(Math.random() * this.namePool.length);
                            el.textContent = this.namePool[idx];
                        }

                        if (tick >= totalTicks) {
                            clearInterval(this.interval);
                            this.interval = null;
                            this.reveal(playerData);
                        }
                    }, 80);
                } catch (e) {
                    console.error('[Live] shuffle failed to start:', e);
                    this.finish(playerData);
                }
            },

            reveal(playerData) {
                try {
                    const nameEl = document.getElementById('shuffle-name');
                    const statusEl = document.getElementById('shuffle-status');
                    const revealName = document.getElementById('shuffle-reveal-name');
                    const revealRole = document.getElementById('shuffle-reveal-role');
                    const center = document.getElementById('shuffle-center');
                    const ringOuter = document.getElementById('shuffle-ring-outer');
                    const ringInner = document.getElementById('shuffle-ring-inner');

                    if (!center || !revealName || !revealRole) {
                        this.finish(playerData);
                        return;
                    }

                    const pName = playerData.player?.name || 'Unknown';
                    const playerType = playerData.player?.player_type || playerData.player?.playerType;
                    const pRole = typeof playerType === 'object' ? (playerType?.type || playerType?.name || '') : (playerType || '');

                    if (ringOuter) ringOuter.style.display = 'none';
                    if (ringInner) ringInner.style.display = 'none';
                    if (statusEl) statusEl.classList.add('hidden');

                    if (playerData.player?.image_path) {
                        if (nameEl) nameEl.classList.add('hidden');
                        const img = document.createElement('img');
                        img.src = '/storage/' + playerData.player.image_path;
                        img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
                        center.innerHTML = '';
                        center.appendChild(img);
                    } else if (nameEl) {
                        nameEl.textContent = pName;
                    }

                    center.classList.add('revealed');

                    revealName.textContent = pName;
                    revealName.classList.remove('hidden');
                    revealRole.textContent = pRole;
                    revealRole.classList.remove('hidden');

                    setTimeout(() => {
                        try {
                            // Put the name node back — the image branch above removed it, and
                            // the next run looks it up by id.
                            center.innerHTML = '<span class="shuffle-name" id="shuffle-name">—</span>';
                            center.classList.remove('revealed');
                        } catch (e) {
                            console.error('[Live] shuffle teardown failed:', e);
                        }
                        // Always, even if the teardown above threw.
                        this.finish(playerData);
                    }, 1800);
                } catch (e) {
                    console.error('[Live] shuffle reveal failed:', e);
                    this.finish(playerData);
                }
            }
        };

        // Initialize Echo
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env('PUSHER_APP_KEY') }}',
            cluster: '{{ env('PUSHER_APP_CLUSTER') }}',
            forceTLS: true
        });

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

        function showWaiting() {
            console.log('[Live] showWaiting()');
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
        let upNextIndex = 0;
        let upNextTimer = null;

        function stopUpNext() {
            if (upNextTimer) { clearInterval(upNextTimer); upNextTimer = null; }
        }

        function renderUpNext(names) {
            const wrap = document.getElementById('waiting-upnext');
            const label = document.getElementById('waiting-upnext-name');
            if (!wrap || !label) return;

            if (!Array.isArray(names) || names.length === 0) {
                wrap.classList.add('hidden');
                stopUpNext();
                return;
            }

            wrap.classList.remove('hidden');

            const show = () => {
                if (upNextIndex >= names.length) upNextIndex = 0;
                label.textContent = names[upNextIndex] || '';
                upNextIndex = (upNextIndex + 1) % names.length;
                // Re-trigger the keyframe: removing the class and forcing a reflow is the
                // only reliable way to replay a CSS animation on the same element.
                label.classList.remove('swapping');
                void label.offsetWidth;
                label.classList.add('swapping');
            };

            if (!upNextTimer) {
                show();
                upNextTimer = setInterval(show, 2600);
            }
        }

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

            /* Only tease names while nobody is on the block. Left running it kept cycling
               over a live player card, because the cycle has its own interval and was only
               stopped from showCard(). */
            const somebodyUp = data?.auctionPlayer?.status === 'on_auction';
            renderUpNext(started && waiting > 0 && ! somebodyUp ? (data?.waitingPlayers || []) : []);
        }

        function showCard() {
            console.log('[Live] showCard()');
            document.getElementById('waiting-screen').classList.add('hidden');
            document.getElementById('card-container').classList.remove('hidden');
            // Nothing is watching the teaser behind the card; leaving its interval running
            // would keep re-animating a hidden node for the rest of the session.
            stopUpNext();
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
                line.textContent = sealedState.total_rounds > 1 && sealedState.round_number
                    ? `${text} — round ${sealedState.round_number} of ${sealedState.total_rounds}`
                    : text;
            }

            banner.classList.remove('hidden');
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
            document.getElementById('player-name').textContent = p.player.name;

            const playerType = p.player.player_type || p.player.playerType;
            document.getElementById('player-role').textContent = typeof playerType === 'object'
                ? playerType?.type || playerType?.name || ''
                : playerType || '';

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
                return typeof value === 'object' ? (value.style || value.name || '') : String(value);
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

            // Show current bid price if available, otherwise base price
            const price = p.current_price || p.base_price || 0;
            const bidEl = document.getElementById('current-bid');
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
                nameEl.textContent = p.player?.name || '';
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

        // Fetch list of waiting player names for the shuffle pool
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
                lastCallStage = 0;
                const colour = seconds <= 10 ? '#fbbf24' : '#22d3ee';
                ring.setAttribute('stroke', colour);
                secondsEl.style.color = '#fff';
                caption.textContent = clockPaused ? 'Paused' : 'Time Remaining';
                caption.style.color = clockPaused ? '#fbbf24' : '#94a3b8';
                callEl.textContent = '';
                callEl.style.display = 'none';
                hud.style.borderColor = 'rgba(148,163,184,0.25)';
                hud.style.boxShadow = '0 24px 70px rgba(0,0,0,0.6)';
                hud.style.animation = 'none';
            }

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

        let shuffleNamePool = [];
        function fetchShuffleNamePool() {
            fetch(`/auction/${auctionId}/active-player`)
                .then(res => res.json())
                .then(data => {
                    if (data?.waitingPlayers && data.waitingPlayers.length > 0) {
                        shuffleNamePool = data.waitingPlayers;
                    }
                }).catch(() => {});
        }
        fetchShuffleNamePool();

        function fetchActivePlayer() {
            console.log('[Live] fetchActivePlayer() called');
            fetch(`/auction/${auctionId}/active-player`)
                .then(res => res.json())
                .then(data => {
                    console.log('[Live] API response:', data);

                    // Before the card renders: the sealed banner also sets the flag
                    // updatePlayerCard() reads to suppress the public bid figure.
                    renderSealedBanner(data.closed_bid || null);

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

                    // A restart owns the screen for its window. Returning here also keeps
                    // the shuffle from firing on the next player until the notice ends,
                    // so the two announcements cannot overlap.
                    if (renderRestartNotice(data)) return;

                    // Real-time PAUSED overlay (reflects organizer pause/resume within ~2s).
                    const pausedOverlay = document.getElementById('paused-overlay');
                    if (pausedOverlay) {
                        pausedOverlay.classList.toggle('hidden', data?.auction_status !== 'paused');
                    }

                    // ── Clock first, and unconditionally ──
                    // The countdown and closing calls must keep running even while the
                    // shuffle animation is playing, so they are updated before the
                    // isShuffling guard below rather than after it.
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
                        shuffleNamePool = data.waitingPlayers;
                    }

                    // The player card is mid-animation; leave it alone until the reveal
                    // finishes. (The clock above has already been updated.)
                    if (isShuffling) return;

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
                                    console.log('[Live] New player detected, triggering shuffle');
                                    const pool = shuffleNamePool.length > 1 ? shuffleNamePool : [ap.player?.name || 'Player'];
                                    lastOnAuctionPlayerId = ap.id;
                                    lastPlayerId = ap.id;
                                    shuffleController.start(ap, pool);
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
                        if (lastGoodPlayer && !isShuffling) {
                            console.warn('[Live] recovering the card after a handler error');
                            updatePlayerCard(lastGoodPlayer);
                        }
                    } catch (e) {
                        console.error('[Live] recovery failed too:', e);
                    }
                });
        }

        // Listen to public channel for real-time events
        window.Echo.channel(`auction.${auctionId}`)
            .listen('.player-on-sold', (event) => {
                console.log('[Live] Player sold event:', event);
                const auctionPlayer = event.auctionPlayer;
                if (event.winningTeam) {
                    auctionPlayer.sold_to_team = event.winningTeam;
                }
                if (_confettiFiredForPlayer !== auctionPlayer.id) {
                    _confettiFiredForPlayer = auctionPlayer.id;
                    fireConfetti();
                }
                updatePlayerCard(auctionPlayer);
            })
            .listen('.player.onbid', (event) => {
                console.log('[Live] Player on-bid event (instant):', event);
                hasCompletedFirstLoad = true;

                const ap = event.auctionPlayer;
                if (!ap) return;

                if (ap.id !== lastOnAuctionPlayerId) {
                    console.log('[Live] Instant shuffle triggered via event');
                    isShuffling = true;
                    lastOnAuctionPlayerId = ap.id;
                    lastPlayerId = ap.id;
                    const pool = shuffleNamePool.length > 1 ? shuffleNamePool : [ap.player?.name || 'Player'];
                    shuffleController.start(ap, pool);
                }
            });

        // Poll for updates every 2 seconds (for bid updates)
        setInterval(fetchActivePlayer, 2000);

        // Initial fetch
        fetchActivePlayer();

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
        // Entering or leaving fullscreen changes the viewport without firing resize on
        // every browser, and the wall is run fullscreen more often than not.
        document.addEventListener('fullscreenchange', () => setTimeout(scaleLive, 60));
        // Scale on initial load and whenever card becomes visible
        const origShowCard = showCard;
        showCard = function() {
            origShowCard();
            setTimeout(scaleLive, 50);
        };
        scaleLive();
    </script>

</body>

</html>
