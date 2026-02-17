<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auction | {{ $auction->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        /* Fixed-size card container */
        .card-container {
            position: relative;
            width: 1601px;
            height: 910px;
            background: url('/images/player-card.jpeg') no-repeat center center;
            background-size: auto;
        }

        /* Waiting screen */
        #waiting-screen {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0a0a0a 100%);
            z-index: 100;
        }

        #waiting-screen h1 {
            font-size: 72px;
            color: #00bcd4;
            animation: pulse 2s ease-in-out infinite;
            text-shadow: 0 0 30px rgba(0, 188, 212, 0.5);
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.02); }
        }

        /* Loading spinner for LED wall */
        .led-loader {
            width: 80px;
            height: 80px;
            border: 6px solid rgba(0, 188, 212, 0.2);
            border-top-color: #00bcd4;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 40px;
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
            background: #00bcd4;
            border-radius: 50%;
            animation: dot-pulse 1.5s ease-in-out infinite;
            box-shadow: 0 0 10px #00bcd4;
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
            background: #22c55e;
            border-radius: 50%;
            animation: live-blink 1s ease-in-out infinite;
            box-shadow: 0 0 10px #22c55e;
        }

        @keyframes live-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* Position elements inside */
        #player-image {
            position: absolute;
            bottom: 305px;
            left: 114px;
            width: 380px;
            object-fit: cover;
        }

        #player-name {
            position: absolute;
            top: 210px;
            left: 545px;
            font-size: 46px;
            text-transform: uppercase;
        }

        #tmh {
            position: absolute;
            top: 490px;
            left: 600px;
            font-size: 33px;
            text-transform: uppercase;
        }

        #tm {
            position: absolute;
            top: 550px;
            left: 605px;
            font-size: 33px;
            color: #000;
            text-transform: uppercase;
        }

        .hidden {
            display: none !important;
        }

        #twh {
            position: absolute;
            top: 490px;
            left: 825px;
            font-size: 33px;
            text-transform: uppercase;
        }

        #tw {
            position: absolute;
            top: 550px;
            left: 825px;
            font-size: 33px;
            text-transform: uppercase;
            color: #000;
        }

        #trh {
            position: absolute;
            top: 490px;
            left: 1020px;
            font-size: 33px;
            text-transform: uppercase;
        }

        #tr {
            position: absolute;
            top: 550px;
            left: 1050px;
            font-size: 33px;
            color: #000;
            text-transform: uppercase;
        }

        #player-role {
            position: absolute;
            top: 275px;
            left: 570px;
        }

        #player-batting {
            position: absolute;
            top: 334px;
            left: 570px;
            font-size: 34px;
            font-weight: bold;
        }

        #player-bowling {
            position: absolute;
            top: 404px;
            left: 570px;
            font-size: 34px;
            font-weight: bold;
        }

        #current-bid {
            position: absolute;
            left: 234px;
            bottom: 197px;
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
            bottom: 27px;
            left: 112px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 150px;
            height: 150px;
            z-index: 9;
        }

        #team-logo {
            position: absolute;
            bottom: 56px;
            left: 316px;
            width: 170px;
            height: 100px;
            object-fit: contain;
        }

        ul#bid-list {
            font-size: 25px;
        }

        #sold-text {
            bottom: 243px;
            left: 186px;
            position: absolute;
            font-size: 32px;
        }

        /* Bid flash animation */
        .bid-flash {
            animation: bidFlash 0.5s ease-out;
        }

        @keyframes bidFlash {
            0% { color: #00ff00; transform: scale(1.1); }
            100% { color: #fff; transform: scale(1); }
        }

        /* Highest bidder display */
        #highest-bidder {
            position: absolute;
            top: 470px;
            left: 570px;
            font-size: 28px;
            color: #00ff00;
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
        <div class="led-loader"></div>
        <h1>WAITING FOR AUCTION</h1>
        <p class="text-3xl text-gray-400 mt-4">{{ $auction->name }}</p>
        <div class="glow-dots">
            <div class="glow-dot"></div>
            <div class="glow-dot"></div>
            <div class="glow-dot"></div>
        </div>
        <p class="text-xl text-gray-500 mt-8">Next player coming up...</p>
    </div>

    <div id="card-container" class="card-container hidden">
        <!-- Sold Badge (hidden by default, shown when sold) -->
        <div id="sold-badge" class="absolute hidden">
            <img src="/images/sold.png" alt="Sold Badge" class="sold-badge">
        </div>

        <!-- Actual Team Logo -->
        <img id="team-logo" src="" class="absolute object-contain hidden">

        <!-- Player Image -->
        <img id="player-image" src="https://via.placeholder.com/300" alt="Player">

        <!-- Player Name -->
        <h1 id="player-name" class="text-4xl font-bold">Player Name</h1>
        <h1 id="tmh" class="text-4xl font-bold">MATCHES</h1>
        <h1 id="tm" class="text-4xl font-bold">0</h1>

        <h1 id="twh" class="text-4xl font-bold">WKTS</h1>
        <h1 id="tw" class="text-4xl font-bold">0</h1>

        <h1 id="trh" class="text-4xl font-bold">RUNS</h1>
        <h1 id="tr" class="text-4xl font-bold">0</h1>

        <!-- Player Role -->
        <p id="player-role" class="text-2xl font-bold font-uppercase">All Rounder</p>

        <!-- Status Text (BASE VALUE / CURRENT BID / SOLD PRICE) -->
        <h1 id="sold-text" class="text-4xl font-bold">BASE VALUE</h1>

        <!-- Batting / Bowling -->
        <p id="player-batting">Right-Hand Bat</p>
        <p id="player-bowling">Right-Arm Medium</p>

        <!-- Current Bid -->
        <div id="current-bid" class="text-3xl font-extrabold text-white-900">1,00,000</div>

        <!-- Highest Bidder (shown during live bidding) -->
        <div id="highest-bidder" class="hidden">Highest: <span id="bidder-name"></span></div>
    </div>

    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.11.3/echo.iife.js"></script>
    <script>
        const auctionId = {{ $auction->id }};
        let currentStatus = 'waiting';
        let lastPlayerId = null;

        // Initialize Echo
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env('PUSHER_APP_KEY') }}',
            cluster: '{{ env('PUSHER_APP_CLUSTER') }}',
            forceTLS: true
        });

        function formatMillions(amount) {
            if (!amount && amount !== 0) return '0';
            return `${(amount / 1_000_000).toFixed(1)}M Points`;
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

            // Player image
            document.getElementById('player-image').src = p.player.image_path
                ? `/storage/${p.player.image_path}`
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(p.player.name)}`;

            // Stats
            document.getElementById('tm').textContent = p.player.total_matches ?? 0;
            document.getElementById('tw').textContent = p.player.total_wickets ?? 0;
            document.getElementById('tr').textContent = p.player.total_runs ?? 0;

            // Player details
            document.getElementById('player-name').textContent = p.player.name;

            // Handle nested objects for player type
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

            // Price display
            const price = p.final_price || p.current_price || p.base_price || 0;
            document.getElementById('current-bid').textContent = formatMillions(price);

            // Status text and badges
            const soldText = document.getElementById('sold-text');
            const soldBadge = document.getElementById('sold-badge');
            const teamLogo = document.getElementById('team-logo');
            const highestBidder = document.getElementById('highest-bidder');

            if (p.status === 'sold') {
                soldText.textContent = 'SOLD PRICE';
                soldBadge.classList.remove('hidden');
                highestBidder.classList.add('hidden');

                // Show team logo
                if (p.sold_to_team && (p.sold_to_team.logo_path || p.sold_to_team.team_logo)) {
                    teamLogo.src = p.sold_to_team.logo_path || `/storage/${p.sold_to_team.team_logo}`;
                    teamLogo.classList.remove('hidden');
                } else {
                    teamLogo.classList.add('hidden');
                }
            } else if (p.status === 'on_auction') {
                soldText.textContent = 'CURRENT BID';
                soldBadge.classList.add('hidden');
                teamLogo.classList.add('hidden');

                // Show highest bidder during live auction
                const bids = p.bids || [];
                if (bids.length > 0) {
                    const highestBid = bids.sort((a, b) => b.amount - a.amount)[0];
                    document.getElementById('bidder-name').textContent = highestBid.team?.name || 'Unknown';
                    highestBidder.classList.remove('hidden');
                } else {
                    highestBidder.classList.add('hidden');
                }

                // Flash animation on bid update
                const bidElement = document.getElementById('current-bid');
                bidElement.classList.add('bid-flash');
                setTimeout(() => bidElement.classList.remove('bid-flash'), 500);
            } else if (p.status === 'unsold') {
                soldText.textContent = 'UNSOLD';
                soldBadge.classList.add('hidden');
                teamLogo.classList.add('hidden');
                highestBidder.classList.add('hidden');
            } else {
                soldText.textContent = 'BASE VALUE';
                soldBadge.classList.add('hidden');
                teamLogo.classList.add('hidden');
                highestBidder.classList.add('hidden');
            }

            currentStatus = p.status;
            lastPlayerId = p.id;
        }

        function fetchActivePlayer() {
            console.log('[Live] fetchActivePlayer() called');
            fetch(`/auction/${auctionId}/active-player`)
                .then(res => res.json())
                .then(data => {
                    console.log('[Live] API response:', data);
                    if (data?.auctionPlayer) {
                        console.log('[Live] Got active player:', data.auctionPlayer.player?.name);
                        updatePlayerCard(data.auctionPlayer);
                    } else {
                        console.log('[Live] No active player, checking sold-player');
                        // No active player, check for recently sold
                        fetch(`/auction/${auctionId}/sold-player`)
                            .then(res => res.json())
                            .then(soldData => {
                                console.log('[Live] Sold player response:', soldData);
                                if (soldData?.auctionPlayer) {
                                    updatePlayerCard(soldData.auctionPlayer);
                                } else {
                                    showWaiting();
                                }
                            });
                    }
                })
                .catch(err => {
                    console.error('[Live] Fetch error:', err);
                });
        }

        // Listen to public channel for sold events (real-time updates)
        window.Echo.channel(`auction.${auctionId}`)
            .listen('.player-on-sold', (event) => {
                console.log('Player sold event:', event);
                const auctionPlayer = event.auctionPlayer;
                if (event.winningTeam) {
                    auctionPlayer.sold_to_team = event.winningTeam;
                }
                updatePlayerCard(auctionPlayer);
            });

        // Poll for updates every 2 seconds (for bid updates)
        setInterval(fetchActivePlayer, 2000);

        // Initial fetch
        fetchActivePlayer();
    </script>

</body>

</html>
