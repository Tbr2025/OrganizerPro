// resources/js/auction.js

function publicAuctionBoard() {
    return {
        state: 'waiting',
        player: {
            name: '',
            image_url: '',
            base_price: 0,
            role: '',
            batting_style: '',
            bowling_style: '',
            is_wicket_keeper: false,
            stats: {
                matches: 0,
                runs: 0,
                wickets: 0,
            }
        },
        winningTeam: 'No Bids',
        finalPrice: 0,
        tumblerText: '-----',

        init(auctionId) {
            console.log(`Connecting to auction channel: auction.${auctionId}`);
            
            window.Echo.private(`auction.${auctionId}`)
                .listen('.player.onbid', (event) => this.startSpinnerAndShowPlayer(event.auctionPlayer))
                .listen('.player.sold', (event) => this.handlePlayerSold(event));
        },

        handlePlayerSold(event) {
            this.state = 'sold';
            this.finalPrice = event.auctionPlayer.final_price;
            this.winningTeam = event.winningTeam ? event.winningTeam.name : 'Unsold';
            
            setTimeout(() => {
                this.state = 'waiting';
                this.tumblerText = 'Waiting for Next Player...';
            }, 5000);
        },

        startSpinnerAndShowPlayer(auctionPlayerData) {
            this.state = 'waiting';
            let shuffleCount = 0;
            const maxShuffles = 30;

            const shuffleInterval = setInterval(() => {
                this.tumblerText = Math.floor(Math.random() * 900 + 100);
                shuffleCount++;
                if (shuffleCount >= maxShuffles) {
                    clearInterval(shuffleInterval);
                    this.displayPlayer(auctionPlayerData);
                }
            }, 100);
        },

        displayPlayer(auctionPlayerData) {
            this.player = {
                name: auctionPlayerData.player.name,
                image_url: auctionPlayerData.player.image_path ? `/storage/${auctionPlayerData.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(auctionPlayerData.player.name)}`,
                base_price: auctionPlayerData.base_price,
                role: auctionPlayerData.player.player_type?.type ?? 'N/A',
                batting_style: auctionPlayerData.player.batting_profile?.style ?? 'N/A',
                bowling_style: auctionPlayerData.player.bowling_profile?.style ?? 'N/A',
                is_wicket_keeper: auctionPlayerData.player.is_wicket_keeper,
                stats: {
                    matches: auctionPlayerData.player.total_matches || 0,
                    runs: auctionPlayerData.player.total_runs || 0,
                    wickets: auctionPlayerData.player.total_wickets || 0,
                }
            };
            this.state = 'bidding';
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(amount || 0);
        }
    }
}

// Make the function globally available for Alpine.js
window.publicAuctionBoard = publicAuctionBoard;