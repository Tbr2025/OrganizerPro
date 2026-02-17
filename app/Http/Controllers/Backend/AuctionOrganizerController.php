<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\ActualTeam;
use App\Models\Player;
use App\Events\AuctionStatusUpdate;
use App\Events\PlayerOnBid;
use App\Events\PlayerSoldEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuctionOrganizerController extends Controller
{
    // Note: It's assumed you will protect these routes with middleware 
    // to ensure only users with the 'Organizer' role can access them.

    /**
     * Display the Organizer's control panel view.
     */
    public function showPanel(Auction $auction)
    {
        // Fetch available players (waiting status)
        $availablePlayers = $auction->auctionPlayers()
            ->where('status', 'waiting')
            ->with(['player.playerType', 'player.battingProfile', 'player.bowlingProfile'])
            ->get();

        // Fetch current player on auction (if any)
        $currentPlayer = $auction->auctionPlayers()
            ->where('status', 'on_auction')
            ->with([
                'player.playerType',
                'player.battingProfile',
                'player.bowlingProfile',
                'bids.team',
                'bids.user',
                'soldToTeam'
            ])
            ->first();

        // Fetch sold players
        $soldPlayers = $auction->auctionPlayers()
            ->where('status', 'sold')
            ->with(['player', 'soldToTeam'])
            ->get();

        // Fetch teams with their budget calculations
        $teams = ActualTeam::where('tournament_id', $auction->tournament_id)
            ->withCount(['auctionPlayers as players_bought' => function ($query) use ($auction) {
                $query->where('auction_id', $auction->id)->where('status', 'sold');
            }])
            ->withSum(['auctionPlayers as total_spent' => function ($query) use ($auction) {
                $query->where('auction_id', $auction->id)->where('status', 'sold');
            }], 'final_price')
            ->get()
            ->map(function ($team) use ($auction) {
                $team->remaining_budget = $auction->max_budget_per_team - ($team->total_spent ?? 0);
                return $team;
            });

        // Stats
        $stats = [
            'total_players' => $auction->auctionPlayers()->count(),
            'sold_count' => $auction->auctionPlayers()->where('status', 'sold')->count(),
            'unsold_count' => $auction->auctionPlayers()->where('status', 'unsold')->count(),
            'waiting_count' => $availablePlayers->count(),
        ];

        return view('backend.pages.auction.organizer-panel', compact(
            'auction',
            'availablePlayers',
            'currentPlayer',
            'soldPlayers',
            'teams',
            'stats'
        ));
    }

    /**
     * Start the auction.
     */
    public function startAuction(Auction $auction)
    {
        $auction->update(['status' => 'running']);
        broadcast(new AuctionStatusUpdate($auction->id, 'running'));
        return response()->json(['message' => 'Auction has been started.']);
    }

    /**
     * End the auction.
     */
    public function endAuction(Auction $auction)
    {
        $auction->update(['status' => 'completed']);
        broadcast(new AuctionStatusUpdate($auction->id, 'completed'));
        return response()->json(['message' => 'Auction has been completed.']);
    }

    /**
     * Select the next player and put them up for bidding.
     */
    // public function putPlayerOnBid(Request $request, Auction $auction)
    // {
    //     $validated = $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

    //     $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
    //         ->where('auction_id', $auction->id)
    //         ->firstOrFail();

    //     // Reset any other 'on_auction' players
    //     $auction->auctionPlayers()->where('status', 'on_auction')->update(['status' => 'waiting']);

    //     $auctionPlayer->update([
    //         'status' => 'on_auction',
    //         'current_price' => $auctionPlayer->base_price,
    //         'current_bid_team_id' => null,
    //     ]);

    //     // **THE FIX**: Eager-load the relationships the frontend needs BEFORE broadcasting.
    //     // We use fresh() to get the latest state after our update.
    //     $playerDataForBroadcast = $auctionPlayer->fresh([
    //         'player.playerType',
    //         'player.battingProfile',
    //         'player.bowlingProfile',
    //         'bids.team', // Load all bids and their associated team
    //         'bids.user' // Also load the user who placed the bid
    //     ]);

    //     broadcast(new PlayerOnBid($playerDataForBroadcast));

    //     return response()->json(['message' => 'Player is now live for bidding.']);
    // }



    public function putPlayerOnBid(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'auction_player_id' => 'required|exists:auction_players,id'
        ]);

        $auctionPlayer = AuctionPlayer::where('id', $validated['auction_player_id'])
            ->where('auction_id', $auction->id)
            ->where('status', 'waiting') // ✅ only select waiting players
            ->first();

        // If no player found (either doesn't exist or not waiting)
        if (!$auctionPlayer) {
            return response()->json([
                'success' => false,
                'message' => 'Player not available to put on bid. Only players with status "waiting" can be selected.'
            ], 400);
        }

        // Check if any other player is live
        $livePlayer = $auction->auctionPlayers()->where('status', 'on_auction')->first();
        if ($livePlayer) {
            return response()->json([
                'success' => false,
                'message' => 'Some player is already live in the auction! Please close that bid before starting with the next player!'
            ], 400);
        }

        // Set this player live
        $auctionPlayer->update([
            'status' => 'on_auction',
            'current_price' => $auctionPlayer->base_price,
            'current_bid_team_id' => null,
        ]);

        // Eager-load relationships for broadcast
        $playerDataForBroadcast = $auctionPlayer->fresh([
            'player.playerType',
            'player.battingProfile',
            'player.bowlingProfile',
            'bids.team',
            'bids.user'
        ]);

        broadcast(new PlayerOnBid($playerDataForBroadcast));

        return response()->json([
            'success' => true,
            'message' => 'Player is now live for bidding.'
        ]);
    }


    /**
     * Mark the current player as "Sold" to the highest bidder.
     */
    public function sellPlayer(Request $request, Auction $auction)
    {
        // dd($sellPlayer);
        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)->firstOrFail();

        // Find the winning bid
        $winningBid = $auctionPlayer->bids()->latest('amount')->first();

        if ($winningBid) {
            $auctionPlayer->update([
                'status' => 'sold',
                'sold_to_team_id' => $winningBid->team_id,
                'final_price' => $winningBid->amount,
            ]);

            // Update the main player's mode to 'retained'
            Player::where('id', $auctionPlayer->player_id)->update(['player_mode' => 'retained']);

            broadcast(new PlayerSoldEvent($auctionPlayer, $winningBid->team));
        } else {
            // If no bids, mark as unsold
            $this->passPlayer($request, $auction);
        }

        return response()->json(['message' => 'Player status updated to SOLD.']);
    }

    /**
     * Mark the current player as "Unsold/Passed".
     */
    public function passPlayer(Request $request, Auction $auction)
    {
        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('id', $request->auction_player_id)->firstOrFail();

        $auctionPlayer->update(['status' => 'unsold']);

        // Still broadcast the "sold" event so the UI can update, but without a winning team
        broadcast(new PlayerSoldEvent($auctionPlayer, null));

        return response()->json(['message' => 'Player has been passed.']);
    }



    public function togglePause(Auction $auction)
    {
        // 1. Determine the new status
        // If the current status is 'running', the new status will be 'paused'.
        // Otherwise, the new status will be 'running'.
        $newStatus = ($auction->status === 'running') ? 'paused' : 'running';

        // 2. Security/Logic Check: Only allow toggling if the auction is currently running or paused.
        if (!in_array($auction->status, ['running', 'paused'])) {
            return response()->json(['message' => 'Auction cannot be paused or resumed at this time.'], 422); // Unprocessable Entity
        }

        // 3. Update the auction's status in the database
        $auction->update(['status' => $newStatus]);

        // 4. Broadcast the status update to all connected clients
        // This is the crucial step that makes the UI update in real-time.
        broadcast(new AuctionStatusUpdate($auction->id, $newStatus));

        // 5. Return a success response to the Organizer's panel
        return response()->json(['message' => 'Auction status has been updated to ' . $newStatus . '.']);
    }
}
