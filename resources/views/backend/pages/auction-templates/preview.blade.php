<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: {{ $template->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: #000;
            display: flex;
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

        @php
            $positions = $template->element_positions ?? [];
        @endphp

        #player-image {
            position: absolute;
            @if(isset($positions['player_image']['bottom']))
            bottom: {{ $positions['player_image']['bottom'] ?? 305 }}px;
            @endif
            @if(isset($positions['player_image']['top']))
            top: {{ $positions['player_image']['top'] }}px;
            @endif
            left: {{ $positions['player_image']['left'] ?? 114 }}px;
            width: {{ $positions['player_image']['width'] ?? 380 }}px;
            object-fit: cover;
        }

        #player-name {
            position: absolute;
            top: {{ $positions['player_name']['top'] ?? 210 }}px;
            left: {{ $positions['player_name']['left'] ?? 545 }}px;
            font-size: {{ $positions['player_name']['fontSize'] ?? 46 }}px;
            text-transform: uppercase;
            font-weight: bold;
        }

        #player-role {
            position: absolute;
            top: {{ $positions['player_role']['top'] ?? 275 }}px;
            left: {{ $positions['player_role']['left'] ?? 570 }}px;
            font-size: {{ $positions['player_role']['fontSize'] ?? 24 }}px;
            font-weight: bold;
        }

        #player-batting {
            position: absolute;
            top: {{ $positions['batting_style']['top'] ?? 334 }}px;
            left: {{ $positions['batting_style']['left'] ?? 570 }}px;
            font-size: {{ $positions['batting_style']['fontSize'] ?? 34 }}px;
            font-weight: bold;
        }

        #player-bowling {
            position: absolute;
            top: {{ $positions['bowling_style']['top'] ?? 404 }}px;
            left: {{ $positions['bowling_style']['left'] ?? 570 }}px;
            font-size: {{ $positions['bowling_style']['fontSize'] ?? 34 }}px;
            font-weight: bold;
        }

        #current-bid {
            position: absolute;
            @if(isset($positions['current_bid']['bottom']))
            bottom: {{ $positions['current_bid']['bottom'] ?? 197 }}px;
            @endif
            @if(isset($positions['current_bid']['top']))
            top: {{ $positions['current_bid']['top'] }}px;
            @endif
            left: {{ $positions['current_bid']['left'] ?? 234 }}px;
            font-size: {{ $positions['current_bid']['fontSize'] ?? 32 }}px;
            font-weight: bold;
        }

        #sold-text {
            position: absolute;
            @if(isset($positions['bid_label']['bottom']))
            bottom: {{ $positions['bid_label']['bottom'] ?? 243 }}px;
            @endif
            @if(isset($positions['bid_label']['top']))
            top: {{ $positions['bid_label']['top'] }}px;
            @endif
            left: {{ $positions['bid_label']['left'] ?? 186 }}px;
            font-size: {{ $positions['bid_label']['fontSize'] ?? 32 }}px;
            font-weight: bold;
        }

        #sold-badge {
            position: absolute;
            @if(isset($positions['sold_badge']['bottom']))
            bottom: {{ $positions['sold_badge']['bottom'] ?? 27 }}px;
            @endif
            @if(isset($positions['sold_badge']['top']))
            top: {{ $positions['sold_badge']['top'] }}px;
            @endif
            left: {{ $positions['sold_badge']['left'] ?? 112 }}px;
            width: {{ $positions['sold_badge']['width'] ?? 150 }}px;
            height: {{ $positions['sold_badge']['height'] ?? 150 }}px;
        }

        #team-logo {
            position: absolute;
            @if(isset($positions['team_logo']['bottom']))
            bottom: {{ $positions['team_logo']['bottom'] ?? 56 }}px;
            @endif
            @if(isset($positions['team_logo']['top']))
            top: {{ $positions['team_logo']['top'] }}px;
            @endif
            left: {{ $positions['team_logo']['left'] ?? 316 }}px;
            width: {{ $positions['team_logo']['width'] ?? 170 }}px;
            height: {{ $positions['team_logo']['height'] ?? 100 }}px;
            object-fit: contain;
        }

        #tm {
            position: absolute;
            top: {{ $positions['stats_matches']['top'] ?? 550 }}px;
            left: {{ $positions['stats_matches']['left'] ?? 605 }}px;
            font-size: {{ $positions['stats_matches']['fontSize'] ?? 33 }}px;
            font-weight: bold;
            color: #000;
        }

        #tw {
            position: absolute;
            top: {{ $positions['stats_wickets']['top'] ?? 550 }}px;
            left: {{ $positions['stats_wickets']['left'] ?? 825 }}px;
            font-size: {{ $positions['stats_wickets']['fontSize'] ?? 33 }}px;
            font-weight: bold;
            color: #000;
        }

        #tr {
            position: absolute;
            top: {{ $positions['stats_runs']['top'] ?? 550 }}px;
            left: {{ $positions['stats_runs']['left'] ?? 1050 }}px;
            font-size: {{ $positions['stats_runs']['fontSize'] ?? 33 }}px;
            font-weight: bold;
            color: #000;
        }

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
    </style>
</head>
<body class="text-white">

    <div class="card-container" id="preview-container">
        @if($template->sold_badge_image)
            <div id="sold-badge" class="element-marker">
                <img src="{{ asset('storage/' . $template->sold_badge_image) }}" alt="Sold Badge" class="w-full h-full object-contain">
            </div>
        @else
            <div id="sold-badge" class="element-marker flex items-center justify-center text-yellow-400 text-xs">SOLD BADGE</div>
        @endif

        <div id="team-logo" class="element-marker flex items-center justify-center text-xs text-cyan-400">TEAM LOGO</div>

        <img id="player-image"
             src="https://ui-avatars.com/api/?name=Sample+Player&size=400&background=random"
             alt="Player"
             class="element-marker">

        <h1 id="player-name" class="element-marker">SAMPLE PLAYER</h1>

        <p id="player-role" class="element-marker">ALL ROUNDER</p>

        <p id="player-batting" class="element-marker">Right Hand Bat</p>

        <p id="player-bowling" class="element-marker">Right Arm Medium</p>

        <h1 id="sold-text" class="element-marker">SOLD PRICE</h1>

        <div id="current-bid" class="element-marker">10.5M Points</div>

        <span id="tm" class="element-marker">25</span>
        <span id="tw" class="element-marker">48</span>
        <span id="tr" class="element-marker">1250</span>
    </div>

    <div class="scale-info">
        <strong>{{ $template->name }}</strong><br>
        Canvas: {{ $template->canvas_width }} x {{ $template->canvas_height }}px<br>
        <small>Yellow borders show element positions</small>
    </div>

    <script>
        // Auto-scale to fit screen
        function scalePreview() {
            const container = document.getElementById('preview-container');
            const maxWidth = window.innerWidth - 40;
            const maxHeight = window.innerHeight - 100;

            const scaleX = maxWidth / {{ $template->canvas_width }};
            const scaleY = maxHeight / {{ $template->canvas_height }};
            const scale = Math.min(scaleX, scaleY, 1);

            container.style.transform = `scale(${scale})`;
        }

        window.addEventListener('resize', scalePreview);
        scalePreview();
    </script>
</body>
</html>
