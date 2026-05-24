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

        // Helper: check if element is visible
        function isVisible($positions, $key) {
            return ($positions[$key]['visible'] ?? true) !== false && ($positions[$key]['visible'] ?? 1) != 0;
        }
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
        .sold-stamp {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            font-weight: 900;
            color: #22c55e;
            border: 5px solid #22c55e;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 6px;
            text-shadow: 0 0 20px rgba(34,197,94,0.6), 0 2px 4px rgba(0,0,0,0.5);
            box-shadow: 0 0 30px rgba(34,197,94,0.3), inset 0 0 20px rgba(34,197,94,0.1);
            background: rgba(0,0,0,0.6);
        }

        /* HTML fallback unsold stamp */
        .unsold-stamp {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            font-weight: 900;
            color: #ef4444;
            border: 5px solid #ef4444;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 6px;
            text-shadow: 0 0 20px rgba(239,68,68,0.6), 0 2px 4px rgba(0,0,0,0.5);
            box-shadow: 0 0 30px rgba(239,68,68,0.3), inset 0 0 20px rgba(239,68,68,0.1);
            background: rgba(0,0,0,0.6);
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
        .led-loader-ring {
            position: absolute;
            width: 120px; height: 120px;
            border-radius: 50%;
            border: 2px solid transparent;
            border-top-color: rgba(var(--primary-rgb), 0.4);
            border-right-color: rgba(var(--secondary-rgb), 0.3);
            animation: spin 3s linear infinite;
        }

        /* Loading spinner for LED wall */
        .led-loader {
            width: 80px;
            height: 80px;
            border: 6px solid rgba(var(--primary-rgb), 0.2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }

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
            object-fit: cover;
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
            $st = array_merge(['top'=>480,'left'=>550,'width'=>500,'height'=>150,'fontSize'=>20,'zIndex'=>10],
                $positions['stats_table'] ?? []);
        @endphp
        #stats-table-wrap {
            position: absolute;
            {!! elementStyle($positions, 'stats_table', ['top'=>480,'left'=>550,'width'=>500,'height'=>150,'fontSize'=>20], $boxShadowMap, $textShadowMap) !!}
        }
        #stats-table-wrap table {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ $st['fontSize'] ?? 20 }}px;
        }
        #stats-table-wrap thead tr {
            background: {{ $st['headerBg'] ?? 'rgba(0,0,0,0.7)' }};
            color: {{ $st['headerColor'] ?? '#ffffff' }};
        }
        #stats-table-wrap tbody tr {
            background: {{ $st['rowBg'] ?? 'rgba(255,255,255,0.1)' }};
            color: {{ $st['cellColor'] ?? '#ffffff' }};
        }
        #stats-table-wrap th,
        #stats-table-wrap td {
            padding: {{ $st['cellPadding'] ?? 10 }}px;
            border: {{ $st['tableBorderWidth'] ?? 1 }}px solid {{ $st['tableBorderColor'] ?? 'rgba(255,255,255,0.2)' }};
            text-align: center;
        }
        #stats-table-wrap th {
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
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

        /* Highest bidder display */
        #highest-bidder {
            position: absolute;
            {!! elementStyle($positions, 'highest_bidder', ['top'=>470,'left'=>570,'fontSize'=>28,'color'=>'#00ff00'], $boxShadowMap, $textShadowMap) !!}
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

        @if($auction->auction_logo_url || ($auction->tournament && $auction->tournament->logo_url))
        <div style="display:flex;align-items:center;gap:30px;margin-bottom:40px;position:relative;z-index:1;">
            @if($auction->auction_logo_url)
                <img src="{{ $auction->auction_logo_url }}" alt="Auction Logo" style="height:100px;object-fit:contain;">
            @endif
            @if($auction->tournament && $auction->tournament->logo_url)
                <img src="{{ $auction->tournament->logo_url }}" alt="Tournament Logo" style="height:100px;object-fit:contain;">
            @endif
        </div>
        @endif
        <div style="position:relative;display:flex;align-items:center;justify-content:center;margin-bottom:40px;">
            <div class="led-loader-ring"></div>
            <div class="led-loader" style="margin-bottom:0;"></div>
        </div>
        <h1 style="position:relative;z-index:1;">WAITING FOR AUCTION</h1>
        <p class="text-3xl text-gray-400 mt-4" style="position:relative;z-index:1;">{{ $auction->name }}</p>
        <div class="glow-dots" style="position:relative;z-index:1;">
            <div class="glow-dot"></div>
            <div class="glow-dot"></div>
            <div class="glow-dot"></div>
        </div>
    </div>

    <!-- Auction Completed Screen -->
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
                <div class="sold-stamp">SOLD</div>
            @endif
        </div>
        @endif

        <!-- Unsold Badge (hidden by default, shown when unsold) -->
        @if(isVisible($positions, 'sold_badge'))
        <div id="unsold-badge" class="absolute hidden" style="{!! elementStyle($positions, 'sold_badge', ['bottom'=>27,'left'=>112,'width'=>150,'height'=>150,'zIndex'=>9], $boxShadowMap, $textShadowMap) !!}display:none;align-items:center;justify-content:center;">
            @if($unsoldBadgeUrl)
                <img src="{{ $unsoldBadgeUrl }}" alt="Unsold Badge" class="w-full h-full object-contain">
            @else
                <div class="unsold-stamp">UNSOLD</div>
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
        let currentStatus = 'waiting';
        let lastPlayerId = null;
        let lastOnAuctionPlayerId = null;
        let lastActionPlayerId = null;
        let isShuffling = false;
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

            start(playerData, namePool) {
                if (namePool && namePool.length > 1) {
                    this.namePool = namePool;
                }
                isShuffling = true;

                const screen = document.getElementById('shuffle-screen');
                const nameEl = document.getElementById('shuffle-name');
                const statusEl = document.getElementById('shuffle-status');
                const revealName = document.getElementById('shuffle-reveal-name');
                const revealRole = document.getElementById('shuffle-reveal-role');
                const center = document.getElementById('shuffle-center');
                const ringOuter = document.getElementById('shuffle-ring-outer');
                const ringInner = document.getElementById('shuffle-ring-inner');

                center.classList.remove('revealed');
                nameEl.classList.remove('hidden');
                revealName.classList.add('hidden');
                revealRole.classList.add('hidden');
                statusEl.classList.remove('hidden');
                ringOuter.style.display = '';
                ringInner.style.display = '';
                statusEl.textContent = 'Selecting Player...';

                document.getElementById('waiting-screen').classList.add('hidden');
                document.getElementById('card-container').classList.add('hidden');
                screen.classList.remove('hidden');
                screen.style.display = 'flex';

                let tick = 0;
                const totalTicks = 30;
                this.interval = setInterval(() => {
                    tick++;
                    const idx = Math.floor(Math.random() * this.namePool.length);
                    nameEl.textContent = this.namePool[idx];

                    if (tick >= totalTicks) {
                        clearInterval(this.interval);
                        this.interval = null;
                        this.reveal(playerData);
                    }
                }, 80);
            },

            reveal(playerData) {
                const nameEl = document.getElementById('shuffle-name');
                const statusEl = document.getElementById('shuffle-status');
                const revealName = document.getElementById('shuffle-reveal-name');
                const revealRole = document.getElementById('shuffle-reveal-role');
                const center = document.getElementById('shuffle-center');
                const ringOuter = document.getElementById('shuffle-ring-outer');
                const ringInner = document.getElementById('shuffle-ring-inner');

                const pName = playerData.player?.name || 'Unknown';
                const playerType = playerData.player?.player_type || playerData.player?.playerType;
                const pRole = typeof playerType === 'object' ? (playerType?.type || playerType?.name || '') : (playerType || '');

                ringOuter.style.display = 'none';
                ringInner.style.display = 'none';
                statusEl.classList.add('hidden');

                if (playerData.player?.image_path) {
                    nameEl.classList.add('hidden');
                    const img = document.createElement('img');
                    img.src = '/storage/' + playerData.player.image_path;
                    img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
                    center.innerHTML = '';
                    center.appendChild(img);
                } else {
                    nameEl.textContent = pName;
                }

                center.classList.add('revealed');

                revealName.textContent = pName;
                revealName.classList.remove('hidden');
                revealRole.textContent = pRole;
                revealRole.classList.remove('hidden');

                setTimeout(() => {
                    const screen = document.getElementById('shuffle-screen');
                    screen.classList.add('hidden');
                    screen.style.display = 'none';

                    center.innerHTML = '<span class="shuffle-name" id="shuffle-name">—</span>';
                    center.classList.remove('revealed');

                    isShuffling = false;
                    updatePlayerCard(playerData);
                }, 1800);
            }
        };

        // Initialize Echo
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env('PUSHER_APP_KEY') }}',
            cluster: '{{ env('PUSHER_APP_CLUSTER') }}',
            forceTLS: true
        });

        function formatMillions(amount) {
            const n = Number(amount) || 0;
            if (n >= 10000000) {
                const val = n / 10000000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2).replace(/\.?0+$/, '')) + ' Cr';
            }
            if (n >= 100000) {
                const val = n / 100000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2).replace(/\.?0+$/, '')) + ' L';
            }
            if (n >= 1000) {
                const val = n / 1000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(1).replace(/\.?0+$/, '')) + 'K';
            }
            return n.toLocaleString();
        }

        function showWaiting() {
            console.log('[Live] showWaiting()');
            document.getElementById('waiting-screen').classList.remove('hidden');
            document.getElementById('card-container').classList.add('hidden');
            currentStatus = 'waiting';
        }

        function showCard() {
            console.log('[Live] showCard()');
            document.getElementById('waiting-screen').classList.add('hidden');
            document.getElementById('card-container').classList.remove('hidden');
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

            const battingStyle = p.player.batting_profile || p.player.battingProfile;
            document.getElementById('player-batting').textContent = typeof battingStyle === 'object'
                ? battingStyle?.style || battingStyle?.name || 'N/A'
                : battingStyle || 'N/A';

            const bowlingStyle = p.player.bowling_profile || p.player.bowlingProfile;
            document.getElementById('player-bowling').textContent = typeof bowlingStyle === 'object'
                ? bowlingStyle?.style || bowlingStyle?.name || 'N/A'
                : bowlingStyle || 'N/A';

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

            if (p.status === 'sold') {
                resetDramaticStates();
                if (soldText) soldText.textContent = 'SOLD';
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
                if (p.sold_to_team && (p.sold_to_team.logo_path || p.sold_to_team.team_logo)) {
                    if (teamLogo) {
                        teamLogo.src = p.sold_to_team.logo_path || `/storage/${p.sold_to_team.team_logo}`;
                        teamLogo.classList.remove('hidden');
                        teamLogo.classList.add('sold-entrance');
                    }
                } else {
                    if (teamLogo) teamLogo.classList.add('hidden');
                }
                if (highestBidder) highestBidder.classList.add('hidden');
            } else if (p.status === 'on_auction') {
                resetDramaticStates();
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
                if (soldText) {
                    soldText.textContent = 'UNSOLD';
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
                    soldText.textContent = 'SKIPPED';
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
            if (isShuffling) return;

            console.log('[Live] fetchActivePlayer() called');
            fetch(`/auction/${auctionId}/active-player`)
                .then(res => res.json())
                .then(data => {
                    console.log('[Live] API response:', data);
                    if (data?.auction_status === 'completed') {
                        showCompleted();
                        return;
                    }

                    if (data?.waitingPlayers && data.waitingPlayers.length > 0) {
                        shuffleNamePool = data.waitingPlayers;
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
                    console.error('[Live] Fetch error:', err);
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
        function scaleLive() {
            const container = document.getElementById('card-container');
            if (!container || container.classList.contains('hidden')) return;
            const maxWidth = window.innerWidth;
            const maxHeight = window.innerHeight;
            const scaleX = maxWidth / canvasWidth;
            const scaleY = maxHeight / canvasHeight;
            const scale = Math.min(scaleX, scaleY, 1);
            container.style.transform = `scale(${scale})`;
            container.style.transformOrigin = 'center center';
        }
        window.addEventListener('resize', scaleLive);
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
