<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: {{ $template->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @php
        $auction = $template->auction;
        $primaryHex = $auction?->primary_color ?? '#00bcd4';
        $secondaryHex = $auction?->secondary_color ?? '#22c55e';
        $pR = hexdec(substr($primaryHex, 1, 2));
        $pG = hexdec(substr($primaryHex, 3, 2));
        $pB = hexdec(substr($primaryHex, 5, 2));
        $sR = hexdec(substr($secondaryHex, 1, 2));
        $sG = hexdec(substr($secondaryHex, 3, 2));
        $sB = hexdec(substr($secondaryHex, 5, 2));

        $positions = $template->element_positions ?? [];

        // Shadow preset maps
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

        function previewElementStyle($positions, $key, $defaults = [], $boxShadowMap = [], $textShadowMap = []) {
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

        function previewIsVisible($positions, $key) {
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
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .card-container {
            position: relative;
            width: {{ $template->canvas_width }}px;
            height: {{ $template->canvas_height }}px;
            @if($template->background_image)
            background: url('{{ asset('storage/' . $template->background_image) }}') no-repeat center center;
            background-size: cover;
            @else
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            border: 2px dashed #444;
            @endif
            transform-origin: top left;
        }

        .card-container::before {
            content: '';
            position: absolute;
            inset: -3px;
            background: linear-gradient(45deg, var(--primary), var(--secondary), var(--primary));
            background-size: 300% 300%;
            animation: gradient-border 4s ease infinite;
            z-index: -1;
            opacity: 0.7;
            border-radius: 4px;
        }

        @keyframes gradient-border {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* IPL SOLD effects */
        .card-container.sold-state {
            animation: sold-brightness 1.5s ease-out forwards;
        }
        @keyframes sold-brightness {
            0% { filter: brightness(1); }
            20% { filter: brightness(1.6); }
            100% { filter: brightness(1); }
        }
        .card-container.sold-state::before {
            background: linear-gradient(45deg, #fbbf24, #f59e0b, #eab308, #fbbf24) !important;
            background-size: 300% 300% !important;
            animation: gradient-border 2s ease infinite !important;
            opacity: 1 !important;
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
        #team-logo.sold-entrance {
            animation: team-logo-entrance 0.8s ease-out 0.2s forwards;
            opacity: 0;
        }
        @keyframes team-logo-entrance {
            0% { transform: scale(0) rotate(-180deg); opacity: 0; }
            60% { transform: scale(1.15) rotate(5deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }

        /* UNSOLD effects */
        .card-container.unsold-state {
            filter: brightness(0.6) saturate(0.5);
            transition: filter 0.5s ease;
        }
        .card-container.unsold-state::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 30%, rgba(239,68,68,0.15) 100%);
            pointer-events: none;
            z-index: 8;
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

        /* Elements via previewElementStyle */
        #player-image {
            position: absolute;
            {!! previewElementStyle($positions, 'player_image', ['bottom'=>305,'left'=>114,'width'=>380], $boxShadowMap, $textShadowMap) !!}
            object-fit: cover;
        }

        .player-glow {
            position: absolute;
            @if(isset($positions['player_image']['bottom']))
            bottom: {{ ($positions['player_image']['bottom'] ?? 305) - 30 }}px;
            @endif
            @if(isset($positions['player_image']['top']))
            top: {{ ($positions['player_image']['top'] ?? 0) - 30 }}px;
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
            {!! previewElementStyle($positions, 'player_name', ['top'=>210,'left'=>545,'fontSize'=>46,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        #player-role {
            position: absolute;
            {!! previewElementStyle($positions, 'player_role', ['top'=>275,'left'=>570,'fontSize'=>24,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        #player-batting {
            position: absolute;
            {!! previewElementStyle($positions, 'batting_style', ['top'=>334,'left'=>570,'fontSize'=>34,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        #player-bowling {
            position: absolute;
            {!! previewElementStyle($positions, 'bowling_style', ['top'=>404,'left'=>570,'fontSize'=>34,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        #current-bid {
            position: absolute;
            {!! previewElementStyle($positions, 'current_bid', ['bottom'=>197,'left'=>234,'fontSize'=>32,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
            animation: bid-glow 2s ease-in-out infinite;
        }

        @keyframes bid-glow {
            0%, 100% { text-shadow: 0 0 8px rgba(var(--primary-rgb), 0.3); }
            50% { text-shadow: 0 0 20px rgba(var(--primary-rgb), 0.6), 0 0 40px rgba(var(--primary-rgb), 0.2); }
        }

        #sold-text {
            position: absolute;
            {!! previewElementStyle($positions, 'bid_label', ['bottom'=>243,'left'=>186,'fontSize'=>32,'color'=>'#ffffff'], $boxShadowMap, $textShadowMap) !!}
        }

        #sold-badge {
            position: absolute;
            {!! previewElementStyle($positions, 'sold_badge', ['bottom'=>27,'left'=>112,'width'=>150,'height'=>150,'zIndex'=>9], $boxShadowMap, $textShadowMap) !!}
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #team-logo {
            position: absolute;
            {!! previewElementStyle($positions, 'team_logo', ['bottom'=>56,'left'=>316,'width'=>170,'height'=>100], $boxShadowMap, $textShadowMap) !!}
            object-fit: contain;
        }

        #highest-bidder {
            position: absolute;
            {!! previewElementStyle($positions, 'highest_bidder', ['top'=>470,'left'=>570,'fontSize'=>28,'color'=>'#00ff00'], $boxShadowMap, $textShadowMap) !!}
        }

        @php
            $pst = array_merge(['top'=>480,'left'=>550,'width'=>500,'height'=>150,'fontSize'=>20], $positions['stats_table'] ?? []);
        @endphp
        #stats-table-wrap {
            position: absolute;
            {!! previewElementStyle($positions, 'stats_table', ['top'=>480,'left'=>550,'width'=>500,'height'=>150,'fontSize'=>20], $boxShadowMap, $textShadowMap) !!}
        }
        #stats-table-wrap table { width:100%;border-collapse:collapse;font-size:{{ $pst['fontSize'] ?? 20 }}px; }
        #stats-table-wrap thead tr { background:{{ $pst['headerBg'] ?? 'rgba(0,0,0,0.7)' }};color:{{ $pst['headerColor'] ?? '#fff' }}; }
        #stats-table-wrap tbody tr { background:{{ $pst['rowBg'] ?? 'rgba(255,255,255,0.1)' }};color:{{ $pst['cellColor'] ?? '#fff' }}; }
        #stats-table-wrap th, #stats-table-wrap td {
            padding:{{ $pst['cellPadding'] ?? 10 }}px;
            border:{{ $pst['tableBorderWidth'] ?? 1 }}px solid {{ $pst['tableBorderColor'] ?? 'rgba(255,255,255,0.2)' }};
            text-align:center;
        }
        #stats-table-wrap th { font-weight:bold;text-transform:uppercase;letter-spacing:1px; }

        .element-marker {
            border: 2px dashed rgba(255, 255, 0, 0.5);
            background: rgba(255, 255, 0, 0.1);
        }

        .scale-info {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
        }

        .state-btn {
            padding: 8px 20px;
            border: 2px solid #444;
            border-radius: 8px;
            background: #1a1a2e;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .state-btn:hover { border-color: #666; }
        .state-btn.active { border-color: var(--primary); background: rgba(var(--primary-rgb), 0.2); }
    </style>
</head>
<body class="text-white">

    <div class="card-container" id="preview-container">
        <!-- Player image glow -->
        @if(previewIsVisible($positions, 'player_image'))
        <div class="player-glow"></div>
        @endif

        @if(previewIsVisible($positions, 'sold_badge'))
            @if($template->sold_badge_image)
                <div id="sold-badge" class="element-marker">
                    <img src="{{ asset('storage/' . $template->sold_badge_image) }}" alt="Sold Badge" class="w-full h-full object-contain">
                </div>
            @else
                <div id="sold-badge" class="element-marker flex items-center justify-center text-yellow-400 text-xs">SOLD BADGE</div>
            @endif

            {{-- Unsold badge --}}
            @if($template->unsold_badge_image)
                <div id="unsold-badge" class="element-marker" style="display:none;{!! previewElementStyle($positions, 'sold_badge', ['bottom'=>27,'left'=>112,'width'=>150,'height'=>150,'zIndex'=>9], $boxShadowMap, $textShadowMap) !!}align-items:center;justify-content:center;">
                    <img src="{{ asset('storage/' . $template->unsold_badge_image) }}" alt="Unsold Badge" class="w-full h-full object-contain">
                </div>
            @else
                <div id="unsold-badge" class="element-marker" style="display:none;{!! previewElementStyle($positions, 'sold_badge', ['bottom'=>27,'left'=>112,'width'=>150,'height'=>150,'zIndex'=>9], $boxShadowMap, $textShadowMap) !!}align-items:center;justify-content:center;">
                    <span class="text-red-400 text-xs">UNSOLD BADGE</span>
                </div>
            @endif
        @endif

        @if(previewIsVisible($positions, 'team_logo'))
        <div id="team-logo" class="element-marker flex items-center justify-center text-xs text-cyan-400">TEAM LOGO</div>
        @endif

        @if(previewIsVisible($positions, 'player_image'))
        <img id="player-image"
             src="https://ui-avatars.com/api/?name=Sample+Player&size=400&background=random"
             alt="Player"
             class="element-marker">
        @endif

        @if(previewIsVisible($positions, 'player_name'))
        <h1 id="player-name" class="element-marker">SAMPLE PLAYER</h1>
        @endif

        @if(previewIsVisible($positions, 'player_role'))
        <p id="player-role" class="element-marker">ALL ROUNDER</p>
        @endif

        @if(previewIsVisible($positions, 'batting_style'))
        <p id="player-batting" class="element-marker">Right Hand Bat</p>
        @endif

        @if(previewIsVisible($positions, 'bowling_style'))
        <p id="player-bowling" class="element-marker">Right Arm Medium</p>
        @endif

        @if(previewIsVisible($positions, 'bid_label'))
        <h1 id="sold-text" class="element-marker">SOLD PRICE</h1>
        @endif

        @if(previewIsVisible($positions, 'current_bid'))
        <div id="current-bid" class="element-marker">10.5M Points</div>
        @endif

        @if(previewIsVisible($positions, 'highest_bidder'))
        <div id="highest-bidder" class="element-marker">Thunder Kings</div>
        @endif

        @if(previewIsVisible($positions, 'stats_table'))
        @php
            $pvCols = json_decode($positions['stats_table']['tableColumns'] ?? '[]', true) ?: [
                ['label'=>'Matches','field'=>'total_matches'],
                ['label'=>'Runs','field'=>'total_runs'],
                ['label'=>'Wickets','field'=>'total_wickets'],
            ];
            $pvSt = $positions['stats_table'] ?? [];
            $pvCP = $pvSt['cellPadding'] ?? 10;
            $pvBW = $pvSt['tableBorderWidth'] ?? 1;
            $pvBC = $pvSt['tableBorderColor'] ?? 'rgba(255,255,255,0.2)';
            $pvBdr = $pvBW.'px solid '.$pvBC;
            $sampleData = ['total_matches'=>25,'total_runs'=>1250,'total_wickets'=>48,'base_price'=>'50L','batting_avg'=>42.5,'bowling_avg'=>28.3,'strike_rate'=>135.2,'economy'=>7.8,'fifties'=>8,'hundreds'=>2,'catches'=>15,'stumpings'=>3];
        @endphp
        <div id="stats-table-wrap" class="element-marker">
            <table>
                <thead><tr>
                    @foreach($pvCols as $col)
                    <th style="{{ !empty($col['headerBg']) ? 'background:'.$col['headerBg'].';' : '' }}{{ !empty($col['headerColor']) ? 'color:'.$col['headerColor'].';' : '' }}{{ !empty($col['width']) ? 'width:'.$col['width'].';' : '' }}">{{ $col['label'] ?? '' }}</th>
                    @endforeach
                </tr></thead>
                <tbody><tr>
                    @foreach($pvCols as $col)
                    <td style="{{ !empty($col['cellBg']) ? 'background:'.$col['cellBg'].';' : '' }}{{ !empty($col['cellColor']) ? 'color:'.$col['cellColor'].';' : '' }}">{{ $sampleData[$col['field'] ?? ''] ?? 0 }}</td>
                    @endforeach
                </tr></tbody>
            </table>
        </div>
        @endif


        {{-- Custom Elements --}}
        @foreach($positions as $cKey => $cVal)
            @if(str_starts_with($cKey, 'custom_text_') && ($cVal['visible'] ?? true))
                <div class="element-marker" style="position:absolute;{!! previewElementStyle($positions, $cKey, [], $boxShadowMap, $textShadowMap) !!}{{ empty($cVal['width']) ? 'white-space:nowrap;' : 'word-wrap:break-word;' }}">{{ $cVal['content'] ?? '' }}</div>
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
                <div class="element-marker" style="position:absolute;{!! previewElementStyle($positions, $cKey, [], $boxShadowMap, $textShadowMap) !!}{{ $shapeExtra }}"></div>
            @elseif(str_starts_with($cKey, 'custom_image_') && ($cVal['visible'] ?? true) && !empty($cVal['imagePath']))
                <img src="{{ asset('storage/' . $cVal['imagePath']) }}" class="element-marker"
                     style="position:absolute;{!! previewElementStyle($positions, $cKey, [], $boxShadowMap, $textShadowMap) !!}object-fit:contain;" alt="">
            @endif
        @endforeach
    </div>

    {{-- State Toggle Buttons --}}
    <div style="margin-top:30px;display:flex;gap:12px;position:relative;z-index:100;">
        <button class="state-btn active" onclick="setPreviewState('base', this)">Base</button>
        <button class="state-btn" onclick="setPreviewState('sold', this)">Sold</button>
        <button class="state-btn" onclick="setPreviewState('unsold', this)">Unsold</button>
    </div>

    <div class="scale-info">
        <strong>{{ $template->name }}</strong><br>
        Canvas: {{ $template->canvas_width }} x {{ $template->canvas_height }}px<br>
        <small>Yellow borders show element positions</small>
    </div>

    <script>
        function scalePreview() {
            const container = document.getElementById('preview-container');
            const maxWidth = window.innerWidth - 40;
            const maxHeight = window.innerHeight - 160;

            const scaleX = maxWidth / {{ $template->canvas_width }};
            const scaleY = maxHeight / {{ $template->canvas_height }};
            const scale = Math.min(scaleX, scaleY, 1);

            container.style.transform = `scale(${scale})`;
            container.style.marginBottom = (({{ $template->canvas_height }} * scale) - {{ $template->canvas_height }}) + 'px';
        }

        window.addEventListener('resize', scalePreview);
        scalePreview();

        function setPreviewState(state, btn) {
            const container = document.getElementById('preview-container');
            const soldText = document.getElementById('sold-text');
            const soldBadge = document.getElementById('sold-badge');
            const unsoldBadge = document.getElementById('unsold-badge');
            const teamLogo = document.getElementById('team-logo');

            // Reset
            container.classList.remove('sold-state', 'unsold-state');
            if (soldText) soldText.classList.remove('sold-active', 'unsold-active');
            if (soldBadge) soldBadge.classList.remove('sold-entrance');
            if (unsoldBadge) unsoldBadge.style.display = 'none';
            if (teamLogo) teamLogo.classList.remove('sold-entrance');

            // Active button
            document.querySelectorAll('.state-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            if (state === 'sold') {
                container.classList.add('sold-state');
                if (soldText) {
                    soldText.textContent = 'SOLD';
                    soldText.classList.add('sold-active');
                }
                if (soldBadge) soldBadge.classList.add('sold-entrance');
                if (teamLogo) teamLogo.classList.add('sold-entrance');
            } else if (state === 'unsold') {
                container.classList.add('unsold-state');
                if (soldText) {
                    soldText.textContent = 'UNSOLD';
                    soldText.classList.add('unsold-active');
                }
                if (unsoldBadge) unsoldBadge.style.display = 'flex';
            } else {
                if (soldText) soldText.textContent = 'SOLD PRICE';
            }
        }
    </script>
</body>
</html>
