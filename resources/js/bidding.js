function biddingPanel() {
    return {
        // --- State & Data ---
        auctionId: null,
        userTeam: null,
        bidRules: [],
        state: 'waiting',
        
        // **FIX 1**: Initialize player with a default 'schema' to prevent errors on page load
        player: {
            id: null,
            name: 'No Player',
            image_url: '',
            base_price: 0,
            role: 'N/A',
            batting_style: 'N/A',
            bowling_style: 'N/A',
            is_wicket_keeper: false,
        },
        
        currentBid: 0,
        winningTeamName: 'No Bids',
        teamBudget: 0,
        nextBidAmount: 0,
        finalPrice: 0,
        bidError: '',
        canBid: false,
        bidButtonText: 'Place Bid',

        // --- Initialization ---
        init(auctionId, userTeamJson, currentPlayerJson, bidRulesJson) {
            this.auctionId = auctionId;

            // **FIX 2**: Safely parse the JSON strings from the data attributes
            this.userTeam = userTeamJson ? JSON.parse(userTeamJson) : null;
            const currentPlayer = currentPlayerJson ? JSON.parse(currentPlayerJson) : null;
            this.bidRules = bidRulesJson ? JSON.parse(bidRulesJson) : [];

            // This now safely uses the parsed objects
            this.teamBudget = this.userTeam ? parseFloat(this.userTeam.remaining_budget) : 0;

            if (currentPlayer) {
                this.handlePlayerOnBid({ auctionPlayer: currentPlayer });
            }

            // This function safely waits until window.Echo is ready.
            const connectToEcho = () => {
                if (window.Echo) {
                    console.log(`Bidding panel connecting to Echo on channel: auction.${this.auctionId}`);
                    window.Echo.private(`auction.${this.auctionId}`)
                        .listen('.player.onbid', (e) => this.handlePlayerOnBid(e))
                        .listen('.new-bid', (e) => this.handleNewBid(e))
                        .listen('.player.sold', (e) => this.handlePlayerSold(e));
                } else {
                    console.log('Echo not ready for bidding panel, trying again in 100ms...');
                    setTimeout(connectToEcho, 100);
                }
            };
            connectToEcho();
        },

        // --- Event Handlers from Soketi ---
        handlePlayerOnBid(event) {
            this.player = this.formatPlayerForDisplay(event.auctionPlayer);
            this.currentBid = parseFloat(event.auctionPlayer.current_price || event.auctionPlayer.base_price);
            this.winningTeamName = event.auctionPlayer.current_bid_team?.name || 'No Bids';
            this.calculateNextBidAmount();
            this.state = 'bidding';
            this.bidError = '';
        },
        handleNewBid(event) {
            this.currentBid = parseFloat(event.bid.amount);
            this.winningTeamName = event.bid.team.name;
            this.calculateNextBidAmount();
            this.bidError = '';
        },
        handlePlayerSold(event) {
            this.state = 'sold';
            this.finalPrice = event.auctionPlayer.final_price;
            this.winningTeamName = event.winningTeam?.name || 'Unsold';
            
            if (event.winningTeam && this.userTeam && event.winningTeam.id === this.userTeam.id) {
                this.teamBudget -= parseFloat(event.auctionPlayer.final_price);
            }
            setTimeout(() => { this.state = 'waiting'; }, 5000);
        },

        // --- Core Bidding Logic ---
        calculateNextBidAmount() {
            if (!this.player || this.player.id === null) return;
            
            let increment = 100000; // Default increment
            let currentPrice = this.currentBid > 0 ? this.currentBid : this.player.base_price;
            
            for (const rule of this.bidRules) {
                if (currentPrice >= rule.from && currentPrice < rule.to) {
                    increment = rule.increment;
                    break;
                }
            }
           this.nextBidAmount = parseFloat(currentPrice) + parseFloat(increment);

            console.log(currentPrice);
            console.log(this.nextBidAmount);

            this.canBid = (this.nextBidAmount <= this.teamBudget) && (this.winningTeamName !== this.userTeam?.name);
            this.bidButtonText = this.canBid ? `Bid ${this.formatCurrency(this.nextBidAmount)}` : 'Place Bid';

            if (this.winningTeamName === this.userTeam?.name) {
                this.bidButtonText = 'You are the highest bidder';
            } else if (this.nextBidAmount > this.teamBudget) {
                this.bidButtonText = 'Insufficient Budget';
            }
        },
        async placeBid() {
            if (!this.canBid) return;
            this.bidError = '';
            try {
                const response = await fetch(`/admin/team/auction/${this.auctionId}/api/place-bid`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        amount: this.nextBidAmount,
                        auction_player_id: this.player.id
                    })
                });
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || 'Bid could not be placed.');
                }
            } catch (error) {
                this.bidError = error.message;
            }
        },

        // --- Helper Functions ---
        formatPlayerForDisplay(auctionPlayer) {
            return {
                id: auctionPlayer.id,
                name: auctionPlayer.player.name,
                image_url: auctionPlayer.player.image_path ? `/storage/${auctionPlayer.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(auctionPlayer.player.name)}`,
                base_price: parseFloat(auctionPlayer.base_price),
                role: auctionPlayer.player.player_type?.type ?? 'N/A',
                batting_style: auctionPlayer.player.batting_profile?.style ?? 'N/A',
                bowling_style: auctionPlayer.player.bowling_profile?.style ?? 'N/A',
                is_wicket_keeper: auctionPlayer.player.is_wicket_keeper,
            };
        },
formatCurrency(points) {
               points = Number(points) || 0;
    const isNegative = points < 0;
    const absPoints = Math.abs(points);
    let formattedValue;

    if (absPoints >= 1000000) { // 1 Million or more
        // Format to 2 decimal places, then remove .00 if it exists
        formattedValue = (absPoints / 1000000).toFixed(2).replace(/\.00$/, '') + 'M';
    } else if (absPoints >= 1000) { // 1 Thousand or more
        // Format to 1 decimal place, then remove .0 if it exists
        formattedValue = (absPoints / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    } else {
        // For numbers less than 1000, just show the number
        formattedValue = new Intl.NumberFormat('en-US').format(absPoints);
    }

    return `${isNegative ? '-' : ''}${formattedValue} Points`;
}
    }
}

window.biddingPanel = biddingPanel;