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
            width: 1620px;
            /* Set your card image width */
            height: 800px;
            /* Set your card image height */
            background: url('/images/player-card.jpeg') no-repeat center center;
            background-size: auto;
            /* Keep original image size */
        }

        /* Position elements inside */
        #player-image {
            position: absolute;
            top: 121px;
            left: 114px;
            width: 380px;
            height: 428px;
            object-fit: cover;
        }

        #player-name {
            position: absolute;
            top: 620px;
            left: 220px;
        }

        #player-role {
            position: absolute;
            top: 230px;
            left: 570px;
        }

        #player-batting {
            position: absolute;
            top: 270px;
            left: 570px;
        }

        #player-bowling {
            position: absolute;
            top: 290px;
            left: 570px;
        }

        #current-bid {
            position: absolute;
            top: 350px;
            left: 570px;
        }

        #winning-team {
            position: absolute;
            top: 470px;
            left: 570px;
        }

        #bid-list-container {
            position: absolute;
            top: 600px;
            left: 570px;
            width: 250px;
            height: 150px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.5);
            padding: 8px;
            border-radius: 6px;
        }

        #sold-badge {
            position: absolute;
            bottom: 200px;
            left: 400px;
            bottom: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 150px;
            height: 150px;
            z-index: 9;
        }

        #team-logo {
            position: absolute;
            bottom: 30px;
            left: 150px;
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
    </style>
</head>

<body class="text-white">

    <div class="card-container">
        <!-- Sold Badge -->
        <div id="sold-badge" class="absolute ">
            <img src="/images/sold.png" alt="Sold Badge">

        </div>

        <!-- Actual Team Logo -->
        <img id="team-logo" src="" alt="Team Logo" class="absolute object-contain hidden">

        <!-- Player Image -->
        <img id="player-image" src="https://via.placeholder.com/300" alt="Player">

        <!-- Player Name -->
        <h1 id="player-name" class="text-4xl font-bold">Player Name</h1>

        <!-- Player Role -->
        <p id="player-role" class="text-xl text-yellow-300">All Rounder</p>

        <!-- Batting / Bowling -->
        <p id="player-batting" class="text-lg">Batting: Right-Hand Bat</p>
        <p id="player-bowling" class="text-lg">Bowling: Right-Arm Medium</p>

        <!-- Current Bid -->
        <div id="current-bid" class="text-5xl font-extrabold text-yellow-400">₹ 1,00,000</div>

        <!-- Winning Team -->
        <div id="winning-team" class="text-2xl font-bold text-green-400">Chennai Super Kings</div>

        <!-- Bid History -->
        <div id="bid-list-container">
            <ul id="bid-list" class="space-y-1">
                <li>Team A — ₹50,000</li>
                <li>Team B — ₹75,000</li>
            </ul>
        </div>
    </div>

    <script>
        // Format bid in millions (M) or lakhs (L) depending on value
        function formatMillions(amount) {
            if (!amount && amount !== 0) return '0';
            return `${(amount / 1_000_000).toFixed(0)}M Points`;
        }
        // Use it here

        function fetchActivePlayer() {
            fetch(`/auction/{{ $auction->id }}/active-player`)
                .then(res => res.json())
                .then(data => {
                    if (data.auctionPlayer) {
                        const p = data.auctionPlayer;

                        // Player info
                        document.getElementById('player-image').src =
                            p.player.image_path ? `/storage/${p.player.image_path}` :
                            `https://ui-avatars.com/api/?name=${encodeURIComponent(p.player.name)}`;
                        document.getElementById('player-name').textContent = p.player.name;
                        document.getElementById('player-role').textContent = p.player.player_type?.type ?? '';
                        document.getElementById('player-batting').textContent =
                            `Batting: ${p.player.batting_profile?.style ?? 'N/A'}`;
                        document.getElementById('player-bowling').textContent =
                            `Bowling: ${p.player.bowling_profile?.style ?? 'N/A'}`;
                        document.getElementById('current-bid').textContent = formatMillions(p.current_price);

                        // Winning team
                        document.getElementById('winning-team').textContent =
                            p.current_bid_team?.name ?? 'No Bids';

                        // Bid list
                        const bidList = document.getElementById('bid-list');
                        bidList.innerHTML = '';
                        if (p.bids?.length) {
                            p.bids.forEach(bid => {
                                const li = document.createElement('li');
                                li.innerHTML =
                                    `<strong>${bid.team?.name ?? 'Unknown'}</strong> — ₹${bid.amount.toLocaleString('en-IN')}`;
                                bidList.appendChild(li);
                            });
                        } else {
                            bidList.innerHTML = '<li>No bids yet.</li>';
                        }

                        // --- Sold badge & team logo ---
                        const soldBadge = document.getElementById('sold-badge');
                        const teamLogo = document.getElementById('team-logo');

                        if (p.status === 'sold' && p.sold_to_team) {
                            soldBadge.classList.remove('hidden');
                            teamLogo.classList.remove('hidden');
                            teamLogo.src = p.sold_to_team.logo_path ? `${p.sold_to_team.logo_path}` : '';
                        } else {
                            soldBadge.classList.add('hidden');
                            teamLogo.classList.add('hidden');
                        }
                    }
                })
                .catch(console.error);
        }

        setInterval(fetchActivePlayer, 2000);
        fetchActivePlayer();
    </script>

</body>

</html>
