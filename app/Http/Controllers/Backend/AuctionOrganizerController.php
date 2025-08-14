<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionPlayer;
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
        // Fetch all players for this auction to populate the "next player" selector
        $auctionPlayers = $auction->auctionPlayers()->where('status', 'pending')->with('player')->get();
        $availablePlayers = $auction->auctionPlayers()
            ->where('status', 'pending')
            ->with('player')
            ->get();
        return view('backend.pages.auction.organizer-panel', compact('auction', 'auctionPlayers', 'availablePlayers'));
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
    public function putPlayerOnBid(Request $request, Auction $auction)
    {
        $request->validate(['auction_player_id' => 'required|exists:auction_players,id']);

        $auctionPlayer = AuctionPlayer::where('auction_id', $auction->id)
            ->where('id', $request->auction_player_id)
            ->firstOrFail();

        // Reset previous player's 'live' status if any
        $auction->auctionPlayers()->where('status', 'live')->update(['status' => 'pending']);

        // Set the new player's status and current price
        $auctionPlayer->update([
            'status' => 'live',
            'current_price' => $auctionPlayer->base_price,
            'current_bid_team_id' => null,
        ]);

        // Broadcast the event to all listeners
        broadcast(new PlayerOnBid($auctionPlayer));

        return response()->json(['message' => 'Player is now live for bidding.']);
    }

    /**
     * Mark the current player as "Sold" to the highest bidder.
     */
    public function sellPlayer(Request $request, Auction $auction)
    {
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

            // Update the main player's system status to 'Retained'
            Player::where('id', $auctionPlayer->player_id)->update(['player_status' => 'Retained']);

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
}
