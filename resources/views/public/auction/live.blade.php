<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auction | {{ $auction->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>

        @keyframes bounce-text {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}
.animate-bounce-text {
    animation: bounce-text 1s infinite;
}
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
            /* Set your card image height */
            background: url('/images/player-card.jpeg') no-repeat center center;
            background-size: auto;
            /* Keep original image size */
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
            display: none;
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

        #winning-team {
            position: absolute;
            top: 470px;
            left: 570px;
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
            /* bottom: 200px; */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 150px;
            height: 150px;
            z-index: 9;
        }

        #team-logo {
            position: absolute;
            position: absolute;
            bottom: 27px;
            left: 300px;
            width: 100px;
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
    </style>
</head>

<body class="text-white">
<div id="waiting-screen" class="fixed inset-0 flex items-center justify-center bg-black text-white text-5xl font-bold z-50 animate-pulse hidden">
    Waiting for Auction...
</div>
    <div class="card-container">
        <!-- Sold Badge -->



        <!-- Actual Team Logo -->
        <img id="team-logo" src="" class="absolute object-contain ">

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

        <!-- Batting / Bowling -->

        <h1 id="sold-text" class="text-4xl font-bold">0</h1>

        <p id="player-batting">Right-Hand Bat</p>
        <p id="player-bowling"> Right-Arm Medium</p>

        <!-- Current Bid -->
        <div id="current-bid" class="text-3xl font-extrabold text-white-900"> 1,00,000</div>

        <!-- Winning Team -->
        {{-- <div id="winning-team" class="text-2xl font-bold text-green-400">Chennai Super Kings</div> --}}

        <!-- Bid History -->
        {{-- <div id="bid-list-container">
            <ul id="bid-list" class="space-y-1">
                <li>Team A — ₹50,000</li>
                <li>Team B — ₹75,000</li>
            </ul>
        </div> --}}
    </div>

    <script>
        // Format bid in millions (M) or lakhs (L) depending on value
        function formatMillions(amount) {
            if (!amount && amount !== 0) return '0';
            return `${(amount / 1_000_000).toFixed(1)}M Points`;
        }

        // Use it here

        function fetchActivePlayer() {
    fetch(`/auction/{{ $auction->id }}/active-player`)
        .then(res => res.json())
        .then(data => {
            const cardContainer = document.querySelector('.card-container');
            const waitingScreen = document.getElementById('waiting-screen');

            if (data.auctionPlayer) {
                waitingScreen.classList.add('hidden'); // hide waiting
                cardContainer.style.display = 'block'; // show card

                const p = data.auctionPlayer;

                // Player info
                document.getElementById('player-image').src =
                    p.player.image_path ? `/storage/${p.player.image_path}` :
                    `https://ui-avatars.com/api/?name=${encodeURIComponent(p.player.name)}`;
                document.getElementById('tm').textContent = p.player.total_matches ?? 0;
                document.getElementById('tw').textContent = p.player.total_wickets ?? 0;
                document.getElementById('tr').textContent = p.player.total_runs ?? 0;
                document.getElementById('player-name').textContent = p.player.name;
                document.getElementById('player-role').textContent = p.player.player_type?.type ?? '';
                document.getElementById('player-batting').textContent = p.player.batting_profile?.style ?? 'N/A';
                document.getElementById('player-bowling').textContent = p.player.bowling_profile?.style ?? 'N/A';
                document.getElementById('current-bid').textContent = formatMillions(p.current_price);

                // Sold / team logo
                const soldText = document.getElementById('sold-text');
                soldText.textContent = p.status === 'sold' ? 'SOLD PRICE' : 'CURRENT VALUE';
                const teamLogo = document.getElementById('team-logo');
                if (p.status === 'sold' && p.sold_to_team && p.sold_to_team.logo_path) {
                    teamLogo.style.display = 'block';
                    teamLogo.src = p.sold_to_team.logo_path;
                } else {
                    teamLogo.style.display = 'none';
                }

            } else {
                // No player, show full screen waiting animation
                cardContainer.style.display = 'none';
                waitingScreen.classList.remove('hidden');
            }
        })
        .catch(console.error);
}

setInterval(fetchActivePlayer, 2000);
fetchActivePlayer();
    </script>

</body>

</html>
