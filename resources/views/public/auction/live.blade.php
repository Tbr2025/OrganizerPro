@extends('public.layouts.app')

@section('title', 'Live Auction | ' . $auction->name)

@section('content')
    <div id="waiting-screen"
        class="fixed inset-0 flex items-center justify-center bg-black text-white text-5xl font-bold z-50 animate-pulse hidden">
        Waiting for Auction...
    </div>

    <div class="card-container">
        <img id="team-logo" src="" class="absolute object-contain">

        <img id="player-image" src="https://via.placeholder.com/300" alt="Player">
        <h1 id="player-name" class="text-4xl font-bold">Player Name</h1>

        <h1 id="tmh" class="text-4xl font-bold">MATCHES</h1>
        <h1 id="tm" class="text-4xl font-bold">0</h1>
        <h1 id="twh" class="text-4xl font-bold">WKTS</h1>
        <h1 id="tw" class="text-4xl font-bold">0</h1>
        <h1 id="trh" class="text-4xl font-bold">RUNS</h1>
        <h1 id="tr" class="text-4xl font-bold">0</h1>

        <p id="player-role" class="text-2xl font-bold font-uppercase">All Rounder</p>
        <p id="player-batting">Right-Hand Bat</p>
        <p id="player-bowling">Right-Arm Medium</p>
        <div id="current-bid" class="text-3xl font-extrabold text-white-900">1,00,000</div>
        <h1 id="sold-text" class="text-4xl font-bold">0</h1>
    </div>
@endsection

@section('scripts')
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.11.3/echo.iife.js"></script>

    <script>
        Pusher.logToConsole = false;

        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env('PUSHER_APP_KEY') }}',
            cluster: '{{ env('PUSHER_APP_CLUSTER') }}',
            forceTLS: true
        });

        const auctionId = {{ $auction->id }};
        const cardContainer = document.querySelector('.card-container');
        const waitingScreen = document.getElementById('waiting-screen');

        function formatMillions(amount) {
            if (!amount && amount !== 0) return '0';
            return `${(amount / 1_000_000).toFixed(1)}M Points`;
        }

        function showWaitingScreen() {
            cardContainer.style.display = 'none';
            waitingScreen.classList.remove('hidden');
        }

        function updatePlayerCard(p) {
            cardContainer.style.display = 'block';
            waitingScreen.classList.add('hidden');
            if (!p || !p.player) {
                console.warn('No player data available', p);
                showWaitingScreen();
                return;
            }
            cardContainer.style.display = 'block';
            waitingScreen.classList.add('hidden');
            document.getElementById('player-image').src =
                p.player.image_path ?
                `/storage/${p.player.image_path}` :
                `https://ui-avatars.com/api/?name=${encodeURIComponent(p.player.name ?? 'Player')}`;


            document.getElementById('tm').textContent = p.player?.total_matches ?? 0;
            document.getElementById('tw').textContent = p.player?.total_wickets ?? 0;
            document.getElementById('tr').textContent = p.player?.total_runs ?? 0;
            document.getElementById('player-name').textContent = p.player?.name ?? 'Player';
            document.getElementById('player-role').textContent = p.player?.player_type?.type ?? '';
            document.getElementById('player-batting').textContent = p.player?.batting_profile?.style ?? 'N/A';
            document.getElementById('player-bowling').textContent = p.player?.bowling_profile?.style ?? 'N/A';
            document.getElementById('current-bid').textContent = formatMillions(p.current_price ?? 0);

            const soldText = document.getElementById('sold-text');
            soldText.textContent = p.status === 'sold' ? 'SOLD PRICE' : 'CURRENT VALUE';

            const teamLogo = document.getElementById('team-logo');
            if (p.status === 'sold' && p.sold_to_team?.team_logo) {
                teamLogo.src = `/storage/${p.sold_to_team.team_logo}`;
                teamLogo.style.display = 'block';
            } else {
                teamLogo.style.display = 'none';
            }
        }

        // Fetch the active player or latest sold if none
        function fetchInitialPlayer() {
            fetch(`/auction/${auctionId}/active-player`)
                .then(res => res.json())
                .then(data => {
                    if (data?.auctionPlayer) {
                        updatePlayerCard(data.auctionPlayer);
                    } else {
                        // If no active player, fetch latest sold
                        fetch(`/auction/${auctionId}/sold-player`)
                            .then(res => res.json())
                            .then(soldData => {
                                if (soldData?.auctionPlayer) {
                                    updatePlayerCard(soldData.auctionPlayer);
                                } else {
                                    showWaitingScreen();
                                }
                            });
                    }
                }).catch(console.error);
        }

window.Echo.channel(`auction.${auctionId}`)
    .listen('.player-on-bid', (event) => {
        console.log('Player on bid:', event);
        updatePlayerCard(event.auctionPlayer); // correct
    })
    .listen('.player-on-sold', (event) => {
        console.log('Player sold:', event);
        updatePlayerCard(event.auctionPlayer); // use auctionPlayer
    });








        // Fetch the first player on page load
        fetchInitialPlayer();
    </script>
@endsection
